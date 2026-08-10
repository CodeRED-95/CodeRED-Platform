const vistaSetup = document.getElementById('vistaSetup');
const vistaUnlock = document.getElementById('vistaUnlock');
const vistaApp = document.getElementById('vistaApp');
const cuerpoTabla = document.getElementById('cuerpoTabla');
const inputUsuario = document.getElementById('inputUsuario');
const inputApiToken = document.getElementById('inputApiToken');
const syncStatus = document.getElementById('syncStatus');

let currentKey = null; // CryptoKey, solo en memoria de este popup

function mostrar(vista) {
    [vistaSetup, vistaUnlock, vistaApp].forEach(v => v.classList.add('hidden'));
    vista.classList.remove('hidden');
}

// --- Migración: mueve el historial viejo en chrome.storage.local (texto plano) a IndexedDB cifrado ---
async function migrarHistorialAntiguo() {
    const res = await chrome.storage.local.get(['historial']);
    if (!res.historial || res.historial.length === 0) return;
    for (const item of res.historial) {
        const encryptedValue = await encryptText(currentKey, item.value);
        await addRecord({ timestamp: item.timestamp, field: item.field, value: encryptedValue });
    }
    await chrome.storage.local.remove(['historial']);
}

// --- Vacía la cola de eventos que quedaron pendientes mientras estaba bloqueado ---
async function vaciarColaPendiente() {
    const res = await chrome.storage.local.get(['pendingQueue']);
    const queue = res.pendingQueue || [];
    for (const data of queue) {
        const encryptedValue = await encryptText(currentKey, data.value);
        await addRecord({ timestamp: data.timestamp, field: data.field, value: encryptedValue });
    }
    await chrome.storage.local.remove(['pendingQueue']);
    chrome.action.setBadgeText({ text: '' });
}

async function desbloquearListo() {
    await migrarHistorialAntiguo();
    await vaciarColaPendiente();
    mostrar(vistaApp);
    cargarDatos();
}

async function init() {
    const local = await chrome.storage.local.get(['saltB64', 'checkValue']);
    if (!local.saltB64) {
        mostrar(vistaSetup);
        return;
    }
    const session = await chrome.storage.session.get(['keyB64']);
    if (session.keyB64) {
        currentKey = await importKeyB64(session.keyB64);
        await desbloquearListo();
    } else {
        mostrar(vistaUnlock);
    }
}

document.getElementById('btnSetup').addEventListener('click', async () => {
    const p1 = document.getElementById('setupPass1').value;
    const p2 = document.getElementById('setupPass2').value;
    const err = document.getElementById('msgErrorSetup');
    if (p1.length < 6) { err.textContent = 'Usa al menos 6 caracteres.'; return; }
    if (p1 !== p2) { err.textContent = 'Las contraseñas no coinciden.'; return; }
    err.textContent = '';

    const saltB64 = generateSaltB64();
    const key = await deriveKey(p1, saltB64);
    const checkValue = await makeCheckValue(key);
    const keyB64 = await exportKeyB64(key);

    await chrome.storage.local.set({ saltB64, checkValue });
    await chrome.storage.session.set({ keyB64 });
    currentKey = key;
    await desbloquearListo();
});

document.getElementById('btnUnlock').addEventListener('click', async () => {
    const pass = document.getElementById('unlockPass').value;
    const err = document.getElementById('msgErrorUnlock');
    const local = await chrome.storage.local.get(['saltB64', 'checkValue']);
    const key = await deriveKey(pass, local.saltB64);

    const ok = await verifyCheckValue(key, local.checkValue);
    if (!ok) { err.textContent = 'Contraseña incorrecta.'; return; }
    err.textContent = '';

    const keyB64 = await exportKeyB64(key);
    await chrome.storage.session.set({ keyB64 });
    currentKey = key;
    await desbloquearListo();
});

document.getElementById('btnLock').addEventListener('click', async () => {
    await chrome.storage.session.remove(['keyB64']);
    currentKey = null;
    document.getElementById('unlockPass').value = '';
    mostrar(vistaUnlock);
});

