import './popup.css';
import { EXTENSION_VERSION } from '../shared/version';
import { getServiceOrderScheduleState } from '../shared/lima-time';
import { getTokenRequestUrl } from '../models/configuration';

// Derivado de getPlatformApiBaseUrl() para que el dominio viva en un solo
// sitio (models/configuration.ts) y no haya que tocar el popup al migrarlo.
const TOKEN_REQUEST_URL = getTokenRequestUrl();

type StateResponse = {
  success?: boolean;
  configuration?: { tokenMasked?: string | null };
  metadata?: { lastSyncedAt?: string | null; status?: string | null; message?: string | null };
  agencyCount?: number;
};

type ServiceOrderLockResponse = {
  success?: boolean;
  locked?: boolean;
};

type PopupElements = {
  version: HTMLElement;
  tokenState: HTMLElement;
  dot: HTMLElement;
  description: HTMLElement;
  tokenPreview: HTMLElement;
  lastSync: HTMLElement;
  agencyCount: HTMLElement;
  connectionState: HTMLElement;
  primary: HTMLButtonElement;
  configure: HTMLButtonElement;
  requestAnother: HTMLButtonElement;
  options: HTMLButtonElement;
  syncMode: HTMLElement;
  message: HTMLElement;
  lockDescription: HTMLElement;
  lockState: HTMLElement;
  lockCurrent: HTMLElement;
  lockReason: HTMLElement;
  lockAvailable: HTMLElement;
  lockActionLabel: HTMLElement;
  lockToggle: HTMLInputElement;
  lockMessage: HTMLElement;
};

type ConnectionState = { label: string; tone: 'success' | 'warning' | 'missing' | 'error' };

if (typeof document !== 'undefined') {
  void initPopup();
}

async function initPopup(): Promise<void> {
  const elements = getElements();

  elements.version.textContent = 'v' + EXTENSION_VERSION;
  elements.options.addEventListener('click', openOptions);
  elements.configure.addEventListener('click', openOptions);
  elements.primary.addEventListener('click', () => {
    if (elements.primary.dataset.action === 'test') void testConnection(elements);
    else void requestToken();
  });
  elements.requestAnother.addEventListener('click', () => void requestToken());

  chrome.storage.onChanged.addListener(() => {
    void renderState(elements);
    void renderServiceOrderLock(elements);
  });

  await renderState(elements);
  await renderServiceOrderLock(elements);
  elements.lockToggle.addEventListener('change', async () => {
    elements.lockToggle.disabled = true;
    elements.lockMessage.textContent = 'Actualizando bloqueo...';
    try {
      await chrome.runtime.sendMessage({ type: 'SERVICE_ORDER_LOCK_SET', locked: elements.lockToggle.checked });
      elements.lockMessage.textContent = elements.lockToggle.checked ? 'Bloqueo manual activado.' : 'Bloqueo manual desactivado.';
    } catch {
      elements.lockMessage.textContent = 'No fue posible actualizar el bloqueo manual.';
      elements.lockToggle.checked = !elements.lockToggle.checked;
    } finally {
      elements.lockToggle.disabled = false;
      await renderServiceOrderLock(elements);
    }
  });
}

function getElements(): PopupElements {
  return {
    version: requireElement('#extension-version'),
    tokenState: requireElement('#token-state'),
    dot: requireElement('#status-dot'),
    description: requireElement('#description'),
    tokenPreview: requireElement('#token-preview'),
    lastSync: requireElement('#last-sync'),
    agencyCount: requireElement('#agency-count'),
    connectionState: requireElement('#connection-state'),
    primary: requireElement<HTMLButtonElement>('#primary-action'),
    configure: requireElement<HTMLButtonElement>('#configure-token'),
    requestAnother: requireElement<HTMLButtonElement>('#request-another-token'),
    options: requireElement<HTMLButtonElement>('#open-options'),
    syncMode: requireElement('#sync-mode'),
    message: requireElement('#message'),
    lockDescription: requireElement('#service-order-lock-description'),
    lockState: requireElement('#service-order-lock-state'),
    lockCurrent: requireElement('#service-order-lock-current'),
    lockReason: requireElement('#service-order-lock-reason'),
    lockAvailable: requireElement('#service-order-lock-available'),
    lockActionLabel: requireElement('#service-order-lock-action-label'),
    lockToggle: requireElement<HTMLInputElement>('#service-order-lock-toggle'),
    lockMessage: requireElement('#service-order-lock-message'),
  };
}

