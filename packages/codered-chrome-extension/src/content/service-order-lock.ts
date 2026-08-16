import { STORAGE_KEYS } from '../storage/storage-keys';
import { formatRemainingDuration, getServiceOrderScheduleState } from '../shared/lima-time';

const OVERLAY_ID = 'codered-service-order-lock-overlay';
const STORAGE_EVENT_KEY = STORAGE_KEYS.SERVICE_ORDER_LOCK;
const LOCK_ATTRIBUTE = 'data-codered-service-order-locked';
const TARGET_HOSTNAME = 'sysnewos.shalomcontrol.com';
const TARGET_PATH = '/service-order';

export type ServiceOrderLockState = {
  visible: boolean;
  locked: boolean;
  lockedBySchedule: boolean;
  manualLocked: boolean;
  reason: 'schedule' | 'manual' | 'schedule+manual' | 'unlocked' | 'outside-scope';
  remainingLabel: string;
};

type StorageCallback = (state: ServiceOrderLockState) => void;

export function createServiceOrderLockController(deps: {
  getManualLock: () => Promise<boolean>;
  setManualLock: (locked: boolean) => Promise<void>;
}) {
  let manualLocked = false;
  let initialized = false;
  let overlay: HTMLElement | null = null;
  let countdownTimer: number | null = null;
  let observer: MutationObserver | null = null;
  let routeTimer: number | null = null;
  let storageListenerBound = false;
  let overlayListenersBound = false;
  const callbacks = new Set<StorageCallback>();

  async function initialize(): Promise<void> {
    if (initialized) return;
    initialized = true;
    manualLocked = await deps.getManualLock();
    bindStorageListener();
    bindRouteObservers();
    evaluateAndRender();
  }

  function bindStorageListener(): void {
    if (storageListenerBound) return;
    if (typeof chrome === 'undefined' || typeof chrome.storage?.onChanged?.addListener !== 'function') return;
    storageListenerBound = true;
    chrome.storage.onChanged.addListener((changes, areaName) => {
      if (areaName !== 'local') return;
      if (!(STORAGE_EVENT_KEY in changes)) return;
      manualLocked = Boolean(changes[STORAGE_EVENT_KEY].newValue);
      evaluateAndRender();
    });
  }

  function bindRouteObservers(): void {
    patchHistory();
    window.addEventListener('popstate', scheduleRouteCheck, { passive: true });
    window.addEventListener('hashchange', scheduleRouteCheck, { passive: true });
    observer = new MutationObserver(scheduleRouteCheck);
    observer.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style', 'hidden'] });
  }

  function patchHistory(): void {
    const win = window as Window & { __coderedServiceOrderHistoryPatched__?: boolean };
    if (win.__coderedServiceOrderHistoryPatched__) return;
    win.__coderedServiceOrderHistoryPatched__ = true;
    const pushState = history.pushState.bind(history);
    const replaceState = history.replaceState.bind(history);
    history.pushState = (...args) => {
      const result = pushState(...args);
      scheduleRouteCheck();
      return result;
    };
    history.replaceState = (...args) => {
      const result = replaceState(...args);
      scheduleRouteCheck();
      return result;
    };
  }

  function scheduleRouteCheck(): void {
    if (routeTimer) window.clearTimeout(routeTimer);
    routeTimer = window.setTimeout(() => {
      routeTimer = null;
      evaluateAndRender();
    }, 25);
  }

  function isTargetPage(): boolean {
    return window.location.hostname.toLowerCase() === TARGET_HOSTNAME && normalizePath(window.location.pathname) === TARGET_PATH;
  }

  function normalizePath(pathname: string): string {
    return pathname.toLowerCase().replace(/\/+$/, '') || '/';
  }

  function evaluateState(): ServiceOrderLockState {
    const visible = isTargetPage();
    const scheduleState = getServiceOrderScheduleState(new Date(), manualLocked);
    if (!visible) {
      return {
        visible: false,
        locked: false,
        lockedBySchedule: false,
        manualLocked: false,
        reason: 'outside-scope',
        remainingLabel: '',
      };
    }
    return {
      visible: true,
      locked: scheduleState.locked,
      lockedBySchedule: scheduleState.lockedBySchedule,
      manualLocked,
      reason: scheduleState.reason,
      remainingLabel: scheduleState.remainingLabel,
    };
  }

  function evaluateAndRender(): void {
    const state = evaluateState();
    notify(state);
    if (!state.visible) {
      removeOverlay();
      stopCountdown();
      return;
    }
    if (state.locked) {
      renderOverlay(state);
      startCountdown();
      return;
    }
    removeOverlay();
    stopCountdown();
  }

  function notify(state: ServiceOrderLockState): void {
    for (const callback of callbacks) callback(state);
  }

  function renderOverlay(state: ServiceOrderLockState): void {
    const existing = document.getElementById(OVERLAY_ID) as HTMLElement | null;
    overlay = existing ?? document.createElement('div');
    overlay.id = OVERLAY_ID;
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-live', 'assertive');
    overlay.setAttribute(LOCK_ATTRIBUTE, 'true');
    overlay.tabIndex = -1;
    overlay.innerHTML = overlayMarkup(state);
    applyOverlayStyles(overlay);
    if (!existing) document.documentElement.appendChild(overlay);
    trapFocus();
    attachOverlayListeners();
    syncCountdownText(state);
  }

  function overlayMarkup(state: ServiceOrderLockState): string {
    const reason = state.reason === 'schedule+manual' ? 'Horario + bloqueo manual' : state.reason === 'manual' ? 'Bloqueo manual' : 'Fuera del horario permitido';
    return `
      <div class="codered-service-order-lock-card">
        <div class="codered-service-order-lock-badge">Operaciones temporalmente bloqueadas</div>
        <h2>Operaciones temporalmente bloqueadas</h2>
        <p>Las operaciones en este módulo se encuentran fuera del horario permitido.</p>
        <p>Podrás continuar a partir de las 08:00 h.</p>
        <dl>
          <div><dt>Estado</dt><dd>BLOQUEADO</dd></div>
          <div><dt>Motivo</dt><dd>${escapeHtml(reason)}</dd></div>
          <div><dt>Horario permitido</dt><dd>08:00 h — 20:05 h</dd></div>
          <div><dt>Disponible nuevamente en</dt><dd id="codered-service-order-lock-countdown">${state.remainingLabel}</dd></div>
        </dl>
      </div>
    `;
  }

  function applyOverlayStyles(element: HTMLElement): void {
    element.style.position = 'fixed';
    element.style.top = '0';
    element.style.left = '0';
    element.style.right = '0';
    element.style.bottom = '0';
    element.style.zIndex = '2147483647';
    element.style.background = 'rgba(7, 11, 20, 0.92)';
    element.style.backdropFilter = 'blur(10px)';
    element.style.display = 'grid';
    element.style.placeItems = 'center';
    element.style.pointerEvents = 'auto';
    element.style.fontFamily = 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
    element.style.color = '#f8fafc';
    element.style.padding = '24px';
  }

  function attachOverlayListeners(): void {
    if (overlayListenersBound) return;
    overlayListenersBound = true;
    overlay?.addEventListener('keydown', blockEvent, true);
    overlay?.addEventListener('click', blockEvent, true);
    document.addEventListener('keydown', blockEvent, true);
    document.addEventListener('keypress', blockEvent, true);
    document.addEventListener('keyup', blockEvent, true);
    document.addEventListener('click', blockEvent, true);
    document.addEventListener('focusin', blockEvent, true);
  }

  function trapFocus(): void {
    overlay?.focus();
  }

  function startCountdown(): void {
    if (countdownTimer) return;
    countdownTimer = window.setInterval(() => {
      const state = evaluateState();
      if (!state.visible || !state.locked) {
        evaluateAndRender();
        return;
      }
      syncCountdownText(state);
    }, 1000);
  }

  function syncCountdownText(state: ServiceOrderLockState): void {
    const node = document.getElementById('codered-service-order-lock-countdown');
    if (node) node.textContent = state.remainingLabel || formatRemainingDuration(0);
  }

  function stopCountdown(): void {
    if (countdownTimer) window.clearInterval(countdownTimer);
    countdownTimer = null;
  }

  function removeOverlay(): void {
    overlay?.remove();
    overlay = null;
    overlayListenersBound = false;
  }

  function blockEvent(event: Event): void {
    if (!overlay?.isConnected) return;
    if (event.target instanceof HTMLElement && event.target.closest(`#${OVERLAY_ID}`)) return;
    event.preventDefault();
    event.stopPropagation();
    // Impide interacción accidental mientras el overlay está activo.
    if ('stopImmediatePropagation' in event) (event as Event & { stopImmediatePropagation: () => void }).stopImmediatePropagation();
  }

  function onStateChange(callback: StorageCallback): () => void {
    callbacks.add(callback);
    callback(evaluateState());
    return () => callbacks.delete(callback);
  }

  async function setManualLock(locked: boolean): Promise<void> {
    manualLocked = locked;
    await deps.setManualLock(locked);
    evaluateAndRender();
  }

  function getState(): ServiceOrderLockState {
    return evaluateState();
  }

  return {
    initialize,
    getState,
    onStateChange,
    setManualLock,
    refresh: evaluateAndRender,
    destroy() {
      stopCountdown();
      observer?.disconnect();
      observer = null;
      removeOverlay();
      callbacks.clear();
    },
  };
}

function escapeHtml(value: string): string {
  return value.replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character] ?? character));
}
