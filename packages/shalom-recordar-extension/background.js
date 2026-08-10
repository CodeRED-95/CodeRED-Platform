importScripts("crypto.js", "db.js", "sync.js");

const MAX_PENDING_QUEUE = 500; // tope de eventos en espera mientras la extensión está bloqueada

async function getSessionKey() {
  const session = await chrome.storage.session.get(["keyB64"]);
  if (!session.keyB64) return null;
  return importKeyB64(session.keyB64);
}

async function queuePending(data) {
  const res = await chrome.storage.local.get(["pendingQueue"]);
  const queue = res.pendingQueue || [];
  queue.push(data);
  while (queue.length > MAX_PENDING_QUEUE) queue.shift();
  await chrome.storage.local.set({ pendingQueue: queue });
  chrome.action.setBadgeBackgroundColor({ color: "#d32f2f" });
  chrome.action.setBadgeText({ text: "!" });
}

async function handleSaveData(data) {
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

chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === "saveData") {
    handleSaveData(request.data);
  } else if (request.action === "manualSync") {
    ShalomRecordarSync.syncNow()
      .then(() => sendResponse({ ok: true }))
      .catch((err) => sendResponse({ error: err.message }));
    return true; // indica que sendResponse será llamado asincronamente
  }
});