function requireElement<T extends HTMLElement = HTMLElement>(selector: string): T {
  const element = document.querySelector<T>(selector);
  if (!element) throw new Error('No se encontro el elemento del popup: ' + selector);
  return element;
}

async function renderState(elements: PopupElements): Promise<void> {
  setLoading(elements);

  try {
    const state = await chrome.runtime.sendMessage({ type: 'GET_STATE' }) as StateResponse;
    applyState(elements, state);
  } catch {
    applyReadError(elements);
  }
}

function setLoading(elements: PopupElements): void {
  elements.tokenState.textContent = 'Cargando estado...';
  elements.dot.dataset.tone = 'warning';
  elements.description.textContent = 'Cargando estado...';
  elements.message.textContent = '';
  elements.message.dataset.tone = '';
}

function applyState(elements: PopupElements, state: StateResponse): void {
  const tokenMasked = maskPopupToken(state.configuration?.tokenMasked ?? null);
  const configured = Boolean(tokenMasked);
  const connection = getConnectionState(configured, state.metadata?.status ?? null);

  elements.tokenState.textContent = configured ? 'Token configurado' : 'Token no configurado';
  elements.dot.dataset.tone = configured ? 'success' : 'missing';
  elements.description.textContent = configured
    ? 'La extensión está lista para sincronizar agencias.'
    : 'Solicita un token o configura uno existente.';
  elements.tokenPreview.hidden = !configured;
  elements.tokenPreview.textContent = configured ? tokenMasked : '';
  elements.lastSync.textContent = configured ? formatPopupDate(state.metadata?.lastSyncedAt ?? null) : 'Sin sincronizar';
  elements.agencyCount.textContent = String(configured ? state.agencyCount ?? 0 : 0);
  elements.connectionState.textContent = connection.label;
  elements.connectionState.dataset.tone = connection.tone;
  elements.primary.textContent = configured ? 'Probar conexión' : 'Solicitar token';
  elements.primary.dataset.action = configured ? 'test' : 'request';
  elements.configure.textContent = 'Configurar token';
  elements.requestAnother.hidden = !configured;
  elements.syncMode.textContent = configured ? 'Activada' : 'Pausada';
  elements.syncMode.dataset.tone = configured ? 'success' : 'muted';
  elements.message.textContent = state.metadata?.message ?? '';
  elements.message.dataset.tone = '';
}

function applyReadError(elements: PopupElements): void {
  elements.tokenState.textContent = 'Token no configurado';
  elements.dot.dataset.tone = 'error';
  elements.description.textContent = 'No fue posible leer el estado local.';
  elements.tokenPreview.hidden = true;
  elements.tokenPreview.textContent = '';
  elements.lastSync.textContent = 'Sin sincronizar';
  elements.agencyCount.textContent = '0';
  elements.connectionState.textContent = 'Desconectado';
  elements.connectionState.dataset.tone = 'missing';
  elements.primary.textContent = 'Solicitar token';
  elements.primary.dataset.action = 'request';
  elements.configure.textContent = 'Configurar token';
  elements.requestAnother.hidden = true;
  elements.syncMode.textContent = 'Pausada';
  elements.syncMode.dataset.tone = 'muted';
  elements.message.textContent = 'No fue posible leer el estado local.';
  elements.message.dataset.tone = 'error';
}