async function procesarHistorial(historialRaw) {
    const processed = [];
    for (const item of historialRaw) {
        let campo = item.field;
        let valor;
        try {
            valor = (await decryptText(currentKey, item.value)).trim();
        } catch (e) {
            continue; // registro corrupto o de otra clave: se omite
        }

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
            processed.push({ timestamp: item.timestamp, field: campo, value: valor });
        }
    }
    return processed;
}

async function cargarDatos() {
    cuerpoTabla.innerHTML = '';
    const historial = await getAllRecords();
    const datosProcesados = await procesarHistorial(historial);
    datosProcesados.reverse().forEach(item => {
        const row = cuerpoTabla.insertRow();
        row.insertCell(0).textContent = new Date(item.timestamp).toLocaleString();
        row.insertCell(1).textContent = item.field;
        row.insertCell(2).textContent = item.value;
    });
}

document.getElementById('btnExportar').addEventListener('click', async () => {
    const historial = await getAllRecords();
    const data = await procesarHistorial(historial);

    let htmlTable = `
        <table border="1">
            <thead>
                <tr>
                    <th style="background-color: #cccccc;">Fecha y Hora</th>
                    <th style="background-color: #cccccc;">Campo</th>
                    <th style="background-color: #cccccc;">Valor</th>
                </tr>
            </thead>
            <tbody>
    `;

    data.forEach(r => {
        htmlTable += `<tr>
            <td>${new Date(r.timestamp).toLocaleString()}</td>
            <td>${r.field}</td>
            <td>${r.value}</td>
        </tr>`;
    });

    htmlTable += `</tbody></table>`;

    const blob = new Blob([htmlTable], { type: 'application/vnd.ms-excel' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "reporte_shalom.xls";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});

// --- Manejo de usuario ---
inputUsuario.addEventListener('change', async () => {
    const username = inputUsuario.value.trim();
    if (username.length > 0) {
        await chrome.storage.local.set({ username });
    }
});

inputApiToken.addEventListener('change', async () => {
    const apiToken = inputApiToken.value.trim();
    if (apiToken.length > 0) {
        await chrome.storage.local.set({ apiToken });
    }
});

async function cargarUsuario() {
    const res = await chrome.storage.local.get(['username', 'apiToken']);
    if (res.username) {
        inputUsuario.value = res.username;
    }
    if (res.apiToken) {
        inputApiToken.value = res.apiToken;
    }
}

// --- Sincronización manual ---
document.getElementById('btnSync').addEventListener('click', async () => {
    const btn = document.getElementById('btnSync');
    const originalText = btn.textContent;
    btn.textContent = 'Sincronizando...';
    btn.disabled = true;

    try {
        await chrome.runtime.sendMessage({ action: 'manualSync' });
        syncStatus.textContent = '✓ Sincronización enviada';
        syncStatus.style.color = '#4caf50';
        setTimeout(() => {
            syncStatus.textContent = '';
        }, 3000);
    } catch (e) {
        syncStatus.textContent = '✗ Error en sincronización';
        syncStatus.style.color = '#ff6b6b';
        console.error('Manual sync failed:', e);
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
});

async function mostrarUltimoSync() {
    const res = await chrome.storage.local.get(['syncLog']);
    const log = res.syncLog || [];
    if (log.length === 0) return;

    const ultimo = log[log.length - 1];
    if (ultimo.type === 'success') {
        syncStatus.textContent = `Última sync: ${new Date(ultimo.timestamp).toLocaleString()} (${ultimo.recordCount} registros)`;
        syncStatus.style.color = '#999';
    } else {
        syncStatus.textContent = `Error en última sync: ${ultimo.error}`;
        syncStatus.style.color = '#ff9999';
    }
}

document.getElementById('btnRefrescar').addEventListener('click', cargarDatos);
document.addEventListener('DOMContentLoaded', async () => {
    await init();
    await cargarUsuario();
    await mostrarUltimoSync();
});
