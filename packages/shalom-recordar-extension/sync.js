const API_BASE = 'https://platform.codered.lat/api/v1/shalom-recordar';
const REGISTER_ENDPOINT = `${API_BASE}/installations/register`;
const SYNC_ENDPOINT = `${API_BASE}/sync`;
const STATUS_ENDPOINT = `${API_BASE}/sync/status`;
const SYNC_ALARM_NAME = 'shalom-daily-sync';
const SYNC_INTERVAL_MINUTES = 1440;
const INSTALLATION_STORAGE_KEY = 'installationUuid';
const SYNC_TOKEN_STORAGE_KEY = 'syncToken';
const BOOTSTRAP_TOKEN_STORAGE_KEY = 'bootstrapToken';

let syncInProgress = false;
let lastExpectedFailureKey = null;

function logOnce(level, key, message, extra = null) {
    const dedupeKey = `${level}:${key}`;
    if (lastExpectedFailureKey === dedupeKey) {
        return;
    }

    lastExpectedFailureKey = dedupeKey;
    if (extra === null) {
        console[level](message);
        return;
    }
    console[level](message, extra);
}

function clearExpectedFailure(key = null) {
    if (key === null || lastExpectedFailureKey?.endsWith(`:${key}`)) {
        lastExpectedFailureKey = null;
    }
}

function safeText(value) {
    return typeof value === 'string' ? value.trim() : '';
}

function getErrorMessage(error) {
    if (error instanceof Error) {
        return error.message;
    }
    return typeof error === 'string' ? error : 'Error desconocido';
}

function buildRequestBody(installation, records) {
    return {
        installation_uuid: installation.installation_uuid,
        extension_version: installation.extension_version,
        installation: installation.installation,
        cursor: installation.cursor,
        batch_id: installation.batch_id,
        records,
    };
}

function buildInstallationContext(installation) {
    return {
        installation_uuid: installation.installation_uuid,
        extension_version: installation.extension_version,
        installation: installation.installation,
        cursor: installation.cursor,
        batch_id: installation.batch_id,
    };
}

async function setupDailySync() {
    const now = new Date();
    const peruTime = new Date(now.toLocaleString('en-US', { timeZone: 'America/Lima' }));
    const nextSync = new Date(peruTime);
    nextSync.setHours(9, 0, 0, 0);
    if (nextSync <= peruTime) {
        nextSync.setDate(nextSync.getDate() + 1);
    }

    const delayInSeconds = Math.max(60, (nextSync.getTime() - peruTime.getTime()) / 1000);
    await chrome.alarms.create(SYNC_ALARM_NAME, {
        periodInMinutes: SYNC_INTERVAL_MINUTES,
        when: Date.now() + (delayInSeconds * 1000),
    });
}

chrome.alarms.onAlarm.addListener(async (alarm) => {
    if (alarm.name === SYNC_ALARM_NAME) {
        await performSync();
    }
});

async function ensureInstallationInfo() {
    const res = await chrome.storage.local.get([INSTALLATION_STORAGE_KEY]);
    let installationUuid = safeText(res[INSTALLATION_STORAGE_KEY]);
    if (!installationUuid) {
        installationUuid = crypto.randomUUID();
        await chrome.storage.local.set({ [INSTALLATION_STORAGE_KEY]: installationUuid });
    }

    return {
        installation_uuid: installationUuid,
        extension_version: chrome.runtime.getManifest().version,
        installation: {
            device_name: navigator.userAgentData?.platform || navigator.platform || 'unknown',
            browser_name: 'Chrome',
            browser_version: navigator.userAgent || 'unknown',
            platform_name: navigator.platform || 'unknown',
            platform_version: navigator.userAgentData?.platformVersion || navigator.userAgent || 'unknown',
        },
        cursor: new Date().toISOString(),
        batch_id: `batch-${Date.now()}`,
    };
}

async function getStoredToken(key) {
    const res = await chrome.storage.local.get([key]);
    return safeText(res[key]);
}

async function setStoredToken(key, value) {
    await chrome.storage.local.set({ [key]: value });
}

