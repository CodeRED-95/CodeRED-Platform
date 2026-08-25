import './popup.css';
import { EXTENSION_VERSION } from '../shared/version';
import { DEFAULT_BLOCK_RULE, evaluateRule, parseBlockRuleSet, type BlockRule } from '../shared/block-rules';
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
  forcedUnlock?: {
    active: boolean;
    createdAt: string;
    expiresAt: string;
    restrictedPeriodId: string;
  } | null;
  rule?: BlockRule | null;
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
  lockTitle: HTMLElement;
  lockScheduleTitle: HTMLElement;
  lockSchedule: HTMLElement;
  lockDescription: HTMLElement;
  lockState: HTMLElement;
  lockCurrent: HTMLElement;
  lockReason: HTMLElement;
  lockAvailable: HTMLElement;
  lockActionLabel: HTMLElement;
  lockStatus: HTMLElement;
  lockToggle: HTMLInputElement;
  lockMessage: HTMLElement;
  forceAction: HTMLButtonElement;
  forceModal: HTMLElement;
  forceConfirmation: HTMLInputElement;
  forceAck: HTMLInputElement;
  forceCancel: HTMLButtonElement;
  forceConfirm: HTMLButtonElement;
  forceFeedback: HTMLElement;
};

type ConnectionState = { label: string; tone: 'success' | 'warning' | 'missing' | 'error' };

