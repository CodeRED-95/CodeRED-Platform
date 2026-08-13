// Ejecutar con: node --experimental-test-module-mocks --test test/
//
// Cubre la atribución de consultas DNI al usuario delegado (headers hacia
// CodeRED), la idempotencia ante reintentos (X-Request-Id) y que el
// descuento de créditos no admita condiciones de carrera.
import { test, mock, before, beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { Readable } from 'node:stream'
import { Buffer } from 'node:buffer'
import { DatabaseSync } from 'node:sqlite'
import bcrypt from 'bcryptjs'

const fixtureUsers = new Map()
const fixturePermissions = new Map()

mock.module('pg', {
  namedExports: {
    Pool: class FakePool {
      on() {}
      async query(text, params = []) {
        if (text.includes('lower(email) = lower($1)')) {
          const email = String(params[0]).toLowerCase()
          const user = [...fixtureUsers.values()].find(u => u.email.toLowerCase() === email)
          return { rows: user ? [{ ...user }] : [] }
        }
        if (text.includes('FROM users WHERE id = $1')) {
          const user = fixtureUsers.get(params[0])
          return { rows: user ? [{ ...user }] : [] }
        }
        if (text.includes('is_super_admin')) {
          const perm = fixturePermissions.get(params[0]) || {}
          return { rows: [{ is_super_admin: Boolean(perm.isSuperAdmin), has_view: Boolean(perm.hasView), has_manage: Boolean(perm.hasManage) }] }
        }
        throw new Error(`Consulta no soportada por el doble de pg en tests: ${text}`)
      }
    }
  }
})

const { createAppBackend } = await import('../server/app-backend.js')

const HASH = bcrypt.hashSync('correct-password', 10)

const seedUser = (id, overrides = {}) => {
  fixtureUsers.set(id, {
    id,
    name: `Usuario ${id}`,
    email: `user${id}@codered.test`,
    password: HASH,
    status: 'active',
    deleted_at: null,
    ...overrides
  })
}

let tmpDir
let backend
let dbFile
let originalFetch

before(() => {
  tmpDir = mkdtempSync(join(tmpdir(), 'dj-dni-test-'))
  originalFetch = globalThis.fetch
})

afterEach(() => {
  globalThis.fetch = originalFetch
})

beforeEach(() => {
  fixtureUsers.clear()
  fixturePermissions.clear()
  dbFile = join(tmpDir, `${Date.now()}-${Math.random().toString(36).slice(2)}.db`)
  backend = createAppBackend({
    DATABASE_PATH: dbFile,
    CODERED_DB_HOST: 'fake',
    CODERED_DB_DATABASE: 'fake',
    CODERED_DB_USERNAME: 'fake',
    CODERED_DB_PASSWORD: 'fake',
    CODERED_API_URL: 'http://codered.test',
    CODERED_API_TOKEN: 'fake-token',
    COOKIE_SECURE: 'false'
  })
})

const fakeRequest = ({ method = 'GET', url, body, headers = {}, ip = '127.0.0.1' }) => {
  const payload = body !== undefined ? Buffer.from(JSON.stringify(body)) : Buffer.alloc(0)
  const req = Readable.from(payload.length ? [payload] : [])
  req.method = method
  req.url = url
  req.headers = { 'content-type': 'application/json', ...headers }
  req.socket = { remoteAddress: ip }
  return req
}

const fakeResponse = () => ({
  statusCode: 0,
  headers: {},
  chunks: [],
  writeHead(status, headers) { this.statusCode = status; Object.assign(this.headers, headers) },
  setHeader(name, value) { this.headers[name] = value },
  end(chunk) { if (chunk) this.chunks.push(chunk) }
})

const call = async options => {
  const req = fakeRequest(options)
  const res = fakeResponse()
  await backend(req, res, () => {})
  const bodyText = res.chunks.join('')
  return { status: res.statusCode, json: bodyText ? JSON.parse(bodyText) : null, headers: res.headers }
}

const cookieFrom = response => {
  const raw = response.headers['Set-Cookie']
  const match = /dj_session=([^;]+)/.exec(raw || '')
  return match ? `dj_session=${match[1]}` : null
}

const loginAndGrantCredits = async (userId, credits = 1) => {
  seedUser(userId)
  fixturePermissions.set(userId, { hasView: true })
  const login = await call({ method: 'POST', url: '/api/auth/login', body: { email: fixtureUsers.get(userId).email, password: 'correct-password' } })
  const cookie = cookieFrom(login)
  // Otorga créditos directamente en la base local (equivalente a lo que
  // haría un admin vía /api/admin/users/:id, sin depender de esa ruta).
  // credits_total tiene CHECK(> 0): un usuario "sin créditos" simplemente
  // no recibe ningún lote.
  if (credits > 0) {
    const db = new DatabaseSync(dbFile)
    db.prepare(`INSERT INTO credit_batches (codered_user_id, credits_total, credits_remaining, expires_at, source)
      VALUES (?, ?, ?, ?, 'admin')`).run(userId, credits, credits, new Date(Date.now() + 86400000).toISOString())
    db.close()
  }
  return cookie
}

// Forma real de GET /api/v1/dni/{dni} en CodeRED: los campos van dentro de
// "data" (Laravel JsonResource), success/meta quedan al nivel superior.
// Ver App\Http\Resources\Api\V1\DniResource — un mock plano aquí habría
// enmascarado exactamente el bug que este archivo encontró en la práctica
// (queryDniViaCodered leía payload.nombre_completo en vez de
// payload.data.nombre_completo).
const mockCoderedDniSuccess = ({ expectHeaders } = {}) => {
  globalThis.fetch = async (url, options) => {
    if (expectHeaders) expectHeaders(options.headers)
    return {
      ok: true,
      json: async () => ({
        success: true,
        data: { dni: '12345678', nombres: 'JUAN', apellido_paterno: 'PEREZ', apellido_materno: 'QUISPE', nombre_completo: 'JUAN PEREZ QUISPE' },
        meta: { source: 'internal' }
      })
    }
  }
}

test('DJ envía X-CodeRED-User-Id y X-Request-Id al consultar vía CodeRED', async () => {
  const cookie = await loginAndGrantCredits(1)
  let capturedHeaders
  mockCoderedDniSuccess({ expectHeaders: headers => { capturedHeaders = headers } })

  const response = await call({ method: 'GET', url: '/api/dni/12345678', headers: { cookie, 'x-request-id': 'req-abc-1' } })
  assert.equal(response.status, 200)
  assert.equal(response.json.nombreCompleto, 'JUAN PEREZ QUISPE')
  assert.equal(capturedHeaders['X-CodeRED-User-Id'], '1')
  assert.equal(capturedHeaders['X-Request-Id'], 'req-abc-1')
  assert.ok(capturedHeaders.Authorization.includes('fake-token'))
})

test('una consulta exitosa descuenta exactamente un crédito', async () => {
  const cookie = await loginAndGrantCredits(1, 3)
  mockCoderedDniSuccess()

  const before = await call({ method: 'GET', url: '/api/credit-requests', headers: { cookie } })
  assert.equal(before.json.user.credits, 3)

  await call({ method: 'GET', url: '/api/dni/12345678', headers: { cookie, 'x-request-id': 'req-1' } })

  const after = await call({ method: 'GET', url: '/api/credit-requests', headers: { cookie } })
  assert.equal(after.json.user.credits, 2)
  assert.equal(after.json.user.queriesUsed, 1)
})

test('reintentar con el mismo X-Request-Id no descuenta un segundo crédito', async () => {
  const cookie = await loginAndGrantCredits(1, 1)
  mockCoderedDniSuccess()

  const first = await call({ method: 'GET', url: '/api/dni/12345678', headers: { cookie, 'x-request-id': 'retry-key' } })
  assert.equal(first.status, 200)

  const retry = await call({ method: 'GET', url: '/api/dni/12345678', headers: { cookie, 'x-request-id': 'retry-key' } })
  assert.equal(retry.status, 200)
  assert.deepEqual(retry.json, first.json)

  const state = await call({ method: 'GET', url: '/api/credit-requests', headers: { cookie } })
  assert.equal(state.json.user.credits, 0)
  assert.equal(state.json.user.queriesUsed, 1)
})

test('un X-Request-Id distinto sí genera una consulta y un descuento nuevos', async () => {
  const cookie = await loginAndGrantCredits(1, 2)
  mockCoderedDniSuccess()

  await call({ method: 'GET', url: '/api/dni/12345678', headers: { cookie, 'x-request-id': 'req-a' } })
  await call({ method: 'GET', url: '/api/dni/12345678', headers: { cookie, 'x-request-id': 'req-b' } })

  const state = await call({ method: 'GET', url: '/api/credit-requests', headers: { cookie } })
  assert.equal(state.json.user.credits, 0)
  assert.equal(state.json.user.queriesUsed, 2)
})

test('sin créditos disponibles responde 402 y no llama a CodeRED', async () => {
  const cookie = await loginAndGrantCredits(1, 0)
  let fetchCalled = false
  globalThis.fetch = async () => { fetchCalled = true; throw new Error('no debería llamarse') }

  const response = await call({ method: 'GET', url: '/api/dni/12345678', headers: { cookie } })
  assert.equal(response.status, 402)
  assert.equal(fetchCalled, false)
})

test('un fallo de CodeRED (DNI no encontrado) devuelve el crédito reservado', async () => {
  const cookie = await loginAndGrantCredits(1, 1)
  globalThis.fetch = async () => ({ ok: false, json: async () => ({ success: false, message: 'DNI no encontrado' }) })

  const response = await call({ method: 'GET', url: '/api/dni/12345678', headers: { cookie } })
  assert.equal(response.status, 502)

  const state = await call({ method: 'GET', url: '/api/credit-requests', headers: { cookie } })
  assert.equal(state.json.user.credits, 1)
  assert.equal(state.json.user.queriesUsed, 0)
})

test('un DNI con formato inválido no llega a tocar créditos ni a llamar a CodeRED', async () => {
  const cookie = await loginAndGrantCredits(1, 1)
  let fetchCalled = false
  globalThis.fetch = async () => { fetchCalled = true; throw new Error('no debería llamarse') }

  const response = await call({ method: 'GET', url: '/api/dni/abc', headers: { cookie } })
  assert.equal(response.status, 404)
  assert.equal(fetchCalled, false)

  const state = await call({ method: 'GET', url: '/api/credit-requests', headers: { cookie } })
  assert.equal(state.json.user.credits, 1)
})

test('concurrencia: con 1 crédito disponible, 5 consultas en paralelo solo permiten 1 éxito', async () => {
  const cookie = await loginAndGrantCredits(1, 1)
  mockCoderedDniSuccess()

  const attempts = await Promise.all(
    Array.from({ length: 5 }, (_, index) =>
      call({ method: 'GET', url: '/api/dni/12345678', headers: { cookie, 'x-request-id': `parallel-${index}` } }))
  )

  const succeeded = attempts.filter(response => response.status === 200)
  const exhausted = attempts.filter(response => response.status === 402)
  assert.equal(succeeded.length, 1)
  assert.equal(exhausted.length, 4)

  const state = await call({ method: 'GET', url: '/api/credit-requests', headers: { cookie } })
  assert.equal(state.json.user.credits, 0)
  assert.equal(state.json.user.queriesUsed, 1)
})

test('dos usuarios distintos mantienen consumos separados', async () => {
  const cookieA = await loginAndGrantCredits(1, 5)
  const cookieB = await loginAndGrantCredits(2, 5)
  mockCoderedDniSuccess()

  await call({ method: 'GET', url: '/api/dni/12345678', headers: { cookie: cookieA, 'x-request-id': 'a-1' } })
  await call({ method: 'GET', url: '/api/dni/12345678', headers: { cookie: cookieA, 'x-request-id': 'a-2' } })
  await call({ method: 'GET', url: '/api/dni/12345678', headers: { cookie: cookieB, 'x-request-id': 'b-1' } })

  const stateA = await call({ method: 'GET', url: '/api/credit-requests', headers: { cookie: cookieA } })
  const stateB = await call({ method: 'GET', url: '/api/credit-requests', headers: { cookie: cookieB } })
  assert.equal(stateA.json.user.credits, 3)
  assert.equal(stateA.json.user.queriesUsed, 2)
  assert.equal(stateB.json.user.credits, 4)
  assert.equal(stateB.json.user.queriesUsed, 1)
})

test('no es posible falsificar el usuario: el userId siempre viene de la sesión, nunca de un parámetro', async () => {
  const cookieAttacker = await loginAndGrantCredits(1, 0)
  const cookieVictim = await loginAndGrantCredits(2, 5)
  mockCoderedDniSuccess()

  // El endpoint DNI no acepta ningún parámetro de usuario — solo la
  // sesión. Confirmamos que, aunque el atacante intente pasar un
  // codered_user_id de la víctima por header, la sesión (cookie) es lo
  // único que determina de quién se descuenta el crédito.
  const response = await call({
    method: 'GET',
    url: '/api/dni/12345678',
    headers: { cookie: cookieAttacker, 'x-codered-user-id': '2', 'x-request-id': 'spoof-1' }
  })
  assert.equal(response.status, 402, 'debe seguir usando los 0 créditos del atacante, no los de la víctima')

  const victimState = await call({ method: 'GET', url: '/api/credit-requests', headers: { cookie: cookieVictim } })
  assert.equal(victimState.json.user.credits, 5, 'la cuenta de la víctima no debió tocarse')
})
