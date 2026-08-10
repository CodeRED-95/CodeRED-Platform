(() => {
// Buffer para la Clave
let claveBuffer = { 'swal-input1': '', 'swal-input2': '', 'swal-input3': '', 'swal-input4': '' };

const CONTENT_STATE_KEY = '__shalomRecordarContentState__';
const DUPLICATE_WINDOW_MS = 1000;

function getContentState() {
    const globalState = globalThis[CONTENT_STATE_KEY] || {};
    globalThis[CONTENT_STATE_KEY] = globalState;
    return globalState;
}

function getInputSource(target) {
    return String(target?.id || target?.name || target?.placeholder || target?.getAttribute?.('aria-label') || target?.tagName || 'sin_nombre').trim();
}

function normalizeDigits(rawValue) {
    return String(rawValue ?? '').trim().replace(/\s+/g, '');
}

function classifyDocumentValue(rawValue) {
    const value = normalizeDigits(rawValue);

    if (!/^\d+$/.test(value)) {
        return null;
    }

    if (value.length === 8) {
        return { field: 'DNI', value };
    }

    if (value.length === 9) {
        return { field: 'CE', value };
    }

    if (value.length === 11) {
        return { field: 'RUC', value };
    }

    return null;
}

function captureFingerprint(type, value, source) {
    return [type, value, source].join('|');
}

function shouldSkipDuplicateCapture(type, value, source) {
    const state = getContentState();
    const now = Date.now();
    const key = captureFingerprint(type, value, source);
    const duplicate = state.lastCapture?.key === key && (now - state.lastCapture.at) <= DUPLICATE_WINDOW_MS;

    if (!duplicate) {
        state.lastCapture = { key, at: now };
    }

    return duplicate;
}

function queueCapture(classified, source) {
    const state = getContentState();
    if (state.initialized === true && shouldSkipDuplicateCapture(classified.field, classified.value, source)) {
        return;
    }

    chrome.runtime.sendMessage({
        action: 'saveData',
        data: {
            field: classified.field,
            value: classified.value,
            source,
            timestamp: new Date().toISOString(),
        },
    });
}

function captureDocumentValue(rawValue, source) {
    const classified = classifyDocumentValue(rawValue);
    if (!classified) {
        return;
    }

    queueCapture(classified, source);
}

function handleFieldEvent(event) {
    const target = event.target;
    if (!target || !('value' in target)) {
        return;
    }

    if (['swal-input1', 'swal-input2', 'swal-input3', 'swal-input4'].includes(target.id)) {
        claveBuffer[target.id] = target.value;
        return;
    }

    const source = getInputSource(target);
    const value = normalizeDigits(target.value);

    if (event.type === 'keyup' && event.key && event.key !== 'Enter' && value.length < 8) {
        return;
    }

    if (value === '') {
        return;
    }

    captureDocumentValue(value, source);
}

function ensureListeners() {
    const state = getContentState();
    if (state.initialized === true) {
        return;
    }

    state.initialized = true;

    for (const type of ['input', 'change', 'blur', 'keyup']) {
        document.addEventListener(type, handleFieldEvent, true);
    }
}

// 1. Capturar pulsaciones para llenar el buffer de la clave
ensureListeners();

// 2. Observador para detectar el cierre del modal de validación (ÉXITO)
const targetNode = document.getElementById('modalValidarCodigo');
if (targetNode) {
    const state = getContentState();
    if (!state.claveObserverAttached) {
        state.claveObserverAttached = true;
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'style') {
                    const style = targetNode.getAttribute('style');
                    // Si el modal pasa a display: none, enviamos la clave completa
                    if (style && style.includes('display: none')) {
                        const claveCompleta = `${claveBuffer['swal-input1']}${claveBuffer['swal-input2']}${claveBuffer['swal-input3']}${claveBuffer['swal-input4']}`;

                        if (claveCompleta !== '') {
                            if (!shouldSkipDuplicateCapture('Clave', claveCompleta, 'modalValidarCodigo')) {
                                chrome.runtime.sendMessage({
                                    action: 'saveData',
                                    data: { field: 'Clave', value: claveCompleta, source: 'modalValidarCodigo', timestamp: new Date().toISOString() }
                                });
                            }
                            // Limpiar buffer tras enviar
                            claveBuffer = { 'swal-input1': '', 'swal-input2': '', 'swal-input3': '', 'swal-input4': '' };
                        }
                    }
                }
            });
        });
        observer.observe(targetNode, { attributes: true });
        state.claveObserver = observer;
    }
}
})();