let serviceOrderLockTimer: number | null = null;

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
  wireForcedUnlockModal(elements);

  chrome.storage.onChanged.addListener(() => {
    void renderState(elements);
    void renderServiceOrderLock(elements);
  });

  await renderState(elements);
  await renderServiceOrderLock(elements);
  if (serviceOrderLockTimer !== null) window.clearInterval(serviceOrderLockTimer);
  serviceOrderLockTimer = window.setInterval(() => {
    void renderServiceOrderLock(elements);
  }, 1000);

  window.addEventListener('unload', () => {
    if (serviceOrderLockTimer !== null) window.clearInterval(serviceOrderLockTimer);
    serviceOrderLockTimer = null;
  }, { once: true });

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

  elements.forceAction.addEventListener('click', async () => {
    const state = await readServiceOrderLockState();
    if (state?.forcedUnlock?.active) {
      if (window.confirm('¿Deseas finalizar el desbloqueo forzoso?')) {
        await chrome.runtime.sendMessage({ type: 'SERVICE_ORDER_FORCED_UNLOCK_SET', active: false });
        await renderServiceOrderLock(elements);
      }
      return;
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
    lockTitle: requireElement('#service-order-lock-title'),
    lockScheduleTitle: requireElement('#service-order-lock-schedule-title'),
    lockSchedule: requireElement('#service-order-lock-schedule'),
    lockDescription: requireElement('#service-order-lock-description'),
    lockState: requireElement('#service-order-lock-state'),
    lockCurrent: requireElement('#service-order-lock-current'),
    lockReason: requireElement('#service-order-lock-reason'),
    lockAvailable: requireElement('#service-order-lock-available'),
    lockActionLabel: requireElement('#service-order-lock-action-label'),
    lockStatus: requireElement('#service-order-lock-status'),
    lockToggle: requireElement<HTMLInputElement>('#service-order-lock-toggle'),
    lockMessage: requireElement('#service-order-lock-message'),
    forceAction: requireElement<HTMLButtonElement>('#service-order-force-action'),
    forceModal: requireElement('#forced-unlock-modal'),
    forceConfirmation: requireElement<HTMLInputElement>('#forced-unlock-confirmation'),
    forceAck: requireElement<HTMLInputElement>('#forced-unlock-ack'),
    forceCancel: requireElement<HTMLButtonElement>('#forced-unlock-cancel'),
    forceConfirm: requireElement<HTMLButtonElement>('#forced-unlock-confirm'),
    forceFeedback: requireElement('#forced-unlock-feedback'),
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
    const response = await readServiceOrderLockState();
    const manualLocked = Boolean(response?.locked);
    const forcedUnlock = response?.forcedUnlock?.active ? response.forcedUnlock : null;
    const rule = response?.rule ?? DEFAULT_BLOCK_RULE;
    const evaluation = evaluateRule(rule, new Date());
    const scheduleLocked = evaluation.blockedBySchedule;
    const forcedUnlockActive = Boolean(forcedUnlock && scheduleLocked);
    const locked = manualLocked || (scheduleLocked && !forcedUnlockActive);
    const nextChange = evaluation.nextChangeAt ? formatServiceOrderNextChange(evaluation.nextChangeAt, rule.timezone) : 'Sin cambios programados';
    elements.lockTitle.textContent = `Control de ${rule.label}`;
    elements.lockScheduleTitle.textContent = rule.windowMode === 'allowed' ? 'Horario permitido' : 'Horario bloqueado';
    elements.lockSchedule.textContent = evaluation.scheduleLabel;
    elements.lockToggle.checked = manualLocked;
    elements.lockState.textContent = manualLocked
      ? 'BLOQUEADO MANUALMENTE'
      : forcedUnlockActive
        ? 'DESBLOQUEADO (FORZOSO)'
        : locked
          ? 'BLOQUEADO'
          : 'DESBLOQUEADO';
    elements.lockState.dataset.tone = manualLocked || locked ? 'warning' : forcedUnlockActive ? 'warning' : 'success';
    elements.lockCurrent.textContent = manualLocked
      ? 'Bloqueado manualmente.'
      : forcedUnlockActive
        ? 'Operando fuera del horario permitido.'
        : locked
          ? 'Bloqueado por horario.'
          : 'Todo funciona normalmente.';
    elements.lockReason.textContent = manualLocked
      ? 'Bloqueo manual'
      : forcedUnlockActive
        ? 'Excepción manual fuera del horario'
        : scheduleLocked
          ? rule.windowMode === 'allowed'
            ? 'Fuera del horario permitido'
            : 'Dentro del horario bloqueado'
          : 'Funcionamiento normal';
    elements.lockAvailable.textContent = nextChange;
    elements.lockDescription.textContent = `El horario lo define CodeRED Platform (${rule.timezone}).`;
    elements.lockStatus.dataset.tone = manualLocked || locked || forcedUnlockActive ? 'warning' : 'success';
    elements.lockStatus.querySelector('p')!.textContent = locked
      ? manualLocked
        ? 'Bloqueo manual activo.'
        : 'Bloqueado por horario.'
      : forcedUnlockActive
        ? 'Excepción de horario activa.'
      : 'Todo funciona normalmente.';
    elements.lockActionLabel.textContent = locked ? 'Desbloquear manualmente' : 'Bloquear manualmente';
    elements.forceAction.textContent = forcedUnlockActive ? 'Finalizar desbloqueo forzoso' : 'Desbloquear forzosamente';
    elements.forceAction.hidden = manualLocked || (!scheduleLocked && !forcedUnlockActive);
    elements.lockMessage.textContent = '';
    elements.lockToggle.disabled = false;
    elements.forceFeedback.textContent = '';
    return;
  } catch {
    elements.lockState.textContent = 'DESCONOCIDO';
    elements.lockState.dataset.tone = 'muted';
    elements.lockCurrent.textContent = 'DESCONOCIDO';
    elements.lockReason.textContent = 'No fue posible leer el bloqueo';
    elements.lockDescription.textContent = 'No fue posible leer el bloqueo manual.';
    elements.lockStatus.dataset.tone = 'muted';
    elements.lockStatus.querySelector('p')!.textContent = 'No fue posible leer el bloqueo.';
    elements.lockMessage.textContent = 'No fue posible leer el estado del bloqueo.';
    elements.lockToggle.disabled = false;
  }
}

async function readServiceOrderLockState(): Promise<ServiceOrderLockResponse | null> {
  try {
    const [lockState, forcedState, rulesState] = await Promise.all([
      chrome.runtime.sendMessage({ type: 'SERVICE_ORDER_LOCK_GET' }) as Promise<ServiceOrderLockResponse>,
      chrome.runtime.sendMessage({ type: 'SERVICE_ORDER_FORCED_UNLOCK_GET' }) as Promise<ServiceOrderLockResponse>,
      chrome.runtime.sendMessage({ type: 'BLOCK_RULES_GET' }) as Promise<{ activeRule?: unknown }>,
    ]);
    return {
      success: lockState?.success ?? forcedState?.success,
      locked: lockState?.locked,
      forcedUnlock: forcedState?.forcedUnlock ?? null,
      rule: normalizeActiveRule(rulesState?.activeRule),
    };
  } catch {
    return null;
  }
}

/**
 * El service worker devuelve la regla ya normalizada, pero el popup vuelve a
 * pasarla por el parser para no depender de la forma exacta del mensaje.
 */
function normalizeActiveRule(value: unknown): BlockRule | null {
  const parsed = parseBlockRuleSet({ rules: [toApiShape(value)] });
  return parsed?.rules[0] ?? null;
}

function toApiShape(value: unknown): unknown {
  if (typeof value !== 'object' || value === null) return value;
  const rule = value as Record<string, unknown>;
  if ('host_pattern' in rule) return rule;
  return {
    id: rule.id,
    label: rule.label,
    host_pattern: Array.isArray(rule.hostPatterns) ? rule.hostPatterns[0] : rule.hostPattern,
    host_patterns: rule.hostPatterns,
    path_pattern: rule.pathPattern,
    window_mode: rule.windowMode,
    timezone: rule.timezone,
    windows: Array.isArray(rule.windows)
      ? rule.windows.map((window) => {
          const item = window as Record<string, unknown>;
          return { day_of_week: item.dayOfWeek, start_time: item.start, end_time: item.end };
        })
      : [],
  };
}

function wireForcedUnlockModal(elements: PopupElements): void {
  const sync = () => {
    const ready = elements.forceConfirmation.value.trim().toUpperCase() === 'DESBLOQUEAR' && elements.forceAck.checked;
    elements.forceConfirm.disabled = !ready;
  };

  const open = () => {
    elements.forceModal.hidden = false;
    elements.forceModal.setAttribute('aria-hidden', 'false');
    elements.forceConfirmation.value = '';
    elements.forceAck.checked = false;
    elements.forceConfirm.disabled = true;
    elements.forceFeedback.textContent = '';
    window.setTimeout(() => elements.forceConfirmation.focus(), 0);
  };

  const close = () => {
    elements.forceModal.hidden = true;
    elements.forceModal.setAttribute('aria-hidden', 'true');
  };

  elements.forceConfirmation.addEventListener('input', sync);
  elements.forceAck.addEventListener('change', sync);
  elements.forceCancel.addEventListener('click', close);
  elements.forceModal.querySelector('.popup-modal__backdrop')?.addEventListener('click', close);
  elements.forceConfirm.addEventListener('click', () => {
    void (async () => {
      elements.forceFeedback.textContent = 'Aplicando excepción...';
      try {
        const response = await chrome.runtime.sendMessage({ type: 'SERVICE_ORDER_FORCED_UNLOCK_SET', active: true }) as { success?: boolean; message?: string };
        if (response?.success === false) {
          elements.forceFeedback.textContent = response.message ?? 'No fue posible aplicar la excepción.';
          return;
        }
        close();
        await renderServiceOrderLock(elements);
      } catch {
        elements.forceFeedback.textContent = 'No fue posible aplicar la excepción.';
      }
    })();
  });

  elements.forceAction.addEventListener('click', async () => {
    const state = await readServiceOrderLockState();
    const forcedActive = Boolean(state?.forcedUnlock?.active);
    if (forcedActive) {
      const confirmed = window.confirm('¿Deseas finalizar el desbloqueo forzoso?');
      if (confirmed) {
        await chrome.runtime.sendMessage({ type: 'SERVICE_ORDER_FORCED_UNLOCK_SET', active: false });
        await renderServiceOrderLock(elements);
      }
      return;
    }
    open();
  });
}

function formatServiceOrderNextChange(date: Date, timeZone = 'America/Lima'): string {
  const formatter = new Intl.DateTimeFormat('es-PE', {
    timeZone,
    weekday: 'short',
    hour: 'numeric',
    minute: '2-digit',
  });
  const parts = formatter.formatToParts(date);
  const weekday = parts.find((part) => part.type === 'weekday')?.value ?? '';
  const hour = parts.find((part) => part.type === 'hour')?.value ?? '';
  const minute = parts.find((part) => part.type === 'minute')?.value ?? '';
  const dayPeriod = parts.find((part) => part.type === 'dayPeriod')?.value ?? '';
  const normalizedWeekday = weekday ? capitalize(weekday) : 'Hoy';
  const normalizedPeriod = dayPeriod || 'p. m.';
  return `${normalizedWeekday}, ${hour}:${minute} ${normalizedPeriod}`;
}

function capitalize(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1);
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
