// Ejecutar con: node --experimental-test-module-mocks --test test/
//
// Cubre el proxy de Declaración Jurada (/api/declarations -> CodeRED Platform
// /api/v1/declarations, ability declaraciones:gestionar). Es la misma API que
// consume CodeRED Mobile: aquí ya no se compone ningún PDF en el navegador.
//
// Lo que se verifica: que el endpoint exige sesión, que el token técnico viaja
// solo del servidor hacia arriba (nunca al cliente) junto a X-CodeRED-User-Id
// con el usuario real, que la paginación es la del servidor, que el PDF se
// reenvía como binario sin guardarse, y que ningún fallo —401, 403, 422, 429,
// 500 o caída de red— llega al navegador con detalle técnico.
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

const API_TOKEN = 'token-tecnico-de-servidor'

const baseEnv = tmpDirPath => ({
  DATABASE_PATH: join(tmpDirPath, `${Date.now()}-${Math.random().toString(36).slice(2)}.db`),
  CODERED_DB_HOST: 'fake',
  CODERED_DB_DATABASE: 'fake',
  CODERED_DB_USERNAME: 'fake',
  CODERED_DB_PASSWORD: 'fake',
  CODERED_API_URL: 'http://codered.test',
  CODERED_API_TOKEN: API_TOKEN,
  COOKIE_SECURE: 'false'
})

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
  tmpDir = mkdtempSync(join(tmpdir(), 'dj-declarations-test-'))
  originalFetch = globalThis.fetch
})

afterEach(() => {
  globalThis.fetch = originalFetch
})

beforeEach(() => {
  fixtureUsers.clear()
  fixturePermissions.clear()
  backend = createAppBackend(baseEnv(tmpDir))
})

const fakeRequest = ({ method = 'GET', url, headers = {}, body = null, ip = '127.0.0.1' }) => {
  const req = Readable.from(body ? [Buffer.from(JSON.stringify(body))] : [])
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

const call = async (options, target = backend) => {
  const req = fakeRequest(options)
  const res = fakeResponse()
  await target(req, res, () => {})
  const raw = res.chunks
  const bodyText = raw.map(chunk => Buffer.isBuffer(chunk) ? chunk.toString('binary') : String(chunk)).join('')
  // El PDF llega como binario: intentar interpretarlo como JSON debe fallar
  // en silencio, no romper la prueba.
  let parsed
  try { parsed = bodyText ? JSON.parse(bodyText) : null } catch { parsed = null }
  return { status: res.statusCode, json: parsed, text: bodyText, chunks: raw, headers: res.headers }
}

const cookieFrom = response => {
  const raw = response.headers['Set-Cookie']
  const match = /dj_session=([^;]+)/.exec(raw || '')
  return match ? `dj_session=${match[1]}` : null
}

const loginWithSession = async userId => {
  seedUser(userId)
  fixturePermissions.set(userId, { hasView: true })
  const response = await call({
    method: 'POST',
    url: '/api/auth/login',
    body: { email: fixtureUsers.get(userId).email, password: 'correct-password' }
  })
  const cookie = cookieFrom(response)
  assert.ok(cookie, 'debería haber iniciado sesión')
  return cookie
}

const jsonUpstream = (status, payload) => ({
  ok: status >= 200 && status < 300,
  status,
  headers: new Map(),
  json: async () => payload
})

const DECLARATION_BODY = {
  remitente_dni: '12345678',
  remitente_nombre: 'JUAN CARLOS PEREZ QUISPE',
  destinatario_dni: '87654321',
  destinatario_nombre: 'MARIA ELENA RODRIGUEZ',
  agency_id: 42,
  sede_destino: 'LIMA - AGENCIA CENTRAL',
  items: [{ cantidad: '2', descripcion: 'CAJAS DE ROPA' }]
}

test('POST /api/declarations sin sesión responde 401', async () => {
  const response = await call({ method: 'POST', url: '/api/declarations', body: DECLARATION_BODY })
  assert.equal(response.status, 401)
})

test('GET /api/declarations sin sesión responde 401', async () => {
  const response = await call({ method: 'GET', url: '/api/declarations' })
  assert.equal(response.status, 401)
})

test('POST /api/declarations delega en la API oficial con el usuario de la sesión', async () => {
  const cookie = await loginWithSession(7)

  let capturedUrl = null
  let capturedOptions = null
  globalThis.fetch = async (url, options) => {
    capturedUrl = url
    capturedOptions = options
    return jsonUpstream(201, { success: true, data: { id: 84, codigo: 'DJ-2026-000084' } })
  }

  const response = await call({
    method: 'POST',
    url: '/api/declarations',
    headers: { cookie },
    body: DECLARATION_BODY
  })

  assert.equal(response.status, 201)
  assert.equal(response.json.data.id, 84)

  // Una sola API para los dos clientes: la misma que consume CodeRED Mobile.
  assert.equal(String(capturedUrl), 'http://codered.test/api/v1/declarations')
  assert.equal(capturedOptions.method, 'POST')
  assert.equal(capturedOptions.headers.Authorization, `Bearer ${API_TOKEN}`)
  // La acción se atribuye al usuario real, no al cliente técnico.
  assert.equal(capturedOptions.headers['X-CodeRED-User-Id'], '7')
  assert.equal(JSON.parse(capturedOptions.body).agency_id, 42)

  // El token técnico jamás sale hacia el navegador.
  assert.ok(!response.text.includes(API_TOKEN))
  assert.equal(response.headers['Cache-Control'], 'no-store')
})

test('GET /api/declarations respeta la paginación del servidor', async () => {
  const cookie = await loginWithSession(7)

  let capturedUrl = null
  globalThis.fetch = async url => {
    capturedUrl = String(url)
    return jsonUpstream(200, {
      success: true,
      data: [{ id: 84 }],
      meta: { current_page: 2, last_page: 5, per_page: 10, total: 47 }
    })
  }

  const response = await call({ method: 'GET', url: '/api/declarations?page=2&per_page=10', headers: { cookie } })

  assert.equal(response.status, 200)
  assert.equal(capturedUrl, 'http://codered.test/api/v1/declarations?page=2&per_page=10')
  // El meta viaja intacto: el cliente no inventa su propia paginación.
  assert.equal(response.json.meta.last_page, 5)
  assert.equal(response.json.meta.total, 47)
})

test('una página fuera de rango se normaliza antes de llegar a la API', async () => {
  const cookie = await loginWithSession(7)

  let capturedUrl = null
  globalThis.fetch = async url => {
    capturedUrl = String(url)
    return jsonUpstream(200, { success: true, data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } })
  }

  await call({ method: 'GET', url: '/api/declarations?page=-3&per_page=9999', headers: { cookie } })

  assert.equal(capturedUrl, 'http://codered.test/api/v1/declarations?page=1&per_page=20')
})

