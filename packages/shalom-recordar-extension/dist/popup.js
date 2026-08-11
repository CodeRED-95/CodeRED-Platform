const el = (id) => document.getElementById(id);

const loadingView = el('loadingView');
const loginView = el('loginView');
const sessionView = el('sessionView');
const statusEl = el('status');
const tagline = el('tagline');
const avatarBtn = el('btnAvatar');
const accountMenu = el('accountMenu');

function sendMessage(request) {
    return new Promise((resolve, reject) => {
        chrome.runtime.sendMessage(request, (response) => {
            const error = chrome.runtime.lastError;
            if (error) {
                reject(new Error(error.message));
                return;
            }
            resolve(response);
        });
    });
}

/** Última sesión conocida; evita releer storage en cada interacción del menú. */
function setStatus(message, tone = 'muted') {
    statusEl.textContent = message ?? '';
    statusEl.style.color = tone === 'error' ? '#fca5a5' : tone === 'success' ? '#86efac' : tone === 'warn' ? '#fbbf24' : '#9ca3af';
}

function initials(state) {
    const source = state?.user?.name || state?.user?.email || '';
    const trimmed = source.trim();
    return trimmed ? trimmed[0].toUpperCase() : '?';
}

function formatDate(value) {
    if (!value) return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date.toLocaleString();
}

