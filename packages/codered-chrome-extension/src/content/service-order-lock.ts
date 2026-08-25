import { STORAGE_KEYS } from '../storage/storage-keys';
import {
  DEFAULT_BLOCK_RULE_SET,
  evaluateRuleSet,
  formatRemainingDuration,
  getRulePeriodId,
  getNextRuleChange,
  type BlockRule,
  type BlockRuleSet,
} from '../shared/block-rules';

const OVERLAY_ID = 'codered-service-order-lock-overlay';
const STORAGE_EVENT_KEY = STORAGE_KEYS.SERVICE_ORDER_LOCK;
const FORCED_UNLOCK_KEY = STORAGE_KEYS.SERVICE_ORDER_FORCED_UNLOCK;
const BLOCK_RULES_KEY = STORAGE_KEYS.BLOCK_RULES;
const LOCK_ATTRIBUTE = 'data-codered-service-order-locked';
const FORCED_UNLOCK_LOG_KEY = 'codered_service_order_forced_unlock_log';

export type ServiceOrderForcedUnlock = {
  active: boolean;
  createdAt: string;
  expiresAt: string;
  restrictedPeriodId: string;
};

export type ServiceOrderLockState = {
  visible: boolean;
  locked: boolean;
  lockedBySchedule: boolean;
  manualLocked: boolean;
  forcedUnlockActive: boolean;
  reason: 'schedule' | 'manual' | 'schedule+manual' | 'unlocked' | 'outside-scope';
  remainingLabel: string;
  ruleLabel: string;
  scheduleLabel: string;
  windowMode: 'allowed' | 'blocked';
};

type StorageCallback = (state: ServiceOrderLockState) => void;

