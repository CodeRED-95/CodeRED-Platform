(() => {
const CONTENT_STATE_KEY = '__shalomRecordarContentState__';
const DOCUMENT_SELECTOR = 'input, textarea, [contenteditable="true"]';
const DOC_LISTENER_KEY = '__shalomRecordarDocumentKeydownListener__';

function getContentState() {
    const globalState = globalThis[CONTENT_STATE_KEY] || {};
    globalThis[CONTENT_STATE_KEY] = globalState;
    return globalState;
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

function getInputSource(target) {
    return String(target?.id || target?.name || target?.placeholder || target?.getAttribute?.('aria-label') || target?.tagName || 'sin_nombre').trim();
}

function getFieldValue(target) {
    if (!target) return '';
    if (typeof target.value === 'string') return target.value;
    if (typeof target.textContent === 'string') return target.textContent;
    return '';
}

function isCaptureTarget(target) {
    if (!target || target.nodeType !== 1) {
        return typeof target?.value === 'string' || typeof target?.textContent === 'string';
    }

    if (target.matches?.(DOCUMENT_SELECTOR)) {
        return true;
    }

    return typeof target.closest === 'function' && Boolean(target.closest(DOCUMENT_SELECTOR));
}

function shouldIgnoreKeydown(event) {
    return event.defaultPrevented || event.isComposing || event.key !== 'Enter' || event.shiftKey || event.altKey || event.ctrlKey || event.metaKey;
}

function queueCapture(classified, source, eventTimeStamp) {
    const captureId = [source, classified.field, classified.value, String(eventTimeStamp ?? Date.now())].join('|');
    chrome.runtime.sendMessage({
        action: 'saveData',
        data: {
            captureId,
            field: classified.field,
            value: classified.value,
            source,
            timestamp: new Date().toISOString(),
        },
    });
}

function captureOnEnter(event) {
    if (shouldIgnoreKeydown(event)) {
        return;
    }

    const target = event.target;
    if (!isCaptureTarget(target)) {
        return;
    }

    const classified = classifyDocumentValue(getFieldValue(target));
    if (!classified) {
        return;
    }

    queueCapture(classified, getInputSource(target), event.timeStamp);
}

function ensureDocumentListener() {
    const state = getContentState();
    if (state.documentListenerAttached) {
        return;
    }

    state.documentListenerAttached = true;
    if (globalThis[DOC_LISTENER_KEY]) {
        return;
    }

    globalThis[DOC_LISTENER_KEY] = captureOnEnter;
    document.addEventListener('keydown', captureOnEnter, true);
}

function ensureMutationObserver() {
    const state = getContentState();
    if (state.captureObserverAttached || typeof MutationObserver === 'undefined') {
        return;
    }

    const root = document.documentElement || document.body;
    if (!root) {
        return;
    }

    state.captureObserverAttached = true;
    const observer = new MutationObserver(() => {
        ensureDocumentListener();
    });

    observer.observe(root, {
        childList: true,
        subtree: true,
    });

    state.captureObserver = observer;
}

ensureDocumentListener();
ensureMutationObserver();
})();
