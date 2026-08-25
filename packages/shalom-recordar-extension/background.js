importScripts('crypto.js', 'db.js', 'sync.js');

const MAX_PENDING_QUEUE = 500; // tope de eventos en espera mientras la extensión está bloqueada
const recentCaptureIds = [];
let syncInFlight = null;

function rememberCaptureId(captureId) {
  if (typeof captureId !== 'string' || captureId.length === 0) {
    return false;
  }

  if (recentCaptureIds.includes(captureId)) {
    return true;
  }

  recentCaptureIds.push(captureId);
  while (recentCaptureIds.length > MAX_PENDING_QUEUE) {
    recentCaptureIds.shift();
  }

  return false;
}

async function getSessionKey() {
  const session = await chrome.storage.session.get(['keyB64']);
  if (!session.keyB64) return null;
  return importKeyB64(session.keyB64);
}

async function queuePending(data) {
  const res = await chrome.storage.local.get(['pendingQueue']);
  const queue = res.pendingQueue || [];
  queue.push(data);
  while (queue.length > MAX_PENDING_QUEUE) queue.shift();
  await chrome.storage.local.set({ pendingQueue: queue });
  chrome.action.setBadgeBackgroundColor({ color: '#d32f2f' });
  chrome.action.setBadgeText({ text: '!' });
}

async function handleSaveData(data) {
  if (!data || rememberCaptureId(data.captureId)) {
    return;
  }

  const key = await getSessionKey();
  if (!key) {
    // Bloqueado: no hay clave en memoria de sesión (aún no se abrió el popup para desbloquear).
    // Se guarda temporalmente para no perder el dato; se cifrará al desbloquear.
    await queuePending(data);
    return;
  }
  const encryptedValue = await encryptText(key, data.value);
  await addRecord({
    timestamp: data.timestamp,
    field: data.field,
    value: encryptedValue,
  });
}

function runExclusive(task) {
  if (syncInFlight) {
    return syncInFlight;
  }

  syncInFlight = Promise.resolve()
      .then(task)
      .finally(() => {
        syncInFlight = null;
      });

  return syncInFlight;
}

async function manualSync() {
  return runExclusive(() => ShalomRecordarSync.syncNow());
}

async function checkAutomaticSync(reason = 'startup', now) {
  return runExclusive(async () => {
    await ShalomRecordarSync.ensureDailyAutomaticSyncAlarm();
    // `now` inyectable para pruebas deterministas; en produccion se omite y
    // runAutomaticSyncIfNeeded usa la fecha real.
    const options = { source: reason };
    // Duck-typing, no `instanceof Date`: el objeto puede venir de otro realm
    // (un contexto vm en pruebas, o un mensaje entre contextos) y ahi
    // `instanceof` da false aunque sea una fecha valida.
    if (now && typeof now.getTime === 'function' && !Number.isNaN(now.getTime())) {
        options.now = now;
    }
    return ShalomRecordarSync.runAutomaticSyncIfNeeded(options);
  });
}

async function bootstrap(reason = 'startup', now) {
  return checkAutomaticSync(reason, now);
}

chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === 'saveData') {
    handleSaveData(request.data);
    return false;
  }

  if (request.action === 'manualSync') {
    manualSync()
      .then((result) => sendResponse({ ok: true, result }))
      .catch((err) => sendResponse({ ok: false, error: err?.message || 'No se pudo sincronizar.' }));
    return true; // indica que sendResponse será llamado asincrónicamente
  }

  if (request.action === 'checkAutomaticSync') {
    checkAutomaticSync(request.reason || 'popup')
      .then((result) => sendResponse({ ok: true, result }))
      .catch((err) => sendResponse({ ok: false, error: err?.message || 'No se pudo revisar la sincronización automática.' }));
    return true;
  }

  return false;
});

chrome.runtime.onInstalled.addListener(() => {
  bootstrap('installed').catch(() => {});
});

chrome.runtime.onStartup.addListener(() => {
  bootstrap('startup').catch(() => {});
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm?.name !== ShalomRecordarSync.DAILY_SYNC_ALARM_NAME) {
    return;
  }

  checkAutomaticSync('alarm')
      .then(() => {})
      .catch(() => {});
});

globalThis.ShalomRecordarBackground = {
  handleSaveData,
  manualSync,
  checkAutomaticSync,
  bootstrap,
  runExclusive,
  queuePending,
};