export function createServiceOrderLockController(deps: {
  getManualLock: () => Promise<boolean>;
  setManualLock: (locked: boolean) => Promise<void>;
  getRuleSet?: () => Promise<BlockRuleSet>;
}) {
  let manualLocked = false;
  let forcedUnlock: ServiceOrderForcedUnlock | null = null;
  let ruleSet: BlockRuleSet = DEFAULT_BLOCK_RULE_SET;
  let initialized = false;
  let overlay: HTMLElement | null = null;
  let countdownTimer: number | null = null;
  let stateTimer: number | null = null;
  let observer: MutationObserver | null = null;
  let routeTimer: number | null = null;
  let storageListenerBound = false;
  let overlayListenersBound = false;
  const callbacks = new Set<StorageCallback>();

  async function initialize(): Promise<void> {
    if (initialized) return;
    initialized = true;
    manualLocked = await deps.getManualLock();
    forcedUnlock = await readForcedUnlock();
    ruleSet = await readRuleSet();
    bindStorageListener();
    bindRouteObservers();
    startStateTimer();
    evaluateAndRender();
  }

  async function readRuleSet(): Promise<BlockRuleSet> {
    if (deps.getRuleSet) {
      try {
        return await deps.getRuleSet();
      } catch {
        return ruleSet;
      }
    }
    return ruleSet;
  }

  function bindStorageListener(): void {
    if (storageListenerBound) return;
    if (typeof chrome === 'undefined' || typeof chrome.storage?.onChanged?.addListener !== 'function') return;
    storageListenerBound = true;
    chrome.storage.onChanged.addListener((changes, areaName) => {
      if (areaName !== 'local') return;
      if (STORAGE_EVENT_KEY in changes) manualLocked = Boolean(changes[STORAGE_EVENT_KEY].newValue);
      if (FORCED_UNLOCK_KEY in changes) forcedUnlock = normalizeForcedUnlock(changes[FORCED_UNLOCK_KEY].newValue);
      if (BLOCK_RULES_KEY in changes) {
        // El panel publico reglas nuevas: se aplican sin recargar la pagina.
        void readRuleSet().then((next) => {
          ruleSet = next;
          evaluateAndRender();
        });
        return;
      }
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

  function startStateTimer(): void {
    if (stateTimer) return;
    stateTimer = window.setInterval(() => {
      void refreshForcedUnlockIfNeeded().then(() => {
        evaluateAndRender();
      });
    }, 1000);
  }

  function currentEvaluation(now = new Date()) {
    return evaluateRuleSet(ruleSet, window.location.hostname, window.location.pathname, now);
  }

  function activeRule(): BlockRule | null {
    return currentEvaluation().rule;
  }

  function evaluateState(): ServiceOrderLockState {
    const now = new Date();
    const evaluation = currentEvaluation(now);

    if (!evaluation.matched || !evaluation.rule) {
      return {
        visible: false,
        locked: false,
        lockedBySchedule: false,
        manualLocked: false,
        forcedUnlockActive: false,
        reason: 'outside-scope',
        remainingLabel: '',
        ruleLabel: '',
        scheduleLabel: '',
        windowMode: 'allowed',
      };
    }

    const lockedBySchedule = evaluation.blockedBySchedule;
    const forcedUnlockActive = isForcedUnlockValid(evaluation.rule, now);
    const locked = manualLocked || (lockedBySchedule && !forcedUnlockActive);

    return {
      visible: true,
      locked,
      lockedBySchedule,
      manualLocked,
      forcedUnlockActive,
      reason: manualLocked
        ? lockedBySchedule && forcedUnlockActive
          ? 'schedule+manual'
          : 'manual'
        : lockedBySchedule
          ? forcedUnlockActive
            ? 'unlocked'
            : 'schedule'
          : 'unlocked',
      remainingLabel: evaluation.remainingLabel,
      ruleLabel: evaluation.rule.label,
      scheduleLabel: evaluation.scheduleLabel,
      windowMode: evaluation.rule.windowMode,
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
    const reason = state.reason === 'schedule+manual'
      ? 'Horario + bloqueo manual'
      : state.reason === 'manual'
        ? 'Bloqueo manual'
        : state.windowMode === 'allowed'
          ? 'Fuera del horario permitido'
          : 'Dentro del horario bloqueado';
    const scheduleTitle = state.windowMode === 'allowed' ? 'Horario permitido' : 'Horario bloqueado';
    const nextChange = nextChangeLabel();

    return `
      <div class="codered-service-order-lock-card">
        <div class="codered-service-order-lock-hero">
          <div class="codered-service-order-lock-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
              <path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v7a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-7a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 8V7a3 3 0 1 1 6 0v3H9Zm3 4a1.75 1.75 0 0 1 .95 3.23V18a1 1 0 1 1-2 0v-.77A1.75 1.75 0 0 1 12 14Z"/>
            </svg>
          </div>
          <div class="codered-service-order-lock-hero-copy">
            <span class="codered-service-order-lock-badge">Bloqueo activo</span>
            <h2>Operaciones temporalmente bloqueadas</h2>
            <p>${escapeHtml(state.ruleLabel)}: las operaciones en este módulo están bloqueadas en este momento.</p>
            ${nextChange ? `<p class="codered-service-order-lock-emphasis">Podrás continuar ${escapeHtml(nextChange)}.</p>` : ''}
          </div>
        </div>

        <dl class="codered-service-order-lock-details">
          <div><dt>Estado</dt><dd class="codered-service-order-lock-pill">BLOQUEADO</dd></div>
          <div><dt>Motivo</dt><dd>${escapeHtml(reason)}</dd></div>
          <div><dt>${escapeHtml(scheduleTitle)}</dt><dd>${escapeHtml(state.scheduleLabel)}</dd></div>
          <div><dt>Disponible nuevamente en</dt><dd id="codered-service-order-lock-countdown" class="codered-service-order-lock-countdown">${state.remainingLabel}</dd></div>
        </dl>
      </div>
    `;
  }

  function nextChangeLabel(): string {
    const rule = activeRule();
    if (!rule) return '';
    const next = getNextRuleChange(rule, new Date());
    if (!next) return '';

    return new Intl.DateTimeFormat('es-PE', {
      timeZone: rule.timezone,
      weekday: 'long',
      hour: '2-digit',
      minute: '2-digit',
    }).format(next);
  }

  function applyOverlayStyles(element: HTMLElement): void {
    element.style.position = 'fixed';
    element.style.top = '0';
    element.style.left = '0';
    element.style.right = '0';
    element.style.bottom = '0';
    element.style.zIndex = '2147483647';
    element.style.background = 'rgba(255, 255, 255, 0.68)';
    element.style.backdropFilter = 'none';
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
      #${OVERLAY_ID} { font-synthesis: none; }
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
      #${OVERLAY_ID} .codered-service-order-lock-card::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 4px;
        border-radius: 18px 18px 0 0;
        background: linear-gradient(90deg, #2563eb 0%, #60a5fa 100%);
      }
      #${OVERLAY_ID} .codered-service-order-lock-hero { display: grid; grid-template-columns: 84px minmax(0, 1fr); gap: 18px; align-items: center; }
      #${OVERLAY_ID} .codered-service-order-lock-icon { width: 84px; height: 84px; border-radius: 999px; display: grid; place-items: center; background: linear-gradient(180deg, #eaf2ff 0%, #dbeafe 100%); color: #2563eb; box-shadow: inset 0 0 0 1px rgba(37, 99, 235, 0.12); }
      #${OVERLAY_ID} .codered-service-order-lock-icon svg { width: 36px; height: 36px; fill: currentColor; }
      #${OVERLAY_ID} .codered-service-order-lock-hero-copy { min-width: 0; }
      #${OVERLAY_ID} .codered-service-order-lock-badge { display: inline-flex; align-items: center; min-height: 28px; padding: 0 12px; border-radius: 999px; background: #eff6ff; color: #2563eb; font-size: 12px; font-weight: 700; letter-spacing: .04em; margin-bottom: 10px; }
      #${OVERLAY_ID} h2 { margin: 0; font-size: 22px; line-height: 1.15; color: #0f172a; }
      #${OVERLAY_ID} p { margin: 10px 0 0; font-size: 15px; line-height: 1.5; color: #475569; }
      #${OVERLAY_ID} .codered-service-order-lock-emphasis { color: #2563eb; font-weight: 700; }
      #${OVERLAY_ID} .codered-service-order-lock-details { margin: 20px 0 0; padding: 18px 0 0; border-top: 1px solid #eef2f7; display: grid; gap: 14px; }
      #${OVERLAY_ID} .codered-service-order-lock-details > div { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 16px; align-items: center; }
      #${OVERLAY_ID} dt { margin: 0; color: #475569; font-size: 13px; font-weight: 600; }
      #${OVERLAY_ID} dd { margin: 0; color: #0f172a; font-size: 14px; font-weight: 700; text-align: right; }
      #${OVERLAY_ID} .codered-service-order-lock-pill { display: inline-flex; align-items: center; justify-content: center; min-height: 30px; padding: 0 12px; border-radius: 999px; background: #fee2e2; color: #dc2626; box-shadow: inset 0 0 0 1px rgba(220, 38, 38, 0.08); }
      #${OVERLAY_ID} .codered-service-order-lock-countdown { color: #2563eb; font-size: 16px; }
      @media (max-width: 560px) {
        #${OVERLAY_ID} { padding: 18px; }
        #${OVERLAY_ID} .codered-service-order-lock-card { width: min(100%, 520px); padding: 22px 18px 18px; }
        #${OVERLAY_ID} .codered-service-order-lock-hero { grid-template-columns: 64px minmax(0, 1fr); gap: 14px; }
        #${OVERLAY_ID} .codered-service-order-lock-icon { width: 64px; height: 64px; }
        #${OVERLAY_ID} h2 { font-size: 20px; }
        #${OVERLAY_ID} p { font-size: 14px; }
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
      void refreshForcedUnlockIfNeeded().then(() => {
        const state = evaluateState();
        if (!state.visible || !state.locked) {
          evaluateAndRender();
          return;
        }
        syncCountdownText(state);
      });
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

  async function readForcedUnlock(): Promise<ServiceOrderForcedUnlock | null> {
    if (typeof chrome === 'undefined' || typeof chrome.storage?.local?.get !== 'function') return null;
    const data = await chrome.storage.local.get([FORCED_UNLOCK_KEY]);
    return normalizeForcedUnlock(data[FORCED_UNLOCK_KEY]);
  }

  function normalizeForcedUnlock(value: unknown): ServiceOrderForcedUnlock | null {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
    const candidate = value as Partial<ServiceOrderForcedUnlock>;
    if (candidate.active !== true) return null;
    if (typeof candidate.createdAt !== 'string' || typeof candidate.expiresAt !== 'string' || typeof candidate.restrictedPeriodId !== 'string') return null;
    return candidate as ServiceOrderForcedUnlock;
  }

  function isForcedUnlockValid(rule: BlockRule, now = new Date()): boolean {
    if (!forcedUnlock?.active) return false;
    const expiresAt = Date.parse(forcedUnlock.expiresAt);
    const currentPeriodId = getRulePeriodId(rule, now);
    if (!Number.isFinite(expiresAt) || expiresAt <= now.getTime()) return false;
    if (!currentPeriodId || forcedUnlock.restrictedPeriodId !== currentPeriodId) return false;
    return true;
  }

  async function refreshForcedUnlockIfNeeded(): Promise<void> {
    const current = await readForcedUnlock();
    if (!current) {
      forcedUnlock = null;
      return;
    }
    const now = new Date();
    const rule = activeRule();
    const expiresAt = Date.parse(current.expiresAt);
    const currentPeriodId = rule ? getRulePeriodId(rule, now) : null;

    // Fuera del alcance de cualquier regla no se toca la excepcion: sigue
    // siendo valida para la pestana que si esta bloqueada.
    if (!rule) {
      forcedUnlock = current;
      return;
    }

    if (!Number.isFinite(expiresAt) || expiresAt <= now.getTime() || !currentPeriodId || currentPeriodId !== current.restrictedPeriodId) {
      forcedUnlock = null;
      if (typeof chrome !== 'undefined' && typeof chrome.storage?.local?.remove === 'function') {
        await chrome.storage.local.remove([FORCED_UNLOCK_KEY]);
        await logForcedUnlock('forced_unlock_expired', current);
      }
      return;
    }
    forcedUnlock = current;
  }

  async function setForcedUnlockActive(active: boolean): Promise<void> {
    if (active) {
      const now = new Date();
      const rule = activeRule();
      if (!rule) return;
      const nextChange = getNextRuleChange(rule, now);
      const value: ServiceOrderForcedUnlock = {
        active: true,
        createdAt: now.toISOString(),
        expiresAt: (nextChange ?? new Date(now.getTime() + 60 * 60 * 1000)).toISOString(),
        restrictedPeriodId: getRulePeriodId(rule, now) ?? '',
      };
      forcedUnlock = value;
      await chrome.storage.local.set({ [FORCED_UNLOCK_KEY]: value });
      await logForcedUnlock('forced_unlock_started', value);
      return;
    }
    const current = await readForcedUnlock();
    if (current) await logForcedUnlock('forced_unlock_ended', current);
    forcedUnlock = null;
    await chrome.storage.local.remove([FORCED_UNLOCK_KEY]);
  }

  async function logForcedUnlock(type: 'forced_unlock_started' | 'forced_unlock_ended' | 'forced_unlock_expired', forced: ServiceOrderForcedUnlock): Promise<void> {
    if (typeof chrome === 'undefined' || typeof chrome.storage?.local?.get !== 'function') return;
    const data = await chrome.storage.local.get([FORCED_UNLOCK_LOG_KEY]);
    const log = Array.isArray(data[FORCED_UNLOCK_LOG_KEY]) ? data[FORCED_UNLOCK_LOG_KEY] : [];
    const entry = { type, at: new Date().toISOString(), createdAt: forced.createdAt, expiresAt: forced.expiresAt, restrictedPeriodId: forced.restrictedPeriodId };
    await chrome.storage.local.set({ [FORCED_UNLOCK_LOG_KEY]: [...log, entry].slice(-50) });
  }

  function blockEvent(event: Event): void {
    if (!overlay?.isConnected) return;
    if (event.target instanceof HTMLElement && event.target.closest(`#${OVERLAY_ID}`)) return;
    event.preventDefault();
    event.stopPropagation();
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
    setForcedUnlockActive,
    refresh: evaluateAndRender,
    async reloadRules(): Promise<void> {
      ruleSet = await readRuleSet();
      evaluateAndRender();
    },
    destroy() {
      stopCountdown();
      if (stateTimer) window.clearInterval(stateTimer);
      stateTimer = null;
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
