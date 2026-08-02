import './popup.css';
import { EXTENSION_VERSION } from '../shared/version';

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
  extensionVersion: document.querySelector<HTMLElement>('#extension-version')!,
  footerVersion: document.querySelector<HTMLElement>('#footer-version')!,
  service: document.querySelector<HTMLElement>('#service-state')!,
  syncMode: document.querySelector<HTMLElement>('#sync-mode')!,
  primary: document.querySelector<HTMLButtonElement>('#primary-action')!,
  request: document.querySelector<HTMLButtonElement>('#request-token')!,
  requestCard: document.querySelector<HTMLButtonElement>('#request-token-card')!,
  configure: document.querySelector<HTMLButtonElement>('#configure-token')!,
  configureCard: document.querySelector<HTMLButtonElement>('#configure-token-card')!,
  options: document.querySelector<HTMLButtonElement>('#options')!,
  close: document.querySelector<HTMLButtonElement>('#close-popup')!,
  learnMore: document.querySelector<HTMLButtonElement>('#learn-more')!,
  message: document.querySelector<HTMLElement>('#message')!,
};

void init();

async function init(): Promise<void> {
  elements.extensionVersion.textContent = `v${EXTENSION_VERSION}`;
  elements.footerVersion.textContent = EXTENSION_VERSION;
  elements.options.addEventListener('click', openOptions);
  elements.close.addEventListener('click', () => window.close());
  elements.learnMore.addEventListener('click', openHelp);
  elements.configure.addEventListener('click', openOptions);
  elements.configureCard.addEventListener('click', openOptions);
  elements.request.addEventListener('click', requestToken);
  elements.requestCard.addEventListener('click', requestToken);
  elements.primary.addEventListener('click', () => {
    if (elements.primary.dataset.action === 'test') void testConnection();
    else void requestToken();
  });

  await renderState();
}

async function renderState(): Promise<void> {
  const state = await chrome.runtime.sendMessage({ type: 'GET_STATE' }) as StateResponse;
  const configured = Boolean(state.configuration?.tokenMasked);
  const serviceState = getServiceState(configured, state.metadata?.status);

  elements.summary.textContent = configured ? 'Conectado' : 'Token no configurado';
  elements.state.textContent = configured ? 'Token configurado' : 'Token no configurado';
  elements.dot.dataset.state = configured ? 'ok' : 'missing';
  elements.help.textContent = configured
    ? 'La extensión puede sincronizar el catálogo de CodeRED Platform.'
    : 'Solicita un token de CodeRED Platform o configura uno existente.';
  elements.masked.textContent = state.configuration?.tokenMasked ?? '-';
  elements.updated.textContent = state.metadata?.lastSyncedAt ? new Date(state.metadata.lastSyncedAt).toLocaleString('es-PE') : 'Sin sincronizar';
  elements.count.textContent = String(state.agencyCount ?? 0);
  elements.version.textContent = state.metadata?.catalogRevision ?? '-';
  elements.service.textContent = serviceState.label;
  elements.service.dataset.tone = serviceState.tone;
  elements.syncMode.textContent = configured ? 'Activada' : 'Pausada';
  elements.syncMode.dataset.tone = configured ? 'success' : 'muted';
  elements.primary.textContent = configured ? 'Probar conexión' : 'Solicitar token';
  elements.primary.dataset.action = configured ? 'test' : 'request';
  elements.configure.textContent = configured ? 'Administrar token' : 'Configurar token';
  elements.request.hidden = !configured;
  elements.message.textContent = state.metadata?.message ?? '';
  elements.message.dataset.tone = '';
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
  await chrome.tabs.create({ url: `https://platform.codered.host/solicitar-token?source=shalom-extension&installation_name=Buscador%20Shalom%20Control&version=${encodeURIComponent(EXTENSION_VERSION)}` });
}

function openOptions(): void {
  chrome.runtime.openOptionsPage();
}

async function openHelp(): Promise<void> {
  await chrome.tabs.create({ url: 'https://platform.codered.host/solicitar-token?source=shalom-extension-help' });
}

function getServiceState(configured: boolean, status?: string | null): { label: string; tone: string } {
  if (!configured) return { label: 'Desconectado', tone: 'missing' };
  if (status === 'synchronized') return { label: 'Sincronizado', tone: 'success' };
  if (status === 'updating') return { label: 'Actualizando', tone: 'warning' };
  if (status === 'unauthorized' || status === 'forbidden') return { label: 'Revisar token', tone: 'error' };
  if (status === 'error') return { label: 'Con error', tone: 'error' };

  return { label: 'Conectado', tone: 'success' };
}
