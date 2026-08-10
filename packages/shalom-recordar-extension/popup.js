const loginView = document.getElementById('loginView');
const sessionView = document.getElementById('sessionView');
const statusEl = document.getElementById('status');
const userName = document.getElementById('userName');
const userEmail = document.getElementById('userEmail');
const lastSync = document.getElementById('lastSync');

function setStatus(message, tone = 'muted') {
    statusEl.textContent = message;
    statusEl.style.color = tone === 'error' ? '#fca5a5' : tone === 'success' ? '#86efac' : '#9ca3af';
}

function showLoggedOut() {
    loginView.classList.remove('hidden');
    sessionView.classList.add('hidden');
}

function showLoggedIn(state) {
    loginView.classList.add('hidden');
    sessionView.classList.remove('hidden');
    userName.textContent = state.user?.name || 'Usuario autenticado';
    userEmail.textContent = state.user?.email || '';
    lastSync.textContent = state.server?.last_synced_at
        ? `Última sincronización: ${new Date(state.server.last_synced_at).toLocaleString()}`
        : 'Aún no hay sincronizaciones';
}

async function refreshState() {
    const state = await globalThis.ShalomRecordarSync.getSessionState();
    if (state.authenticated) {
        showLoggedIn(state);
        setStatus('Sesión activa', 'success');
    } else {
        showLoggedOut();
        setStatus('Inicia sesión para sincronizar tu extensión.');
    }
}

document.getElementById('btnLogin').addEventListener('click', async () => {
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const btn = document.getElementById('btnLogin');
    btn.disabled = true;
    btn.textContent = 'Validando...';
    try {
        const result = await globalThis.ShalomRecordarSync.login({ email, password });
        if (!result.ok) {
            setStatus(result.message || 'No se pudo iniciar sesión.', 'error');
            return;
        }
        await refreshState();
        setStatus('Sesión iniciada correctamente.', 'success');
    } catch (error) {
        setStatus(error instanceof Error ? error.message : 'No se pudo iniciar sesión.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Iniciar sesión';
    }
});

document.getElementById('btnSync').addEventListener('click', async () => {
    const btn = document.getElementById('btnSync');
    btn.disabled = true;
    btn.textContent = 'Sincronizando...';
    try {
        const result = await globalThis.ShalomRecordarSync.syncNow();
        if (!result.ok) {
            setStatus(result.message || 'No se pudo sincronizar.', 'error');
            return;
        }
        await refreshState();
        setStatus(result.message || 'Sincronización completada.', 'success');
    } catch (error) {
        setStatus(error instanceof Error ? error.message : 'No se pudo sincronizar.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Sincronizar ahora';
    }
});

document.getElementById('btnRefresh').addEventListener('click', refreshState);

document.getElementById('btnLogout').addEventListener('click', async () => {
    await globalThis.ShalomRecordarSync.logout();
    showLoggedOut();
    setStatus('Sesión cerrada.');
});

document.addEventListener('DOMContentLoaded', refreshState);
