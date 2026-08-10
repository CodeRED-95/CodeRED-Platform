/**
 * Pruebas rápidas de captura por Enter en content.js.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const EXTENSION_DIR = path.resolve(__dirname, '..');
const SOURCE = fs.readFileSync(path.join(EXTENSION_DIR, 'content.js'), 'utf8');

let listeners = {};
const registrations = [];
const messages = [];

function registerListener(type, handler) {
    listeners[type] ||= [];
    listeners[type].push(handler);
    registrations.push(type);
}

function emitKeydown(target, overrides = {}) {
    const event = {
        key: 'Enter',
        target: { nodeType: 1, matches: () => true, closest: () => null, ...target },
        defaultPrevented: false,
        isComposing: false,
        shiftKey: false,
        altKey: false,
        ctrlKey: false,
        metaKey: false,
        timeStamp: 1000,
        preventDefault() {},
        ...overrides,
    };

    for (const handler of listeners.keydown || []) {
        handler(event);
    }
}

function emit(type, target, overrides = {}) {
    const event = { target, ...overrides };
    for (const handler of listeners[type] || []) {
        handler(event);
    }
}

function reset() {
    listeners = {};
    registrations.length = 0;
    messages.length = 0;
}

function loadContentScript(sandbox) {
    vm.runInContext(SOURCE, sandbox);
}

function createSandbox() {
    return {
        chrome: {
            runtime: {
                sendMessage(message) {
                    messages.push(message);
                },
            },
        },
        document: {
            addEventListener(type, handler) {
                registerListener(type, handler);
            },
            documentElement: { nodeType: 1 },
            body: { nodeType: 1 },
            getElementById() {
                return null;
            },
        },
        MutationObserver: class {
            observe() {}
            disconnect() {}
        },
        Date: class extends Date {
            static now() {
                return 1_000_000;
            }
        },
        console,
        Node: { ELEMENT_NODE: 1 },
    };
}

const tests = [];
const test = (name, fn) => tests.push([name, fn]);

test('DNI + Enter guarda una vez', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emitKeydown({ id: 'inputnombre', value: ' 00456879 ' });

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'DNI');
    assert.equal(messages[0].data.value, '00456879');
});

test('CE + Enter guarda una vez', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emitKeydown({ name: 'documento', value: '004568798' });

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'CE');
    assert.equal(messages[0].data.value, '004568798');
});

test('RUC + Enter guarda una vez', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emitKeydown({ placeholder: 'ruc', value: '20004568791' });

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'RUC');
    assert.equal(messages[0].data.value, '20004568791');
});

test('Clave + Enter guarda Clave', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emitKeydown({ id: 'swal-input1', value: '3535' });

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'Clave');
    assert.equal(messages[0].data.value, '3535');
});

test('OS + Enter guarda OS', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emitKeydown({ id: 'inputos', value: 'OS-12345' });

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'OS');
    assert.equal(messages[0].data.value, 'OS-12345');
});

test('DNI sin Enter no guarda', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { nodeType: 1, value: '71218478' });
    emit('change', { nodeType: 1, value: '71218478' });
    emit('blur', { nodeType: 1, value: '71218478' });

    assert.equal(messages.length, 0);
});

test('Clave sin Enter no guarda', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { nodeType: 1, id: 'swal-input1', value: '3535' });

    assert.equal(messages.length, 0);
});

test('OS sin Enter no guarda', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { nodeType: 1, id: 'inputos', value: 'OS-12345' });

    assert.equal(messages.length, 0);
});

test('una pulsación Enter produce un solo registro incluso con doble inicialización', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);
    const before = registrations.filter((type) => type === 'keydown').length;
    loadContentScript(sandbox);
    const after = registrations.filter((type) => type === 'keydown').length;

    emitKeydown({ id: 'inputnombre', value: '00456879' });

    assert.equal(after, before, 'no debe registrar otro listener');
    assert.equal(messages.length, 1);
});

test('mismo dato con nuevo Enter posterior vuelve a guardarse', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emitKeydown({ id: 'inputnombre', value: '00456879' });
    emitKeydown({ id: 'inputnombre', value: '00456879' }, { timeStamp: 5000 });

    assert.equal(messages.length, 2);
});

if (require.main === module) {
    (async () => {
        for (const [name, fn] of tests) {
            try {
                const out = fn();
                if (out && typeof out.then === 'function') {
                    await out;
                }
                process.stdout.write(`  ✓ ${name}\n`);
            } catch (error) {
                process.stderr.write(`  ✗ ${name}\n`);
                throw error;
            }
        }
    })();
}
