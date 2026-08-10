const API_BASE = 'https://platform.codered.lat/api/v1/shalom-recordar';
const LOGIN_ENDPOINT = `${API_BASE}/auth/login`;
const LOGOUT_ENDPOINT = `${API_BASE}/auth/logout`;
const SYNC_ENDPOINT = `${API_BASE}/sync`;
const STATUS_ENDPOINT = `${API_BASE}/sync/status`;
const PLATFORM_ACCOUNT_URL = 'https://platform.codered.lat/account/profile';

const INSTALLATION_STORAGE_KEY = 'installationUuid';
const SYNC_TOKEN_STORAGE_KEY = 'syncToken';
const USER_STORAGE_KEY = 'syncUser';
const META_STORAGE_KEY = 'syncMeta';
const EXTENSION_VERSION = chrome.runtime.getManifest().version;

/**
 * Claves que se borran al cerrar sesión.
 *
 * Deliberadamente NO incluye `installationUuid` (identifica al navegador, no al
 * usuario, y debe sobrevivir a un cambio de cuenta) ni `pendingQueue` ni la base
 * IndexedDB con el historial: cerrar sesión no es borrar datos.
 */
const SESSION_STORAGE_KEYS = [SYNC_TOKEN_STORAGE_KEY, USER_STORAGE_KEY];

function safeText(value) {
    return typeof value === 'string' ? value.trim() : '';
}

/**
 * Marca temporal en el formato exacto que valida la API: `Y-m-d\TH:i:s\Z`.
 *
 * `Date.prototype.toISOString()` siempre añade milisegundos
 * (`2026-08-10T12:15:55.080Z`), que la regla `date_format` del servidor
 * rechaza con un 422. Sin recortarlos, "Sincronizar ahora" fallaba siempre.
 */
