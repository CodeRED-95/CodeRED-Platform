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
const AUTO_SYNC_DATE_STORAGE_KEY = 'lastAutomaticSyncDate';
const AUTO_SYNC_AT_STORAGE_KEY = 'lastAutomaticSyncAt';
const DAILY_SYNC_ALARM_NAME = 'shalom-recordar-daily-sync';
const DAILY_SYNC_HOUR = 8;
const PERU_TIME_ZONE = 'America/Lima';
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

function getLimaDateParts(value = new Date()) {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: PERU_TIME_ZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(value).reduce((acc, part) => {
        if (part.type !== 'literal') {
            acc[part.type] = part.value;
        }
        return acc;
    }, {});

    return {
        year: Number(parts.year),
        month: Number(parts.month),
        day: Number(parts.day),
        hour: Number(parts.hour),
        minute: Number(parts.minute),
        second: Number(parts.second),
    };
}

function formatLimaDate(value) {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;

    const parts = getLimaDateParts(date);
    const pad = (number) => String(number).padStart(2, '0');

    return `${pad(parts.day)}/${pad(parts.month)}/${parts.year} ${pad(parts.hour)}:${pad(parts.minute)}`;
}

function getLimaDateString(value = new Date()) {
    const parts = getLimaDateParts(value);
    const pad = (number) => String(number).padStart(2, '0');

    return `${parts.year}-${pad(parts.month)}-${pad(parts.day)}`;
}

function isAfterDailySyncHour(value = new Date()) {
    const parts = getLimaDateParts(value);
    return parts.hour > DAILY_SYNC_HOUR || (parts.hour === DAILY_SYNC_HOUR && parts.minute >= 0);
}

function getNextAutomaticSyncAt(value = new Date()) {
    const parts = getLimaDateParts(value);
    const nowMinutes = (parts.hour * 60) + parts.minute;
    const target = new Date(Date.UTC(parts.year, parts.month - 1, parts.day, 13, 0, 0));

    if (nowMinutes >= DAILY_SYNC_HOUR * 60) {
        target.setUTCDate(target.getUTCDate() + 1);
    }

    return target;
}

async function getAutomaticSyncState(now = new Date()) {
    const local = await getStored([AUTO_SYNC_DATE_STORAGE_KEY, AUTO_SYNC_AT_STORAGE_KEY]);
    const lastAutomaticSyncDate = safeText(local[AUTO_SYNC_DATE_STORAGE_KEY]);
    const lastAutomaticSyncAt = safeText(local[AUTO_SYNC_AT_STORAGE_KEY]);
    const nextAutomaticSyncAt = getNextAutomaticSyncAt(now);
    const today = getLimaDateString(now);

    return {
        lastAutomaticSyncDate: lastAutomaticSyncDate || null,
        lastAutomaticSyncAt: lastAutomaticSyncAt || null,
        lastAutomaticSyncAtLabel: formatLimaDate(lastAutomaticSyncAt),
        nextAutomaticSyncAt: nextAutomaticSyncAt.toISOString(),
        nextAutomaticSyncAtLabel: formatLimaDate(nextAutomaticSyncAt),
        currentLimaDate: today,
        automaticSyncDoneToday: lastAutomaticSyncDate === today,
        automaticSyncAvailable: isAfterDailySyncHour(now),
    };
}