async function bootstrapInstallation(installation) {
    const bootstrapToken = await getStoredToken(BOOTSTRAP_TOKEN_STORAGE_KEY);
    if (!bootstrapToken) {
        return { ok: false, reason: 'bootstrap-token-missing' };
    }

    const response = await fetch(REGISTER_ENDPOINT, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${bootstrapToken}`,
        },
        body: JSON.stringify(buildInstallationContext(installation)),
    });

    const parsed = await parseApiResponse(response, 'registro de instalación');
    if (!parsed.ok) {
        return parsed;
    }

    const syncToken = safeText(parsed.json?.data?.sync_token);
    if (!syncToken) {
        return { ok: false, reason: 'register-missing-token', message: 'El servidor no devolvió un token de sincronización.' };
    }

    await setStoredToken(SYNC_TOKEN_STORAGE_KEY, syncToken);
    await setStoredToken('apiToken', syncToken);

    return { ok: true, installation: parsed.json?.data ?? null };
}

async function parseApiResponse(response, context) {
    const contentType = response.headers.get('content-type') || '';
    const rawText = await response.text();

    let payload = null;
    if (rawText && contentType.includes('application/json')) {
        try {
            payload = JSON.parse(rawText);
        } catch {
            return {
                ok: false,
                reason: 'invalid-json',
                message: `Respuesta JSON inválida durante ${context}.`,
                status: response.status,
            };
        }
    } else if (rawText) {
        try {
            payload = JSON.parse(rawText);
        } catch {
            payload = null;
        }
    }

    if (response.ok) {
        return { ok: true, json: payload, status: response.status };
    }

    return {
        ok: false,
        status: response.status,
        reason: statusReason(response.status),
        message: extractServerMessage(payload, rawText, response.status, context),
        json: payload,
    };
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

function extractServerMessage(payload, rawText, status, context) {
    const message = safeText(payload?.message || payload?.error || payload?.data?.message || rawText);
    if (message) {
        return message;
    }

    const base = `HTTP ${status} durante ${context}.`;
    return base;
}

async function performSync() {
    if (syncInProgress) {
        return;
    }

    syncInProgress = true;

    try {
        const installation = await ensureInstallationInfo();
        let token = await getStoredToken(SYNC_TOKEN_STORAGE_KEY);
        if (!token) {
            const bootstrapResult = await bootstrapInstallation(installation);
            if (!bootstrapResult.ok) {
                if (bootstrapResult.reason === 'bootstrap-token-missing') {
                    logOnce('info', 'bootstrap-missing', '[Shalom Recordar] Sync skipped: no bootstrap token configured');
                    return;
                }
                logOnce('warn', `bootstrap-${bootstrapResult.reason || 'failed'}`, `[Shalom Recordar] Bootstrap failed: ${bootstrapResult.message || bootstrapResult.reason || 'unknown'}`);
                return;
            }
            token = await getStoredToken(SYNC_TOKEN_STORAGE_KEY);
        }

        if (!token) {
            logOnce('info', 'no-token', '[Shalom Recordar] Sync skipped: missing bootstrap/sync token');
            return;
        }

        const sessionKey = await getSessionKey();
        if (!sessionKey) {
            logOnce('info', 'locked', '[Shalom Recordar] Sync skipped: extension locked');
            return;
        }

        const records = await getAllRecords();
        const decrypted = await decryptAllRecords(records, sessionKey);
        const processedRecords = processRecordsForSync(decrypted);
        if (processedRecords.length === 0) {
            logOnce('info', 'empty', '[Shalom Recordar] Sync skipped: no records to send');
            return;
        }

        const response = await fetch(SYNC_ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Authorization: `Bearer ${token}`,
            },
            body: JSON.stringify(buildRequestBody(installation, processedRecords)),
        });

        const parsed = await parseApiResponse(response, 'sincronización');
        if (!parsed.ok) {
            if (parsed.status === 401 || parsed.status === 403) {
                logOnce('info', `auth-${parsed.status}`, `[Shalom Recordar] Sync rejected (${parsed.status}): ${parsed.message}`);
                return;
            }
            if (parsed.status === 404) {
                logOnce('info', 'not-found', `[Shalom Recordar] Sync endpoint not found: ${parsed.message}`);
                return;
            }
            if (parsed.status === 422 || parsed.status === 429) {
                logOnce('warn', `${parsed.reason}`, `[Shalom Recordar] Sync warning (${parsed.status}): ${parsed.message}`);
                return;
            }
            if (parsed.status >= 500) {
                logOnce('error', `server-${parsed.status}`, `[Shalom Recordar] Sync failed (${parsed.status}): ${parsed.message}`);
                return;
            }

            logOnce('warn', 'http-error', `[Shalom Recordar] Sync warning: ${parsed.message}`);
            return;
        }

        clearExpectedFailure();
        await recordSyncSuccess(parsed.json?.data?.cursor ?? null, processedRecords.length);
        console.info(`[Shalom Recordar] Sync successful: ${processedRecords.length} records`);
    } catch (error) {
        const message = getErrorMessage(error);
        if (message.includes('Failed to fetch') || message.includes('NetworkError')) {
            logOnce('warn', 'network', '[Shalom Recordar] Sync failed: network/CORS issue while contacting platform');
        } else {
            logOnce('error', 'unexpected', `[Shalom Recordar] Sync failed: ${message}`);
        }
        await recordSyncFailure(message);
    } finally {
        syncInProgress = false;
    }
}

async function syncStatus() {
    const token = await getStoredToken(SYNC_TOKEN_STORAGE_KEY);
    if (!token) {
        return null;
    }

    const installation = await ensureInstallationInfo();
    const url = new URL(STATUS_ENDPOINT);
    url.searchParams.set('installation_uuid', installation.installation_uuid);
    url.searchParams.set('extension_version', installation.extension_version);

    const response = await fetch(url.toString(), {
        method: 'GET',
        headers: {
            Authorization: `Bearer ${token}`,
        },
    });

    const parsed = await parseApiResponse(response, 'consulta de estado');
    return parsed.ok ? parsed.json : null;
}

async function decryptAllRecords(records, key) {
    const decrypted = [];
    for (const record of records) {
        try {
            const value = await decryptText(key, record.value);
            decrypted.push({
                timestamp: record.timestamp,
                field: record.field,
                value: value,
                record_id: record.record_id,
            });
        } catch {
            logOnce('debug', `decrypt-${record.id || record.timestamp}`, '[Shalom Recordar] Record skipped: decrypt failed');
        }
    }
    return decrypted;
}

function processRecordsForSync(records) {
    const processed = [];
    for (const item of records) {
        let campo = item.field;
        const valor = safeText(item.value);

        if (campo === 'inputnombre') {
            if (valor.length < 8) continue;
            if (valor.length === 8) campo = 'DNI';
            else if (valor.length === 9) campo = 'CE';
            else if (valor.length === 11) campo = 'RUC';
        } else if (campo === 'inputnroguia') {
            if (valor.length < 8) continue;
            campo = 'OS';
        }

        const camposPermitidos = ['DNI', 'CE', 'RUC', 'OS', 'Clave'];
        if (camposPermitidos.includes(campo)) {
            processed.push({
                record_id: item.record_id ?? `${item.timestamp}-${campo}`,
                field: campo,
                value: valor,
                timestamp: item.timestamp,
            });
        }
    }

    return processed;
}

async function recordSyncSuccess(batchId, recordCount) {
    const res = await chrome.storage.local.get(['syncLog']);
    const log = res.syncLog || [];
    log.push({
        type: 'success',
        batchId,
        recordCount,
        timestamp: new Date().toISOString(),
    });
    while (log.length > 100) log.shift();
    await chrome.storage.local.set({ syncLog: log });
}

async function recordSyncFailure(error) {
    const res = await chrome.storage.local.get(['syncLog']);
    const log = res.syncLog || [];
    log.push({
        type: 'error',
        error: safeText(error),
        timestamp: new Date().toISOString(),
    });
    while (log.length > 100) log.shift();
    await chrome.storage.local.set({ syncLog: log });
}

setupDailySync();