function formatPeruDate(value) {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;

    return new Intl.DateTimeFormat('es-PE', {
        timeZone: 'America/Lima',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(date).replace(',', '');
}

function escapeHtml(text) {
    return String(text ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

/** Pinta los últimos registros locales; más reciente primero, con scroll interno. */
async function renderRecentRecords() {
    const list = el('recordsList');
    const meta = el('recordsMeta');
    if (!list) return;

    let records = [];
    try {
        const stored = await chrome.storage.local.get(['pendingQueue']);
        const queue = Array.isArray(stored.pendingQueue) ? stored.pendingQueue : [];
        records = queue
            .map((record) => ({
                field: String(record?.field ?? '').trim() || 'sin_nombre',
                value: typeof record?.value === 'string' ? record.value : '',
                timestamp: String(record?.timestamp ?? ''),
            }))
            .sort((a, b) => (b.timestamp || '').localeCompare(a.timestamp || ''))
            .slice(0, 20);
        meta.dataset.total = String(queue.length);
    } catch {
        records = [];
        meta.dataset.total = '0';
    }

    if (records.length === 0) {
        list.innerHTML = '<div class="records-empty">No hay registros todavía.</div>';
        meta.textContent = '';
        return;
    }

    meta.textContent = `${records.length}/${meta.dataset.total || records.length}`;
    list.innerHTML = records.map((record) => {
        const when = formatDate(record.timestamp) || 'Sin fecha';
        return `<div class="rec" role="listitem">`
            + `<span class="rec-field">${escapeHtml(record.field)}</span>`
            + `<span class="rec-time">${escapeHtml(when)}</span>`
            + `<span class="rec-value">${escapeHtml(record.value)}</span>`
            + `</div>`;
    }).join('');
}

function closeMenu() {
    accountMenu.classList.add('hidden');
    avatarBtn.setAttribute('aria-expanded', 'false');
}

function toggleMenu() {
    const willOpen = accountMenu.classList.contains('hidden');
    accountMenu.classList.toggle('hidden', !willOpen);
    avatarBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
}

/** Solo se muestra una vista a la vez: cargando, login o sesión. */
function showView(name) {
    loadingView.classList.toggle('hidden', name !== 'loading');
    loginView.classList.toggle('hidden', name !== 'login');
    sessionView.classList.toggle('hidden', name !== 'session');

    const authenticated = name === 'session';
    avatarBtn.classList.toggle('hidden', !authenticated);
    if (!authenticated) {
        closeMenu();
    }
}

function showLoading() {
    showView('loading');
    setStatus('Validando sesión guardada...');
}

function showLoggedOut(message, tone = 'muted') {
    showView('login');
    tagline.textContent = 'Inicia sesión con tu cuenta de CodeRED Platform. No se guarda tu contraseña.';
    setStatus(message ?? 'Inicia sesión para sincronizar tu extensión.', tone);
}

function showLoggedIn(state) {
    showView('session');

    const name = state.user?.name || 'Usuario autenticado';
    const email = state.user?.email || '';

    tagline.textContent = 'Sesión activa en CodeRED Platform.';
    el('userName').textContent = name;
    el('userEmail').textContent = email;
    avatarBtn.textContent = initials(state);

    el('menuName').textContent = name;
    el('menuEmail').textContent = email;

    // "Degradado" = hay token válido guardado pero la plataforma no respondió.
    const degraded = Boolean(state.degraded);
    el('connDot').className = degraded ? 'dot warn' : 'dot';
    el('connText').textContent = degraded ? 'Sin conexión con la plataforma' : 'Conectado';
    el('menuState').textContent = degraded ? 'Sin conexión' : 'Conectado';

    const lastSync = formatDate(state.server?.last_synced_at) || formatDate(state.meta?.lastSyncAt);
    el('lastSync').textContent = lastSync ?? 'Aún no hay sincronizaciones';

    const lastAutomaticSync = formatPeruDate(state.automatic_sync?.lastAutomaticSyncAt);
    el('lastAutomaticSync').textContent = lastAutomaticSync || 'Aún no hay sincronización automática';
    const nextAutomaticSync = formatPeruDate(state.automatic_sync?.nextAutomaticSyncAt);
    el('nextAutomaticSync').textContent = nextAutomaticSync || '—';

    // "Registros locales": lo que hay en el navegador pendiente/guardado. El
    // conteo del servidor se muestra aparte solo si difiere sería confuso, así
    // que aquí prima lo local, que es lo que lista el popup debajo.
    renderRecentRecords().then(() => {
        const total = Number(el('recordsMeta').dataset.total ?? '0');
        el('recordCount').textContent = String(Number.isFinite(total) ? total : 0);
    });
}

async function refreshState({ silent = false } = {}) {
    if (!silent) {
        showLoading();
    }

    try {
        const state = await globalThis.ShalomRecordarSync.getSessionState();

        if (state.authenticated) {
            showLoggedIn(state);
            setStatus(state.degraded ? (state.error?.message ?? 'No se pudo contactar con la plataforma.') : 'Sesión activa', state.degraded ? 'warn' : 'success');
            if (state.automatic_sync?.automaticSyncAvailable && !state.automatic_sync?.automaticSyncDoneToday) {
                try {
                    const auto = await sendMessage({ action: 'checkAutomaticSync', reason: 'popup' });
                    if (auto?.ok && auto.result?.ok && !auto.result?.skipped) {
                        await refreshState({ silent: true });
                        setStatus('Sincronización automática completada.', 'success');
                    }
                } catch {
                    // Silencioso: si el worker no responde, el usuario sigue
                    // pudiendo sincronizar manualmente.
                }
            }
            return state;
        }

        showLoggedOut(state.error?.message, state.reason === 'session-revoked' ? 'error' : 'muted');
        return state;
    } catch (error) {
        showLoggedOut(error instanceof Error ? error.message : 'No se pudo cargar la sesión.', 'error');
        return null;
    }
}

async function withButton(button, busyLabel, action) {
    const original = button.textContent;
    button.disabled = true;
    button.textContent = busyLabel;
    try {
        return await action();
    } finally {
        button.disabled = false;
        button.textContent = original;
    }
}

async function doSync(button) {
    return withButton(button, 'Sincronizando...', async () => {
        const response = await sendMessage({ action: 'manualSync' });
        const result = response?.result || { ok: false, message: response?.error || 'No se pudo sincronizar.' };

        if (!result.ok) {
            if (result.reason === 'session-revoked') {
                await refreshState({ silent: true });
                setStatus(result.message, 'error');
                return;
            }
            setStatus(result.message || 'No se pudo sincronizar.', 'error');
            return;
        }

        await refreshState({ silent: true });
        setStatus(result.synced > 0 ? `Sincronización completada: ${result.synced} registro(s).` : (result.message || 'Sin registros nuevos.'), 'success');
    });
}

el('btnLogin').addEventListener('click', async () => {
    const email = el('email').value.trim();
    const password = el('password').value;
    const btn = el('btnLogin');

    if (!email || !password) {
        setStatus('Introduce correo y contraseña.', 'error');
        return;
    }

    await withButton(btn, 'Validando...', async () => {
        try {
            const result = await globalThis.ShalomRecordarSync.login({ email, password });
            if (!result.ok) {
                setStatus(result.message || 'No se pudo iniciar sesión.', 'error');
                return;
            }

            // El campo de contraseña se vacía en cuanto deja de hacer falta.
            el('password').value = '';

            // Se pinta de inmediato con lo devuelto por el login para que la
            // vista cambie sin esperar a la validación de estado.
            showLoggedIn({ user: result.user, server: result.data ?? null, meta: {} });
            setStatus('Sesión iniciada correctamente.', 'success');

            await refreshState({ silent: true });
        } catch (error) {
            setStatus(error instanceof Error ? error.message : 'No se pudo iniciar sesión.', 'error');
        }
    });
});

el('btnSync').addEventListener('click', () => doSync(el('btnSync')));
el('btnRefresh').addEventListener('click', () => refreshState());

el('btnExport').addEventListener('click', async () => {
    await withButton(el('btnExport'), 'Exportando...', async () => {
        try {
            const payload = await globalThis.ShalomRecordarSync.buildExportPayload();
            const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `shalom-recordar-${new Date().toISOString().slice(0, 10)}.json`;
            link.click();
            URL.revokeObjectURL(url);
            setStatus(`Exportados ${payload.records.length + payload.pending.length} registro(s).`, 'success');
        } catch (error) {
            setStatus(error instanceof Error ? error.message : 'No se pudo exportar.', 'error');
        }
    });
});

avatarBtn.addEventListener('click', (event) => {
    event.stopPropagation();
    toggleMenu();
});

document.addEventListener('click', (event) => {
    if (!accountMenu.classList.contains('hidden') && !accountMenu.contains(event.target) && event.target !== avatarBtn) {
        closeMenu();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeMenu();
    }
});

el('menuSync').addEventListener('click', async () => {
    closeMenu();
    await doSync(el('btnSync'));
});

el('menuAccount').addEventListener('click', () => {
    closeMenu();
    chrome.tabs.create({ url: globalThis.ShalomRecordarSync.PLATFORM_ACCOUNT_URL });
});

el('menuLogout').addEventListener('click', async () => {
    closeMenu();
    await withButton(el('menuLogout'), 'Cerrando...', async () => {
        // Cerrar sesión no borra el historial local: solo revoca credenciales.
        const result = await globalThis.ShalomRecordarSync.logout();
        showLoggedOut(result.revoked ? 'Sesión cerrada y token revocado.' : 'Sesión cerrada en este navegador.');
    });
});

document.addEventListener('DOMContentLoaded', () => refreshState());
