const API_BASE = 'https://platform.codered.lat/api/v1/shalom-recordar';
const LOGIN_ENDPOINT = `${API_BASE}/auth/login`;
const SYNC_ENDPOINT = `${API_BASE}/sync`;
const STATUS_ENDPOINT = `${API_BASE}/sync/status`;
const INSTALLATION_STORAGE_KEY = 'installationUuid';
const SYNC_TOKEN_STORAGE_KEY = 'syncToken';
const USER_STORAGE_KEY = 'syncUser';
const META_STORAGE_KEY = 'syncMeta';
const EXTENSION_VERSION = chrome.runtime.getManifest().version;

function safeText(value) {
    return typeof value === 'string' ? value.trim() : '';
}

function fingerprintError(error) {
    if (error instanceof Error) {
        return `${error.name}:${error.message}`;
    }
    if (typeof error === 'string') {
        return error;
    }
    return 'unknown-error';
}

function getRequestErrorMessage(error) {
    if (error instanceof TypeError && /fetch/i.test(error.message)) {
        return 'No se pudo conectar con CodeRED Platform. Revisa red, HTTPS o CORS.';
    }
    return error instanceof Error ? error.message : 'Error desconocido';
}

function statusReason(status) {
    if (status === 401) return 'unauthorized';
    if (status === 403) return 'forbidden';
    if (status === 404) return 'not-found';
    if (status === 422) return 'validation';
    if (status === 429) return 'rate-limit';
    if (status >= 500) return 'server-error';
    return 'http-error';
}

async function getStored(keys) {
    return chrome.storage.local.get(keys);
}

async function setStored(values) {
    await chrome.storage.local.set(values);
}

async function getInstallationUuid() {
    const local = await getStored([INSTALLATION_STORAGE_KEY]);
    let installationUuid = safeText(local[INSTALLATION_STORAGE_KEY]);
    if (!installationUuid) {
        installationUuid = crypto.randomUUID();
        await setStored({ [INSTALLATION_STORAGE_KEY]: installationUuid });
    }

    return installationUuid;
}

async function getSyncContext() {
    const [installationUuid, meta] = await Promise.all([
        getInstallationUuid(),
        getStored([USER_STORAGE_KEY, META_STORAGE_KEY, SYNC_TOKEN_STORAGE_KEY]),
    ]);

    return {
        installation_uuid: installationUuid,
        extension_version: EXTENSION_VERSION,
        user: meta[USER_STORAGE_KEY] ?? null,
        meta: meta[META_STORAGE_KEY] ?? {},
        token: safeText(meta[SYNC_TOKEN_STORAGE_KEY]),
    };
}

async function parseResponse(response, context) {
    const rawText = await response.text();
    let payload = null;

    if (rawText) {
        try {
            payload = JSON.parse(rawText);
        } catch {
            if (response.ok) {
                throw new Error(`Respuesta JSON inválida durante ${context}.`);
            }
        }
    }

    if (response.ok) {
        return { ok: true, status: response.status, json: payload };
    }

    return {
        ok: false,
        status: response.status,
        reason: statusReason(response.status),
        message: safeText(payload?.message || payload?.error || rawText) || `HTTP ${response.status} durante ${context}.`,
        json: payload,
    };
}

async function requestJson(url, options, context) {
    try {
        const response = await fetch(url, options);
        return await parseResponse(response, context);
    } catch (error) {
        return {
            ok: false,
            status: 0,
            reason: 'network',
            message: getRequestErrorMessage(error),
            error,
        };
    }
}

async function login({ email, password }) {
    const syncContext = await getSyncContext();
    const response = await requestJson(LOGIN_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
            email,
            password,
            installation_uuid: syncContext.installation_uuid,
            extension_version: syncContext.extension_version,
            installation: syncContext.meta.installation ?? {},
            device_name: syncContext.meta.device_name ?? null,
            browser_name: syncContext.meta.browser_name ?? null,
            browser_version: syncContext.meta.browser_version ?? null,
            platform_name: syncContext.meta.platform_name ?? null,
            platform_version: syncContext.meta.platform_version ?? null,
        }),
    }, 'login');

    if (!response.ok) {
        return response;
    }

    const token = safeText(response.json?.data?.sync_token);
    const user = response.json?.data?.user ?? null;
    if (!token || !user) {
        return { ok: false, status: 500, reason: 'invalid-response', message: 'El servidor no devolvió un token de instalación válido.' };
    }

    await setStored({
        [SYNC_TOKEN_STORAGE_KEY]: token,
        [USER_STORAGE_KEY]: user,
        [META_STORAGE_KEY]: {
            ...(syncContext.meta ?? {}),
            lastLoginAt: new Date().toISOString(),
            installation_uuid: syncContext.installation_uuid,
            extension_version: syncContext.extension_version,
        },
    });

    return { ok: true, status: response.status, data: response.json.data };
}

async function logout() {
    await chrome.storage.local.remove([SYNC_TOKEN_STORAGE_KEY, USER_STORAGE_KEY]);
}

async function getSessionState() {
    const syncContext = await getSyncContext();
    const token = syncContext.token;
    if (!token) {
        return { authenticated: false, installation_uuid: syncContext.installation_uuid, extension_version: syncContext.extension_version };
    }

    const status = await requestJson(STATUS_ENDPOINT, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
        },
    }, 'status');

    return {
        authenticated: status.ok,
        installation_uuid: syncContext.installation_uuid,
        extension_version: syncContext.extension_version,
        user: syncContext.user,
        server: status.ok ? status.json?.data ?? null : null,
        error: status.ok ? null : { reason: status.reason, message: status.message, status: status.status },
    };
}

async function syncNow() {
    const syncContext = await getSyncContext();
    if (!syncContext.token) {
        return { ok: false, reason: 'unauthorized', message: 'Inicia sesión primero.' };
    }

    const records = await chrome.storage.local.get(['pendingQueue']);
    const pending = Array.isArray(records.pendingQueue) ? records.pendingQueue : [];
    if (pending.length === 0) {
        return { ok: true, status: 204, message: 'No hay registros para sincronizar.' };
    }

    const payload = {
        installation_uuid: syncContext.installation_uuid,
        extension_version: syncContext.extension_version,
        batch_id: `batch-${Date.now()}`,
        cursor: new Date().toISOString(),
        records: pending.map((record, index) => ({
            field: safeText(record.field),
            value: safeText(record.value),
            timestamp: safeText(record.timestamp) || new Date().toISOString(),
            record_id: safeText(record.record_id) || `local-${index}`,
            cursor: safeText(record.cursor) || new Date().toISOString(),
        })),
    };

    const response = await requestJson(SYNC_ENDPOINT, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${syncContext.token}`,
        },
        body: JSON.stringify(payload),
    }, 'sync');

    if (!response.ok) {
        return response;
    }

    await chrome.storage.local.set({ pendingQueue: [] });
    await setStored({ [META_STORAGE_KEY]: { ...(syncContext.meta ?? {}), lastSyncAt: new Date().toISOString(), lastSyncResult: response.json?.data ?? null } });

    return { ok: true, status: response.status, data: response.json?.data ?? null };
}

globalThis.ShalomRecordarSync = {
    login,
    logout,
    getSessionState,
    syncNow,
    getRequestErrorMessage,
    statusReason,
    fingerprintError,
};