async function markAutomaticSyncSuccess(now = new Date()) {
    const automaticSyncAt = now instanceof Date && !Number.isNaN(now.getTime()) ? now : new Date();
    const automaticSyncDate = getLimaDateString(automaticSyncAt);
    const storedMeta = await getStored([META_STORAGE_KEY]);
    await setStored({
        [AUTO_SYNC_DATE_STORAGE_KEY]: automaticSyncDate,
        [AUTO_SYNC_AT_STORAGE_KEY]: automaticSyncAt.toISOString(),
        [META_STORAGE_KEY]: {
            ...((storedMeta[META_STORAGE_KEY]) ?? {}),
            lastAutomaticSyncAt: automaticSyncAt.toISOString(),
            lastAutomaticSyncDate: automaticSyncDate,
        },
    });
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

/*
 * Límites que valida el servidor (SyncShalomRecordarRequest). Se replican aquí
 * para adaptar el registro ANTES de enviarlo: la validación es por lote, así que
 * un solo registro fuera de rango devolvía 422 para todo el envío y la cola no
 * volvía a vaciarse nunca.
 */
const FIELD_MAX = 100;
const VALUE_MAX = 2000;
const RECORDS_MAX = 500;

/**
 * Adapta un registro local al contrato del servidor.
 *
 * Devuelve null si es irrecuperable (sin campo o sin valor), para descartarlo
 * en vez de bloquear el lote entero.
 */
function normalizeRecord(record, index) {
    const field = safeText(record?.field).slice(0, FIELD_MAX);
    const rawValue = typeof record?.value === 'string' ? record.value : '';
    const value = rawValue.trim().slice(0, VALUE_MAX);

    if (field === '' || value === '') {
        return null;
    }

    return {
        field,
        value,
        timestamp: isoSeconds(safeText(record?.timestamp) || undefined),
        record_id: safeText(record?.record_id) || `local-${index}`,
        cursor: isoSeconds(safeText(record?.cursor) || undefined),
    };
}

/**
 * Traduce una respuesta de error a un mensaje accionable.
 *
 * Para 422 se derivan los campos concretos que fallaron; el resto de códigos
 * tienen mensaje propio para que el usuario sepa si debe reintentar, esperar o
 * volver a iniciar sesión.
 */
function describeFailure(response) {
    const status = response?.status ?? 0;

    if (status === 0) {
        return 'Sin conexión con CodeRED Platform. Revisa tu red e inténtalo de nuevo.';
    }

    if (status === 401) {
        return 'Tu sesión expiró o fue revocada. Inicia sesión nuevamente.';
    }

    if (status === 403) {
        return 'Tu cuenta no tiene permiso para sincronizar. Contacta al administrador.';
    }

    if (status === 429) {
        return 'Demasiadas peticiones seguidas. Espera un momento y vuelve a intentarlo.';
    }

    if (status >= 500) {
        return 'CodeRED Platform tuvo un problema interno. Inténtalo más tarde.';
    }

    if (status === 422) {
        const errors = response?.json?.errors;

        if (errors && typeof errors === 'object') {
            // records.3.value -> "valor (registro 4)", legible para el usuario.
            const labels = { field: 'campo', value: 'valor', timestamp: 'fecha', record_id: 'identificador', cursor: 'cursor' };
            const detalles = Object.keys(errors).slice(0, 3).map((key) => {
                const match = key.match(/^records\.(\d+)\.(\w+)$/);
                if (match) {
                    return `${labels[match[2]] ?? match[2]} (registro ${Number(match[1]) + 1})`;
                }
                return labels[key] ?? key;
            });

            if (detalles.length > 0) {
                const extra = Object.keys(errors).length > detalles.length ? ' y otros' : '';
                return `El servidor rechazó estos datos: ${detalles.join(', ')}${extra}.`;
            }
        }

        return response?.message || 'El servidor rechazó los datos enviados.';
    }

    return response?.message || 'No se pudo completar la operación.';
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
    const automaticSync = await getAutomaticSyncState();
    const base = {
        installation_uuid: syncContext.installation_uuid,
        extension_version: syncContext.extension_version,
        user: syncContext.user,
        meta: syncContext.meta,
        automatic_sync: automaticSync,
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
            error: { reason: status.reason, message: describeFailure(status), status: status.status },
        };
    }

    // Problema temporal: se mantiene la sesión y se avisa.
    return {
        ...base,
        authenticated: true,
        degraded: true,
        server: null,
        error: { reason: status.reason, message: describeFailure(status), status: status.status },
    };
}

/**
 * Últimos registros guardados localmente, más reciente primero.
 *
 * El historial vive en `pendingQueue` (chrome.storage.local): la base cifrada
 * de IndexedDB solo se llena si el popup llegó a desbloquear una clave de
 * sesión, cosa que este flujo no hace. Se lee la cola, que es la fuente real.
 *
 * @param {number} limit
 */
