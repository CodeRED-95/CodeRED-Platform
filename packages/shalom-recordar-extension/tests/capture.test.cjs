/**
 * Pruebas rápidas de captura automática por inputs en content.js.
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
const mutationObservers = [];
const timers = new Map();
let timerSeq = 0;
const sandboxState = {
    claveFields: {},
};

function registerListener(type, handler) {
    listeners[type] ||= [];
    listeners[type].push(handler);
    registrations.push(type);
}

function emit(type, target, overrides = {}) {
    if (target?.id && sandboxState.claveFields[target.id]) {
        sandboxState.claveFields[target.id].value = target.value ?? '';
    }
    const event = {
        type,
        target: { nodeType: 1, ...target },
        defaultPrevented: false,
        isComposing: false,
        timeStamp: 1000,
        ...overrides,
    };
    for (const handler of listeners[type] || []) {
        handler(event);
    }
}

function reset() {
    listeners = {};
    registrations.length = 0;
    messages.length = 0;
    mutationObservers.length = 0;
    timers.clear();
    timerSeq = 0;
    sandboxState.claveFields = {};
}

function loadContentScript(sandbox) {
    vm.runInContext(SOURCE, sandbox);
}

function createSandbox() {
    sandboxState.claveFields = {
        'swal-input1': { id: 'swal-input1', value: '' },
        'swal-input2': { id: 'swal-input2', value: '' },
        'swal-input3': { id: 'swal-input3', value: '' },
        'swal-input4': { id: 'swal-input4', value: '' },
    };
    const modal = {
        id: 'modalValidarCodigo',
        style: 'display: block;',
        getAttribute(name) {
            if (name === 'style') return this.style;
            return null;
        },
    };

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
            getElementById(id) {
                if (id === 'modalValidarCodigo') return modal;
                if (sandboxState.claveFields[id]) return sandboxState.claveFields[id];
                return null;
            },
        },
        MutationObserver: class {
            constructor(callback) {
                this.callback = callback;
                mutationObservers.push(this);
            }
            observe(target, options) {
                this.target = target;
                this.options = options;
            }
            disconnect() {}
        },
        Date: class extends Date {
            static now() {
                return 1_000_000;
            }
        },
        setTimeout(handler, delay) {
            const id = ++timerSeq;
            timers.set(id, { handler, delay });
            return id;
        },
        clearTimeout(id) {
            timers.delete(id);
        },
        console,
        Node: { ELEMENT_NODE: 1 },
    };
}

function closeClaveModal() {
    // SweetAlert resetea las casillas del PIN al cerrar el modal. El capturador
    // no puede fiarse de sus valores en ese instante: la clave debe salir del
    // buffer. Se vacian aqui para que la prueba refleje el DOM real.
    for (const field of Object.values(sandboxState.claveFields)) {
        field.value = '';
    }
    for (const observer of mutationObservers) {
        if (observer.target && observer.target.id === 'modalValidarCodigo') {
            observer.target.style = 'display: none;';
            observer.callback([{ attributeName: 'style', target: observer.target }]);
        }
    }
}

function runTimers() {
    const pending = [...timers.entries()];
    timers.clear();
    for (const [, timer] of pending) {
        timer.handler();
    }
}

const tests = [];
const test = (name, fn) => tests.push([name, fn]);

test('DNI por input guarda una vez', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnombre', value: ' 00456879 ' });

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'DNI');
    assert.equal(messages[0].data.value, '00456879');
});

test('CE por input guarda una vez', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnombre', value: '004568798' });

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'CE');
    assert.equal(messages[0].data.value, '004568798');
});

test('RUC por input guarda una vez', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnombre', value: '20004568791' });

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'RUC');
    assert.equal(messages[0].data.value, '20004568791');
});

test('Clave por input guarda Clave', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'swal-input1', value: '3535' });
    runTimers();
    closeClaveModal();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'Clave');
    assert.equal(messages[0].data.value, '3535');
});

test('Clave de 57 guarda un solo registro completo', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'swal-input1', value: '5' });
    emit('input', { id: 'swal-input2', value: '7' });
    runTimers();
    closeClaveModal();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'Clave');
    assert.equal(messages[0].data.value, '57');
});

test('Clave conserva ceros iniciales y el primer dígito completo', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'swal-input1', value: '0123' });
    runTimers();
    closeClaveModal();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'Clave');
    assert.equal(messages[0].data.value, '0123');
});

test('Clave progresiva por input termina en un solo registro final', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'swal-input1', value: '3' });
    emit('input', { id: 'swal-input1', value: '35' });
    emit('input', { id: 'swal-input1', value: '353' });
    emit('input', { id: 'swal-input1', value: '3535' });
    runTimers();
    closeClaveModal();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'Clave');
    assert.equal(messages[0].data.value, '3535');
});

test('Clave de 0123 conserva ceros iniciales', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'swal-input1', value: '0' });
    emit('input', { id: 'swal-input2', value: '1' });
    emit('input', { id: 'swal-input3', value: '2' });
    emit('input', { id: 'swal-input4', value: '3' });
    runTimers();
    closeClaveModal();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'Clave');
    assert.equal(messages[0].data.value, '0123');
});

test('OS por input guarda OS', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnroguia', value: '7121847' });
    runTimers();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'OS');
    assert.equal(messages[0].data.value, '7121847');
});

test('DNI sin cambio relevante no guarda', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { nodeType: 1, value: '71218478' });
    emit('blur', { nodeType: 1, value: '71218478' });

    assert.equal(messages.length, 0);
});

test('Clave sin cambio relevante no guarda', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('blur', { nodeType: 1, id: 'swal-input1', value: '3535' });

    assert.equal(messages.length, 0);
});

test('OS sin cambio relevante no guarda', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('blur', { nodeType: 1, id: 'inputnroguia', value: '7121847' });

    assert.equal(messages.length, 0);
});

test('OS progresiva por input termina en un solo registro final', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnroguia', value: '8' });
    emit('input', { id: 'inputnroguia', value: '89' });
    emit('input', { id: 'inputnroguia', value: '899' });
    emit('input', { id: 'inputnroguia', value: '8990' });
    emit('input', { id: 'inputnroguia', value: '89906' });
    emit('input', { id: 'inputnroguia', value: '899061' });
    emit('input', { id: 'inputnroguia', value: '8990618' });
    emit('input', { id: 'inputnroguia', value: '89906189' });
    runTimers();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'OS');
    assert.equal(messages[0].data.value, '89906189');
});

test('OS de más de 8 dígitos se ignora', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnroguia', value: '899061890' });
    runTimers();

    assert.equal(messages.length, 0);
});

test('debounce + blur en OS no duplica', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnroguia', value: '89906189' });
    emit('blur', { nodeType: 1, id: 'inputnroguia', value: '89906189' });
    runTimers();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.value, '89906189');
});

test('inputnroguia nunca se clasifica como DNI', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnroguia', value: '71218478' });
    runTimers();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'OS');
    assert.equal(messages[0].data.value, '71218478');
});

test('Clave con valor de 8 dígitos nunca se reclasifica como DNI', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'swal-input1', value: '00456879' });
    runTimers();
    closeClaveModal();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'Clave');
    assert.equal(messages[0].data.value, '00456879');
});

test('un input produce un solo registro incluso con doble inicialización', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);
    const before = registrations.filter((type) => type === 'input' || type === 'change').length;
    loadContentScript(sandbox);
    const after = registrations.filter((type) => type === 'input' || type === 'change').length;

    emit('input', { id: 'inputnombre', value: '00456879' });
    emit('change', { id: 'inputnombre', value: '00456879' });

    assert.equal(after, before, 'no debe registrar otro listener');
    assert.equal(messages.length, 1);
});

test('mismo dato en una operación posterior vuelve a guardarse', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnombre', value: '00456879' });
    emit('input', { id: 'inputnombre', value: '00456879' }, { timeStamp: 5000 });

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
