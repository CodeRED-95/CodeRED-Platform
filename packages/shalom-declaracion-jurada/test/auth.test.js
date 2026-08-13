// Ejecutar con: node --experimental-test-module-mocks --test test/
//
// Prueba la unificación de identidad con CodeRED Platform sin necesitar un
// Postgres real: se sustituye el módulo "pg" por un doble en memoria que
// responde a las mismas consultas que server/app-backend.js emite (login
// por email, lookup por id, permisos declaracion-jurada.view/.manage).
import { test, mock, before, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { Readable } from 'node:stream'
import { Buffer } from 'node:buffer'
import bcrypt from 'bcryptjs'

/** @type {Map<number, {id:number,name:string,email:string,password:string,status:string,deleted_at:string|null}>} */
const fixtureUsers = new Map()
/** @type {Map<number, {hasView?:boolean,hasManage?:boolean,isSuperAdmin?:boolean}>} */
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

before(() => {
  tmpDir = mkdtempSync(join(tmpdir(), 'dj-auth-test-'))
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
  let nextCalled = false
  await backend(req, res, () => { nextCalled = true })
  const bodyText = res.chunks.join('')
  return { status: res.statusCode, json: bodyText ? JSON.parse(bodyText) : null, headers: res.headers, nextCalled }
}

const cookieFrom = response => {
  const raw = response.headers['Set-Cookie']
  const match = /dj_session=([^;]+)/.exec(raw || '')
  return match ? `dj_session=${match[1]}` : null
}

const login = async (email, password, extra = {}) => call({ method: 'POST', url: '/api/auth/login', body: { email, password }, ...extra })

test('usuario inexistente: login rechazado con 401', async () => {
  const response = await login('nadie@codered.test', 'lo-que-sea')
  assert.equal(response.status, 401)
})

test('contraseña incorrecta: login rechazado con 401', async () => {
  seedUser(1)
  fixturePermissions.set(1, { hasView: true })
  const response = await login(fixtureUsers.get(1).email, 'password-incorrecto')
  assert.equal(response.status, 401)
})

test('usuario CodeRED correcto y con permiso: login válido y crea sesión', async () => {
  seedUser(1)
  fixturePermissions.set(1, { hasView: true })
  const response = await login(fixtureUsers.get(1).email, 'correct-password')
  assert.equal(response.status, 200)
  assert.equal(response.json.user.coderedUserId, 1)
  assert.ok(cookieFrom(response), 'debe emitir cookie de sesión')
})

test('usuario sin declaracion-jurada.view: acceso rechazado con 403', async () => {
  seedUser(1)
  fixturePermissions.set(1, { hasView: false })
  const response = await login(fixtureUsers.get(1).email, 'correct-password')
  assert.equal(response.status, 403)
})

test('super-admin tiene acceso aunque no tenga el permiso explícito', async () => {
  seedUser(1)
  fixturePermissions.set(1, { isSuperAdmin: true })
  const response = await login(fixtureUsers.get(1).email, 'correct-password')
  assert.equal(response.status, 200)
})

test('endpoint de registro retirado responde 404', async () => {
  const response = await call({ method: 'POST', url: '/api/auth/register', body: { email: 'x@x.com', password: 'password123' } })
  assert.equal(response.status, 404)
})

test('endpoint de acceso con Google retirado responde 404', async () => {
  const response = await call({ method: 'POST', url: '/api/auth/google', body: { credential: 'x' } })
  assert.equal(response.status, 404)
})

test('usuario desactivado en CodeRED: acceso rechazado', async () => {
  seedUser(1, { status: 'suspended' })
  fixturePermissions.set(1, { hasView: true })
  const response = await login(fixtureUsers.get(1).email, 'correct-password')
  assert.equal(response.status, 401)
})

test('usuario eliminado (soft delete) en CodeRED: acceso rechazado', async () => {
  seedUser(1, { deleted_at: '2026-01-01 00:00:00' })
  fixturePermissions.set(1, { hasView: true })
  const response = await login(fixtureUsers.get(1).email, 'correct-password')
  assert.equal(response.status, 401)
})

test('cambio de contraseña en CodeRED: la anterior deja de servir y la nueva funciona', async () => {
  seedUser(1)
  fixturePermissions.set(1, { hasView: true })
  const first = await login(fixtureUsers.get(1).email, 'correct-password')
  assert.equal(first.status, 200)

  fixtureUsers.get(1).password = bcrypt.hashSync('nueva-clave-2026', 10)

  const withOldPassword = await login(fixtureUsers.get(1).email, 'correct-password')
  assert.equal(withOldPassword.status, 401)

  const withNewPassword = await login(fixtureUsers.get(1).email, 'nueva-clave-2026')
  assert.equal(withNewPassword.status, 200)
})

test('cambio de email en CodeRED: la identidad histórica sigue ligada por codered_user_id', async () => {
  seedUser(1)
  fixturePermissions.set(1, { hasView: true })
  const first = await login(fixtureUsers.get(1).email, 'correct-password')
  const cookie = cookieFrom(first)
  assert.equal(first.json.user.coderedUserId, 1)

  fixtureUsers.get(1).email = 'nuevo-correo@codered.test'
  const session = await call({ method: 'GET', url: '/api/auth/session', headers: { cookie } })
  assert.equal(session.json.user.coderedUserId, 1)
})

test('logout invalida la sesión', async () => {
  seedUser(1)
  fixturePermissions.set(1, { hasView: true })
  const loginResponse = await login(fixtureUsers.get(1).email, 'correct-password')
  const cookie = cookieFrom(loginResponse)

  const beforeLogout = await call({ method: 'GET', url: '/api/auth/session', headers: { cookie } })
  assert.equal(beforeLogout.json.user.coderedUserId, 1)

  await call({ method: 'POST', url: '/api/auth/logout', body: {}, headers: { cookie } })

  const afterLogout = await call({ method: 'GET', url: '/api/auth/session', headers: { cookie } })
  assert.equal(afterLogout.json.user, null)
})

test('rutas privadas sin sesión responden 401', async () => {
  const response = await call({ method: 'GET', url: '/api/store' })
  assert.equal(response.status, 401)
})

test('acceso a administración sin permiso declaracion-jurada.manage responde 403', async () => {
  seedUser(1)
  fixturePermissions.set(1, { hasView: true, hasManage: false })
  const loginResponse = await login(fixtureUsers.get(1).email, 'correct-password')
  const cookie = cookieFrom(loginResponse)

  const response = await call({ method: 'GET', url: '/api/admin/users', headers: { cookie } })
  assert.equal(response.status, 403)
})

test('acceso a administración con permiso declaracion-jurada.manage funciona', async () => {
  seedUser(1)
  fixturePermissions.set(1, { hasView: true, hasManage: true })
  const loginResponse = await login(fixtureUsers.get(1).email, 'correct-password')
  const cookie = cookieFrom(loginResponse)

  const response = await call({ method: 'GET', url: '/api/admin/users', headers: { cookie } })
  assert.equal(response.status, 200)
})

test('inyección SQL básica en el email no autentica ni rompe la consulta', async () => {
  seedUser(1)
  fixturePermissions.set(1, { hasView: true })
  // Formato inválido (con espacios): rechazado antes de tocar la base.
  const malformed = await login("' OR '1'='1", 'correct-password')
  assert.equal(malformed.status, 400)

  // Formato válido pero con metacaracteres SQL: la query parametrizada
  // ($1) no interpreta comillas/operadores — simplemente no hay match.
  const injectionLike = await login("union@codered.test'--", 'correct-password')
  assert.equal(injectionLike.status, 401)
})

test('rate limiting: más de 5 intentos fallidos desde la misma IP+correo devuelve 429', async () => {
  seedUser(1)
  fixturePermissions.set(1, { hasView: true })
  const email = fixtureUsers.get(1).email
  let last
  for (let i = 0; i < 6; i += 1) {
    last = await login(email, 'password-incorrecto', { ip: '10.0.0.9' })
  }
  assert.equal(last.status, 429)
  assert.ok(last.headers['Retry-After'])
})

test('la contraseña nunca se expone en la respuesta de sesión', async () => {
  seedUser(1)
  fixturePermissions.set(1, { hasView: true })
  const response = await login(fixtureUsers.get(1).email, 'correct-password')
  const serialized = JSON.stringify(response.json)
  assert.ok(!serialized.includes(HASH))
  assert.ok(!serialized.includes('correct-password'))
})
