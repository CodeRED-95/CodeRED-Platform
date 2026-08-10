/**
 * Pruebas rápidas de la lógica de sesión del popup, sin navegador ni red.
 *
 * Se sustituyen `chrome`, `fetch` y `crypto` por dobles en memoria para poder
 * ejecutar sync.js con Node:  node tests/session.test.js
 *
 * No contacta con CodeRED Platform ni con ninguna integración externa.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const EXTENSION_DIR = path.resolve(__dirname, '..');

let storage = {};
let fetchHandler = null;
const fetchCalls = [];

function makeChrome() {
    return {
        runtime: { getManifest: () => ({ version: '2.6.0' }) },
        storage: {
            local: {
                async get(keys) {
                    const list = Array.isArray(keys) ? keys : [keys];
                    return Object.fromEntries(list.filter((k) => k in storage).map((k) => [k, storage[k]]));
                },
                async set(values) {
                    Object.assign(storage, values);
                },
                async remove(keys) {
                    for (const key of (Array.isArray(keys) ? keys : [keys])) {
                        delete storage[key];
                    }
                },
            },
        },
        tabs: { create() {} },
    };
}

function jsonResponse(status, body) {
    return {
        ok: status >= 200 && status < 300,
        status,
        async text() {
            return JSON.stringify(body);
        },
    };
}

function loadSync() {
    const sandbox = {
        chrome: makeChrome(),
        crypto: { randomUUID: () => '550e8400-e29b-41d4-a716-446655440000' },
        console,
        URLSearchParams,
        Date,
        JSON,
        Promise,
        Array,
        Object,
        Number,
        TypeError,
        Error,
        RegExp,
        async fetch(url, options) {
            fetchCalls.push({ url, options });
            return fetchHandler(url, options);
        },
    };
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    vm.runInContext(fs.readFileSync(path.join(EXTENSION_DIR, 'sync.js'), 'utf8'), sandbox);

    return sandbox.ShalomRecordarSync;
}

function reset() {
    storage = {};
    fetchCalls.length = 0;
    fetchHandler = () => jsonResponse(200, {});
}

const tests = [];
const test = (name, fn) => tests.push([name, fn]);

test('isoSeconds: el timestamp enviado no lleva milisegundos', async () => {
    const api = loadSync();
    reset();
    storage.syncToken = 'tok';
    storage.pendingQueue = [{ field: 'DNI', value: '12345678', timestamp: new Date().toISOString() }];

    fetchHandler = () => jsonResponse(200, { data: { created: 1 } });
    await api.syncNow();

    const body = JSON.parse(fetchCalls.at(-1).options.body);
    const pattern = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/;
    assert.match(body.records[0].timestamp, pattern, 'el timestamp del registro debe ir sin milisegundos');
    assert.match(body.cursor, pattern, 'el cursor debe ir sin milisegundos');
    assert.equal(body.records[0].field, 'DNI', 'el tipo debe conservarse al sincronizar');
    assert.equal(body.records[0].value, '12345678');
});

test('login guarda token y usuario, y nunca la contraseña', async () => {
    const api = loadSync();
    reset();
    fetchHandler = () => jsonResponse(200, { data: { sync_token: 'tok-123', user: { name: 'Victor', email: 'v@codered.lat' } } });

    const result = await api.login({ email: 'v@codered.lat', password: 'Secreta123' });

    assert.equal(result.ok, true);
    assert.equal(storage.syncToken, 'tok-123');
    assert.equal(storage.syncUser.email, 'v@codered.lat');
    assert.equal(JSON.stringify(storage).includes('Secreta123'), false, 'la contraseña no debe quedar almacenada');
});

test('sin token guardado se pide login', async () => {
    const api = loadSync();
    reset();

    const state = await api.getSessionState();

    assert.equal(state.authenticated, false);
    assert.equal(state.reason, 'no-session');
    assert.equal(fetchCalls.length, 0, 'no debe consultarse el servidor sin token');
});

test('token guardado y estado 200: sesión restaurada tras reabrir el popup', async () => {
    const api = loadSync();
    reset();
    storage.syncToken = 'tok-123';
    storage.syncUser = { name: 'Victor', email: 'v@codered.lat' };
    fetchHandler = () => jsonResponse(200, { data: { user: { name: 'Victor', email: 'v@codered.lat' }, last_synced_at: '2026-08-10T10:00:00Z', records_count: 7 } });

    const state = await api.getSessionState();

    assert.equal(state.authenticated, true);
    assert.equal(state.user.email, 'v@codered.lat');
    assert.equal(state.server.records_count, 7);
    assert.match(fetchCalls[0].url, /installation_uuid=/, 'el estado debe enviar installation_uuid');
});

test('token revocado (401): limpia la sesión y vuelve al login', async () => {
    const api = loadSync();
    reset();
    storage.syncToken = 'tok-viejo';
    storage.syncUser = { email: 'v@codered.lat' };
    storage.installationUuid = 'uuid-1';
    storage.pendingQueue = [{ field: 'f', value: 'v' }];
    fetchHandler = () => jsonResponse(401, { message: 'Unauthenticated.' });

    const state = await api.getSessionState();

    assert.equal(state.authenticated, false);
    assert.equal(state.reason, 'session-revoked');
    assert.equal('syncToken' in storage, false);
    assert.equal('syncUser' in storage, false);
    assert.equal(storage.installationUuid, 'uuid-1', 'la instalación debe conservarse');
    assert.equal(storage.pendingQueue.length, 1, 'los registros locales no se tocan');
});

test('fallo de red: NO cierra la sesión', async () => {
    const api = loadSync();
    reset();
    storage.syncToken = 'tok-123';
    fetchHandler = () => { throw new TypeError('failed to fetch'); };

    const state = await api.getSessionState();

    assert.equal(state.authenticated, true, 'un corte de red no debe invalidar la sesión');
    assert.equal(state.degraded, true);
    assert.equal(storage.syncToken, 'tok-123');
});

test('logout revoca en el servidor y borra solo las credenciales', async () => {
    const api = loadSync();
    reset();
    storage.syncToken = 'tok-123';
    storage.syncUser = { email: 'v@codered.lat' };
    storage.installationUuid = 'uuid-1';
    storage.pendingQueue = [{ field: 'f' }];
    fetchHandler = () => jsonResponse(200, { success: true });

    const result = await api.logout();

    assert.equal(result.revoked, true);
    assert.match(fetchCalls[0].url, /auth\/logout$/);
    assert.equal('syncToken' in storage, false);
    assert.equal('syncUser' in storage, false);
    assert.equal(storage.installationUuid, 'uuid-1');
    assert.equal(storage.pendingQueue.length, 1, 'cerrar sesión no borra el historial');
});

test('logout sin red también limpia la sesión local', async () => {
    const api = loadSync();
    reset();
    storage.syncToken = 'tok-123';
    fetchHandler = () => { throw new TypeError('failed to fetch'); };

    const result = await api.logout();

    assert.equal(result.ok, true);
    assert.equal('syncToken' in storage, false);
});

test('getRecentRecords devuelve como máximo 20 registros, más recientes primero', async () => {
    const api = loadSync();
    reset();
    storage.pendingQueue = Array.from({ length: 25 }, (_, index) => ({
        field: `campo-${index + 1}`,
        value: `valor-${index + 1}`,
        timestamp: `2026-08-10T10:${String(index).padStart(2, '0')}:00Z`,
    }));

    const records = await api.getRecentRecords(20);

    assert.equal(records.length, 20);
    assert.equal(records[0].field, 'campo-25');
    assert.equal(records.at(-1).field, 'campo-6');
});

test('sincronizar sin sesión no llama al servidor', async () => {
    const api = loadSync();
    reset();

    const result = await api.syncNow();

    assert.equal(result.ok, false);
    assert.equal(result.reason, 'unauthorized');
    assert.equal(fetchCalls.length, 0);
});

test('sincronizar usa el token restaurado sin pedirlo de nuevo', async () => {
    const api = loadSync();
    reset();
    storage.syncToken = 'tok-restaurado';
    storage.pendingQueue = [{ field: 'f', value: 'v' }];
    fetchHandler = () => jsonResponse(200, { data: { created: 1 } });

    const result = await api.syncNow();

    assert.equal(result.ok, true);
    assert.equal(result.synced, 1);
    assert.equal(fetchCalls.at(-1).options.headers.Authorization, 'Bearer tok-restaurado');
    // Se compara por longitud: el array vive en el contexto del vm y tiene otro
    // prototipo, así que deepEqual estricto lo daría por distinto.
    assert.equal(storage.pendingQueue.length, 0, 'la cola se vacía tras sincronizar');
});

test('normalizeRecord: recorta límites y descarta lo irrecuperable', () => {
    const api = loadSync();
    reset();

    const ok = api.normalizeRecord({ field: 'dni', value: '  12345678  ', timestamp: '2026-08-10T12:15:55.080Z' }, 0);
    assert.equal(ok.value, '12345678');
    assert.match(ok.timestamp, /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/, 'sin milisegundos');
    assert.equal(ok.record_id, 'local-0');

    assert.equal(api.normalizeRecord({ field: 'dni', value: '   ' }, 1), null, 'valor vacío se descarta');
    assert.equal(api.normalizeRecord({ field: '', value: 'v' }, 2), null, 'campo vacío se descarta');

    const largo = api.normalizeRecord({ field: 'c'.repeat(150), value: 'x'.repeat(2500) }, 3);
    assert.equal(largo.field.length, 100, 'field recortado a 100');
    assert.equal(largo.value.length, 2000, 'value recortado a 2000');
});

test('syncNow: un registro inválido no bloquea el lote', async () => {
    const api = loadSync();
    reset();
    storage.syncToken = 'tok';
    storage.pendingQueue = [
        { field: 'dni', value: '12345678', timestamp: new Date().toISOString() },
        { field: 'vacio', value: '   ' }, // se descarta
        { field: 'clave', value: '4444', timestamp: new Date().toISOString() },
    ];
    fetchHandler = () => jsonResponse(200, { data: { created: 2 } });

    const result = await api.syncNow();

    assert.equal(result.ok, true);
    assert.equal(result.synced, 2, 'se envían solo los 2 válidos');
    const body = JSON.parse(fetchCalls.at(-1).options.body);
    assert.equal(body.records.length, 2);
    assert.equal(storage.pendingQueue.length, 0, 'la cola se vacía tras aceptar');
});

test('describeFailure: 422 lista los campos inválidos de forma legible', () => {
    const api = loadSync();
    reset();
    const msg = api.describeFailure({ status: 422, json: { errors: { 'records.0.timestamp': ['x'], 'records.2.value': ['y'] } } });
    assert.match(msg, /fecha \(registro 1\)/);
    assert.match(msg, /valor \(registro 3\)/);
});

test('describeFailure: cada código tiene su mensaje', () => {
    const api = loadSync();
    reset();
    assert.match(api.describeFailure({ status: 401 }), /sesión/i);
    assert.match(api.describeFailure({ status: 403 }), /permiso/i);
    assert.match(api.describeFailure({ status: 429 }), /espera|momento/i);
    assert.match(api.describeFailure({ status: 503 }), /más tarde|interno/i);
    assert.match(api.describeFailure({ status: 0 }), /conexión/i);
});

test('getRecentRecords: máximo 20 y más reciente primero', async () => {
    const api = loadSync();
    reset();
    storage.pendingQueue = Array.from({ length: 25 }, (_, i) => ({
        field: `f${i}`,
        value: `v${i}`,
        timestamp: `2026-08-10T12:00:${String(i).padStart(2, '0')}Z`,
    }));

    const recientes = await api.getRecentRecords(20);

    assert.equal(recientes.length, 20, 'tope de 20');
    assert.equal(recientes[0].field, 'f24', 'el más reciente primero');
    assert.equal(recientes[19].field, 'f5');
});

test('getRecentRecords: sin datos devuelve lista vacía', async () => {
    const api = loadSync();
    reset();
    const recientes = await api.getRecentRecords(20);
    assert.equal(recientes.length, 0);
});

(async () => {
    let failed = 0;
    for (const [name, fn] of tests) {
        try {
            await fn();
            console.log(`  ✓ ${name}`);
        } catch (error) {
            failed++;
            console.error(`  ✗ ${name}\n    ${error.message}`);
        }
    }
    console.log(`\n  ${tests.length - failed} de ${tests.length} pruebas en verde`);
    process.exit(failed === 0 ? 0 : 1);
})();