async function getRecentRecords(limit = 20) {
    const stored = await chrome.storage.local.get(['pendingQueue']);
    const queue = Array.isArray(stored.pendingQueue) ? stored.pendingQueue : [];

    return queue
        .map((record) => ({
            field: safeText(record?.field) || 'sin_nombre',
            value: typeof record?.value === 'string' ? record.value : '',
            timestamp: safeText(record?.timestamp),
        }))
        .sort((a, b) => (b.timestamp || '').localeCompare(a.timestamp || ''))
        .slice(0, limit);
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

    // Se sanean y descartan los irrecuperables antes de enviar: así un registro
    // corrupto no bloquea el lote entero con un 422.
    const normalized = pending.map((record, index) => normalizeRecord(record, index)).filter(Boolean).slice(0, RECORDS_MAX);

    if (normalized.length === 0) {
        // Todo era basura (sin campo/valor): se limpia para no reintentar en bucle.
        await chrome.storage.local.set({ pendingQueue: [] });
        return { ok: true, status: 204, synced: 0, message: 'No había registros válidos para sincronizar.' };
    }

    const payload = {
        installation_uuid: syncContext.installation_uuid,
        extension_version: syncContext.extension_version,
        batch_id: `batch-${Date.now()}`,
        cursor: isoSeconds(),
        records: normalized,
    };

    const response = await requestJson(SYNC_ENDPOINT, {
        method: 'POST',
        headers: authHeaders(syncContext.token, { 'Content-Type': 'application/json' }),
        body: JSON.stringify(payload),
    }, 'sync');

    if (!response.ok) {
        if (isSessionRejected(response.status)) {
            await chrome.storage.local.remove(SESSION_STORAGE_KEYS);
            return { ...response, reason: 'session-revoked', message: describeFailure(response) };
        }

        // Se conserva la cola: el fallo puede ser temporal (red, 429, 5xx).
        return { ...response, message: describeFailure(response) };
    }

    // La cola solo se vacía tras un envío aceptado. Los registros que se
    // descartaron por inválidos se pierden a propósito: no eran recuperables.
    await chrome.storage.local.set({ pendingQueue: [] });
    await setStored({
        [META_STORAGE_KEY]: {
            ...(syncContext.meta ?? {}),
            lastSyncAt: new Date().toISOString(),
            lastSyncCount: normalized.length,
            lastSyncResult: response.json?.data ?? null,
        },
    });

    return { ok: true, status: response.status, synced: normalized.length, data: response.json?.data ?? null };
}

async function ensureDailyAutomaticSyncAlarm(now = new Date()) {
    if (!chrome?.alarms?.create) {
        return null;
    }

    const nextAutomaticSyncAt = getNextAutomaticSyncAt(now);
    await chrome.alarms.create(DAILY_SYNC_ALARM_NAME, { when: nextAutomaticSyncAt.getTime() });
    return nextAutomaticSyncAt;
}

async function runAutomaticSyncIfNeeded({ now = new Date(), source = 'unknown' } = {}) {
    const syncContext = await getSyncContext();
    const automaticSyncState = await getAutomaticSyncState(now);

    if (!syncContext.token) {
        return { ok: false, skipped: true, reason: 'no-session', message: 'Esperando sesión válida para la sincronización automática.' };
    }

    if (!automaticSyncState.automaticSyncAvailable) {
        return { ok: true, skipped: true, reason: 'before-window', message: 'Todavía no es hora de la sincronización automática.' };
    }

    if (automaticSyncState.automaticSyncDoneToday) {
        return { ok: true, skipped: true, reason: 'already-done', message: 'La sincronización automática de hoy ya se realizó.' };
    }

    const result = await syncNow();
    if (result.ok) {
        await markAutomaticSyncSuccess(now);
        return {
            ...result,
            automatic: true,
            source,
        };
    }

    return {
        ...result,
        automatic: true,
        source,
        message: result.message || describeFailure(result),
    };
}

async function getAutomaticSyncSummary(now = new Date()) {
    const state = await getAutomaticSyncState(now);
    return {
        ...state,
        lastAutomaticSyncAtLabel: state.lastAutomaticSyncAtLabel || null,
        nextAutomaticSyncAtLabel: state.nextAutomaticSyncAtLabel || null,
    };
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
    ensureDailyAutomaticSyncAlarm,
    runAutomaticSyncIfNeeded,
    getAutomaticSyncState,
    getAutomaticSyncSummary,
    getNextAutomaticSyncAt,
    getLimaDateString,
    formatLimaDate,
    isAfterDailySyncHour,
    buildExportPayload,
    getRecentRecords,
    describeFailure,
    normalizeRecord,
    getRequestErrorMessage,
    statusReason,
    fingerprintError,
    isSessionRejected,
    PLATFORM_ACCOUNT_URL,
    SESSION_STORAGE_KEYS,
    DAILY_SYNC_ALARM_NAME,
    AUTO_SYNC_DATE_STORAGE_KEY,
    AUTO_SYNC_AT_STORAGE_KEY,
};
