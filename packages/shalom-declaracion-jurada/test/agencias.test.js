// Ejecutar con: node --experimental-test-module-mocks --test test/
//
// Cubre el proxy de agencias (GET /api/agencias -> CodeRED Platform
// GET /api/v1/agencias, ability agencias:consultar): endpoint protegido,
// respuesta exitosa con el mapeo de campos esperado, reenvío del término de
// búsqueda, y manejo de errores (CodeRED caído / sin token configurado) sin
// filtrar detalles internos al cliente. Reemplaza el antiguo listado
// publicado en un Gist de GitHub, ajeno a CodeRED — ver App.jsx.
import { test, mock, before, beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { Readable } from 'node:stream'
import { Buffer } from 'node:buffer'
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
let originalFetch

before(() => {
  tmpDir = mkdtempSync(join(tmpdir(), 'dj-agencias-test-'))
  originalFetch = globalThis.fetch
})

afterEach(() => {
  globalThis.fetch = originalFetch
})

beforeEach(() => {
  fixtureUsers.clear()
  fixturePermissions.clear()
  const dbFile = join(tmpDir, `${Date.now()}-${Math.random().toString(36).slice(2)}.db`)
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

const fakeRequest = ({ method = 'GET', url, headers = {}, ip = '127.0.0.1' }) => {
  const req = Readable.from([])
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

const loginWithSession = async userId => {
  seedUser(userId)
  fixturePermissions.set(userId, { hasView: true })
  const payload = Buffer.from(JSON.stringify({ email: fixtureUsers.get(userId).email, password: 'correct-password' }))
  const req = Readable.from([payload])
  req.method = 'POST'
  req.url = '/api/auth/login'
  req.headers = { 'content-type': 'application/json' }
  req.socket = { remoteAddress: '127.0.0.1' }
  const res = fakeResponse()
  await backend(req, res, () => {})
  const bodyText = res.chunks.join('')
  return { status: res.statusCode, json: bodyText ? JSON.parse(bodyText) : null, headers: res.headers }
}

const mockCoderedAgenciasSuccess = ({ expectUrl } = {}) => {
  globalThis.fetch = async (url, options) => {
    if (expectUrl) expectUrl(url, options)
    return {
      ok: true,
      json: async () => ({
        success: true,
        data: [
          {
            internal_id: 42,
            agencia: 'AGENCIA CENTRAL',
            agencia_anterior: null,
            departamento: 'LIMA',
            provincia: 'LIMA',
            distrito: 'LIMA',
            direccion: 'AV. SIEMPRE VIVA 123'
          }
        ],
        meta: { current_page: 1, last_page: 1, total: 1 }
      })
    }
  }
}

test('GET /api/agencias sin sesión responde 401', async () => {
  const response = await call({ method: 'GET', url: '/api/agencias' })
  assert.equal(response.status, 401)
})

test('GET /api/agencias con sesión reenvía la búsqueda a CodeRED y mapea los campos', async () => {
  const loginResponse = await loginWithSession(1)
  const cookie = cookieFrom(loginResponse)
  assert.ok(cookie, 'debería haber iniciado sesión')

  let capturedUrl
  mockCoderedAgenciasSuccess({ expectUrl: url => { capturedUrl = url } })

  const response = await call({ method: 'GET', url: '/api/agencias?search=central', headers: { cookie } })
  assert.equal(response.status, 200)
  assert.deepEqual(response.json.data, [{
    agencyId: 42,
    agencia: 'AGENCIA CENTRAL',
    agenciaAnterior: null,
    departamento: 'LIMA',
    provincia: 'LIMA',
    distrito: 'LIMA',
    direccion: 'AV. SIEMPRE VIVA 123'
  }])
  assert.equal(response.json.meta.total, 1)

  const upstream = new URL(capturedUrl.toString())
  assert.equal(upstream.pathname, '/api/v1/agencias')
  assert.equal(upstream.searchParams.get('agencia'), 'central')
  assert.equal(upstream.searchParams.get('estado'), 'active')
})

test('GET /api/agencias sin término de búsqueda no manda el parámetro "agencia"', async () => {
  const loginResponse = await loginWithSession(2)
  const cookie = cookieFrom(loginResponse)

  let capturedUrl
  mockCoderedAgenciasSuccess({ expectUrl: url => { capturedUrl = url } })

  const response = await call({ method: 'GET', url: '/api/agencias', headers: { cookie } })
  assert.equal(response.status, 200)
  const upstream = new URL(capturedUrl.toString())
  assert.equal(upstream.searchParams.has('agencia'), false)
})

test('si CodeRED no responde, se informa un error genérico sin detalles internos', async () => {
  const loginResponse = await loginWithSession(3)
  const cookie = cookieFrom(loginResponse)

  globalThis.fetch = async () => { throw new Error('ECONNREFUSED algo-interno-sensible') }

  const response = await call({ method: 'GET', url: '/api/agencias', headers: { cookie } })
  assert.equal(response.status, 502)
  assert.equal(response.json.message, 'No se pudo obtener el listado de agencias de CodeRED Platform. Inténtalo nuevamente.')
  assert.ok(!JSON.stringify(response.json).includes('ECONNREFUSED'))
})

test('si CodeRED responde con error de autorización, no se usa un listado local desactualizado', async () => {
  const loginResponse = await loginWithSession(4)
  const cookie = cookieFrom(loginResponse)

  globalThis.fetch = async () => ({ ok: false, json: async () => ({ success: false, message: 'Forbidden' }) })

  const response = await call({ method: 'GET', url: '/api/agencias', headers: { cookie } })
  assert.equal(response.status, 502)
  assert.equal(response.json.message, 'No se pudo obtener el listado de agencias de CodeRED Platform. Inténtalo nuevamente.')
})

test('sin CODERED_API_TOKEN configurado responde 503 en vez de intentar consultar', async () => {
  const dbFile = join(tmpDir, `${Date.now()}-${Math.random().toString(36).slice(2)}.db`)
  const backendWithoutToken = createAppBackend({
    DATABASE_PATH: dbFile,
    CODERED_DB_HOST: 'fake',
    CODERED_DB_DATABASE: 'fake',
    CODERED_DB_USERNAME: 'fake',
    CODERED_DB_PASSWORD: 'fake',
    CODERED_API_URL: 'http://codered.test',
    CODERED_API_TOKEN: '',
    COOKIE_SECURE: 'false'
  })
  seedUser(5)
  fixturePermissions.set(5, { hasView: true })
  const payload = Buffer.from(JSON.stringify({ email: fixtureUsers.get(5).email, password: 'correct-password' }))
  const loginReq = Readable.from([payload])
  loginReq.method = 'POST'
  loginReq.url = '/api/auth/login'
  loginReq.headers = { 'content-type': 'application/json' }
  loginReq.socket = { remoteAddress: '127.0.0.1' }
  const loginRes = fakeResponse()
  await backendWithoutToken(loginReq, loginRes, () => {})
  const cookie = cookieFrom({ headers: loginRes.headers })

  let fetchCalled = false
  globalThis.fetch = async () => { fetchCalled = true; throw new Error('no debería llamarse') }

  const req = fakeRequest({ method: 'GET', url: '/api/agencias', headers: { cookie } })
  const res = fakeResponse()
  await backendWithoutToken(req, res, () => {})
  assert.equal(res.statusCode, 503)
  assert.equal(fetchCalled, false)
})
