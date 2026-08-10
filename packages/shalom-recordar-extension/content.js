(() => {
const CONTENT_STATE_KEY = '__shalomRecordarContentState__';
const DOC_LISTENER_KEY = '__shalomRecordarDocumentKeydownListener__';
const CLAVE_FIELDS = ['swal-input1', 'swal-input2', 'swal-input3', 'swal-input4'];

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
    return getInputId(target) === 'inputnroguia';
}

function isDocumentField(target) {
    return getInputId(target) === 'inputnombre';
}

function shouldIgnoreKeydown(event) {
    return event.defaultPrevented || event.isComposing || event.key !== 'Enter' || event.shiftKey || event.altKey || event.ctrlKey || event.metaKey;
}

function sendCapture(field, value, source, eventTimeStamp) {
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

function captureOnEnter(event) {
    if (shouldIgnoreKeydown(event)) return;

    const target = event.target;
    if (!isCaptureTarget(target)) return;

    const source = getInputSource(target);

    if (isClaveField(target)) {
        const value = normalizeText(getFieldValue(target));
        if (!value) return;
        sendCapture('Clave', value, source, event.timeStamp);
        return;
    }

    if (isOsField(target)) {
        const value = normalizeText(getFieldValue(target));
        if (!value) return;
        sendCapture('OS', value, source, event.timeStamp);
        return;
    }

    if (!isDocumentField(target)) return;

    const classified = classifyDocumentValue(getFieldValue(target));
    if (!classified) return;

    sendCapture(classified.field, classified.value, source, event.timeStamp);
}

function ensureDocumentListener() {
    const state = getContentState();
    if (state.documentListenerAttached) return;
    state.documentListenerAttached = true;
    if (globalThis[DOC_LISTENER_KEY]) return;
    globalThis[DOC_LISTENER_KEY] = captureOnEnter;
    document.addEventListener('keydown', captureOnEnter, true);
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
