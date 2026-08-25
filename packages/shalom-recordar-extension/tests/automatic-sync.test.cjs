/**
 * Pruebas rápidas del flujo de sincronización automática diaria.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const EXTENSION_DIR = path.resolve(__dirname, '..');
const SYNC_SOURCE = fs.readFileSync(path.join(EXTENSION_DIR, 'sync.js'), 'utf8');
const BACKGROUND_SOURCE = fs.readFileSync(path.join(EXTENSION_DIR, 'background.js'), 'utf8');

let storage = {};
let fetchHandler = null;
const fetchCalls = [];
const listeners = {
    startup: [],
    installed: [],
    alarms: [],
    messages: [],
};

function reset() {
    storage = {};
    fetchCalls.length = 0;
    listeners.startup.length = 0;
    listeners.installed.length = 0;
    listeners.alarms.length = 0;
    listeners.messages.length = 0;
    fetchHandler = () => ({
        ok: true,
        status: 200,
        async text() {
            return JSON.stringify({ data: { created: 1 } });
        },
    });
}

function makeChrome() {
    return {
        runtime: {
            getManifest: () => ({ version: '2.8.0' }),
            onStartup: { addListener(fn) { listeners.startup.push(fn); } },
            onInstalled: { addListener(fn) { listeners.installed.push(fn); } },
            onMessage: { addListener(fn) { listeners.messages.push(fn); } },
        },
        alarms: {
            create(name, alarmInfo) {
                storage.lastAlarm = { name, alarmInfo };
            },
            onAlarm: { addListener(fn) { listeners.alarms.push(fn); } },
        },
        action: {
            setBadgeBackgroundColor() {},
            setBadgeText() {},
        },
        storage: {
            local: {
                async get(keys) {
                    const list = Array.isArray(keys) ? keys : [keys];
                    return Object.fromEntries(list.filter((key) => key in storage).map((key) => [key, storage[key]]));
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
            session: {
                async get(keys) {
                    const list = Array.isArray(keys) ? keys : [keys];
                    return Object.fromEntries(list.filter((key) => key in storage).map((key) => [key, storage[key]]));
                },
                async set(values) {
                    Object.assign(storage, values);
                },
            },
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
        Intl,
        async fetch(url, options) {
            fetchCalls.push({ url, options });
            return fetchHandler(url, options);
        },
    };
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    vm.runInContext(SYNC_SOURCE, sandbox);
    return sandbox.ShalomRecordarSync;
}

function loadBackground(syncApi) {
    const sandbox = {
        chrome: makeChrome(),
        console,
        importScripts() {},
        ShalomRecordarSync: syncApi,
        crypto: { randomUUID: () => '550e8400-e29b-41d4-a716-446655440000' },
        Promise,
        setTimeout,
        clearTimeout,
    };
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    vm.runInContext(BACKGROUND_SOURCE, sandbox);
    return sandbox.ShalomRecordarBackground;
}

const tests = [];
const test = (name, fn) => tests.push([name, fn]);

test('07:00 en Perú programa la próxima alarma para las 08:00 del mismo día', async () => {
    reset();
    const api = loadSync();
    const next = api.getNextAutomaticSyncAt(new Date('2026-08-11T12:00:00Z'));
    assert.equal(next.toISOString(), '2026-08-11T13:00:00.000Z');

    const state = await api.getAutomaticSyncState(new Date('2026-08-11T12:00:00Z'));
    assert.equal(state.automaticSyncAvailable, false);
    assert.equal(state.automaticSyncDoneToday, false);
    assert.equal(state.nextAutomaticSyncAt, '2026-08-11T13:00:00.000Z');
});

test('08:00 en Perú ya habilita la sincronización automática', async () => {
    reset();
    const api = loadSync();
    const state = await api.getAutomaticSyncState(new Date('2026-08-11T13:00:00Z'));
    assert.equal(state.automaticSyncAvailable, true);
    assert.equal(state.currentLimaDate, '2026-08-11');
    assert.equal(state.nextAutomaticSyncAt, '2026-08-12T13:00:00.000Z');
});

test('si no hay sesión, la sincronización automática se omite sin error', async () => {
    reset();
    const api = loadSync();
    const result = await api.runAutomaticSyncIfNeeded({ now: new Date('2026-08-11T15:00:00Z'), source: 'startup' });
    assert.equal(result.skipped, true);
    assert.equal(result.reason, 'no-session');
});

test('10:00 en Perú y sin sync previa: ejecuta sincronización automática y marca el día', async () => {
    reset();
    storage.syncToken = 'tok-1';
    storage.syncUser = { email: 'demo@codered.lat' };
    storage.pendingQueue = [{ field: 'DNI', value: '12345678', timestamp: '2026-08-11T13:00:00Z' }];

    const api = loadSync();
    fetchHandler = () => ({
        ok: true,
        status: 200,
        async text() {
            return JSON.stringify({ data: { accepted: 1 } });
        },
    });

    const result = await api.runAutomaticSyncIfNeeded({ now: new Date('2026-08-11T15:00:00Z'), source: 'startup' });

    assert.equal(result.ok, true);
    assert.equal(result.automatic, true);
    assert.equal(storage.lastAutomaticSyncDate, '2026-08-11');
    assert.equal(storage.lastAutomaticSyncAt, '2026-08-11T15:00:00.000Z');
});

test('si la sincronización automática falla, no se marca el día como completado', async () => {
    reset();
    storage.syncToken = 'tok-1';
    storage.syncUser = { email: 'demo@codered.lat' };
    storage.pendingQueue = [{ field: 'DNI', value: '12345678', timestamp: '2026-08-11T13:00:00Z' }];

    const api = loadSync();
    fetchHandler = () => ({
        ok: false,
        status: 500,
        async text() {
            return JSON.stringify({ message: 'error' });
        },
    });

    const result = await api.runAutomaticSyncIfNeeded({ now: new Date('2026-08-11T15:00:00Z'), source: 'alarm' });

    assert.equal(result.ok, false);
    assert.equal(result.automatic, true);
    assert.equal(storage.lastAutomaticSyncDate, undefined);
});

test('un inicio de Chrome después de las 08:00 dispara la recuperación automática pendiente', async () => {
    reset();
    storage.syncToken = 'tok-1';
    storage.syncUser = { email: 'demo@codered.lat' };
    storage.pendingQueue = [{ field: 'DNI', value: '12345678', timestamp: '2026-08-11T13:00:00Z' }];

    const api = loadSync();
    fetchHandler = () => ({
        ok: true,
        status: 200,
        async text() {
            return JSON.stringify({ data: { accepted: 1 } });
        },
    });

    const background = loadBackground(api);
    // Fecha inyectada: sin esto el test dependia del dia real del sistema.
    await background.bootstrap('startup', new Date('2026-08-11T15:00:00Z'));

    assert.equal(storage.lastAutomaticSyncDate, '2026-08-11');
    assert.equal(storage.lastAlarm.name, api.DAILY_SYNC_ALARM_NAME);
});

test('dos disparos simultáneos usan una sola ejecución gracias al lock compartido', async () => {
    reset();
    let resolveSync;
    let calls = 0;
    const api = {
        DAILY_SYNC_ALARM_NAME: 'shalom-recordar-daily-sync',
        async ensureDailyAutomaticSyncAlarm() {
            storage.alarmEnsured = true;
        },
        async runAutomaticSyncIfNeeded() {
            calls += 1;
            await new Promise((resolve) => {
                resolveSync = resolve;
            });
            return { ok: true, automatic: true, synced: 1 };
        },
        async syncNow() {
            calls += 1;
            await new Promise((resolve) => {
                resolveSync = resolve;
            });
            return { ok: true, synced: 1 };
        },
    };

    const background = loadBackground(api);
    const first = background.manualSync();
    const second = background.checkAutomaticSync('popup');

    await Promise.resolve();
    assert.equal(calls, 1);
    resolveSync();
    await Promise.all([first, second]);
    assert.equal(calls, 1);
});

(async () => {
    let failed = 0;
    for (const [name, fn] of tests) {
        try {
            await fn();
            console.log(`  ✓ ${name}`);
        } catch (error) {
            failed += 1;
            console.error(`  ✗ ${name}\n    ${error.message}`);
        }
    }
    console.log(`\n  ${tests.length - failed} de ${tests.length} pruebas en verde`);
    process.exit(failed === 0 ? 0 : 1);
})();
