import { createHash, randomBytes, scrypt as scryptCallback, createCipheriv, createDecipheriv, createPublicKey, timingSafeEqual, verify as verifySignature } from 'node:crypto'
import { Buffer } from 'node:buffer'
import { mkdirSync } from 'node:fs'
import { dirname } from 'node:path'
import { promisify } from 'node:util'
import { DatabaseSync } from 'node:sqlite'
import { Pool } from 'pg'

const scrypt = promisify(scryptCallback)
const SESSION_COOKIE = 'dj_session'
const SESSION_DAYS = 7

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

const passwordHash = async (password, salt = randomBytes(16).toString('hex')) => ({
  salt,
  hash: (await scrypt(password, salt, 64)).toString('hex')
})

const safeUser = (user, creditBatches = []) => user && ({
  id: user.id,
  email: user.email,
  role: user.role,
  credits: user.credits,
  queriesUsed: user.queries_used,
  creditsExpiresAt: user.credits_expires_at,
  creditBatches,
  coderedLinked: Boolean(user.codered_user_id),
  coderedName: user.codered_name || null
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
  const databasePath = env.DATABASE_PATH || '.data/declaracion-jurada.db'
  mkdirSync(dirname(databasePath), { recursive: true })
  const db = new DatabaseSync(databasePath)
  db.exec(`
    PRAGMA journal_mode = WAL;
    PRAGMA foreign_keys = ON;
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      email TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      password_salt TEXT NOT NULL,
      role TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('user', 'admin')),
      api_key_encrypted TEXT,
      credits INTEGER NOT NULL DEFAULT 0 CHECK(credits >= 0),
      queries_used INTEGER NOT NULL DEFAULT 0 CHECK(queries_used >= 0),
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS sessions (
      token_hash TEXT PRIMARY KEY,
      user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      expires_at INTEGER NOT NULL
    );
    CREATE TABLE IF NOT EXISTS credit_requests (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      credits INTEGER NOT NULL CHECK(credits > 0),
      status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'rejected')),
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      resolved_at TEXT,
      resolved_by INTEGER REFERENCES users(id)
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
    CREATE TABLE IF NOT EXISTS email_verifications (
      email TEXT PRIMARY KEY,
      code_hash TEXT NOT NULL,
      expires_at INTEGER NOT NULL,
      attempts INTEGER NOT NULL DEFAULT 0,
      purpose TEXT NOT NULL DEFAULT 'register'
    );
    CREATE TABLE IF NOT EXISTS credit_batches (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      credit_request_id INTEGER UNIQUE REFERENCES credit_requests(id) ON DELETE SET NULL,
      credits_total INTEGER NOT NULL CHECK(credits_total > 0),
      credits_remaining INTEGER NOT NULL CHECK(credits_remaining >= 0),
      expires_at TEXT,
      source TEXT NOT NULL DEFAULT 'purchase',
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
  `)

  const addColumn = (table, definition) => {
    const name = definition.split(' ')[0]
    if (!db.prepare(`PRAGMA table_info(${table})`).all().some(column => column.name === name)) {
      db.exec(`ALTER TABLE ${table} ADD COLUMN ${definition}`)
    }
  }
  addColumn('users', 'credits_expires_at TEXT')
  addColumn('credit_requests', 'package_id INTEGER')
  addColumn('credit_requests', 'payment_method_id INTEGER')
  addColumn('credit_requests', 'amount REAL')
  addColumn('credit_requests', 'reference TEXT')
  addColumn('credit_requests', 'receipt_sent INTEGER NOT NULL DEFAULT 0')
  addColumn('payment_methods', 'image_data TEXT')
  addColumn('users', 'google_sub TEXT')
  addColumn('email_verifications', "purpose TEXT NOT NULL DEFAULT 'register'")
  db.exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_google_sub ON users(google_sub) WHERE google_sub IS NOT NULL')
  // Interconectividad con CodeRED Platform: enlace best-effort (por email) a
  // la tabla `users` de CodeRED Platform. No es una FK real (bases de datos
  // separadas) — es un vínculo informativo poblado por lookupCoderedUser().
  addColumn('users', 'codered_user_id INTEGER')
  addColumn('users', 'codered_name TEXT')

  db.prepare(`INSERT INTO credit_batches (user_id, credits_total, credits_remaining, expires_at, source)
    SELECT u.id, u.credits, u.credits, u.credits_expires_at, 'legacy'
    FROM users u WHERE u.credits > 0
    AND NOT EXISTS (SELECT 1 FROM credit_batches b WHERE b.user_id = u.id)`).run()

  const batchRows = userId => db.prepare(`SELECT id, credits_total AS creditsTotal,
    credits_remaining AS creditsRemaining, expires_at AS expiresAt, source, created_at AS createdAt
    FROM credit_batches WHERE user_id = ? ORDER BY created_at DESC, id DESC`).all(userId)

  const syncUserCredits = userId => {
    const now = new Date().toISOString()
    const summary = db.prepare(`SELECT COALESCE(SUM(credits_remaining), 0) AS credits,
      MIN(expires_at) AS nextExpiry FROM credit_batches
      WHERE user_id = ? AND credits_remaining > 0 AND (expires_at IS NULL OR expires_at > ?)`)
      .get(userId, now)
    db.prepare('UPDATE users SET credits = ?, credits_expires_at = ? WHERE id = ?')
      .run(summary.credits, summary.nextExpiry, userId)
    return summary
  }

  const userPayload = user => {
    if (!user) return null
    syncUserCredits(user.id)
    const refreshed = db.prepare('SELECT * FROM users WHERE id = ?').get(user.id)
    const now = Date.now()
    const batches = batchRows(user.id).map(batch => ({
      ...batch,
      active: batch.creditsRemaining > 0 && (!batch.expiresAt || new Date(batch.expiresAt).getTime() > now)
    }))
    return safeUser(refreshed, batches)
  }

  // --- Interconectividad con CodeRED Platform -------------------------------
  // Pool read-only hacia la misma base PostgreSQL de CodeRED Platform (solo
  // se crea si CODERED_DB_HOST está configurado). Se usa exclusivamente para
  // reconocer, por email, si una cuenta de Declaración Jurada corresponde a
  // un usuario existente de la plataforma — nunca para escribir en `users`.
  const coderedPool = env.CODERED_DB_HOST
    ? new Pool({
        host: env.CODERED_DB_HOST,
        port: Number(env.CODERED_DB_PORT) || 5432,
        database: env.CODERED_DB_DATABASE,
        user: env.CODERED_DB_USERNAME,
        password: env.CODERED_DB_PASSWORD,
        max: 3,
        idleTimeoutMillis: 30000,
        connectionTimeoutMillis: 5000
      })
    : null

  if (coderedPool) coderedPool.on('error', error => console.error('CodeRED Postgres pool error:', error.message))

  // Best-effort: nunca debe romper el registro/login de Declaración Jurada
  // si CodeRED Platform o la base compartida no están disponibles.
  const linkCoderedUser = async (userId, email) => {
    if (!coderedPool) return
    try {
      const { rows } = await coderedPool.query(
        'SELECT id, name FROM users WHERE lower(email) = lower($1) AND deleted_at IS NULL LIMIT 1',
        [email]
      )
      const match = rows[0]
      db.prepare('UPDATE users SET codered_user_id = ?, codered_name = ? WHERE id = ?')
        .run(match?.id ?? null, match?.name ?? null, userId)
    } catch (error) {
      console.error('lookupCoderedUser error:', error.message)
    }
  }

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

  const sendVerificationCode = async (email, code) => {
    const provider = setting('email_provider') || env.EMAIL_PROVIDER || (setting('mailgun_api_key') || env.MAILGUN_API_KEY ? 'mailgun' : 'resend')
    const emailFrom = setting('email_from') || env.EMAIL_FROM
    const senderAddress = String(emailFrom || '').match(/<([^<>]+)>$/)?.[1] || String(emailFrom || '').trim()
    if (!/^\S+@\S+\.\S+$/.test(senderAddress)) throw new Error('EMAIL_FROM_INVALID')
    const subject = 'Código de verificación - Declaración Jurada'
    const html = `<p>Tu código de verificación es:</p><h1>${code}</h1><p>Vence en 10 minutos.</p>`
    let response
    if (provider === 'mailgun') {
      const encryptedMailgunKey = setting('mailgun_api_key')
      const mailgunApiKey = encryptedMailgunKey ? decryptApiKey(encryptedMailgunKey) : env.MAILGUN_API_KEY
      const mailgunDomain = setting('mailgun_domain') || env.MAILGUN_DOMAIN
      const mailgunRegion = setting('mailgun_region') || env.MAILGUN_REGION || 'us'
      if (!mailgunApiKey || !mailgunDomain || !emailFrom) throw new Error('EMAIL_NOT_CONFIGURED')
      const form = new URLSearchParams({ from: emailFrom, to: email, subject, html })
      const apiHost = mailgunRegion === 'eu' ? 'https://api.eu.mailgun.net' : 'https://api.mailgun.net'
      response = await fetch(`${apiHost}/v3/${encodeURIComponent(mailgunDomain)}/messages`, {
        method: 'POST',
        headers: {
          Authorization: `Basic ${Buffer.from(`api:${mailgunApiKey}`).toString('base64')}`,
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: form,
        signal: AbortSignal.timeout(10000)
      })
    } else {
      const encryptedResendKey = setting('resend_api_key')
      const resendApiKey = encryptedResendKey ? decryptApiKey(encryptedResendKey) : env.RESEND_API_KEY
      if (!resendApiKey || !emailFrom) throw new Error('EMAIL_NOT_CONFIGURED')
      response = await fetch(env.RESEND_API_URL || 'https://api.resend.com/emails', {
        method: 'POST',
        headers: { Authorization: `Bearer ${resendApiKey}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ from: emailFrom, to: [email], subject, html }),
        signal: AbortSignal.timeout(10000)
      })
    }
    if (!response.ok) {
      const details = await response.text().catch(() => '')
      console.error(`${provider} email error (${response.status}):`, details.slice(0, 1000))
      const error = new Error('EMAIL_SEND_FAILED')
      error.statusCode = response.status
      throw error
    }
  }

  const emailFailureMessage = (error, fallback) => {
    if (error.message === 'EMAIL_NOT_CONFIGURED') return 'El envío de correos aún no está configurado.'
    if (error.message === 'EMAIL_FROM_INVALID') return 'El remitente no es válido. Usa por ejemplo: Declaración Jurada <auth@tudominio.com>.'
    if (error.statusCode === 401) return 'Mailgun rechazó la API Key. Usa una Private API Key o Domain Sending Key válida.'
    if (error.statusCode === 404) return 'Mailgun no encontró el dominio. Comprueba el dominio de envío y la región US/EU.'
    if (error.statusCode === 400) return 'Mailgun rechazó el remitente o la configuración del dominio.'
    if (error.statusCode === 403) return 'Mailgun no autorizó el envío. Verifica el dominio o autoriza el destinatario si usas Sandbox.'
    return fallback
  }

  const saveVerificationCode = (email, code, purpose) => db.prepare(`INSERT INTO email_verifications
    (email, code_hash, expires_at, attempts, purpose) VALUES (?, ?, ?, 0, ?)
    ON CONFLICT(email) DO UPDATE SET code_hash = excluded.code_hash,
    expires_at = excluded.expires_at, attempts = 0, purpose = excluded.purpose`)
    .run(email, sha256(code), Date.now() + 600000, purpose)

  let googleKeysCache = { expiresAt: 0, keys: [] }
  const verifyGoogleCredential = async credential => {
    const clientId = setting('google_client_id') || env.GOOGLE_CLIENT_ID
    if (!clientId) throw new Error('GOOGLE_NOT_CONFIGURED')
    const parts = String(credential || '').split('.')
    if (parts.length !== 3) throw new Error('INVALID_GOOGLE_TOKEN')
    const header = JSON.parse(Buffer.from(parts[0], 'base64url').toString('utf8'))
    const payload = JSON.parse(Buffer.from(parts[1], 'base64url').toString('utf8'))
    if (header.alg !== 'RS256' || !header.kid) throw new Error('INVALID_GOOGLE_TOKEN')
    if (googleKeysCache.expiresAt <= Date.now()) {
      const keysResponse = await fetch(env.GOOGLE_JWKS_URL || 'https://www.googleapis.com/oauth2/v3/certs', {
        signal: AbortSignal.timeout(10000)
      })
      if (!keysResponse.ok) throw new Error('GOOGLE_KEYS_FAILED')
      googleKeysCache = { keys: (await keysResponse.json()).keys || [], expiresAt: Date.now() + 3600000 }
    }
    const jwk = googleKeysCache.keys.find(key => key.kid === header.kid)
    if (!jwk || !verifySignature('RSA-SHA256', Buffer.from(`${parts[0]}.${parts[1]}`), createPublicKey({ key: jwk, format: 'jwk' }), Buffer.from(parts[2], 'base64url'))) {
      throw new Error('INVALID_GOOGLE_TOKEN')
    }
    const audienceValid = Array.isArray(payload.aud) ? payload.aud.includes(clientId) : payload.aud === clientId
    const issuerValid = ['accounts.google.com', 'https://accounts.google.com'].includes(payload.iss)
    if (!audienceValid || !issuerValid || payload.exp * 1000 <= Date.now() || payload.email_verified !== true || !payload.email || !payload.sub) {
      throw new Error('INVALID_GOOGLE_TOKEN')
    }
    return { email: String(payload.email).toLowerCase(), sub: String(payload.sub) }
  }

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

  const ensureAdmin = (async () => {
    if (!env.ADMIN_EMAIL || !env.ADMIN_PASSWORD) return
    const email = env.ADMIN_EMAIL.trim().toLowerCase()
    if (db.prepare('SELECT id FROM users WHERE email = ?').get(email)) return
    const password = await passwordHash(env.ADMIN_PASSWORD)
    db.prepare(`INSERT INTO users (email, password_hash, password_salt, role)
      VALUES (?, ?, ?, 'admin')`).run(email, password.hash, password.salt)
  })()

  const currentUser = request => {
    const token = cookieValue(request, SESSION_COOKIE)
    if (!token) return null
    return db.prepare(`SELECT u.* FROM sessions s JOIN users u ON u.id = s.user_id
      WHERE s.token_hash = ? AND s.expires_at > ?`).get(sha256(token), Date.now()) ?? null
  }

  const requireUser = (request, response, role) => {
    const user = currentUser(request)
    if (!user) {
      json(response, 401, { message: 'Debes iniciar sesión.' })
      return null
    }
    if (role && user.role !== role) {
      json(response, 403, { message: 'No tienes permiso para esta acción.' })
      return null
    }
    return user
  }

  const createSession = (response, userId) => {
    const token = randomBytes(32).toString('base64url')
    const expiresAt = Date.now() + SESSION_DAYS * 86_400_000
    db.prepare('INSERT INTO sessions (token_hash, user_id, expires_at) VALUES (?, ?, ?)')
      .run(sha256(token), userId, expiresAt)
    const secure = env.COOKIE_SECURE === 'true' ? '; Secure' : ''
    return `${SESSION_COOKIE}=${encodeURIComponent(token)}; Path=/; HttpOnly; SameSite=Strict; Max-Age=${SESSION_DAYS * 86400}${secure}`
  }

  const handleAuth = async (request, response, pathname) => {
    if (pathname === '/api/auth/config' && request.method === 'GET') {
      return json(response, 200, { googleClientId: setting('google_client_id') || env.GOOGLE_CLIENT_ID || '' })
    }

    if (pathname === '/api/auth/session' && request.method === 'GET') {
      return json(response, 200, { user: userPayload(currentUser(request)) })
    }

    if (pathname === '/api/auth/send-code' && request.method === 'POST') {
      const body = await readJson(request)
      const email = String(body.email || '').trim().toLowerCase()
      if (!/^\S+@\S+\.\S+$/.test(email)) return json(response, 400, { message: 'Ingresa un correo válido.' })
      if (db.prepare('SELECT id FROM users WHERE email = ?').get(email)) return json(response, 409, { message: 'Este correo ya está registrado.' })
      const code = String(Math.floor(100000 + Math.random() * 900000))
      try {
        await sendVerificationCode(email, code)
      } catch (error) {
        return json(response, 503, { message: emailFailureMessage(error, 'No se pudo enviar el código de verificación.') })
      }
      saveVerificationCode(email, code, 'register')
      return json(response, 200, { success: true })
    }

    if (pathname === '/api/auth/password/send-code' && request.method === 'POST') {
      const body = await readJson(request)
      const email = String(body.email || '').trim().toLowerCase()
      if (!/^\S+@\S+\.\S+$/.test(email)) return json(response, 400, { message: 'Ingresa un correo válido.' })
      if (db.prepare('SELECT id FROM users WHERE email = ?').get(email)) {
        const code = String(Math.floor(100000 + Math.random() * 900000))
        try {
          await sendVerificationCode(email, code)
          saveVerificationCode(email, code, 'reset')
        } catch (error) {
          return json(response, 503, { message: emailFailureMessage(error, 'No se pudo enviar el código de recuperación.') })
        }
      }
      return json(response, 200, { success: true })
    }

    if (pathname === '/api/auth/password/reset' && request.method === 'POST') {
      const body = await readJson(request)
      const email = String(body.email || '').trim().toLowerCase()
      const password = String(body.password || '')
      const code = String(body.code || '').trim()
      const user = db.prepare('SELECT * FROM users WHERE email = ?').get(email)
      const verification = db.prepare("SELECT * FROM email_verifications WHERE email = ? AND purpose = 'reset'").get(email)
      if (password.length < 8) return json(response, 400, { message: 'La contraseña debe tener al menos 8 caracteres.' })
      if (!user || !verification || verification.expires_at < Date.now() || verification.attempts >= 5 || verification.code_hash !== sha256(code)) {
        if (verification) db.prepare('UPDATE email_verifications SET attempts = attempts + 1 WHERE email = ?').run(email)
        return json(response, 400, { message: 'El código es inválido o ha vencido.' })
      }
      const derived = await passwordHash(password)
      db.prepare('UPDATE users SET password_hash = ?, password_salt = ? WHERE id = ?').run(derived.hash, derived.salt, user.id)
      db.prepare('DELETE FROM sessions WHERE user_id = ?').run(user.id)
      db.prepare('DELETE FROM email_verifications WHERE email = ?').run(email)
      return json(response, 200, { success: true })
    }

    if (pathname === '/api/auth/change-password' && request.method === 'POST') {
      const user = requireUser(request, response)
      if (!user) return
      const body = await readJson(request)
      const currentPassword = String(body.currentPassword || '')
      const newPassword = String(body.newPassword || '')
      if (newPassword.length < 8) return json(response, 400, { message: 'La nueva contraseña debe tener al menos 8 caracteres.' })
      const derivedCurrent = await passwordHash(currentPassword, user.password_salt)
      if (!timingSafeEqual(Buffer.from(derivedCurrent.hash, 'hex'), Buffer.from(user.password_hash, 'hex'))) {
        return json(response, 401, { message: 'La contraseña actual no es correcta.' })
      }
      const derivedNew = await passwordHash(newPassword)
      db.prepare('UPDATE users SET password_hash = ?, password_salt = ? WHERE id = ?').run(derivedNew.hash, derivedNew.salt, user.id)
      db.prepare('DELETE FROM sessions WHERE user_id = ? AND token_hash != ?').run(user.id, sha256(cookieValue(request, SESSION_COOKIE) || ''))
      return json(response, 200, { success: true })
    }

    if (pathname === '/api/auth/google' && request.method === 'POST') {
      try {
        const body = await readJson(request)
        const identity = await verifyGoogleCredential(body.credential)
        let user = db.prepare('SELECT * FROM users WHERE google_sub = ? OR email = ?').get(identity.sub, identity.email)
        if (!user) {
          const derived = await passwordHash(randomBytes(32).toString('base64url'))
          const result = db.prepare(`INSERT INTO users (email, password_hash, password_salt, google_sub)
            VALUES (?, ?, ?, ?)`).run(identity.email, derived.hash, derived.salt, identity.sub)
          user = db.prepare('SELECT * FROM users WHERE id = ?').get(result.lastInsertRowid)
        } else if (!user.google_sub) {
          db.prepare('UPDATE users SET google_sub = ? WHERE id = ?').run(identity.sub, user.id)
          user.google_sub = identity.sub
        } else if (user.google_sub !== identity.sub) {
          return json(response, 409, { message: 'El correo ya está asociado a otra cuenta de Google.' })
        }
        if (!user.codered_user_id) await linkCoderedUser(user.id, user.email)
        return json(response, 200, { user: userPayload(user) }, { 'Set-Cookie': createSession(response, user.id) })
      } catch (error) {
        const message = error.message === 'GOOGLE_NOT_CONFIGURED'
          ? 'El acceso con Google aún no está configurado.'
          : 'No se pudo validar la cuenta de Google.'
        return json(response, 401, { message })
      }
    }

    if (pathname === '/api/auth/register' && request.method === 'POST') {
      const body = await readJson(request)
      const email = String(body.email || '').trim().toLowerCase()
      const password = String(body.password || '')
      const code = String(body.code || '').trim()
      if (!/^\S+@\S+\.\S+$/.test(email) || password.length < 8) {
        return json(response, 400, { message: 'Usa un correo válido y una contraseña de al menos 8 caracteres.' })
      }
      if (db.prepare('SELECT id FROM users WHERE email = ?').get(email)) {
        return json(response, 409, { message: 'Este correo ya está registrado.' })
      }
      const verification = db.prepare("SELECT * FROM email_verifications WHERE email = ? AND purpose = 'register'").get(email)
      if (!verification || verification.expires_at < Date.now() || verification.attempts >= 5 || verification.code_hash !== sha256(code)) {
        if (verification) db.prepare('UPDATE email_verifications SET attempts = attempts + 1 WHERE email = ?').run(email)
        return json(response, 400, { message: 'El código es inválido o ha vencido.' })
      }
      const derived = await passwordHash(password)
      const result = db.prepare(`INSERT INTO users (email, password_hash, password_salt)
        VALUES (?, ?, ?)`).run(email, derived.hash, derived.salt)
      db.prepare('DELETE FROM email_verifications WHERE email = ?').run(email)
      await linkCoderedUser(result.lastInsertRowid, email)
      return json(response, 201, { user: userPayload(db.prepare('SELECT * FROM users WHERE id = ?').get(result.lastInsertRowid)) }, {
        'Set-Cookie': createSession(response, result.lastInsertRowid)
      })
    }

    if (pathname === '/api/auth/login' && request.method === 'POST') {
      const body = await readJson(request)
      const user = db.prepare('SELECT * FROM users WHERE email = ?').get(String(body.email || '').trim().toLowerCase())
      if (!user) return json(response, 401, { message: 'Correo o contraseña incorrectos.' })
      const derived = await passwordHash(String(body.password || ''), user.password_salt)
      const validPassword = timingSafeEqual(Buffer.from(derived.hash, 'hex'), Buffer.from(user.password_hash, 'hex'))
      if (!validPassword) return json(response, 401, { message: 'Correo o contraseña incorrectos.' })
      if (!user.codered_user_id) await linkCoderedUser(user.id, user.email)
      return json(response, 200, { user: userPayload(user) }, { 'Set-Cookie': createSession(response, user.id) })
    }

    if (pathname === '/api/auth/logout' && request.method === 'POST') {
      const token = cookieValue(request, SESSION_COOKIE)
      if (token) db.prepare('DELETE FROM sessions WHERE token_hash = ?').run(sha256(token))
      return json(response, 200, { success: true }, {
        'Set-Cookie': `${SESSION_COOKIE}=; Path=/; HttpOnly; SameSite=Strict; Max-Age=0`
      })
    }
    return false
  }

  // Interconectividad de consultas: si CODERED_API_TOKEN está configurado,
  // el DNI se resuelve a través de la propia API de CodeRED Platform
  // (GET /api/v1/dni/{dni}, ability dni:consultar) en vez de llamar
  // directamente al proveedor. Esto centraliza el consumo y la auditoría de
  // consultas DNI en CodeRED, y evita mantener una clave de proveedor
  // duplicada. Si no está configurado, se conserva el flujo original
  // (perudevs_api_key propio) para instalaciones independientes.
  const queryDniViaCodered = async dni => {
    const url = new URL(`/api/v1/dni/${dni}`, env.CODERED_API_URL)
    const upstream = await fetch(url, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${env.CODERED_API_TOKEN}` },
      signal: AbortSignal.timeout(12000)
    })
    const payload = await upstream.json().catch(() => ({}))
    if (!upstream.ok || !payload?.success || !payload?.nombre_completo) {
      throw new Error(payload?.message || 'No se encontró información para el DNI ingresado.')
    }
    return payload.nombre_completo
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

  const handleDni = async (request, response, dni) => {
    const user = requireUser(request, response)
    if (!user) return
    const useCodered = Boolean(env.CODERED_API_TOKEN)
    if (!useCodered && !setting('perudevs_api_key')) {
      return json(response, 503, { message: 'El servicio de consulta aún no está configurado.' })
    }
    const now = new Date().toISOString()
    let reservedBatch
    db.exec('BEGIN IMMEDIATE')
    try {
      syncUserCredits(user.id)
      reservedBatch = db.prepare(`SELECT id FROM credit_batches
        WHERE user_id = ? AND credits_remaining > 0 AND (expires_at IS NULL OR expires_at > ?)
        ORDER BY CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END, expires_at, id LIMIT 1`).get(user.id, now)
      if (!reservedBatch) {
        db.exec('ROLLBACK')
        return json(response, 402, { message: 'No tienes consultas vigentes disponibles.' })
      }
      db.prepare('UPDATE credit_batches SET credits_remaining = credits_remaining - 1 WHERE id = ?').run(reservedBatch.id)
      syncUserCredits(user.id)
      db.exec('COMMIT')
    } catch (error) {
      db.exec('ROLLBACK')
      throw error
    }

    try {
      const nombreCompleto = useCodered ? await queryDniViaCodered(dni) : await queryDniViaProvider(dni)
      db.prepare('UPDATE users SET queries_used = queries_used + 1 WHERE id = ?').run(user.id)
      const updatedUser = userPayload(db.prepare('SELECT * FROM users WHERE id = ?').get(user.id))
      return json(response, 200, { nombreCompleto, ...updatedUser })
    } catch (error) {
      db.prepare('UPDATE credit_batches SET credits_remaining = credits_remaining + 1 WHERE id = ?').run(reservedBatch.id)
      syncUserCredits(user.id)
      return json(response, error.notConfigured ? 503 : 502, { message: error.message || 'No se pudo consultar el DNI.' })
    }
  }

  const handleCredits = async (request, response, pathname) => {
    const user = requireUser(request, response)
    if (!user) return
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
        WHERE r.user_id = ? ORDER BY r.id DESC`).all(user.id)
      return json(response, 200, { requests: rows, user: userPayload(user) })
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
        (user_id, credits, package_id, payment_method_id, amount, reference)
        VALUES (?, ?, ?, ?, ?, ?)`).run(user.id, selectedPackage.credits, selectedPackage.id, method.id, selectedPackage.price, reference)
      try {
        await sendPurchaseToTelegram({ id: result.lastInsertRowid, email: user.email, selectedPackage, method, reference, receipt })
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
    const admin = requireUser(request, response, 'admin')
    if (!admin) return
    if (pathname === '/api/admin/users' && request.method === 'GET') {
      const url = new URL(request.url, 'http://localhost')
      const pageSize = 10
      const total = db.prepare('SELECT COUNT(*) AS total FROM users').get().total
      const pages = Math.max(1, Math.ceil(total / pageSize))
      const page = Math.min(pages, Math.max(1, Number.parseInt(url.searchParams.get('page') || '1', 10) || 1))
      const rows = db.prepare('SELECT * FROM users ORDER BY id DESC LIMIT ? OFFSET ?').all(pageSize, (page - 1) * pageSize)
      return json(response, 200, { users: rows.map(userPayload), total, page, pages })
    }
    if (pathname === '/api/admin/credit-requests' && request.method === 'GET') {
      const requests = db.prepare(`SELECT r.id, r.credits, r.status, r.created_at AS createdAt,
        r.amount, r.reference, r.receipt_sent AS receiptSent, u.email, m.name AS paymentMethod, p.name AS packageName,
        b.credits_remaining AS creditsRemaining, b.expires_at AS expiresAt
        FROM credit_requests r JOIN users u ON u.id = r.user_id
        LEFT JOIN payment_methods m ON m.id = r.payment_method_id
        LEFT JOIN pricing_packages p ON p.id = r.package_id
        LEFT JOIN credit_batches b ON b.credit_request_id = r.id ORDER BY r.id DESC`).all()
      return json(response, 200, { requests })
    }
    if (pathname === '/api/admin/config' && request.method === 'GET') {
      const emailProvider = setting('email_provider') || env.EMAIL_PROVIDER || (setting('mailgun_api_key') || env.MAILGUN_API_KEY ? 'mailgun' : 'resend')
      const emailCredentialsConfigured = emailProvider === 'mailgun'
        ? Boolean(setting('mailgun_api_key') || env.MAILGUN_API_KEY) && Boolean(setting('mailgun_domain') || env.MAILGUN_DOMAIN)
        : Boolean(setting('resend_api_key') || env.RESEND_API_KEY)
      return json(response, 200, {
        hasApiKey: Boolean(setting('perudevs_api_key')),
        coderedDniBridge: Boolean(env.CODERED_API_TOKEN),
        coderedUserLinking: Boolean(coderedPool),
        telegramConfigured: Boolean(setting('telegram_bot_token') || env.TELEGRAM_BOT_TOKEN) && Boolean(setting('telegram_chat_id') || env.TELEGRAM_CHAT_ID),
        telegramChatId: setting('telegram_chat_id') || env.TELEGRAM_CHAT_ID || '',
        emailProvider,
        emailConfigured: emailCredentialsConfigured && Boolean(setting('email_from') || env.EMAIL_FROM),
        emailFrom: setting('email_from') || env.EMAIL_FROM || '',
        mailgunDomain: setting('mailgun_domain') || env.MAILGUN_DOMAIN || '',
        mailgunRegion: setting('mailgun_region') || env.MAILGUN_REGION || 'us',
        googleClientId: setting('google_client_id') || env.GOOGLE_CLIENT_ID || '',
        packages: db.prepare(`SELECT id, name, credits, price, validity_days AS validityDays, active FROM pricing_packages ORDER BY id DESC`).all(),
        methods: db.prepare('SELECT id, name, instructions, image_data AS imageData, active FROM payment_methods ORDER BY id DESC').all()
      })
    }
    if (pathname === '/api/admin/config' && request.method === 'PATCH') {
      const body = await readJson(request)
      if (String(body.apiKey || '').trim()) setSetting('perudevs_api_key', encryptApiKey(String(body.apiKey).trim()))
      if (String(body.telegramBotToken || '').trim()) setSetting('telegram_bot_token', encryptApiKey(String(body.telegramBotToken).trim()))
      if (body.telegramChatId !== undefined) setSetting('telegram_chat_id', String(body.telegramChatId).trim() || null)
      if (String(body.resendApiKey || '').trim()) setSetting('resend_api_key', encryptApiKey(String(body.resendApiKey).trim()))
      if (String(body.mailgunApiKey || '').trim()) setSetting('mailgun_api_key', encryptApiKey(String(body.mailgunApiKey).trim()))
      if (body.emailProvider !== undefined && ['resend', 'mailgun'].includes(body.emailProvider)) setSetting('email_provider', body.emailProvider)
      if (body.mailgunDomain !== undefined) setSetting('mailgun_domain', String(body.mailgunDomain).trim() || null)
      if (body.mailgunRegion !== undefined && ['us', 'eu'].includes(body.mailgunRegion)) setSetting('mailgun_region', body.mailgunRegion)
      if (body.emailFrom !== undefined) setSetting('email_from', String(body.emailFrom).trim() || null)
      if (body.googleClientId !== undefined) setSetting('google_client_id', String(body.googleClientId).trim() || null)
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
      const body = await readJson(request)
      if (body.removeCredits !== undefined) {
        const removeCredits = Number(body.removeCredits)
        if (!Number.isInteger(removeCredits) || removeCredits < 1) {
          return json(response, 400, { message: 'Indica una cantidad válida para retirar.' })
        }
        db.exec('BEGIN IMMEDIATE')
        try {
          syncUserCredits(userMatch[1])
          const now = new Date().toISOString()
          const batches = db.prepare(`SELECT id, credits_remaining FROM credit_batches
            WHERE user_id = ? AND credits_remaining > 0 AND (expires_at IS NULL OR expires_at > ?)
            ORDER BY CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END, expires_at, id`).all(userMatch[1], now)
          const available = batches.reduce((total, batch) => total + batch.credits_remaining, 0)
          if (removeCredits > available) throw new Error(`El usuario solo tiene ${available} consultas disponibles.`)
          let remaining = removeCredits
          for (const batch of batches) {
            if (!remaining) break
            const amount = Math.min(remaining, batch.credits_remaining)
            db.prepare('UPDATE credit_batches SET credits_remaining = credits_remaining - ? WHERE id = ?').run(amount, batch.id)
            remaining -= amount
          }
          syncUserCredits(userMatch[1])
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
        (user_id, credits_total, credits_remaining, expires_at, source) VALUES (?, ?, ?, ?, 'admin')`)
        .run(userMatch[1], addCredits, addCredits, expiresAt)
      syncUserCredits(userMatch[1])
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
        syncUserCredits(creditRequest.user_id)
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
        db.prepare(`UPDATE credit_requests SET status = ?, resolved_at = CURRENT_TIMESTAMP, resolved_by = ? WHERE id = ?`)
          .run(body.status, admin.id, requestMatch[1])
        if (body.status === 'approved') {
          const validityDays = db.prepare('SELECT validity_days FROM pricing_packages WHERE id = ?').get(creditRequest.package_id)?.validity_days ?? 30
          const expiresAt = new Date(Date.now() + validityDays * 86400000).toISOString()
          db.prepare(`INSERT INTO credit_batches
            (user_id, credit_request_id, credits_total, credits_remaining, expires_at, source)
            VALUES (?, ?, ?, ?, ?, 'purchase')`)
            .run(creditRequest.user_id, creditRequest.id, creditRequest.credits, creditRequest.credits, expiresAt)
          syncUserCredits(creditRequest.user_id)
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
      await ensureAdmin
      if (pathname.startsWith('/api/auth/')) {
        const handled = await handleAuth(request, response, pathname)
        if (handled !== false) return
      }
      const dniMatch = pathname.match(/^\/api\/dni\/(\d{8})$/)
      if (dniMatch && request.method === 'GET') return handleDni(request, response, dniMatch[1])
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
