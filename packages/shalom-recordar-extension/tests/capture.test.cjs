/**
 * Pruebas rápidas de captura por Enter en content.js.
 *
 * Verifica que la extensión solo guarda documentos cuando el usuario
 * presiona Enter y que no se generan duplicados técnicos por listeners o
 * reinicializaciones del content script.
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
let fakeNow = 1_000_000;

function createDateClass() {
    return class extends Date {
        constructor(...args) {
            super(...(args.length ? args : [Date.now()]));
        }

        static now() {
            return fakeNow;
        }
    };
}

function registerListener(type, handler) {
    listeners[type] ||= [];
    listeners[type].push(handler);
    registrations.push(type);
}

function emitKeydown(target, overrides = {}) {
    const elementTarget = {
        nodeType: 1,
        matches: () => true,
        closest: () => null,
        ...target,
    };
    const event = {
        key: 'Enter',
        target: elementTarget,
        defaultPrevented: false,
        isComposing: false,
        shiftKey: false,
        altKey: false,
        ctrlKey: false,
        metaKey: false,
        timeStamp: fakeNow,
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
    fakeNow = 1_000_000;
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
        Date: createDateClass(),
        console,
        Node: { ELEMENT_NODE: 1 },
    };
}

const tests = [];
const test = (name, fn) => tests.push([name, fn]);

test('no guarda al escribir sin Enter', () => {
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

test('guarda DNI una sola vez al presionar Enter', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emitKeydown({ id: 'inputnombre', name: 'inputnombre', placeholder: 'inputnombre', value: ' 00456879 ' });

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'DNI');
    assert.equal(messages[0].data.value, '00456879');
});

test('guarda CE y RUC una sola vez al presionar Enter', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emitKeydown({ value: '004568798' });
    emitKeydown({ value: '20004568791' });

    assert.equal(messages.length, 2);
    assert.deepEqual(messages.map((message) => message.data.field), ['CE', 'RUC']);
});

test('ignora longitudes inválidas y caracteres no numéricos', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emitKeydown({ value: '0045687' });
    emitKeydown({ value: '1234567890' });
    emitKeydown({ value: 'ABC00456879' });

    assert.equal(messages.length, 0);
});

test('preserva ceros iniciales y no convierte a número', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emitKeydown({ value: '00456879' });

    assert.equal(messages[0].data.value, '00456879');
});

test('una pulsación Enter no genera duplicados aunque se cargue otra vez el script', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);
    const before = registrations.filter((type) => type === 'keydown').length;

    loadContentScript(sandbox);
    const after = registrations.filter((type) => type === 'keydown').length;

    emitKeydown({ value: '71218478' });

    assert.equal(after, before, 'la segunda carga no debe registrar un listener adicional');
    assert.equal(messages.length, 1);
});

test('el mismo documento puede guardarse de nuevo en un Enter posterior legítimo', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emitKeydown({ value: '20123456789' });
    fakeNow += 300_000;
    emitKeydown({ value: '20123456789' });

    assert.equal(messages.length, 2);
    assert.equal(messages[0].data.value, '20123456789');
    assert.equal(messages[1].data.value, '20123456789');
});

test('MutationObserver solo asegura listeners y no guarda datos por sí mismo', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    assert.ok(registrations.includes('keydown'));
    assert.equal(messages.length, 0);
});

if (require.main === module) {
    for (const [name, fn] of tests) {
        try {
            fn();
            process.stdout.write(`  ✓ ${name}\n`);
        } catch (error) {
            process.stderr.write(`  ✗ ${name}\n`);
            throw error;
        }
    }
}
