import './popup.css';

type StateResponse = {
  configuration?: { tokenMasked?: string | null };
  metadata?: { lastSyncedAt?: string | null; catalogRevision?: string | null; message?: string | null; status?: string | null };
  agencyCount?: number;
};

const elements = {
  summary: document.querySelector<HTMLElement>('#summary')!,
  state: document.querySelector<HTMLElement>('#token-state')!,
  dot: document.querySelector<HTMLElement>('#status-dot')!,
  help: document.querySelector<HTMLElement>('#token-help')!,
  masked: document.querySelector<HTMLElement>('#token-masked')!,
  updated: document.querySelector<HTMLElement>('#updated')!,
  count: document.querySelector<HTMLElement>('#agency-count')!,
  version: document.querySelector<HTMLElement>('#catalog-version')!,
  primary: document.querySelector<HTMLButtonElement>('#primary-action')!,
  request: document.querySelector<HTMLButtonElement>('#request-token')!,
  configure: document.querySelector<HTMLButtonElement>('#configure-token')!,
  options: document.querySelector<HTMLButtonElement>('#options')!,
  message: document.querySelector<HTMLElement>('#message')!,
};

void init();

async function init(): Promise<void> {
  elements.options.addEventListener('click', openOptions);
  elements.configure.addEventListener('click', openOptions);
  elements.request.addEventListener('click', requestToken);
  elements.primary.addEventListener('click', () => {
    if (elements.primary.dataset.action === 'test') void testConnection();
    else void requestToken();
  });

  await renderState();
}

async function renderState(): Promise<void> {
  const state = await chrome.runtime.sendMessage({ type: 'GET_STATE' }) as StateResponse;
  const configured = Boolean(state.configuration?.tokenMasked);
  elements.summary.textContent = configured ? 'Conectado' : 'Sin configurar';
  elements.state.textContent = configured ? 'Token configurado' : 'Token no configurado';
  elements.dot.dataset.state = configured ? 'ok' : 'missing';
  elements.help.textContent = configured ? 'La extensión puede sincronizar el catálogo de CodeRED Platform.' : 'Solicita un token de CodeRED Platform o configura uno existente.';
  elements.masked.textContent = state.configuration?.tokenMasked ?? '-';
  elements.updated.textContent = state.metadata?.lastSyncedAt ? new Date(state.metadata.lastSyncedAt).toLocaleString('es-PE') : 'Sin sincronizar';
  elements.count.textContent = String(state.agencyCount ?? 0);
  elements.version.textContent = state.metadata?.catalogRevision ?? '-';
  elements.primary.textContent = configured ? 'Probar conexión' : 'Solicitar token';
  elements.primary.dataset.action = configured ? 'test' : 'request';
  elements.configure.textContent = configured ? 'Administrar token' : 'Configurar token';
  elements.request.hidden = !configured;
  elements.message.textContent = state.metadata?.message ?? '';
}

async function testConnection(): Promise<void> {
  elements.primary.disabled = true;
  elements.message.textContent = 'Probando conexión...';
  try {
    const response = await chrome.runtime.sendMessage({ type: 'API_TEST_CONNECTION' });
    elements.message.textContent = response.message ?? (response.success ? 'Conexión validada.' : 'No fue posible validar la conexión.');
    elements.message.dataset.tone = response.success ? 'success' : 'error';
  } finally {
    elements.primary.disabled = false;
    await renderState();
  }
}

async function requestToken(): Promise<void> {
  await chrome.tabs.create({ url: 'https://platform.codered.host/solicitar-token?source=shalom-extension&installation_name=Buscador%20Shalom%20Control' });
}

function openOptions(): void {
  chrome.runtime.openOptionsPage();
}