async function renderServiceOrderLock(elements: PopupElements): Promise<void> {
  try {
    const response = await chrome.runtime.sendMessage({ type: 'SERVICE_ORDER_LOCK_GET' }) as ServiceOrderLockResponse;
    const manualLocked = Boolean(response.locked);
    const scheduleState = getServiceOrderScheduleState(new Date(), manualLocked);
    const locked = scheduleState.locked;
    elements.lockToggle.checked = manualLocked;
    elements.lockState.textContent = locked ? 'BLOQUEADO' : 'DESBLOQUEADO';
    elements.lockState.dataset.tone = locked ? 'warning' : 'success';
    elements.lockCurrent.textContent = locked ? 'BLOQUEADO' : 'DESBLOQUEADO';
    elements.lockReason.textContent = scheduleState.reason === 'schedule+manual'
      ? 'Horario + bloqueo manual'
      : scheduleState.reason === 'schedule'
        ? 'Fuera del horario permitido'
        : scheduleState.reason === 'manual'
          ? 'Bloqueo manual'
          : 'Funcionamiento normal';
    elements.lockAvailable.textContent = '08:00 h';
    elements.lockDescription.textContent = locked
      ? scheduleState.reason === 'manual'
        ? 'El bloqueo manual está activo para esta pestaña.'
        : scheduleState.reason === 'schedule+manual'
          ? 'La página sigue bloqueada por horario y bloqueo manual.'
          : 'Las operaciones están fuera del horario permitido.'
      : 'El horario automático sigue siendo el único bloqueo fuera de horario.';
    elements.lockActionLabel.textContent = locked ? 'Desbloquear manualmente' : 'Bloquear manualmente';
    elements.lockMessage.textContent = '';
  } catch {
    elements.lockState.textContent = 'DESCONOCIDO';
    elements.lockState.dataset.tone = 'muted';
    elements.lockCurrent.textContent = 'DESCONOCIDO';
    elements.lockReason.textContent = 'No fue posible leer el bloqueo';
    elements.lockDescription.textContent = 'No fue posible leer el bloqueo manual.';
    elements.lockMessage.textContent = 'No fue posible leer el estado del bloqueo.';
  }
}

async function testConnection(elements: PopupElements): Promise<void> {
  elements.primary.disabled = true;
  elements.message.textContent = 'Probando conexión...';
  elements.message.dataset.tone = '';

  try {
    const response = await chrome.runtime.sendMessage({ type: 'API_TEST_CONNECTION' }) as { success?: boolean; message?: string };
    elements.message.textContent = response.message ?? (response.success ? 'Conexión validada.' : 'No fue posible validar la conexión.');
    elements.message.dataset.tone = response.success ? 'success' : 'error';
  } catch {
    elements.message.textContent = 'No fue posible validar la conexión.';
    elements.message.dataset.tone = 'error';
  } finally {
    elements.primary.disabled = false;
    await renderState(elements);
  }
}

async function requestToken(): Promise<void> {
  await chrome.tabs.create({ url: TOKEN_REQUEST_URL });
}

function openOptions(): void {
  chrome.runtime.openOptionsPage();
}

export function maskPopupToken(value: string | null | undefined): string | null {
  const token = value?.trim();
  if (!token) return null;
  if (token.includes('•')) return token;
  if (token.length <= 8) return '•'.repeat(token.length);
  return token.slice(0, 4) + '•'.repeat(Math.max(4, token.length - 8)) + token.slice(-4);
}

export function formatPopupDate(value: string | null | undefined): string {
  if (!value) return 'Sin sincronizar';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Sin sincronizar';
  return date.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' });
}

export function getConnectionState(configured: boolean, status: string | null | undefined): ConnectionState {
  if (!configured) return { label: 'Desconectado', tone: 'missing' };
  if (status === 'synchronized' || status === 'updated' || status === 'unchanged') return { label: 'Sincronizado', tone: 'success' };
  if (status === 'updating') return { label: 'Actualizando', tone: 'warning' };
  if (status === 'unauthorized' || status === 'forbidden') return { label: 'Revisar token', tone: 'error' };
  if (status === 'error' || status === 'token_expired') return { label: 'Con error', tone: 'error' };
  return { label: 'Conectado', tone: 'success' };
}