function isoSeconds(value) {
    const date = value ? new Date(value) : new Date();
    const usable = Number.isNaN(date.getTime()) ? new Date() : date;

    return usable.toISOString().replace(/\.\d{3}Z$/, 'Z');
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

/**
 * Un token revocado o caducado obliga a rehacer login; cualquier otro fallo
 * (red caída, 500, límite de peticiones) NO debe cerrar la sesión, porque el
 * token sigue siendo válido y perderla sería un falso negativo.
 */
function isSessionRejected(status) {
    return status === 401 || status === 403;
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

/** Cabeceras autenticadas. El token nunca se registra ni se devuelve al UI. */
function authHeaders(token, extra = {}) {
    return { Accept: 'application/json', Authorization: `Bearer ${token}`, ...extra };
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

    // La contraseña no se guarda en ningún punto: solo viaja en esta petición.
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

    return { ok: true, status: response.status, data: response.json.data, user };
}

/**
 * Cierra la sesión. Intenta revocar el token en la plataforma y, pase lo que
 * pase con esa llamada, limpia siempre las credenciales locales: si el token ya
 * estaba revocado o no hay red, la sesión local debe desaparecer igualmente.
 */
async function logout() {
    const syncContext = await getSyncContext();
    let revoked = false;

    if (syncContext.token) {
        const response = await requestJson(LOGOUT_ENDPOINT, {
            method: 'POST',
            headers: authHeaders(syncContext.token, { 'Content-Type': 'application/json' }),
            body: JSON.stringify({ installation_uuid: syncContext.installation_uuid }),
        }, 'logout');
        revoked = response.ok;
    }

    await chrome.storage.local.remove(SESSION_STORAGE_KEYS);

    // El historial local y la cola pendiente se conservan a propósito.
    return { ok: true, revoked };
}

/**
 * Estado de sesión para el popup.
 *
 * Devuelve `authenticated` solo cuando hay token y la plataforma lo acepta. Si
 * lo rechaza (401/403) se limpia la sesión y se pide login; ante un fallo de red
 * se conserva la sesión y se informa del problema.
 */
async function getSessionState() {
    const syncContext = await getSyncContext();
    const base = {
        installation_uuid: syncContext.installation_uuid,
        extension_version: syncContext.extension_version,
        user: syncContext.user,
        meta: syncContext.meta,
    };

    if (!syncContext.token) {
        return { ...base, authenticated: false, reason: 'no-session' };
    }

    const query = new URLSearchParams({
        installation_uuid: syncContext.installation_uuid,
        extension_version: syncContext.extension_version,
    });

    const status = await requestJson(`${STATUS_ENDPOINT}?${query.toString()}`, {
        method: 'GET',
        headers: authHeaders(syncContext.token),
    }, 'status');

    if (status.ok) {
        const serverUser = status.json?.data?.user ?? null;
        if (serverUser) {
            // La copia local puede quedarse vieja; manda el servidor.
            await setStored({ [USER_STORAGE_KEY]: serverUser });
        }

        return {
            ...base,
            authenticated: true,
            user: serverUser ?? syncContext.user,
            server: status.json?.data ?? null,
            error: null,
        };
    }

    if (isSessionRejected(status.status)) {
        await chrome.storage.local.remove(SESSION_STORAGE_KEYS);

        return {
            ...base,
            authenticated: false,
            user: null,
            reason: 'session-revoked',
            error: { reason: status.reason, message: 'Tu sesión expiró o fue revocada. Inicia sesión nuevamente.', status: status.status },
        };
    }

    // Problema temporal: se mantiene la sesión y se avisa.
    return {
        ...base,
        authenticated: true,
        degraded: true,
        server: null,
        error: { reason: status.reason, message: status.message, status: status.status },
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
        await setStored({
            [META_STORAGE_KEY]: { ...(syncContext.meta ?? {}), lastSyncAt: new Date().toISOString(), lastSyncCount: 0 },
        });

        return { ok: true, status: 204, synced: 0, message: 'No hay registros nuevos para sincronizar.' };
    }

    const payload = {
        installation_uuid: syncContext.installation_uuid,
        extension_version: syncContext.extension_version,
        batch_id: `batch-${Date.now()}`,
        cursor: isoSeconds(),
        records: pending.map((record, index) => ({
            field: safeText(record.field),
            value: safeText(record.value),
            // Se normaliza siempre: los registros guardados por content.js
            // llevan milisegundos y el servidor los rechaza.
            timestamp: isoSeconds(safeText(record.timestamp) || undefined),
            record_id: safeText(record.record_id) || `local-${index}`,
            cursor: isoSeconds(safeText(record.cursor) || undefined),
        })),
    };

    const response = await requestJson(SYNC_ENDPOINT, {
        method: 'POST',
        headers: authHeaders(syncContext.token, { 'Content-Type': 'application/json' }),
        body: JSON.stringify(payload),
    }, 'sync');

    if (!response.ok) {
        if (isSessionRejected(response.status)) {
            await chrome.storage.local.remove(SESSION_STORAGE_KEYS);
            return { ...response, reason: 'session-revoked', message: 'Tu sesión expiró o fue revocada. Inicia sesión nuevamente.' };
        }

        return response;
    }

    await chrome.storage.local.set({ pendingQueue: [] });
    await setStored({
        [META_STORAGE_KEY]: {
            ...(syncContext.meta ?? {}),
            lastSyncAt: new Date().toISOString(),
            lastSyncCount: pending.length,
            lastSyncResult: response.json?.data ?? null,
        },
    });

    return { ok: true, status: response.status, synced: pending.length, data: response.json?.data ?? null };
}

/**
 * Exporta el historial local (cola pendiente + registros de IndexedDB) como
 * JSON descargable. No incluye token ni credenciales.
 */
async function buildExportPayload() {
    const syncContext = await getSyncContext();
    const stored = await chrome.storage.local.get(['pendingQueue']);
    let records = [];

    if (typeof globalThis.getAllRecords === 'function') {
        try {
            records = await globalThis.getAllRecords();
        } catch {
            records = [];
        }
    }

    return {
        exported_at: new Date().toISOString(),
        installation_uuid: syncContext.installation_uuid,
        extension_version: syncContext.extension_version,
        user: syncContext.user ? { name: syncContext.user.name ?? null, email: syncContext.user.email ?? null } : null,
        pending: Array.isArray(stored.pendingQueue) ? stored.pendingQueue : [],
        records,
    };
}

globalThis.ShalomRecordarSync = {
    login,
    logout,
    getSessionState,
    syncNow,
    buildExportPayload,
    getRequestErrorMessage,
    statusReason,
    fingerprintError,
    isSessionRejected,
    PLATFORM_ACCOUNT_URL,
    SESSION_STORAGE_KEYS,
};
