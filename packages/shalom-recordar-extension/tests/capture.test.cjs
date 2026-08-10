/**
 * Pruebas rápidas de captura semántica en content.js.
 *
 * No usa navegador real ni red. Solo verifica que la extensión clasifica
 * DNI/CE/RUC y que evita duplicados triviales por eventos repetidos.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const EXTENSION_DIR = path.resolve(__dirname, '..');

let listeners = {};
const messages = [];

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

let fakeNow = 1_000_000;

const sandbox = {
    chrome: {
        runtime: {
            sendMessage(message) {
                messages.push(message);
            },
        },
    },
    document: {
        addEventListener(type, handler) {
            listeners[type] = handler;
        },
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
};

sandbox.globalThis = sandbox;
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(path.join(EXTENSION_DIR, 'content.js'), 'utf8'), sandbox);

function emit(type, target) {
    listeners[type]?.({ target });
}

function reset() {
    messages.length = 0;
    fakeNow = 1_000_000;
}

const tests = [];
const test = (name, fn) => tests.push([name, fn]);

test('clasifica 8 dígitos como DNI', () => {
    reset();
    emit('input', { id: 'inputnombre', name: 'inputnombre', placeholder: 'inputnombre', value: ' 71218478 ' });
    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'DNI');
    assert.equal(messages[0].data.value, '71218478');
});

test('clasifica 9 dígitos como CE', () => {
    reset();
    emit('input', { id: 'campo', name: 'campo', placeholder: 'campo', value: '712184798' });
    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'CE');
});

test('clasifica 11 dígitos como RUC', () => {
    reset();
    emit('change', { id: 'campo', name: 'campo', placeholder: 'campo', value: '20123456789' });
    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'RUC');
});

test('ignora longitudes inválidas y caracteres no numéricos', () => {
    reset();
    emit('input', { value: '1234567' });
    emit('input', { value: '1234567890' });
    emit('input', { value: 'ABC12345678' });
    assert.equal(messages.length, 0);
});

test('deduplica eventos consecutivos del mismo valor', () => {
    reset();
    emit('input', { value: '71218478' });
    fakeNow += 200;
    emit('change', { value: '71218478' });
    fakeNow += 1600;
    emit('blur', { value: '71218478' });
    assert.equal(messages.length, 2);
    assert.equal(messages[0].data.field, 'DNI');
    assert.equal(messages[1].data.field, 'DNI');
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

