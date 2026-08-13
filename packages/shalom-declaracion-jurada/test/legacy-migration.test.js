// Ejecutar con: node --experimental-test-module-mocks --test test/
//
// Regresión: la primera versión de la migración de identidad local ->
// CodeRED dejaba credit_batches.user_id / credit_requests.user_id /
// credit_requests.resolved_by con NOT NULL (heredado del esquema viejo)
// después de remapear los datos a codered_user_id. Cualquier INSERT nuevo
// que no fuera de una cuenta migrada (p. ej. otorgar créditos a un usuario
// de CodeRED que nunca tuvo cuenta local) fallaba con
// "NOT NULL constraint failed: credit_batches.user_id". Este archivo monta
// a mano una base con el esquema legacy (como quedaría un despliegue real
// antes de actualizar) para probar la migración de extremo a extremo.
import { test, mock } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { DatabaseSync } from 'node:sqlite'

mock.module('pg', {
  namedExports: {
    Pool: class FakePool {
      on() {}
      async query() { return { rows: [] } }
    }
  }
})

const { createAppBackend } = await import('../server/app-backend.js')

test('migrar desde el esquema legacy permite otorgar créditos a un usuario nuevo sin cuenta local previa', () => {
  const dbFile = join(mkdtempSync(join(tmpdir(), 'dj-legacy-migration-')), 'legacy.db')

  // Monta el esquema legacy tal como lo dejaba la versión con auth propia,
  // con una cuenta ya vinculada a CodeRED (caso típico de un despliegue
  // real antes de esta migración).
  const seed = new DatabaseSync(dbFile)
  seed.exec(`
    CREATE TABLE users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      email TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      password_salt TEXT NOT NULL,
      role TEXT NOT NULL DEFAULT 'user',
      credits INTEGER NOT NULL DEFAULT 0,
      queries_used INTEGER NOT NULL DEFAULT 0,
      credits_expires_at TEXT,
      codered_user_id INTEGER,
      codered_name TEXT,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE credit_requests (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL REFERENCES users(id),
      credits INTEGER NOT NULL,
      status TEXT NOT NULL DEFAULT 'pending',
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      resolved_at TEXT,
      resolved_by INTEGER REFERENCES users(id)
    );
    CREATE TABLE credit_batches (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL REFERENCES users(id),
      credit_request_id INTEGER,
      credits_total INTEGER NOT NULL CHECK(credits_total > 0),
      credits_remaining INTEGER NOT NULL CHECK(credits_remaining >= 0),
      expires_at TEXT,
      source TEXT NOT NULL DEFAULT 'purchase',
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
  `)
  seed.prepare(`INSERT INTO users (id, email, password_hash, password_salt, credits, queries_used, codered_user_id)
    VALUES (1, 'legacy@codered.test', 'hash', 'salt', 3, 2, 501)`).run()
  seed.prepare(`INSERT INTO credit_batches (user_id, credits_total, credits_remaining, source) VALUES (1, 3, 3, 'legacy')`).run()
  seed.close()

  const backend = createAppBackend({
    DATABASE_PATH: dbFile,
    CODERED_DB_HOST: 'fake',
    CODERED_DB_DATABASE: 'fake',
    CODERED_DB_USERNAME: 'fake',
    CODERED_DB_PASSWORD: 'fake'
  })
  assert.equal(typeof backend, 'function', 'createAppBackend debe completar la migración sin lanzar')

  const db = new DatabaseSync(dbFile)

  // Las columnas legacy quedaron fuera.
  const batchColumns = db.prepare('PRAGMA table_info(credit_batches)').all().map(c => c.name)
  const requestColumns = db.prepare('PRAGMA table_info(credit_requests)').all().map(c => c.name)
  assert.ok(!batchColumns.includes('user_id'), 'credit_batches.user_id debe haberse eliminado')
  assert.ok(!requestColumns.includes('user_id'), 'credit_requests.user_id debe haberse eliminado')
  assert.ok(!requestColumns.includes('resolved_by'), 'credit_requests.resolved_by debe haberse eliminado')

  // La cuenta legacy se migró correctamente.
  const migrated = db.prepare('SELECT * FROM users WHERE codered_user_id = 501').get()
  assert.equal(migrated.email, 'legacy@codered.test')
  assert.equal(migrated.credits, 3)
  const migratedBatch = db.prepare('SELECT * FROM credit_batches WHERE codered_user_id = 501').get()
  assert.equal(migratedBatch.credits_remaining, 3)

  // Este es el caso que rompía antes del fix: un usuario de CodeRED que
  // JAMÁS tuvo cuenta local (no viene de legacy_users) recibe un lote
  // nuevo — no debe fallar por NOT NULL en una columna que ya no existe.
  assert.doesNotThrow(() => {
    db.prepare(`INSERT INTO users (codered_user_id, email) VALUES (999, 'nuevo@codered.test')`).run()
    db.prepare(`INSERT INTO credit_batches (codered_user_id, credits_total, credits_remaining, source)
      VALUES (999, 5, 5, 'admin')`).run()
  })
  const freshUserBatch = db.prepare('SELECT * FROM credit_batches WHERE codered_user_id = 999').get()
  assert.equal(freshUserBatch.credits_remaining, 5)

  db.close()
})
