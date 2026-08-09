// Sincronización automática con CodeRED Platform (diaria a las 9:00 AM Perú = UTC-5)

const SYNC_ALARM_NAME = 'shalom-daily-sync';
// Dominio productivo: codered.lat (migrado desde codered.host).
const API_ENDPOINT = 'https://platform.codered.lat/api/v1/shalom/sync';
const SYNC_INTERVAL_MINUTES = 1440; // 24 horas

function getNextSyncTime() {
    const now = new Date();
    // Convertir a UTC-5 (Perú)
    const peruTime = new Date(now.toLocaleString('en-US', { timeZone: 'America/Lima' }));

    let nextSync = new Date(peruTime);
    nextSync.setHours(9, 0, 0, 0); // 9:00 AM

    // Si ya pasaron las 9:00 AM hoy, programar para mañana
    if (nextSync <= peruTime) {
        nextSync.setDate(nextSync.getDate() + 1);
    }

    // Convertir de vuelta a UTC para chrome.alarms (que siempre usa UTC)
    const diffMs = peruTime.getTime() - now.getTime();
    return (nextSync.getTime() + diffMs) / 1000; // segundos
}

async function setupDailySync() {
    const delayInSeconds = Math.max(60, getNextSyncTime()); // mín 60s de espera

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

async function performSync() {
    const username = await getUsernameFromStorage();
    if (!username) {
        console.log('[Shalom] Sync skipped: username not configured');
        return;
    }

    const key = await getSessionKey();
    if (!key) {
        console.log('[Shalom] Sync skipped: not unlocked');
        return;
    }

    try {
        const records = await getAllRecords();
        const decrypted = await decryptAllRecords(records, key);
        const processedRecords = processRecordsForSync(decrypted);

        if (processedRecords.length === 0) {
            console.log('[Shalom] No records to sync');
            return;
        }

        const response = await fetch(API_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                username: username,
                records: processedRecords,
            }),
        });

        if (!response.ok) {
            const err = await response.text();
            throw new Error(`HTTP ${response.status}: ${err}`);
        }

        const result = await response.json();
        await recordSyncSuccess(result.batch_id, processedRecords.length);
        console.log(`[Shalom] Sync successful: ${result.record_count} records, batch ${result.batch_id}`);
    } catch (e) {
        console.error('[Shalom] Sync failed:', e.message);
        await recordSyncFailure(e.message);
    }
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
            });
        } catch (e) {
            console.warn('[Shalom] Failed to decrypt record:', record.id);
        }
    }
    return decrypted;
}

function processRecordsForSync(records) {
    const processed = [];
    for (const item of records) {
        let campo = item.field;
        let valor = item.value.trim();

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
                field: campo,
                value: valor,
                timestamp: item.timestamp,
            });
        }
    }
    return processed;
}

async function getUsernameFromStorage() {
    const res = await chrome.storage.local.get(['username']);
    return res.username || null;
}

async function recordSyncSuccess(batchId, recordCount) {
    const res = await chrome.storage.local.get(['syncLog']);
    const log = res.syncLog || [];
    log.push({
        type: 'success',
        batchId: batchId,
        recordCount: recordCount,
        timestamp: new Date().toISOString(),
    });
    while (log.length > 100) log.shift(); // Guardar solo últimos 100
    await chrome.storage.local.set({ syncLog: log });
}

async function recordSyncFailure(error) {
    const res = await chrome.storage.local.get(['syncLog']);
    const log = res.syncLog || [];
    log.push({
        type: 'error',
        error: error,
        timestamp: new Date().toISOString(),
    });
    while (log.length > 100) log.shift();
    await chrome.storage.local.set({ syncLog: log });
}

// Inicializar sincronización al cargar el service worker
setupDailySync();