test('el PDF se reenvía como binario, sin almacenarlo ni cachearlo', async () => {
  const cookie = await loginWithSession(7)
  const pdf = Buffer.from('%PDF-1.7 documento oficial')

  globalThis.fetch = async () => ({
    ok: true,
    status: 200,
    headers: new Map([
      ['content-type', 'application/pdf'],
      ['content-disposition', 'attachment; filename="declaracion-jurada-12345678.pdf"']
    ]),
    arrayBuffer: async () => pdf.buffer.slice(pdf.byteOffset, pdf.byteOffset + pdf.byteLength)
  })

  const response = await call({ method: 'GET', url: '/api/declarations/84/pdf', headers: { cookie } })

  assert.equal(response.status, 200)
  assert.equal(response.headers['Content-Type'], 'application/pdf')
  assert.match(response.headers['Content-Disposition'], /declaracion-jurada-12345678\.pdf/)
  assert.equal(response.headers['Cache-Control'], 'no-store')
  assert.equal(Buffer.concat(response.chunks).toString('ascii'), '%PDF-1.7 documento oficial')
})

test('un 403 de la API se explica al usuario sin detalle técnico', async () => {
  const cookie = await loginWithSession(7)
  globalThis.fetch = async () => jsonUpstream(403, { success: false, message: 'El usuario delegado no tiene permiso para esta acción.' })

  const response = await call({ method: 'POST', url: '/api/declarations', headers: { cookie }, body: DECLARATION_BODY })

  assert.equal(response.status, 403)
  assert.equal(response.json.message, 'El usuario delegado no tiene permiso para esta acción.')
})

test('un 422 conserva el mensaje de validación de la API', async () => {
  const cookie = await loginWithSession(7)
  globalThis.fetch = async () => jsonUpstream(422, {
    message: 'La agencia seleccionada no existe.',
    errors: { agency_id: ['La agencia seleccionada no existe.'] }
  })

  const response = await call({ method: 'POST', url: '/api/declarations', headers: { cookie }, body: DECLARATION_BODY })

  assert.equal(response.status, 422)
  assert.equal(response.json.message, 'La agencia seleccionada no existe.')
})

test('un 429 se traslada tal cual para que la pantalla pida esperar', async () => {
  const cookie = await loginWithSession(7)
  globalThis.fetch = async () => jsonUpstream(429, { message: 'Demasiadas solicitudes. Espera un momento.' })

  const response = await call({ method: 'POST', url: '/api/declarations', headers: { cookie }, body: DECLARATION_BODY })

  assert.equal(response.status, 429)
  assert.match(response.json.message, /Demasiadas solicitudes/)
})

test('un 500 de la API nunca filtra su detalle interno al navegador', async () => {
  const cookie = await loginWithSession(7)
  globalThis.fetch = async () => jsonUpstream(500, {
    message: 'SQLSTATE[42P01]: Undefined table: relation "declarations" does not exist',
    exception: 'Illuminate\\Database\\QueryException'
  })

  const response = await call({ method: 'POST', url: '/api/declarations', headers: { cookie }, body: DECLARATION_BODY })

  assert.equal(response.status, 500)
  assert.doesNotMatch(response.json.message, /SQLSTATE/)
  assert.doesNotMatch(response.json.message, /QueryException/)
  assert.equal(response.json.message, 'No se pudo generar la declaración.')
})

test('si CodeRED no responde, el usuario ve un aviso de conexión y no un error interno', async () => {
  const cookie = await loginWithSession(7)
  globalThis.fetch = async () => { throw new Error('The operation was aborted due to timeout') }

  const response = await call({ method: 'GET', url: '/api/declarations', headers: { cookie } })

  assert.equal(response.status, 504)
  assert.match(response.json.message, /No se pudo contactar con CodeRED Platform/)
  assert.doesNotMatch(response.json.message, /timeout|abort/i)
})

test('sin token técnico configurado el módulo se declara no disponible', async () => {
  const env = baseEnv(tmpDir)
  delete env.CODERED_API_TOKEN
  const unconfigured = createAppBackend(env)

  seedUser(7)
  fixturePermissions.set(7, { hasView: true })
  const login = await call({
    method: 'POST',
    url: '/api/auth/login',
    body: { email: fixtureUsers.get(7).email, password: 'correct-password' }
  }, unconfigured)

  const response = await call({
    method: 'GET',
    url: '/api/declarations',
    headers: { cookie: cookieFrom(login) }
  }, unconfigured)

  assert.equal(response.status, 503)
  assert.match(response.json.message, /aún no está configurada/)
})
