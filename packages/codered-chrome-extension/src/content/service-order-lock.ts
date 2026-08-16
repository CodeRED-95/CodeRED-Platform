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
        <button class="codered-service-order-lock-close" type="button" aria-label="Cerrar aviso" title="Cerrar aviso" disabled>×</button>
        <div class="codered-service-order-lock-hero">
          <div class="codered-service-order-lock-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
              <path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v7a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-7a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 8V7a3 3 0 1 1 6 0v3H9Zm3 4a1.75 1.75 0 0 1 .95 3.23V18a1 1 0 1 1-2 0v-.77A1.75 1.75 0 0 1 12 14Z"/>
            </svg>
          </div>
          <div class="codered-service-order-lock-hero-copy">
            <span class="codered-service-order-lock-badge">Bloqueo activo</span>
            <h2>Operaciones temporalmente bloqueadas</h2>
            <p>Las operaciones en este módulo se encuentran fuera del horario permitido.</p>
            <p class="codered-service-order-lock-emphasis">Podrás continuar a partir de las 08:00 h.</p>
          </div>
        </div>

        <div class="codered-service-order-lock-callout codered-service-order-lock-callout--warning">
          <strong>Estás a punto de desbloquear el módulo fuera del horario permitido (08:00 h – 20:05 h).</strong>
          <span>Esta acción puede afectar procesos y métricas del sistema. Úsalo solo si es estrictamente necesario.</span>
        </div>

        <div class="codered-service-order-lock-callout codered-service-order-lock-callout--info">
          <strong>Podrás continuar a partir de las 08:00 h o desactivar manualmente esta opción cuando ya no la necesites.</strong>
        </div>

        <dl class="codered-service-order-lock-details">
          <div><dt>Estado</dt><dd class="codered-service-order-lock-pill">BLOQUEADO</dd></div>
          <div><dt>Motivo</dt><dd>${escapeHtml(reason)}</dd></div>
          <div><dt>Horario permitido</dt><dd>08:00 h - 20:05 h</dd></div>
          <div><dt>Disponible nuevamente en</dt><dd id="codered-service-order-lock-countdown" class="codered-service-order-lock-countdown">${state.remainingLabel}</dd></div>
        </dl>

        <div class="codered-service-order-lock-footnote">
          <span class="codered-service-order-lock-footnote-icon" aria-hidden="true">i</span>
          <p>El sistema seguirá bloqueado automáticamente si coincide con el horario restringido.</p>
        </div>
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
    element.style.background = 'rgba(255, 255, 255, 0.68)';
    element.style.backdropFilter = 'blur(2px)';
    element.style.display = 'grid';
    element.style.placeItems = 'center';
    element.style.pointerEvents = 'auto';
    element.style.fontFamily = 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
    element.style.color = '#1f2937';
    element.style.padding = '24px';
    element.style.overflow = 'hidden';
    element.style.isolation = 'isolate';
    ensureOverlayStyles();
  }

  function ensureOverlayStyles(): void {
    if (document.getElementById('codered-service-order-lock-styles')) return;
    const style = document.createElement('style');
    style.id = 'codered-service-order-lock-styles';
    style.textContent = `
      #${OVERLAY_ID} {
        font-synthesis: none;
      }

      #${OVERLAY_ID} .codered-service-order-lock-card {
        width: min(520px, calc(100vw - 48px));
        border-radius: 18px;
        border: 1px solid #e5e7eb;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(247, 250, 255, 0.98) 100%);
        box-shadow: 0 18px 44px rgba(15, 23, 42, 0.12);
        padding: 28px 28px 24px;
        color: #1f2937;
        position: relative;
      }

      #${OVERLAY_ID} .codered-service-order-lock-close {
        position: absolute;
        top: 14px;
        right: 14px;
        width: 28px;
        height: 28px;
        border: 0;
        border-radius: 999px;
        background: transparent;
        color: #64748b;
        font-size: 26px;
        line-height: 1;
        cursor: default;
      }

      #${OVERLAY_ID} .codered-service-order-lock-card::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 4px;
        border-radius: 18px 18px 0 0;
        background: linear-gradient(90deg, #2563eb 0%, #60a5fa 100%);
      }

      #${OVERLAY_ID} .codered-service-order-lock-hero {
        display: grid;
        grid-template-columns: 84px minmax(0, 1fr);
        gap: 18px;
        align-items: center;
      }

      #${OVERLAY_ID} .codered-service-order-lock-icon {
        width: 84px;
        height: 84px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        background: linear-gradient(180deg, #eaf2ff 0%, #dbeafe 100%);
        color: #2563eb;
        box-shadow: inset 0 0 0 1px rgba(37, 99, 235, 0.12);
      }

      #${OVERLAY_ID} .codered-service-order-lock-icon svg {
        width: 36px;
        height: 36px;
        fill: currentColor;
      }

      #${OVERLAY_ID} .codered-service-order-lock-hero-copy {
        min-width: 0;
      }

      #${OVERLAY_ID} .codered-service-order-lock-badge {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 0 12px;
        border-radius: 999px;
        background: #eff6ff;
        color: #2563eb;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
        margin-bottom: 10px;
      }

      #${OVERLAY_ID} h2 {
        margin: 0;
        font-size: 22px;
        line-height: 1.15;
        color: #0f172a;
      }

      #${OVERLAY_ID} p {
        margin: 10px 0 0;
        font-size: 15px;
        line-height: 1.5;
        color: #475569;
      }

      #${OVERLAY_ID} .codered-service-order-lock-emphasis {
        color: #2563eb;
        font-weight: 700;
      }

      #${OVERLAY_ID} .codered-service-order-lock-details {
        margin: 20px 0 0;
        padding: 18px 0 0;
        border-top: 1px solid #eef2f7;
        display: grid;
        gap: 14px;
      }

      #${OVERLAY_ID} .codered-service-order-lock-details > div {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 16px;
        align-items: center;
      }

      #${OVERLAY_ID} dt {
        margin: 0;
        color: #475569;
        font-size: 13px;
        font-weight: 600;
      }

      #${OVERLAY_ID} dd {
        margin: 0;
        color: #0f172a;
        font-size: 14px;
        font-weight: 700;
        text-align: right;
      }

      #${OVERLAY_ID} .codered-service-order-lock-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 30px;
        padding: 0 12px;
        border-radius: 999px;
        background: #fee2e2;
        color: #dc2626;
        box-shadow: inset 0 0 0 1px rgba(220, 38, 38, 0.08);
      }

      #${OVERLAY_ID} .codered-service-order-lock-countdown {
        color: #2563eb;
        font-size: 16px;
      }

      #${OVERLAY_ID} .codered-service-order-lock-callout {
        margin-top: 14px;
        border-radius: 12px;
        border: 1px solid transparent;
        padding: 12px 14px;
        display: grid;
        gap: 4px;
      }

      #${OVERLAY_ID} .codered-service-order-lock-callout strong,
      #${OVERLAY_ID} .codered-service-order-lock-callout span {
        display: block;
      }

      #${OVERLAY_ID} .codered-service-order-lock-callout--warning {
        border-color: #f6d59f;
        background: #fff7ed;
        color: #9a3412;
      }

      #${OVERLAY_ID} .codered-service-order-lock-callout--warning strong {
        font-size: 14px;
        color: #9a3412;
      }

      #${OVERLAY_ID} .codered-service-order-lock-callout--warning span {
        font-size: 13px;
        color: #dc2626;
      }

      #${OVERLAY_ID} .codered-service-order-lock-callout--info {
        border-color: #bfdbfe;
        background: #eff6ff;
        color: #2563eb;
      }

      #${OVERLAY_ID} .codered-service-order-lock-callout--info strong {
        font-size: 13px;
        color: #2563eb;
      }

      #${OVERLAY_ID} .codered-service-order-lock-footnote {
        margin-top: 16px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 12px;
        background: #eef2ff;
        color: #4f46e5;
      }

      #${OVERLAY_ID} .codered-service-order-lock-footnote-icon {
        display: inline-grid;
        place-items: center;
        width: 18px;
        height: 18px;
        flex: 0 0 auto;
        border-radius: 999px;
        background: #2563eb;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
      }

      #${OVERLAY_ID} .codered-service-order-lock-footnote p {
        margin: 0;
        font-size: 13px;
        line-height: 1.45;
        color: #4338ca;
      }

      @media (max-width: 560px) {
        #${OVERLAY_ID} {
          padding: 18px;
        }

        #${OVERLAY_ID} .codered-service-order-lock-card {
          width: min(100%, 520px);
          padding: 22px 18px 18px;
        }

        #${OVERLAY_ID} .codered-service-order-lock-hero {
          grid-template-columns: 64px minmax(0, 1fr);
          gap: 14px;
        }

        #${OVERLAY_ID} .codered-service-order-lock-icon {
          width: 64px;
          height: 64px;
        }

        #${OVERLAY_ID} h2 {
          font-size: 20px;
        }

        #${OVERLAY_ID} p {
          font-size: 14px;
        }
      }
    `;
    document.head.appendChild(style);
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
