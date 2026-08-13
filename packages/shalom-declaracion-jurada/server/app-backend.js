import { createHash, randomBytes, randomUUID, createCipheriv, createDecipheriv } from 'node:crypto'
import { Buffer } from 'node:buffer'
import { mkdirSync } from 'node:fs'
import { dirname } from 'node:path'
import { DatabaseSync } from 'node:sqlite'
import { Pool } from 'pg'
import bcrypt from 'bcryptjs'

const SESSION_COOKIE = 'dj_session'
const SESSION_DAYS = 7
// Abilities esperadas en CodeRED Platform (config/api.php no aplica aquí —
// estas son permisos de app, no abilities de token API — ver
// database/seeders/PermissionsSeeder.php en el repo raíz).
const VIEW_PERMISSION = 'declaracion-jurada.view'
const MANAGE_PERMISSION = 'declaracion-jurada.manage'
// Cuánto tiempo se confía en la última verificación de permisos/estado de
// una sesión antes de volver a consultar CodeRED. Si se revoca el permiso
// o se desactiva la cuenta en CodeRED, la sesión deja de funcionar en como
// máximo este intervalo, sin necesidad de forzar un logout inmediato.
const PERMISSION_CACHE_MS = 5 * 60 * 1000
const LOGIN_MAX_ATTEMPTS = 5
const LOGIN_WINDOW_MS = 15 * 60 * 1000

const json = (response, status, payload, headers = {}) => {
  response.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8', ...headers })
  response.end(JSON.stringify(payload))
}

const readJson = async (request, maxSize = 32_768) => {
  const chunks = []
  let size = 0
  for await (const chunk of request) {
    size += chunk.length
    if (size > maxSize) throw new Error('PAYLOAD_TOO_LARGE')
    chunks.push(chunk)
  }
  return chunks.length ? JSON.parse(Buffer.concat(chunks).toString('utf8')) : {}
}

const cookieValue = (request, name) => {
  const cookies = request.headers.cookie?.split(';') ?? []
  const cookie = cookies.find(value => value.trim().startsWith(`${name}=`))
  return cookie ? decodeURIComponent(cookie.trim().slice(name.length + 1)) : null
}

const sha256 = value => createHash('sha256').update(value).digest('hex')

const requestIp = request => request.headers['x-forwarded-for']?.split(',')[0]?.trim() || request.socket?.remoteAddress || null

const safeUser = (user, creditBatches = []) => user && ({
  coderedUserId: user.codered_user_id,
  email: user.email,
  name: user.name,
  credits: user.credits,
  queriesUsed: user.queries_used,
  creditsExpiresAt: user.credits_expires_at,
  creditBatches,
  canManage: Boolean(user.has_manage_permission)
})

const getPersonName = payload => {
  const data = payload?.data ?? payload?.resultado ?? payload?.result ?? payload ?? {}
  return String(data.nombreCompleto ?? data.nombre_completo ?? data.fullName ?? data.full_name ?? data.nombre ?? data.name ?? [
    data.nombres ?? data.first_name,
    data.apellidoPaterno ?? data.apellido_paterno ?? data.first_last_name,
    data.apellidoMaterno ?? data.apellido_materno ?? data.second_last_name
  ].filter(Boolean).join(' ')).trim()
}

const parseReceiptImage = (value, fileName) => {
  const match = String(value || '').match(/^data:(image\/(?:png|jpeg|webp));base64,([A-Za-z0-9+/=]+)$/)
  if (!match) throw new Error('INVALID_RECEIPT')
  const buffer = Buffer.from(match[2], 'base64')
  if (!buffer.length || buffer.length > 5 * 1024 * 1024) throw new Error('INVALID_RECEIPT')
  const validSignature = match[1] === 'image/png'
    ? buffer.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))
    : match[1] === 'image/jpeg'
      ? buffer[0] === 0xff && buffer[1] === 0xd8 && buffer.at(-2) === 0xff && buffer.at(-1) === 0xd9
      : buffer.subarray(0, 4).toString() === 'RIFF' && buffer.subarray(8, 12).toString() === 'WEBP'
  if (!validSignature) throw new Error('INVALID_RECEIPT')
  const extension = { 'image/png': 'png', 'image/jpeg': 'jpg', 'image/webp': 'webp' }[match[1]]
  const safeName = String(fileName || `comprobante.${extension}`).replace(/[^a-zA-Z0-9._-]/g, '_').slice(-80)
  return { buffer, mimeType: match[1], fileName: safeName || `comprobante.${extension}` }
}

