(() => {
const CONTENT_STATE_KEY = '__shalomRecordarContentState__';
const DOC_INPUT_ID = 'inputnombre';
const OS_INPUT_ID = 'inputnroguia';
const CLAVE_FIELDS = ['swal-input1', 'swal-input2', 'swal-input3', 'swal-input4'];
// Senales de que la clave fue CORRECTA: el servidor solo inyecta estos
// formularios cuando el codigo validado es valido. El cierre del modal de
// validacion no sirve: tambien ocurre al cancelar o fallar.
//   - frmEntrega  (action .../entrega/ajax): registro de la entrega.
//   - formPagoOS  (action .../pagos/Generar): ventana "Comprobante".
const CONFIRMATION_FORMS = [
    { id: 'frmEntrega', actionHint: 'entrega/ajax' },
    { id: 'formPagoOS', actionHint: 'pagos/generar' },
];
const DEDUPE_WINDOW_MS = 1500;
const OS_DEBOUNCE_MS = 650;

function getContentState() {
    const globalState = globalThis[CONTENT_STATE_KEY] || {};
    globalThis[CONTENT_STATE_KEY] = globalState;
    return globalState;
}

// El contexto de la extension se invalida al recargarla o actualizarla: el
// content script viejo sigue vivo en la pagina pero `chrome.runtime` pasa a ser
// undefined y cualquier sendMessage revienta. Se comprueba antes de usarlo y se
// desmonta todo para dejar de escuchar en una pagina ya huerfana.
function isExtensionContextValid() {
    try {
        return Boolean(globalThis.chrome && chrome.runtime && chrome.runtime.id);
    } catch {
        return false;
    }
}

function teardownCapture() {
    const state = getContentState();
    if (state.torndown) return;
    state.torndown = true;

    for (const key of Object.keys(state.osDebounceTimers || {})) {
        clearTimeout(state.osDebounceTimers[key]);
    }
    state.osDebounceTimers = {};
    state.claveBuffer = {};

    if (state.captureObserver) {
        state.captureObserver.disconnect();
        state.captureObserver = null;
    }
    state.captureObserverAttached = false;

    if (state.documentListenerAttached) {
        document.removeEventListener('input', handleCapture, true);
        document.removeEventListener('change', handleCapture, true);
        document.removeEventListener('blur', handleCapture, true);
        state.documentListenerAttached = false;
    }
}

function normalizeText(rawValue) {
    return String(rawValue ?? '').trim();
}

function normalizeDigits(rawValue) {
    return normalizeText(rawValue).replace(/\s+/g, '');
}

function classifyDocumentValue(rawValue) {
    const value = normalizeDigits(rawValue);

    if (!/^\d+$/.test(value)) {
        return null;
    }

    if (value.length === 8) return { field: 'DNI', value };
    if (value.length === 9) return { field: 'CE', value };
    if (value.length === 11) return { field: 'RUC', value };
    return null;
}

function getInputSource(target) {
    const raw = String(target?.id || target?.name || target?.placeholder || target?.getAttribute?.('aria-label') || target?.tagName || 'sin_nombre');
    return raw.trim();
}

function getInputId(target) {
    return getInputSource(target).toLowerCase();
}

function getFieldValue(target) {
    if (!target) return '';
    if (typeof target.value === 'string') return target.value;
    if (typeof target.textContent === 'string') return target.textContent;
    return '';
}

function isCaptureTarget(target) {
    return Boolean(target) && (target.nodeType === 1 || typeof target.value === 'string' || typeof target.textContent === 'string');
}

function isClaveField(target) {
    return CLAVE_FIELDS.includes(getInputId(target));
}

function isOsField(target) {
    return getInputId(target) === OS_INPUT_ID;
}

function isDocumentField(target) {
    return getInputId(target) === DOC_INPUT_ID;
}

function isRelevantField(target) {
    return isClaveField(target) || isOsField(target) || isDocumentField(target);
}

function shouldCapture(event) {
    return Boolean(event)
        && !event.defaultPrevented
        && !event.isComposing
        && (event.type === 'input' || event.type === 'change');
}

function canSendCapture(state, dedupeKey, eventTimeStamp) {
    const now = typeof eventTimeStamp === 'number' && Number.isFinite(eventTimeStamp) ? eventTimeStamp : Date.now();
    const previous = state.lastCaptureByKey?.[dedupeKey];
    if (previous && now - previous < DEDUPE_WINDOW_MS) {
        return false;
    }

    state.lastCaptureByKey ||= {};
    state.lastCaptureByKey[dedupeKey] = now;
    return true;
}

function sendCapture(field, value, source, eventTimeStamp) {
    if (!isExtensionContextValid()) {
        teardownCapture();
        return;
    }

    const state = getContentState();
    const dedupeKey = [source, field, value].join('|');
    if (!canSendCapture(state, dedupeKey, eventTimeStamp)) {
        return;
    }

    const message = {
        action: 'saveData',
        data: {
            captureId: [source, field, value, String(eventTimeStamp ?? Date.now())].join('|'),
            field,
            value,
            source,
            timestamp: new Date().toISOString(),
        },
    };

    try {
        // El callback consume chrome.runtime.lastError: sin el, un service
        // worker dormido o sin receptor deja un error no capturado en consola.
        chrome.runtime.sendMessage(message, () => void chrome.runtime.lastError);
    } catch {
        // El contexto pudo invalidarse entre la comprobacion y el envio.
        teardownCapture();
    }
}

function captureDocument(target, source, eventTimeStamp) {
    const classified = classifyDocumentValue(getFieldValue(target));
    if (!classified) return;
    sendCapture(classified.field, classified.value, source, eventTimeStamp);
}

function captureOs(target, source, eventTimeStamp) {
    const value = normalizeText(getFieldValue(target));
    if (!/^\d+$/.test(value) || value.length > 8) return null;
    sendCapture('OS', value, source, eventTimeStamp);
    return value;
}

function getOsDebounceKey(source) {
    return ['os', source].join('|');
}

function clearOsDebounce(state, source) {
    const key = getOsDebounceKey(source);
    const timer = state.osDebounceTimers?.[key];
    if (timer) {
        clearTimeout(timer);
        delete state.osDebounceTimers[key];
    }
}

function saveOsFinalValue(target, source, eventTimeStamp) {
    const state = getContentState();
    const value = captureOs(target, source, eventTimeStamp);
    if (!value) {
        clearOsDebounce(state, source);
        return;
    }

    clearOsDebounce(state, source);
}

function scheduleOsDebouncedSave(target, source, eventTimeStamp) {
    const state = getContentState();
    state.osDebounceTimers ||= {};
    const key = getOsDebounceKey(source);

    clearOsDebounce(state, source);
    state.osDebounceTimers[key] = setTimeout(() => {
        delete state.osDebounceTimers[key];
        saveOsFinalValue(target, source, eventTimeStamp);
    }, OS_DEBOUNCE_MS);
}

function captureClave(target) {
    const state = getContentState();
    state.claveBuffer ||= {};
    // Se indexa por la id normalizada (misma que CLAVE_FIELDS). El valor se
    // guarda en crudo salvo el trim: es lo que el usuario tecleo en la casilla,
    // y es la unica fuente fiable de la clave.
    state.claveBuffer[getInputId(target)] = normalizeText(getFieldValue(target));
}

function getClaveCompleteValue() {
    const state = getContentState();
    const buffer = state.claveBuffer || {};

    // Solo desde el buffer, nunca del DOM. Cuando el modal se cierra, SweetAlert
    // ya reseteo o reordeno las casillas swal-input*, asi que leer sus valores
    // en ese instante capturaba una clave incorrecta. El buffer conserva lo que
    // el usuario tecleo, en el orden fijo 1-2-3-4.
    return CLAVE_FIELDS.map((fieldId) => buffer[fieldId] || '').join('');
}

function isElementVisible(target) {
    for (let node = target; node; node = node.parentElement) {
        if (node.hidden || (node.getAttribute && node.getAttribute('aria-hidden') === 'true')) return false;
        const style = String((node.getAttribute && node.getAttribute('style')) || '').replace(/\s+/g, '').toLowerCase();
        if (style.includes('display:none') || style.includes('visibility:hidden')) return false;
    }
    return true;
}

function matchesConfirmationForm(form, actionHint) {
    if (!form || !isElementVisible(form)) return false;
    const action = String((form.getAttribute && form.getAttribute('action')) || '').toLowerCase();
    // El id basta; la action se comprueba como respaldo cuando esta disponible.
    return action === '' || action.includes(actionHint);
}

// Primer formulario de confirmacion presente y visible, o null.
function findConfirmationForm() {
    for (const target of CONFIRMATION_FORMS) {
        const form = document.getElementById(target.id);
        if (matchesConfirmationForm(form, target.actionHint)) return { form, id: target.id };
    }
    return null;
}

/**
 * Captura la clave cuando aparece cualquiera de los formularios de
 * confirmacion (entrega o comprobante).
 *
 * Es la senal fiable de que el codigo fue correcto: el servidor solo los
 * inyecta tras validar. El buffer conserva lo ultimo tecleado, de modo que si
 * hubo intentos fallidos previos, se envia el intento acertado.
 *
 * Se marca `entregaConfirmed` para no reenviar mientras el formulario sigue en
 * pantalla, y se reinicia cuando ninguno esta presente, para que la siguiente
 * operacion pueda dispararse de nuevo.
 */
function checkEntregaConfirmation() {
    const state = getContentState();
    const match = findConfirmationForm();

    if (!match) {
        state.entregaConfirmed = false;
        return;
    }

    if (state.entregaConfirmed) return;
    state.entregaConfirmed = true;

    const value = getClaveCompleteValue();
    if (!value) return;

    sendCapture('Clave', value, match.id, Date.now());
    state.claveBuffer = {};
}

function handleCapture(event) {
    if (!shouldCapture(event)) return;

    const target = event.target;
    if (!isCaptureTarget(target)) return;

    const source = getInputSource(target);
    if (!isRelevantField(target)) return;

    if (isClaveField(target)) {
        captureClave(target);
        return;
    }

    if (isOsField(target)) {
        if (event.type === 'input') {
            scheduleOsDebouncedSave(target, source, event.timeStamp);
            return;
        }

        clearOsDebounce(getContentState(), source);
        saveOsFinalValue(target, source, event.timeStamp);
        return;
    }

    if (isDocumentField(target)) {
        captureDocument(target, source, event.timeStamp);
    }
}

function ensureDocumentListener() {
    const state = getContentState();
    if (state.torndown || state.documentListenerAttached) return;
    state.documentListenerAttached = true;
    document.addEventListener('input', handleCapture, true);
    document.addEventListener('change', handleCapture, true);
    document.addEventListener('blur', handleCapture, true);
}

function ensureMutationObserver() {
    const state = getContentState();
    if (state.torndown || state.captureObserverAttached || typeof MutationObserver === 'undefined') return;

    const root = document.documentElement || document.body;
    if (!root) return;

    state.captureObserverAttached = true;
    const observer = new MutationObserver(() => {
        if (!isExtensionContextValid()) {
            teardownCapture();
            return;
        }
        ensureDocumentListener();
        checkEntregaConfirmation();
    });

    observer.observe(root, { childList: true, subtree: true });
    state.captureObserver = observer;
    checkEntregaConfirmation();
}

ensureDocumentListener();
ensureMutationObserver();
})();