export const createAppBackend = (env) => {
  if (!env.CODERED_DB_HOST) {
    throw new Error('CODERED_DB_HOST es obligatorio: Declaración Jurada ya no gestiona su propia identidad de usuarios; autentica contra la tabla users de CodeRED Platform.')
  }

  const databasePath = env.DATABASE_PATH || '.data/declaracion-jurada.db'
  mkdirSync(dirname(databasePath), { recursive: true })
  const db = new DatabaseSync(databasePath)
  db.exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON;')

  const tableExists = name => !!db.prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?").get(name)
  const columnExists = (table, column) => tableExists(table) && db.prepare(`PRAGMA table_info(${table})`).all().some(c => c.name === column)
  const addColumn = (table, definition) => {
    const name = definition.split(' ')[0]
    if (!columnExists(table, name)) db.exec(`ALTER TABLE ${table} ADD COLUMN ${definition}`)
  }

  // --- Migración one-shot: identidad local (email+password propios) -> identidad de CodeRED Platform ---
  // Detecta el esquema anterior (tabla `users` con password_hash propio) y
  // la retira de circulación sin borrar nada: se renombra a `legacy_users`
  // para conservar el historial, y las tablas que colgaban de ella se
  // remapean por codered_user_id más abajo. Corre una sola vez: en el
  // siguiente arranque `users` ya no tendrá `password_hash` y esta rama no
  // se vuelve a ejecutar.
  const migratingFromLegacyAuth = columnExists('users', 'password_hash')
  if (migratingFromLegacyAuth) {
    db.exec('ALTER TABLE users RENAME TO legacy_users')
    if (tableExists('sessions')) db.exec('DROP TABLE sessions')
    if (tableExists('email_verifications')) db.exec('DROP TABLE email_verifications')
  }

  db.exec(`
    CREATE TABLE IF NOT EXISTS users (
      codered_user_id INTEGER PRIMARY KEY,
      email TEXT,
      name TEXT,
      queries_used INTEGER NOT NULL DEFAULT 0 CHECK(queries_used >= 0),
      credits INTEGER NOT NULL DEFAULT 0 CHECK(credits >= 0),
      credits_expires_at TEXT,
      permission_checked_at INTEGER,
      has_view_permission INTEGER NOT NULL DEFAULT 0,
      has_manage_permission INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS sessions (
      token_hash TEXT PRIMARY KEY,
      codered_user_id INTEGER NOT NULL REFERENCES users(codered_user_id) ON DELETE CASCADE,
      expires_at INTEGER NOT NULL
    );
    CREATE TABLE IF NOT EXISTS auth_events (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      event TEXT NOT NULL,
      codered_user_id INTEGER,
      email TEXT,
      ip TEXT,
      user_agent TEXT,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS credit_requests (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      codered_user_id INTEGER NOT NULL REFERENCES users(codered_user_id) ON DELETE CASCADE,
      credits INTEGER NOT NULL CHECK(credits > 0),
      status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'rejected')),
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      resolved_at TEXT,
      resolved_by_codered_user_id INTEGER,
      package_id INTEGER,
      payment_method_id INTEGER,
      amount REAL,
      reference TEXT,
      receipt_sent INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS app_settings (
      key TEXT PRIMARY KEY,
      value TEXT
    );
    CREATE TABLE IF NOT EXISTS pricing_packages (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      credits INTEGER NOT NULL CHECK(credits > 0),
      price REAL NOT NULL CHECK(price >= 0),
      validity_days INTEGER NOT NULL DEFAULT 30 CHECK(validity_days > 0),
      active INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS payment_methods (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      instructions TEXT NOT NULL,
      image_data TEXT,
      active INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS credit_batches (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      codered_user_id INTEGER NOT NULL REFERENCES users(codered_user_id) ON DELETE CASCADE,
      credit_request_id INTEGER UNIQUE REFERENCES credit_requests(id) ON DELETE SET NULL,
      credits_total INTEGER NOT NULL CHECK(credits_total > 0),
      credits_remaining INTEGER NOT NULL CHECK(credits_remaining >= 0),
      expires_at TEXT,
      source TEXT NOT NULL DEFAULT 'purchase',
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
  `)

  // Instalaciones que venían de un esquema anterior a este remapeo (antes
  // de que credit_requests/credit_batches tuvieran codered_user_id) llegan
  // aquí con las tablas ya creadas por versiones previas de este archivo.
  addColumn('credit_requests', 'codered_user_id INTEGER')
  addColumn('credit_requests', 'resolved_by_codered_user_id INTEGER')
  addColumn('credit_batches', 'codered_user_id INTEGER')
  addColumn('payment_methods', 'image_data TEXT')

  if (migratingFromLegacyAuth) {
    const linked = db.prepare('SELECT * FROM legacy_users WHERE codered_user_id IS NOT NULL').all()
    for (const legacy of linked) {
      db.prepare(`INSERT OR IGNORE INTO users (codered_user_id, email, credits, queries_used, credits_expires_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?)`).run(legacy.codered_user_id, legacy.email, legacy.credits, legacy.queries_used, legacy.credits_expires_at, legacy.created_at)
      db.prepare('UPDATE credit_batches SET codered_user_id = ? WHERE user_id = ?').run(legacy.codered_user_id, legacy.id)
      db.prepare('UPDATE credit_requests SET codered_user_id = ? WHERE user_id = ?').run(legacy.codered_user_id, legacy.id)
      db.prepare('UPDATE credit_requests SET resolved_by_codered_user_id = ? WHERE resolved_by = ?').run(legacy.codered_user_id, legacy.id)
    }
    const unmapped = db.prepare('SELECT id, email, credits, queries_used FROM legacy_users WHERE codered_user_id IS NULL').all()
    console.log(`[migracion-auth] ${linked.length} cuenta(s) migrada(s) a la identidad de CodeRED Platform.`)
    if (unmapped.length) {
      console.warn(`[migracion-auth] ${unmapped.length} cuenta(s) local(es) de Declaracion Jurada NO se pudieron vincular a un usuario de CodeRED Platform (no existe ese email alla). Sus datos siguen intactos en la tabla 'legacy_users' para recuperacion manual:`)
      for (const legacyUser of unmapped) {
        console.warn(`  - legacy_users.id=${legacyUser.id} email=${legacyUser.email} credits=${legacyUser.credits} queriesUsed=${legacyUser.queries_used}`)
      }
    }
  }

  // Las columnas legacy (user_id/resolved_by, referenciando la vieja tabla
  // `users` local) llegaron con NOT NULL desde el esquema anterior. Una
  // vez remapeados los datos a codered_user_id/resolved_by_codered_user_id
  // arriba, dejarlas en pie con esa restricción rompe cualquier INSERT
  // nuevo (p. ej. otorgar créditos a un usuario que nunca tuvo cuenta
  // local) — hay que soltarlas, no solo dejar de usarlas.
  const dropColumnIfExists = (table, column) => {
    if (columnExists(table, column)) db.exec(`ALTER TABLE ${table} DROP COLUMN ${column}`)
  }
  dropColumnIfExists('credit_batches', 'user_id')
  dropColumnIfExists('credit_requests', 'user_id')
  dropColumnIfExists('credit_requests', 'resolved_by')

  // --- Identidad: CodeRED Platform es la única fuente de usuarios ---------
  // Pool dedicado con el rol de solo lectura `declaracion_jurada_ro` (ver
  // migración 2026_08_13_000001_create_declaracion_jurada_readonly_role en
  // el repo raíz): únicamente puede hacer SELECT sobre
  // users/roles/permissions/role_user/permission_role.
  const coderedPool = new Pool({
    host: env.CODERED_DB_HOST,
    port: Number(env.CODERED_DB_PORT) || 5432,
    database: env.CODERED_DB_DATABASE,
    user: env.CODERED_DB_USERNAME,
    password: env.CODERED_DB_PASSWORD,
    max: 5,
    idleTimeoutMillis: 30000,
    connectionTimeoutMillis: 5000
  })
  coderedPool.on('error', error => console.error('CodeRED Postgres pool error:', error.message))

  // Todas las consultas usan parámetros posicionales ($1, $2, ...) — nunca
  // se concatena email/ID/entrada HTTP dentro del texto SQL.
  const findCoderedUserByEmail = async email => {
    const { rows } = await coderedPool.query(
      'SELECT id, name, email, password, status, deleted_at FROM users WHERE lower(email) = lower($1) LIMIT 1',
      [email]
    )
    return rows[0] ?? null
  }

  const findCoderedUserById = async id => {
    const { rows } = await coderedPool.query(
      'SELECT id, name, email, status, deleted_at FROM users WHERE id = $1 LIMIT 1',
      [id]
    )
    return rows[0] ?? null
  }

  // Replica User::hasPermission() de CodeRED (app/Models/User.php): super-admin
  // pasa siempre; el resto se resuelve por roles -> permission_role -> permissions.
  const fetchCoderedPermissions = async userId => {
    const { rows } = await coderedPool.query(
      `SELECT
         EXISTS (
           SELECT 1 FROM role_user ru JOIN roles r ON r.id = ru.role_id
           WHERE ru.user_id = $1 AND r.slug = 'super-admin'
         ) AS is_super_admin,
         EXISTS (
           SELECT 1 FROM role_user ru
           JOIN permission_role pr ON pr.role_id = ru.role_id
           JOIN permissions p ON p.id = pr.permission_id
           WHERE ru.user_id = $1 AND p.slug = $2
         ) AS has_view,
         EXISTS (
           SELECT 1 FROM role_user ru
           JOIN permission_role pr ON pr.role_id = ru.role_id
           JOIN permissions p ON p.id = pr.permission_id
           WHERE ru.user_id = $1 AND p.slug = $3
         ) AS has_manage`,
      [userId, VIEW_PERMISSION, MANAGE_PERMISSION]
    )
    const row = rows[0] ?? {}
    return {
      hasView: Boolean(row.is_super_admin || row.has_view),
      hasManage: Boolean(row.is_super_admin || row.has_manage)
    }
  }

  const upsertLocalUser = (coderedUser, permissions) => {
    db.prepare(`INSERT INTO users (codered_user_id, email, name, permission_checked_at, has_view_permission, has_manage_permission)
      VALUES (?, ?, ?, ?, ?, ?)
      ON CONFLICT(codered_user_id) DO UPDATE SET
        email = excluded.email, name = excluded.name, permission_checked_at = excluded.permission_checked_at,
        has_view_permission = excluded.has_view_permission, has_manage_permission = excluded.has_manage_permission`)
      .run(coderedUser.id, coderedUser.email, coderedUser.name, Date.now(), permissions.hasView ? 1 : 0, permissions.hasManage ? 1 : 0)
  }

  // Usado por el panel admin para asignar consultas a un usuario de CodeRED
  // que aún no inició sesión en Declaración Jurada (por lo tanto no tiene
  // fila local todavía). No crea identidad nueva: solo cachea localmente un
  // usuario que ya existe en CodeRED.
  const ensureLocalUserExists = async coderedUserId => {
    const existing = db.prepare('SELECT codered_user_id FROM users WHERE codered_user_id = ?').get(coderedUserId)
    if (existing) return true
    const coderedUser = await findCoderedUserById(coderedUserId)
    if (!coderedUser || coderedUser.deleted_at) return false
    db.prepare('INSERT INTO users (codered_user_id, email, name) VALUES (?, ?, ?)').run(coderedUser.id, coderedUser.email, coderedUser.name)
    return true
  }

  const batchRows = coderedUserId => db.prepare(`SELECT id, credits_total AS creditsTotal,
    credits_remaining AS creditsRemaining, expires_at AS expiresAt, source, created_at AS createdAt
    FROM credit_batches WHERE codered_user_id = ? ORDER BY created_at DESC, id DESC`).all(coderedUserId)

  const syncUserCredits = coderedUserId => {
    const now = new Date().toISOString()
    const summary = db.prepare(`SELECT COALESCE(SUM(credits_remaining), 0) AS credits,
      MIN(expires_at) AS nextExpiry FROM credit_batches
      WHERE codered_user_id = ? AND credits_remaining > 0 AND (expires_at IS NULL OR expires_at > ?)`)
      .get(coderedUserId, now)
    db.prepare('UPDATE users SET credits = ?, credits_expires_at = ? WHERE codered_user_id = ?')
      .run(summary.credits, summary.nextExpiry, coderedUserId)
    return summary
  }

  const userPayload = coderedUserId => {
    syncUserCredits(coderedUserId)
    const user = db.prepare('SELECT * FROM users WHERE codered_user_id = ?').get(coderedUserId)
    if (!user) return null
    const now = Date.now()
    const batches = batchRows(coderedUserId).map(batch => ({
      ...batch,
      active: batch.creditsRemaining > 0 && (!batch.expiresAt || new Date(batch.expiresAt).getTime() > now)
    }))
    return safeUser(user, batches)
  }

  // Cifra la clave del proveedor DNI y el token del bot de Telegram
  // guardados en app_settings — configuración propia de Declaración
  // Jurada, no identidad de usuario.
  const encryptionSecret = env.APP_ENCRYPTION_KEY || ''
  const encryptionKey = encryptionSecret ? createHash('sha256').update(encryptionSecret).digest() : null

  const encryptApiKey = value => {
    if (!encryptionKey) throw new Error('MISSING_ENCRYPTION_KEY')
    const iv = randomBytes(12)
    const cipher = createCipheriv('aes-256-gcm', encryptionKey, iv)
    const encrypted = Buffer.concat([cipher.update(value, 'utf8'), cipher.final()])
    return [iv, cipher.getAuthTag(), encrypted].map(buffer => buffer.toString('base64url')).join('.')
  }

  const decryptApiKey = value => {
    if (!encryptionKey) throw new Error('MISSING_ENCRYPTION_KEY')
    const [iv, tag, encrypted] = value.split('.').map(part => Buffer.from(part, 'base64url'))
    const decipher = createDecipheriv('aes-256-gcm', encryptionKey, iv)
    decipher.setAuthTag(tag)
    return Buffer.concat([decipher.update(encrypted), decipher.final()]).toString('utf8')
  }

  const setting = key => db.prepare('SELECT value FROM app_settings WHERE key = ?').get(key)?.value ?? null
  const setSetting = (key, value) => db.prepare(`INSERT INTO app_settings (key, value) VALUES (?, ?)
    ON CONFLICT(key) DO UPDATE SET value = excluded.value`).run(key, value)

  const sendPurchaseToTelegram = async ({ id, email, selectedPackage, method, reference, receipt }) => {
    const encryptedBotToken = setting('telegram_bot_token')
    const botToken = encryptedBotToken ? decryptApiKey(encryptedBotToken) : env.TELEGRAM_BOT_TOKEN
    const chatId = setting('telegram_chat_id') || env.TELEGRAM_CHAT_ID
    if (!botToken || !chatId) throw new Error('TELEGRAM_NOT_CONFIGURED')
    const form = new FormData()
    form.append('chat_id', chatId)
    form.append('caption', [
      `Nueva solicitud de compra #${id}`,
      `Usuario: ${email}`,
      `Paquete: ${selectedPackage.name}`,
      `Consultas: ${selectedPackage.credits}`,
      `Importe: S/ ${Number(selectedPackage.price).toFixed(2)}`,
      `Método: ${method.name}`,
      `Referencia: ${reference}`
    ].join('\n'))
    form.append('photo', new Blob([receipt.buffer], { type: receipt.mimeType }), receipt.fileName)
    const telegramBaseUrl = env.TELEGRAM_API_BASE_URL || 'https://api.telegram.org'
    const response = await fetch(`${telegramBaseUrl}/bot${botToken}/sendPhoto`, {
      method: 'POST',
      body: form,
      signal: AbortSignal.timeout(15000)
    })
    if (!response.ok) throw new Error('TELEGRAM_SEND_FAILED')
  }

  // --- Rate limiting de login (en memoria, por proceso) -------------------
  // Sin Redis en este paquete: basta un limitador simple en memoria dado
  // que la app corre en una sola instancia (ver README). Nunca bloquea la
  // cuenta en CodeRED — solo retrasa intentos desde la misma IP+correo.
  const loginAttempts = new Map()
  const checkRateLimit = key => {
    const now = Date.now()
    const entry = loginAttempts.get(key)
    if (!entry || now - entry.firstAttemptAt > LOGIN_WINDOW_MS) {
      loginAttempts.set(key, { count: 1, firstAttemptAt: now })
      if (loginAttempts.size > 5000) loginAttempts.clear()
      return 0
    }
    entry.count += 1
    if (entry.count > LOGIN_MAX_ATTEMPTS) {
      return Math.max(1, Math.ceil((entry.firstAttemptAt + LOGIN_WINDOW_MS - now) / 1000))
    }
    return 0
  }
  const clearRateLimit = key => loginAttempts.delete(key)

  // --- Auditoría de autenticación ------------------------------------------
  // Nunca registra password, hash, token completo ni cookie.
  const logAuthEvent = (event, request, { coderedUserId = null, email = null } = {}) => {
    db.prepare('INSERT INTO auth_events (event, codered_user_id, email, ip, user_agent) VALUES (?, ?, ?, ?, ?)')
      .run(event, coderedUserId, email, requestIp(request), request.headers['user-agent'] || null)
  }

  const createSession = coderedUserId => {
    const token = randomBytes(32).toString('base64url')
    const expiresAt = Date.now() + SESSION_DAYS * 86_400_000
    db.prepare('INSERT INTO sessions (token_hash, codered_user_id, expires_at) VALUES (?, ?, ?)')
      .run(sha256(token), coderedUserId, expiresAt)
    const secure = env.COOKIE_SECURE === 'true' ? '; Secure' : ''
    return `${SESSION_COOKIE}=${encodeURIComponent(token)}; Path=/; HttpOnly; SameSite=Strict; Max-Age=${SESSION_DAYS * 86400}${secure}`
  }

  // Sesión propia de Declaración Jurada (cookie HttpOnly local) cuya
  // identidad proviene de CodeRED. Revalida estado/permisos contra CodeRED
  // cuando el caché (PERMISSION_CACHE_MS) expira; si CodeRED no responde,
  // conserva la última decisión conocida en vez de cerrar sesión de golpe.
  const currentSession = async request => {
    const token = cookieValue(request, SESSION_COOKIE)
    if (!token) return null
    const tokenHash = sha256(token)
    const session = db.prepare(`SELECT s.token_hash, s.codered_user_id, s.expires_at,
        u.email, u.name, u.permission_checked_at, u.has_view_permission, u.has_manage_permission
      FROM sessions s JOIN users u ON u.codered_user_id = s.codered_user_id
      WHERE s.token_hash = ? AND s.expires_at > ?`).get(tokenHash, Date.now())
    if (!session) return null

    const stale = !session.permission_checked_at || (Date.now() - session.permission_checked_at) > PERMISSION_CACHE_MS
    if (!stale) return session

    try {
      const coderedUser = await findCoderedUserById(session.codered_user_id)
      if (!coderedUser || coderedUser.deleted_at || coderedUser.status !== 'active') {
        db.prepare('DELETE FROM sessions WHERE token_hash = ?').run(tokenHash)
        return null
      }
      const permissions = await fetchCoderedPermissions(session.codered_user_id)
      db.prepare(`UPDATE users SET permission_checked_at = ?, has_view_permission = ?, has_manage_permission = ?,
        email = ?, name = ? WHERE codered_user_id = ?`)
        .run(Date.now(), permissions.hasView ? 1 : 0, permissions.hasManage ? 1 : 0, coderedUser.email, coderedUser.name, session.codered_user_id)
      if (!permissions.hasView) {
        db.prepare('DELETE FROM sessions WHERE token_hash = ?').run(tokenHash)
        return null
      }
      session.email = coderedUser.email
      session.name = coderedUser.name
      session.has_view_permission = 1
      session.has_manage_permission = permissions.hasManage ? 1 : 0
    } catch (error) {
      console.error('permission refresh error:', error.message)
    }
    return session
  }

  const requireUser = async (request, response, { manage = false } = {}) => {
    const session = await currentSession(request)
    if (!session) {
      json(response, 401, { message: 'Debes iniciar sesión.' })
      return null
    }
    if (manage && !session.has_manage_permission) {
      logAuthEvent('access_denied', request, { coderedUserId: session.codered_user_id })
      json(response, 403, { message: 'No tienes permiso para esta acción.' })
      return null
    }
    return session
  }

  const handleAuth = async (request, response, pathname) => {
    if (pathname === '/api/auth/session' && request.method === 'GET') {
      const session = await currentSession(request)
      return json(response, 200, { user: session ? userPayload(session.codered_user_id) : null })
    }

    if (pathname === '/api/auth/login' && request.method === 'POST') {
      const body = await readJson(request)
      const email = String(body.email || '').trim().toLowerCase()
      const password = String(body.password || '')
      const ip = requestIp(request) || 'unknown'
      const rateLimitKey = `${ip}:${email}`
      const retryAfterSeconds = checkRateLimit(rateLimitKey)
      if (retryAfterSeconds) {
        return json(response, 429, { message: 'Demasiados intentos. Intenta de nuevo en unos minutos.' }, { 'Retry-After': String(retryAfterSeconds) })
      }
      if (!/^\S+@\S+\.\S+$/.test(email) || !password) {
        return json(response, 400, { message: 'Ingresa tu correo y contraseña.' })
      }

      let coderedUser
      try {
        coderedUser = await findCoderedUserByEmail(email)
      } catch (error) {
        console.error('findCoderedUserByEmail error:', error.message)
        return json(response, 503, { message: 'No se pudo validar tu cuenta. Intenta de nuevo en unos minutos.' })
      }

      // Mismo mensaje genérico para "no existe", "inactivo" y "password
      // incorrecto": evita revelar si un correo está registrado en CodeRED.
      if (!coderedUser || coderedUser.deleted_at || coderedUser.status !== 'active') {
        logAuthEvent('login_failed', request, { email })
        return json(response, 401, { message: 'Correo o contraseña incorrectos.' })
      }

      const validPassword = await bcrypt.compare(password, coderedUser.password)
      if (!validPassword) {
        logAuthEvent('login_failed', request, { email, coderedUserId: coderedUser.id })
        return json(response, 401, { message: 'Correo o contraseña incorrectos.' })
      }

      const permissions = await fetchCoderedPermissions(coderedUser.id)
      if (!permissions.hasView) {
        logAuthEvent('login_no_permission', request, { email, coderedUserId: coderedUser.id })
        return json(response, 403, { message: 'Tu cuenta de CodeRED Platform no tiene acceso a Declaración Jurada.' })
      }

      clearRateLimit(rateLimitKey)
      upsertLocalUser(coderedUser, permissions)
      logAuthEvent('login_success', request, { email, coderedUserId: coderedUser.id })
      return json(response, 200, { user: userPayload(coderedUser.id) }, { 'Set-Cookie': createSession(coderedUser.id) })
    }

    if (pathname === '/api/auth/logout' && request.method === 'POST') {
      const token = cookieValue(request, SESSION_COOKIE)
      if (token) {
        const tokenHash = sha256(token)
        const session = db.prepare('SELECT codered_user_id FROM sessions WHERE token_hash = ?').get(tokenHash)
        db.prepare('DELETE FROM sessions WHERE token_hash = ?').run(tokenHash)
        if (session) logAuthEvent('logout', request, { coderedUserId: session.codered_user_id })
      }
      return json(response, 200, { success: true }, {
        'Set-Cookie': `${SESSION_COOKIE}=; Path=/; HttpOnly; SameSite=Strict; Max-Age=0`
      })
    }
    // /api/auth/register, /api/auth/google y demás rutas retiradas caen
    // aquí (return false) y terminan en el 404 genérico del dispatcher.
    return false
  }

  // Interconectividad de consultas: si CODERED_API_TOKEN está configurado,
  // el DNI se resuelve a través de la propia API de CodeRED Platform
  // (GET /api/v1/dni/{dni}, ability dni:consultar) en vez de llamar
  // directamente al proveedor. Esto centraliza el consumo y la auditoría de
  // consultas DNI en CodeRED, y evita mantener una clave de proveedor
  // duplicada. Si no está configurado, se conserva el flujo original
  // (perudevs_api_key propio) para instalaciones independientes.
  const queryDniViaCodered = async (dni, { coderedUserId, requestId }) => {
    const url = new URL(`/api/v1/dni/${dni}`, env.CODERED_API_URL)
    // 15s: cubre holgadamente una consulta DNI exitosa (normalmente
    // sub-segundo). Si CodeRED tarda más — típicamente porque su proveedor
    // externo está caído y agotó sus reintentos (config/dni.php,
    // ~20s) — se corta aquí para devolver un error rápido y limpio al
    // cliente en vez de dejar la conexión pública abierta más de 20s.
    const upstream = await fetch(url, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${env.CODERED_API_TOKEN}`,
        // Identidad delegada: el token sigue siendo el único crédito de
        // autenticación (ApiClient "Declaración Jurada Shalom"); este
        // header solo le dice a CodeRED a qué usuario final atribuir la
        // consulta en api_request_logs. CodeRED valida por su cuenta que
        // este ApiClient esté autorizado a delegar y que el usuario exista,
        // esté activo y tenga declaracion-jurada.view (ver
        // App\Http\Middleware\ResolveDelegatedUser) — nunca confía
        // ciegamente en este valor.
        'X-CodeRED-User-Id': String(coderedUserId),
        // Id de correlación por intento: permite que CodeRED detecte
        // reintentos del mismo request lógico en api_request_logs
        // (is_duplicate_request), aunque la deduplicación real de crédito
        // ocurre aquí mismo (ver dniIdempotencyCache más abajo).
        'X-Request-Id': requestId
      },
      signal: AbortSignal.timeout(15000)
    })
    const payload = await upstream.json().catch(() => ({}))
    // GET /api/v1/dni/{dni} de CodeRED envuelve los campos en "data"
    // (Laravel JsonResource) — success/meta quedan a nivel superior. Ver
    // App\Http\Resources\Api\V1\DniResource.
    const nombreCompleto = payload?.data?.nombre_completo
    if (!upstream.ok || !payload?.success || !nombreCompleto) {
      throw new Error(payload?.message || 'No se encontró información para el DNI ingresado.')
    }
    return nombreCompleto
  }

  const queryDniViaProvider = async dni => {
    const encryptedApiKey = setting('perudevs_api_key')
    if (!encryptedApiKey) {
      const error = new Error('El servicio de consulta aún no está configurado.')
      error.notConfigured = true
      throw error
    }
    const apiKey = decryptApiKey(encryptedApiKey)
    const url = new URL(env.DNI_API_URL || 'https://api.perudevs.com/api/v1/dni/simple')
    url.searchParams.set('document', dni)
    url.searchParams.set('key', apiKey)
    const upstream = await fetch(url, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(12000)
    })
    const payload = await upstream.json().catch(() => ({}))
    const nombreCompleto = getPersonName(payload)
    if (!upstream.ok || !nombreCompleto) throw new Error('No se encontró información para el DNI ingresado.')
    return nombreCompleto
  }

  // Idempotencia de consultas DNI: un mismo X-Request-Id reintentado (doble
  // click, timeout del cliente, retry de red/Nginx) dentro de esta ventana
  // devuelve la MISMA respuesta ya calculada sin volver a descontar
  // créditos. Solo se cachean éxitos: un intento fallido no dejó ningún
  // efecto permanente (el crédito reservado se devuelve siempre, ver más
  // abajo), así que reintentarlo de verdad es seguro y preferible. Clave
  // por usuario+requestId para que un id reutilizado por otro usuario no
  // reutilice la respuesta ajena.
  const DNI_IDEMPOTENCY_WINDOW_MS = 120_000
  const dniIdempotencyCache = new Map()
  const pruneDniIdempotencyCache = () => {
    const now = Date.now()
    for (const [key, entry] of dniIdempotencyCache) {
      if (entry.expiresAt <= now) dniIdempotencyCache.delete(key)
    }
    if (dniIdempotencyCache.size > 5000) dniIdempotencyCache.clear()
  }

  const handleDni = async (request, response, dni) => {
    const session = await requireUser(request, response)
    if (!session) return
    const userId = session.codered_user_id
    const useCodered = Boolean(env.CODERED_API_TOKEN)
    if (!useCodered && !setting('perudevs_api_key')) {
      return json(response, 503, { message: 'El servicio de consulta aún no está configurado.' })
    }

    const clientRequestId = String(request.headers['x-request-id'] || '').trim().slice(0, 64)
    const idempotencyKey = clientRequestId ? `${userId}:${clientRequestId}` : null
    if (idempotencyKey) {
      pruneDniIdempotencyCache()
      const cached = dniIdempotencyCache.get(idempotencyKey)
      if (cached) return json(response, 200, cached.body)
    }
    // Id reenviado a CodeRED para trazabilidad incluso si el cliente no
    // mandó uno propio (cada intento real recibe un id nuevo en ese caso,
    // así que nunca dedupe entre sí — solo el caché local de arriba,
    // ligado al request-id explícito del cliente, evita el doble descuento).
    const upstreamRequestId = clientRequestId || randomUUID()

    const now = new Date().toISOString()
    let reservedBatch
    db.exec('BEGIN IMMEDIATE')
    try {
      syncUserCredits(userId)
      reservedBatch = db.prepare(`SELECT id FROM credit_batches
        WHERE codered_user_id = ? AND credits_remaining > 0 AND (expires_at IS NULL OR expires_at > ?)
        ORDER BY CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END, expires_at, id LIMIT 1`).get(userId, now)
      if (!reservedBatch) {
        db.exec('ROLLBACK')
        return json(response, 402, { message: 'No tienes consultas vigentes disponibles.' })
      }
      db.prepare('UPDATE credit_batches SET credits_remaining = credits_remaining - 1 WHERE id = ?').run(reservedBatch.id)
      syncUserCredits(userId)
      db.exec('COMMIT')
    } catch (error) {
      db.exec('ROLLBACK')
      throw error
    }

    try {
      const nombreCompleto = useCodered
        ? await queryDniViaCodered(dni, { coderedUserId: userId, requestId: upstreamRequestId })
        : await queryDniViaProvider(dni)
      db.prepare('UPDATE users SET queries_used = queries_used + 1 WHERE codered_user_id = ?').run(userId)
      const updatedUser = userPayload(userId)
      const body = { nombreCompleto, ...updatedUser }
      if (idempotencyKey) dniIdempotencyCache.set(idempotencyKey, { body, expiresAt: Date.now() + DNI_IDEMPOTENCY_WINDOW_MS })
      return json(response, 200, body)
    } catch (error) {
      db.prepare('UPDATE credit_batches SET credits_remaining = credits_remaining + 1 WHERE id = ?').run(reservedBatch.id)
      syncUserCredits(userId)
      return json(response, error.notConfigured ? 503 : 502, { message: error.message || 'No se pudo consultar el DNI.' })
    }
  }

  // Agencias: CodeRED Platform es la única fuente de verdad (antes se leía
  // un JSON estático publicado en un Gist de GitHub, completamente ajeno a
  // CodeRED — ver App.jsx). Este proxy reutiliza el mismo ApiClient/token
  // "Declaración Jurada Shalom" que ya existe para DNI (ability adicional
  // agencias:consultar, ver declaracion-jurada:setup en el repo raíz), y
  // reenvía búsqueda/paginación a GET /api/v1/agencias en vez de cargar
  // miles de agencias de una sola vez. No requiere créditos: listar
  // agencias para completar el formulario no es una consulta facturable.
  // Solo se piden agencias con estado "active" (equivalente al scope
  // publicVisible() de CodeRED: activas y no trasladadas).
  const AGENCIAS_PAGE_SIZE = 60

  const handleAgencias = async (request, response) => {
    const session = await requireUser(request, response)
    if (!session) return
    if (!env.CODERED_API_TOKEN) {
      return json(response, 503, { message: 'El listado de agencias aún no está configurado.' })
    }

    const incomingUrl = new URL(request.url, 'http://localhost')
    const search = (incomingUrl.searchParams.get('search') || '').trim().slice(0, 150)
    const page = Math.max(1, Number.parseInt(incomingUrl.searchParams.get('page'), 10) || 1)

    const url = new URL('/api/v1/agencias', env.CODERED_API_URL)
    url.searchParams.set('estado', 'active')
    url.searchParams.set('per_page', String(AGENCIAS_PAGE_SIZE))
    url.searchParams.set('sort', 'name')
    url.searchParams.set('direction', 'asc')
    url.searchParams.set('page', String(page))
    if (search) url.searchParams.set('agencia', search)

    try {
      const upstream = await fetch(url, {
        headers: { Accept: 'application/json', Authorization: `Bearer ${env.CODERED_API_TOKEN}` },
        // Listado de catálogo, no una consulta con proveedor externo detrás:
        // si CodeRED no responde en este margen, se prefiere fallar rápido
        // y dejar reintentar al usuario (ver mensaje de error abajo) antes
        // que dejar el buscador de sedes colgado.
        signal: AbortSignal.timeout(10000)
      })
      const payload = await upstream.json().catch(() => ({}))
      if (!upstream.ok || payload?.success !== true || !Array.isArray(payload?.data)) {
        throw new Error('upstream_error')
      }
      const items = payload.data.map(agencia => ({
        agencyId: agencia.internal_id,
        agencia: agencia.agencia,
        agenciaAnterior: agencia.agencia_anterior || null,
        departamento: agencia.departamento,
        provincia: agencia.provincia,
        distrito: agencia.distrito,
        direccion: agencia.direccion
      }))
      return json(response, 200, {
        data: items,
        meta: {
          currentPage: payload.meta?.current_page ?? page,
          lastPage: payload.meta?.last_page ?? 1,
          total: payload.meta?.total ?? items.length
        }
      })
    } catch (error) {
      // Nunca se usa silenciosamente un listado local desactualizado: si
      // CodeRED no responde, se informa el error al frontend para que
      // muestre un mensaje claro y permita reintentar.
      console.error('Error consultando agencias en CodeRED:', error)
      return json(response, 502, { message: 'No se pudo obtener el listado de agencias de CodeRED Platform. Inténtalo nuevamente.' })
    }
  }

  const handleCredits = async (request, response, pathname) => {
    const session = await requireUser(request, response)
    if (!session) return
    const userId = session.codered_user_id
    if (pathname === '/api/store' && request.method === 'GET') {
      const packages = db.prepare(`SELECT id, name, credits, price, validity_days AS validityDays
        FROM pricing_packages WHERE active = 1 ORDER BY price`).all()
      const methods = db.prepare(`SELECT id, name, instructions, image_data AS imageData FROM payment_methods
        WHERE active = 1 ORDER BY id`).all()
      return json(response, 200, { packages, methods })
    }
    if (pathname === '/api/credit-requests' && request.method === 'GET') {
      const rows = db.prepare(`SELECT r.id, r.credits, r.amount, r.reference, r.status, r.receipt_sent AS receiptSent,
        r.created_at AS createdAt, p.name AS packageName, m.name AS paymentMethod,
        b.credits_remaining AS creditsRemaining, b.expires_at AS expiresAt
        FROM credit_requests r LEFT JOIN pricing_packages p ON p.id = r.package_id
        LEFT JOIN payment_methods m ON m.id = r.payment_method_id
        LEFT JOIN credit_batches b ON b.credit_request_id = r.id
        WHERE r.codered_user_id = ? ORDER BY r.id DESC`).all(userId)
      return json(response, 200, { requests: rows, user: userPayload(userId) })
    }
    if (pathname === '/api/credit-requests' && request.method === 'POST') {
      const body = await readJson(request, 7_500_000)
      const selectedPackage = db.prepare('SELECT * FROM pricing_packages WHERE id = ? AND active = 1').get(Number(body.packageId))
      const method = db.prepare('SELECT * FROM payment_methods WHERE id = ? AND active = 1').get(Number(body.paymentMethodId))
      const reference = String(body.reference || '').trim()
      if (!selectedPackage || !method || reference.length < 3) return json(response, 400, { message: 'Selecciona un paquete, método de pago e ingresa la referencia.' })
      let receipt
      try {
        receipt = parseReceiptImage(body.receiptImage, body.receiptName)
      } catch {
        return json(response, 400, { message: 'Adjunta un comprobante PNG, JPG o WebP de hasta 5 MB.' })
      }
      const result = db.prepare(`INSERT INTO credit_requests
        (codered_user_id, credits, package_id, payment_method_id, amount, reference)
        VALUES (?, ?, ?, ?, ?, ?)`).run(userId, selectedPackage.credits, selectedPackage.id, method.id, selectedPackage.price, reference)
      try {
        await sendPurchaseToTelegram({ id: result.lastInsertRowid, email: session.email, selectedPackage, method, reference, receipt })
        db.prepare('UPDATE credit_requests SET receipt_sent = 1 WHERE id = ?').run(result.lastInsertRowid)
      } catch (error) {
        db.prepare('DELETE FROM credit_requests WHERE id = ?').run(result.lastInsertRowid)
        const message = error.message === 'TELEGRAM_NOT_CONFIGURED'
          ? 'Las notificaciones de compra aún no están configuradas.'
          : 'No se pudo enviar el comprobante. Intenta nuevamente.'
        return json(response, 503, { message })
      }
      return json(response, 201, { success: true })
    }
    return false
  }

  const handleAdmin = async (request, response, pathname) => {
    const session = await requireUser(request, response, { manage: true })
    if (!session) return
    if (pathname === '/api/admin/users' && request.method === 'GET') {
      const url = new URL(request.url, 'http://localhost')
      const pageSize = 10
      const total = db.prepare('SELECT COUNT(*) AS total FROM users').get().total
      const pages = Math.max(1, Math.ceil(total / pageSize))
      const page = Math.min(pages, Math.max(1, Number.parseInt(url.searchParams.get('page') || '1', 10) || 1))
      const rows = db.prepare('SELECT codered_user_id FROM users ORDER BY codered_user_id DESC LIMIT ? OFFSET ?').all(pageSize, (page - 1) * pageSize)
      return json(response, 200, { users: rows.map(row => userPayload(row.codered_user_id)), total, page, pages })
    }
    if (pathname === '/api/admin/credit-requests' && request.method === 'GET') {
      const requests = db.prepare(`SELECT r.id, r.credits, r.status, r.created_at AS createdAt,
        r.amount, r.reference, r.receipt_sent AS receiptSent, u.email, m.name AS paymentMethod, p.name AS packageName,
        b.credits_remaining AS creditsRemaining, b.expires_at AS expiresAt
        FROM credit_requests r JOIN users u ON u.codered_user_id = r.codered_user_id
        LEFT JOIN payment_methods m ON m.id = r.payment_method_id
        LEFT JOIN pricing_packages p ON p.id = r.package_id
        LEFT JOIN credit_batches b ON b.credit_request_id = r.id ORDER BY r.id DESC`).all()
      return json(response, 200, { requests })
    }
    if (pathname === '/api/admin/config' && request.method === 'GET') {
      return json(response, 200, {
        hasApiKey: Boolean(setting('perudevs_api_key')),
        coderedDniBridge: Boolean(env.CODERED_API_TOKEN),
        telegramConfigured: Boolean(setting('telegram_bot_token') || env.TELEGRAM_BOT_TOKEN) && Boolean(setting('telegram_chat_id') || env.TELEGRAM_CHAT_ID),
        telegramChatId: setting('telegram_chat_id') || env.TELEGRAM_CHAT_ID || '',
        packages: db.prepare(`SELECT id, name, credits, price, validity_days AS validityDays, active FROM pricing_packages ORDER BY id DESC`).all(),
        methods: db.prepare('SELECT id, name, instructions, image_data AS imageData, active FROM payment_methods ORDER BY id DESC').all()
      })
    }
    if (pathname === '/api/admin/config' && request.method === 'PATCH') {
      const body = await readJson(request)
      if (String(body.apiKey || '').trim()) setSetting('perudevs_api_key', encryptApiKey(String(body.apiKey).trim()))
      if (String(body.telegramBotToken || '').trim()) setSetting('telegram_bot_token', encryptApiKey(String(body.telegramBotToken).trim()))
      if (body.telegramChatId !== undefined) setSetting('telegram_chat_id', String(body.telegramChatId).trim() || null)
      return json(response, 200, { success: true })
    }
    if (pathname === '/api/admin/packages' && request.method === 'POST') {
      const body = await readJson(request)
      const values = [String(body.name || '').trim(), Number(body.credits), Number(body.price), Number(body.validityDays)]
      if (!values[0] || !Number.isInteger(values[1]) || values[1] < 1 || values[2] < 0 || !Number.isInteger(values[3]) || values[3] < 1) return json(response, 400, { message: 'Datos del paquete inválidos.' })
      db.prepare('INSERT INTO pricing_packages (name, credits, price, validity_days) VALUES (?, ?, ?, ?)').run(...values)
      return json(response, 201, { success: true })
    }
    if (pathname === '/api/admin/payment-methods' && request.method === 'POST') {
      const body = await readJson(request, 7_500_000)
      const name = String(body.name || '').trim()
      const instructions = String(body.instructions || '').trim()
      if (!name || !instructions) return json(response, 400, { message: 'Completa el método y sus instrucciones.' })
      let imageData = null
      if (body.imageData) {
        try {
          parseReceiptImage(body.imageData, body.imageName)
          imageData = body.imageData
        } catch {
          return json(response, 400, { message: 'La imagen debe ser PNG, JPG o WebP y pesar hasta 5 MB.' })
        }
      }
      db.prepare('INSERT INTO payment_methods (name, instructions, image_data) VALUES (?, ?, ?)').run(name, instructions, imageData)
      return json(response, 201, { success: true })
    }
    const catalogMatch = pathname.match(/^\/api\/admin\/(packages|payment-methods)\/(\d+)$/)
    if (catalogMatch && request.method === 'PATCH') {
      const table = catalogMatch[1] === 'packages' ? 'pricing_packages' : 'payment_methods'
      const body = await readJson(request)
      db.prepare(`UPDATE ${table} SET active = ? WHERE id = ?`).run(body.active ? 1 : 0, catalogMatch[2])
      return json(response, 200, { success: true })
    }
    const userMatch = pathname.match(/^\/api\/admin\/users\/(\d+)$/)
    if (userMatch && request.method === 'PATCH') {
      const targetUserId = Number(userMatch[1])
      if (!(await ensureLocalUserExists(targetUserId))) {
        return json(response, 404, { message: 'Ese usuario no existe (o está eliminado) en CodeRED Platform.' })
      }
      const body = await readJson(request)
      if (body.removeCredits !== undefined) {
        const removeCredits = Number(body.removeCredits)
        if (!Number.isInteger(removeCredits) || removeCredits < 1) {
          return json(response, 400, { message: 'Indica una cantidad válida para retirar.' })
        }
        db.exec('BEGIN IMMEDIATE')
        try {
          syncUserCredits(targetUserId)
          const now = new Date().toISOString()
          const batches = db.prepare(`SELECT id, credits_remaining FROM credit_batches
            WHERE codered_user_id = ? AND credits_remaining > 0 AND (expires_at IS NULL OR expires_at > ?)
            ORDER BY CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END, expires_at, id`).all(targetUserId, now)
          const available = batches.reduce((total, batch) => total + batch.credits_remaining, 0)
          if (removeCredits > available) throw new Error(`El usuario solo tiene ${available} consultas disponibles.`)
          let remaining = removeCredits
          for (const batch of batches) {
            if (!remaining) break
            const amount = Math.min(remaining, batch.credits_remaining)
            db.prepare('UPDATE credit_batches SET credits_remaining = credits_remaining - ? WHERE id = ?').run(amount, batch.id)
            remaining -= amount
          }
          syncUserCredits(targetUserId)
          db.exec('COMMIT')
          return json(response, 200, { success: true })
        } catch (error) {
          db.exec('ROLLBACK')
          return json(response, 400, { message: error.message })
        }
      }
      const addCredits = Number(body.addCredits)
      const expiryTime = Date.parse(body.expiresAt)
      const expiresAt = Number.isFinite(expiryTime) ? new Date(expiryTime).toISOString() : null
      if (!Number.isInteger(addCredits) || addCredits < 1 || !expiresAt || expiryTime <= Date.now()) {
        return json(response, 400, { message: 'Indica una cantidad y una fecha de vencimiento futura.' })
      }
      db.prepare(`INSERT INTO credit_batches
        (codered_user_id, credits_total, credits_remaining, expires_at, source) VALUES (?, ?, ?, ?, 'admin')`)
        .run(targetUserId, addCredits, addCredits, expiresAt)
      syncUserCredits(targetUserId)
      return json(response, 200, { success: true })
    }
    const requestMatch = pathname.match(/^\/api\/admin\/credit-requests\/(\d+)$/)
    if (requestMatch && request.method === 'DELETE') {
      db.exec('BEGIN IMMEDIATE')
      try {
        const creditRequest = db.prepare('SELECT * FROM credit_requests WHERE id = ?').get(requestMatch[1])
        if (!creditRequest) throw new Error('La compra ya no existe.')
        db.prepare('DELETE FROM credit_batches WHERE credit_request_id = ?').run(creditRequest.id)
        db.prepare('DELETE FROM credit_requests WHERE id = ?').run(creditRequest.id)
        syncUserCredits(creditRequest.codered_user_id)
        db.exec('COMMIT')
        return json(response, 200, { success: true })
      } catch (error) {
        db.exec('ROLLBACK')
        return json(response, 404, { message: error.message })
      }
    }
    if (requestMatch && request.method === 'PATCH') {
      const body = await readJson(request)
      if (!['approved', 'rejected'].includes(body.status)) return json(response, 400, { message: 'Estado inválido.' })
      db.exec('BEGIN IMMEDIATE')
      try {
        const creditRequest = db.prepare(`SELECT * FROM credit_requests WHERE id = ? AND status = 'pending'`).get(requestMatch[1])
        if (!creditRequest) throw new Error('La solicitud ya fue procesada.')
        db.prepare(`UPDATE credit_requests SET status = ?, resolved_at = CURRENT_TIMESTAMP, resolved_by_codered_user_id = ? WHERE id = ?`)
          .run(body.status, session.codered_user_id, requestMatch[1])
        if (body.status === 'approved') {
          const validityDays = db.prepare('SELECT validity_days FROM pricing_packages WHERE id = ?').get(creditRequest.package_id)?.validity_days ?? 30
          const expiresAt = new Date(Date.now() + validityDays * 86400000).toISOString()
          db.prepare(`INSERT INTO credit_batches
            (codered_user_id, credit_request_id, credits_total, credits_remaining, expires_at, source)
            VALUES (?, ?, ?, ?, ?, 'purchase')`)
            .run(creditRequest.codered_user_id, creditRequest.id, creditRequest.credits, creditRequest.credits, expiresAt)
          syncUserCredits(creditRequest.codered_user_id)
        }
        db.exec('COMMIT')
        return json(response, 200, { success: true })
      } catch (error) {
        db.exec('ROLLBACK')
        return json(response, 409, { message: error.message })
      }
    }
    return false
  }

  return async (request, response, next) => {
    const pathname = new URL(request.url, 'http://localhost').pathname
    if (!pathname.startsWith('/api/')) return next()
    response.setHeader('Cache-Control', 'no-store')
    try {
      // Público, sin sesión: URL de CodeRED Platform para el enlace "Utiliza
      // tu cuenta de CodeRED Platform" (login) y "Administra tu cuenta" en
      // el panel — ninguna gestión de cuenta ocurre aquí, solo se enlaza.
      if (pathname === '/api/config' && request.method === 'GET') {
        return json(response, 200, { coderedUrl: env.CODERED_PUBLIC_URL || '' })
      }
      if (pathname.startsWith('/api/auth/')) {
        const handled = await handleAuth(request, response, pathname)
        if (handled !== false) return
      }
      const dniMatch = pathname.match(/^\/api\/dni\/(\d{8})$/)
      if (dniMatch && request.method === 'GET') return await handleDni(request, response, dniMatch[1])
      if (pathname === '/api/agencias' && request.method === 'GET') return await handleAgencias(request, response)
      if (pathname === '/api/store' || pathname.startsWith('/api/credit-requests')) {
        const handled = await handleCredits(request, response, pathname)
        if (handled !== false) return
      }
      if (pathname.startsWith('/api/admin/')) {
        const handled = await handleAdmin(request, response, pathname)
        if (handled !== false) return
      }
      return json(response, 404, { message: 'Endpoint no encontrado.' })
    } catch (error) {
      const message = error.message === 'MISSING_ENCRYPTION_KEY'
        ? 'Configura APP_ENCRYPTION_KEY antes de asignar claves API.'
        : error.message === 'PAYLOAD_TOO_LARGE'
          ? 'El archivo enviado es demasiado grande.'
          : 'Ocurrió un error interno.'
      console.error(error)
      return json(response, error.message === 'PAYLOAD_TOO_LARGE' ? 413 : 500, { message })
    }
  }
}
