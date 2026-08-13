/**
 * Content script para el Buscador Shalom Control.
 */
import type { Agency } from '../models/agency';
import { statusNotice } from '../models/agency';
import { searchAgencies } from '../search/agency-search';
import { buildMapsUrl } from '../utils/format';
import { getChosenTextForActiveChannel, selectAgencyInDestination } from './agency-selector';
import { bindChannelButtons, detectActiveShalomChannelState, type ShalomChannel } from './shalom-page-adapter';
import {
  getShalomPageCapabilities,
  hostnameMatchesAllowedDomain,
  isNeutralShalomSearchPath,
  isSupportedShalomHost,
  isSupportedShalomPath,
} from './shalom-host';

const CONTAINER_ID = 'mi-buscador-contenedor';
const SEARCH_INPUT_ID = 'codered-search-input';
const RESULTS_PANEL_CLASS = 'codered-results-panel';
const RESULTS_GRID_CLASS = 'codered-results-grid';
const CHANNEL_BADGE_CLASS = 'codered-channel-badge';
const MESSAGE_CLASS = 'codered-search-message';
const DEFAULT_ALLOWED_DOMAINS = ['shalomcontrol.com'];
const CATALOG_STORAGE_KEYS = new Set(['agencies', 'agencyCache', 'catalog', 'catalogVersion', 'syncMetadata', 'codered_agency_catalog', 'codered_catalog_version', 'codered_sync_metadata', 'codered_last_sync_at', 'codered_last_sync_status']);
const HISTORY_PATCH_FLAG = '__coderedShalomHistoryPatched__';

export interface ContentControllerDependencies {
  requestCatalog?: () => Promise<Agency[]>;
  requestStatus?: () => Promise<{ agencyCount?: number }>;
  allowedDomains?: string[];
}

export interface InjectionResult {
  success: boolean;
  reason: 'unsupported-page' | 'already-mounted' | 'target-not-found' | 'mounted' | 'body-not-ready';
  element?: HTMLElement;
}

interface InjectionTarget {
  element: HTMLElement;
  selector: string;
}

export function createShalomContentController(dependencies: ContentControllerDependencies = {}) {
  let agencies: Agency[] = [];
  let activeChannel: Exclude<ShalomChannel, 'AUTO'> | null = null;
  let channelDetectionPending = false;
  let channelRetryAttempts = 0;
  let injectionObserver: MutationObserver | null = null;
  let injectionDebounceTimer: number | null = null;
  let storageListenerBound = false;
  let resizeListenerBound = false;
  let routeObserverBound = false;
  const emittedLogs = new Set<string>();

  async function initializeContentScript(): Promise<void> {
    console.log('[CodeRED Shalom] Content script iniciado');
    console.log(`[CodeRED Shalom] URL actual: ${window.location.href}`);
    if (!hasRequiredContentGlobals()) return;
    // Puerta de runtime: fuera de las rutas autorizadas no se carga el
    // catálogo, no se inyecta la interfaz, no se arranca el MutationObserver y
    // no se escuchan cambios de storage. El manifest ya restringe la
    // inyección, pero esta comprobación es la que garantiza que no se ejecute
    // nada en rutas parecidas ni tras una navegación SPA.
    if (!isSupportedShalomPage()) return;
    await refreshChannelDetection(true);
    await cargarDatos();
    const result = injectSearchIfPossible();
    console.log('[CodeRED Shalom] Resultado de inyección', { reason: result.reason });
    startInjectionObserver();
    startRouteObserver();
    listenForCatalogChanges();
  }

  async function cargarDatos(channel?: Exclude<ShalomChannel, 'AUTO'>): Promise<void> {
    if (channel) activeChannel = channel;
    try {
      agencies = await requestCatalog();
      console.log(`[CodeRED Shalom] Catálogo local cargado: ${agencies.length} agencias`);
      refreshVisibleResults();
    } catch (error) {
      console.error('[CodeRED Shalom] Falló la carga del catálogo', serializeSafeError(error));
      agencies = [];
    }
  }

  function isSupportedShalomPage(): boolean {
    const hostname = window.location.hostname.toLowerCase();
    const pathname = window.location.pathname;
    const allowedDomains = (dependencies.allowedDomains ?? DEFAULT_ALLOWED_DOMAINS).map((domain) => domain.trim().toLowerCase()).filter(Boolean);
    if (!isSupportedShalomHost(hostname)) {
      console.warn('[CodeRED Shalom] Inyección omitida', { reason: 'unsupported-page', hostname });
      return false;
    }
    if (!isSupportedShalomPath(pathname)) {
      console.warn('[CodeRED Shalom] Inyección omitida', { reason: 'unsupported-path', pathname });
      return false;
    }
    if (allowedDomains.length === 0 || allowedDomains.some((domain) => hostnameMatchesAllowedDomain(hostname, domain))) {
      console.log('[CodeRED Shalom] Página compatible');
      return true;
    }
    console.warn('[CodeRED Shalom] Inyección omitida', { reason: 'domain-not-allowed', hostname, allowedDomains });
    return false;
  }

  function findInjectionTarget(): InjectionTarget | null {
    if (isNeutralShalomSearchPath(window.location.pathname)) {
      const serviceOrderTarget = findServiceOrderInsertionTarget();
      if (serviceOrderTarget) {
        return serviceOrderTarget;
      }
    }
    console.log('[CodeRED Shalom] Buscando punto de inyección');
    const selectors = ['.mdl-layout__header-row', 'header .mdl-layout__header-row', '.mdl-layout__header', 'header', '[role="banner"]', '.navbar', '.topbar', '.header'];
    for (const selector of selectors) {
      const elements = Array.from(document.querySelectorAll(selector)).filter((element): element is HTMLElement => element instanceof HTMLElement);
      const visible = elements.find((element) => isElementVisible(element) && !element.closest(`#${CONTAINER_ID}`));
      if (visible) {
        console.log(`[CodeRED Shalom] Target encontrado: ${selector}`);
        return { element: visible, selector };
      }
    }
    console.log('[CodeRED Shalom] Target todavía no disponible');
    return null;
  }

  function findServiceOrderInsertionTarget(): InjectionTarget | null {
    const candidates = Array.from(document.querySelectorAll('main, header, [role="main"], body > div, body > section'))
      .filter((element): element is HTMLElement => element instanceof HTMLElement);

    for (const candidate of candidates) {
      const insertionPoint = findServiceOrderBlock(candidate);
      if (insertionPoint) {
        console.log('[CodeRED Shalom] Target service-order encontrado');
        return insertionPoint;
      }
    }

    return null;
  }

  function findServiceOrderBlock(root: HTMLElement): InjectionTarget | null {
    const elements = Array.from(root.querySelectorAll('*')).filter((element): element is HTMLElement => element instanceof HTMLElement);
    for (const element of elements) {
      if (!isElementVisible(element)) continue;
      if (!containsMeaningfulDirectTextBlock(element)) continue;
      if (!isLikelyServiceOrderAnchor(element)) continue;
      return { element, selector: describeElement(element) };
    }
    return null;
  }

  function containsMeaningfulDirectTextBlock(element: HTMLElement): boolean {
    const directTextChildren = Array.from(element.childNodes).filter((node) => node.nodeType === 1 && (node.textContent ?? '').trim().length > 0);
    return directTextChildren.length > 0;
  }

  function isLikelyServiceOrderAnchor(element: HTMLElement): boolean {
    const text = (element.textContent ?? '').trim();
    if (!text) return false;
    const childDivs = Array.from(element.children).filter((child) => child.tagName === 'DIV');
    if (childDivs.length === 0) return false;
    return childDivs.some((child) => (child.textContent ?? '').trim().length > 0);
  }

  function describeElement(element: HTMLElement): string {
    const tag = element.tagName.toLowerCase();
    const id = element.id ? `#${element.id}` : '';
    const classes = element.classList.length ? `.${Array.from(element.classList).join('.')}` : '';
    return `${tag}${id}${classes}`;
  }

  function createSearchContainer(): HTMLElement {
    const container = document.createElement('div');
    container.id = CONTAINER_ID;
    container.className = 'codered-shalom-search';
    container.innerHTML = `
      <style>${searchStyles()}</style>
      <div class="codered-search-wrapper">
        <span class="codered-search-icon" aria-hidden="true">⌕</span>
        <input id="${SEARCH_INPUT_ID}" class="codered-search-input" type="search" placeholder="Buscar agencia Shalom..." autocomplete="off" />
        <span class="${CHANNEL_BADGE_CLASS}" aria-live="polite"></span>
      </div>
      <div class="${RESULTS_PANEL_CLASS}" hidden>
        <div class="${MESSAGE_CLASS}" aria-live="polite"></div>
        <div class="${RESULTS_GRID_CLASS}" role="listbox"></div>
      </div>
    `;
    updateChannelBadge(container);
    return container;
  }

  function bindSearchEvents(container: HTMLElement): void {
    if (container.dataset.coderedSearchBound === 'true') return;
    container.dataset.coderedSearchBound = 'true';
    const input = container.querySelector<HTMLInputElement>(`#${SEARCH_INPUT_ID}`);
    const panel = container.querySelector<HTMLElement>(`.${RESULTS_PANEL_CLASS}`);
    const grid = container.querySelector<HTMLElement>(`.${RESULTS_GRID_CLASS}`);
    const message = container.querySelector<HTMLElement>(`.${MESSAGE_CLASS}`);
    if (!input || !panel || !grid || !message) return;
    bindResizeReposition(container, panel);

    let debounce: number | null = null;
    input.addEventListener('input', () => {
      if (debounce) window.clearTimeout(debounce);
      debounce = window.setTimeout(() => renderResults(input, panel, grid, message), 150);
    });
    input.addEventListener('focus', () => renderResults(input, panel, grid, message));
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') closeResults(container);
      if (event.key === 'Enter') {
        const first = grid.querySelector<HTMLButtonElement>('.codered-agency-card');
        if (first) first.click();
      }
    });

    const abortController = new window.AbortController();
    document.addEventListener('click', (event) => {
      if (!container.contains(event.target as Node)) closeResults(container);
    }, { signal: abortController.signal });
  }

  function startRouteObserver(): void {
    if (routeObserverBound) return;
    routeObserverBound = true;
    patchHistoryApi();

    window.addEventListener('popstate', handleRouteChange, { passive: true });
    window.addEventListener('hashchange', handleRouteChange, { passive: true });
    document.addEventListener('visibilitychange', handleRouteChange, { passive: true });
  }

  function patchHistoryApi(): void {
    const win = window as Window & { [HISTORY_PATCH_FLAG]?: boolean };
    if (win[HISTORY_PATCH_FLAG]) return;
    win[HISTORY_PATCH_FLAG] = true;
    const originalPushState = history.pushState.bind(history);
    const originalReplaceState = history.replaceState.bind(history);
    history.pushState = (...args) => {
      const result = originalPushState(...args);
      queueRouteRefresh();
      return result;
    };
    history.replaceState = (...args) => {
      const result = originalReplaceState(...args);
      queueRouteRefresh();
      return result;
    };
  }

  let routeRefreshTimer: number | null = null;

  function queueRouteRefresh(): void {
    if (routeRefreshTimer) window.clearTimeout(routeRefreshTimer);
    routeRefreshTimer = window.setTimeout(() => {
      routeRefreshTimer = null;
      handleRouteChange();
    }, 50);
  }

  function handleRouteChange(): void {
    if (!isSupportedShalomPage()) return;
    const container = document.getElementById(CONTAINER_ID);
    if (container && !container.isConnected) {
      container.remove();
    }
    const result = injectSearchIfPossible();
    if (result.element) positionOpenPanel(result.element);
  }

  function injectSearchIfPossible(): InjectionResult {
    if (!document.body) return { success: false, reason: 'body-not-ready' };
    if (!isSupportedShalomPage()) return { success: false, reason: 'unsupported-page' };
    void refreshChannelDetection();

    const existing = document.getElementById(CONTAINER_ID);
    if (existing?.isConnected) {
      bindSearchEvents(existing);
      bindChannelButtons(document, handleChannelChange);
      updateChannelBadge(existing);
      console.log('[CodeRED Shalom] Buscador ya estaba inyectado');
      return { success: true, reason: 'already-mounted', element: existing };
    }

    const target = findInjectionTarget();
    if (!target) return { success: false, reason: 'target-not-found' };

    const container = createSearchContainer();
    mountSearchContainer(target.element, container);
    bindSearchEvents(container);
    bindChannelButtons(document, handleChannelChange);
    console.log('[CodeRED Shalom] Buscador inyectado');
    positionOpenPanel(container);
    return { success: true, reason: 'mounted', element: container };
  }

  function startInjectionObserver(): void {
    if (injectionObserver) return;
    const root = document.documentElement ?? document.body;
    if (!root) return;
    injectionObserver = new MutationObserver(() => {
      if (injectionDebounceTimer) window.clearTimeout(injectionDebounceTimer);
      injectionDebounceTimer = window.setTimeout(() => {
        injectionDebounceTimer = null;
        bindChannelButtons(document, handleChannelChange);
        void refreshChannelDetection();
        void handleRouteChange();
        const existing = document.getElementById(CONTAINER_ID);
        if (!existing?.isConnected) console.log('[CodeRED Shalom] El header cambió; reinyectando');
        const result = injectSearchIfPossible();
        if (result.element) positionOpenPanel(result.element);
      }, 100);
    });
    injectionObserver.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'aria-selected', 'style', 'hidden'] });
  }

  function stopInjectionObserver(): void {
    if (injectionDebounceTimer) window.clearTimeout(injectionDebounceTimer);
    injectionDebounceTimer = null;
    injectionObserver?.disconnect();
    injectionObserver = null;
  }

  function mount(): Promise<InjectionResult> {
    // No se descarga el catálogo fuera de las rutas autorizadas.
    if (!isSupportedShalomPage()) return Promise.resolve({ success: false, reason: 'unsupported-page' });
    return refreshChannelDetection(true).then(() => cargarDatos()).then(() => injectSearchIfPossible());
  }

  function bindResizeReposition(container: HTMLElement, panel: HTMLElement): void {
    if (resizeListenerBound) return;
    resizeListenerBound = true;
    let timer: number | null = null;
    window.addEventListener('resize', () => {
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(() => {
        timer = null;
        if (!panel.hidden) positionResultsPanel(container, panel);
      }, 100);
    });
  }

  function handleChannelChange(nextChannel: Exclude<ShalomChannel, 'AUTO'>): void {
    activeChannel = nextChannel;
    console.log(`[CodeRED Shalom] Canal activo detectado: ${activeChannel}`);
    const container = document.getElementById(CONTAINER_ID);
    if (!container) return;
    updateChannelBadge(container);
    const input = container.querySelector<HTMLInputElement>(`#${SEARCH_INPUT_ID}`);
    if (input) input.value = '';
    closeResults(container);
  }

  function renderResults(input: HTMLInputElement, panel: HTMLElement, grid: HTMLElement, message: HTMLElement): void {
    panel.hidden = false;
    panel.style.left = 'auto';
    panel.style.right = '0';
    panel.style.transform = 'none';
    grid.innerHTML = '';
    message.textContent = '';
    const query = input.value.trim();
    const neutralChannel = isNeutralShalomSearchPath(window.location.pathname);
    if (!activeChannel) {
      if (!neutralChannel) {
        message.textContent = 'Todavía estamos detectando el canal activo de Shalom. Espera unos segundos e intenta de nuevo.';
      } else {
        message.textContent = 'Canal no identificado. Buscando en todas las agencias.';
      }
      positionResultsPanel(input.closest(`#${CONTAINER_ID}`) as HTMLElement, panel);
    }

    const channel = activeChannel;
    const channelAgencies = channel ? agencies.filter((agency) => getChosenTextForActiveChannel(agency, channel)) : agencies;

    if (agencies.length === 0) {
      message.textContent = 'No hay agencias sincronizadas. Abre la configuración de la extensión y pulsa Sincronizar ahora.';
      positionResultsPanel(input.closest(`#${CONTAINER_ID}`) as HTMLElement, panel);
      return;
    }
    if (query.length < 2) {
      message.textContent = channel
        ? `Escribe al menos 2 caracteres para buscar en el canal ${channelLabel(channel)}.`
        : 'Escribe al menos 2 caracteres para buscar en todas las agencias.';
      positionResultsPanel(input.closest(`#${CONTAINER_ID}`) as HTMLElement, panel);
      return;
    }

    const found = searchAgencies(channelAgencies, query, 30).map((result) => result.agency);
    if (found.length === 0) {
      message.textContent = channel
        ? `No se encontraron agencias para ‘${query}’ en el canal ${channelLabel(channel)}.`
        : `No se encontraron agencias para ‘${query}’.`;
      positionResultsPanel(input.closest(`#${CONTAINER_ID}`) as HTMLElement, panel);
      return;
    }
    for (const agency of found) grid.appendChild(createResultCard(agency));
    positionResultsPanel(input.closest(`#${CONTAINER_ID}`) as HTMLElement, panel);
  }

  function createResultCard(agency: Agency): HTMLElement {
    const button = document.createElement('button');
    button.className = 'codered-agency-card tarjeta';
    button.type = 'button';
    button.setAttribute('role', 'option');
    button.innerHTML = cardMarkup(agency);
    button.addEventListener('click', () => selectAgency(agency));
    const map = button.querySelector<HTMLAnchorElement>('.btn-mapa-mini');
    map?.addEventListener('click', (event) => event.stopPropagation());
    return button;
  }

  function selectAgency(agency: Agency): void {
    void refreshChannelDetection();
    const container = document.getElementById(CONTAINER_ID);
    const input = container?.querySelector<HTMLInputElement>(`#${SEARCH_INPUT_ID}`);
    const message = container?.querySelector<HTMLElement>(`.${MESSAGE_CLASS}`);
    const capabilities = getShalomPageCapabilities(window.location.pathname);
    if (!capabilities.agencySelection) {
      if (message) message.textContent = 'Esta página de Shalom solo permite consultar agencias.';
      return;
    }
    const requestedChannel = activeChannel ?? (capabilities.neutralChannel ? 'AUTO' : null);
    if (!requestedChannel) {
      if (message) message.textContent = 'No fue posible determinar el canal activo de Shalom todavía.';
      return;
    }
    if (capabilities.mode === 'neutral') {
      if (message) message.textContent = 'Esta página de Shalom solo permite consultar agencias.';
      return;
    }
    const selected = selectAgencyInDestination(document, agency, requestedChannel);
    if (selected.success) {
      if (input) input.value = '';
      if (container) closeResults(container);
      return;
    }
    if (selected.reason === 'option-not-found') {
      infoOnce('select-agency-unavailable', '[CodeRED Shalom] La agencia seleccionada no está disponible actualmente en Shalom Control', {
        channel: requestedChannel,
        agency: safeAgencyContext(agency),
      });
      if (message) message.textContent = 'La agencia seleccionada no está disponible actualmente en Shalom Control.';
      return;
    }

    warnOnce('select-agency', '[CodeRED Shalom] No se pudo seleccionar agencia', {
      reason: selected.reason,
      channel: requestedChannel,
      agency: safeAgencyContext(agency),
      detail: selected.message,
    });
    if (message) message.textContent = selected.message;
  }

  function refreshVisibleResults(): void {
    const input = document.getElementById(SEARCH_INPUT_ID) as HTMLInputElement | null;
    if (input?.value) input.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function listenForCatalogChanges(): void {
    if (storageListenerBound) return;
    if (typeof chrome === 'undefined' || typeof chrome.storage?.onChanged?.addListener !== 'function') return;
    storageListenerBound = true;
    chrome.storage.onChanged.addListener((changes, areaName) => {
      if (areaName !== 'local') return;
      if (!Object.keys(changes).some((key) => CATALOG_STORAGE_KEYS.has(key))) return;
      void cargarDatos(activeChannel ?? undefined);
    });
  }

  async function refreshChannelDetection(forceLog = false): Promise<Exclude<ShalomChannel, 'AUTO'> | null> {
    const detection = detectActiveShalomChannelState(document);
    if (detection.channel) {
      if (detection.channel !== activeChannel) handleChannelChange(detection.channel);
      channelDetectionPending = false;
      channelRetryAttempts = 0;
      return detection.channel;
    }

    if (isNeutralShalomSearchPath(window.location.pathname)) {
      channelDetectionPending = false;
      channelRetryAttempts = 0;
      return null;
    }

    if (forceLog || !channelDetectionPending) {
      channelDetectionPending = true;
      channelRetryAttempts = 0;
      warnOnce('channel-pending', '[CodeRED Shalom] Canal activo no confirmado todavía; esperando a que Shalom termine de cargar el DOM', {
        path: window.location.pathname,
        reason: detection.reason,
        candidates: detection.candidates,
      });
      scheduleChannelRetry();
    }

    return null;
  }

  function scheduleChannelRetry(): void {
    if (isNeutralShalomSearchPath(window.location.pathname)) {
      channelDetectionPending = false;
      channelRetryAttempts = 0;
      return;
    }
    if (channelRetryAttempts >= 10) {
      channelDetectionPending = false;
      warnOnce('channel-pending-timeout', '[CodeRED Shalom] El canal activo sigue sin poder determinarse tras varios intentos; se detiene la espera automática', {
        path: window.location.pathname,
      });
      return;
    }
    channelRetryAttempts += 1;
    window.setTimeout(() => {
      const detection = detectActiveShalomChannelState(document);
      if (detection.channel) {
        channelDetectionPending = false;
        channelRetryAttempts = 0;
        handleChannelChange(detection.channel);
        return;
      }
      if (detection.reason === 'ambiguous') {
        warnOnce('channel-ambiguous', '[CodeRED Shalom] La detección del canal sigue ambigua; se mantendrá la búsqueda en espera', {
          path: window.location.pathname,
          candidates: detection.candidates,
        });
        channelDetectionPending = false;
        return;
      }
      scheduleChannelRetry();
    }, 200);
  }

  function warnOnce(key: string, message: string, context: Record<string, unknown>): void {
    if (emittedLogs.has(key)) return;
    emittedLogs.add(key);
    console.warn(message, context);
  }

  function infoOnce(key: string, message: string, context: Record<string, unknown>): void {
    if (emittedLogs.has(key)) return;
    emittedLogs.add(key);
    console.info(message, context);
  }

  async function requestCatalog(): Promise<Agency[]> {
    if (dependencies.requestCatalog) return dependencies.requestCatalog();
    if (typeof chrome === 'undefined' || typeof chrome.runtime?.sendMessage !== 'function') {
      console.error('[CodeRED Shalom] chrome.runtime.sendMessage no está disponible.');
      return [];
    }
    const response = await chrome.runtime.sendMessage({ type: 'CATALOG_GET' });
    return Array.isArray(response?.agencies) ? response.agencies : [];
  }

  return { cargarDatos, createSearchContainer, bindSearchEvents, findInjectionTarget, injectSearchIfPossible, initializeContentScript, isSupportedShalomPage, mount, startInjectionObserver, stopInjectionObserver };
}

function safeAgencyContext(agency: Agency): Record<string, unknown> {
  return {
    id: agency.id,
    externalId: agency.externalId,
    code: agency.code,
    name: agency.name,
  };
}

function cardMarkup(agency: Agency): string {
  const notice = statusNotice(agency);
  const services = [agency.terrestrialText ? 'Terrestre' : null, agency.airText ? 'Aéreo' : null].filter(Boolean).join(' / ');
  const badges = [
    `<span class="codered-badge">${escapeHtml(agency.statusLabel)}</span>`,
    services ? `<span class="codered-badge codered-badge-service">${escapeHtml(services)}</span>` : '',
    agency.category ? `<span class="codered-badge">${escapeHtml(agency.category)}</span>` : `<span class="codered-badge codered-badge-muted">Sin categoría</span>`,
    agency.isOperationsCenter ? '<span class="codered-badge codered-badge-co">Centro de Operaciones</span>' : '',
    agency.sendsCategory ? `<span class="codered-badge">Envía: ${escapeHtml(agency.sendsCategory)}</span>` : '',
    agency.receivesCategory ? `<span class="codered-badge">Recibe: ${escapeHtml(agency.receivesCategory)}</span>` : '',
  ].filter(Boolean).join('');
  const location = [agency.department, agency.province, agency.district].filter(Boolean).join(' / ');
  const mapUrl = buildMapsUrl(agency);
  return `
    <span class="codered-card-head">
      <strong>${escapeHtml(agency.name)}</strong>
      <a class="btn-mapa-mini" href="${escapeAttribute(mapUrl)}" target="_blank" rel="noopener noreferrer">MAPA</a>
    </span>
    <span class="codered-card-code">${escapeHtml([agency.code, agency.oldName ? `Antes: ${agency.oldName}` : null].filter(Boolean).join(' · '))}</span>
    <span class="codered-badges">${badges}</span>
    ${location ? `<span class="ubicacion">${escapeHtml(location)}</span>` : ''}
    ${agency.address ? `<span class="direccion">${escapeHtml(agency.address)}</span>` : ''}
    ${agency.reference ? `<span class="direccion">Ref: ${escapeHtml(agency.reference)}</span>` : ''}
    ${notice ? `<span class="codered-notice codered-notice-${notice.tone}">${escapeHtml([notice.message, ...notice.details].join(' · '))}</span>` : ''}
  `;
}

interface SearchInsertionPoint {
  parent: HTMLElement;
  before: Element | null;
  reason: 'before-navigation' | 'before-menu' | 'after-spacer' | 'append';
}

function mountSearchContainer(headerRow: HTMLElement, container: HTMLElement): void {
  insertSearchContainer(headerRow, container);
}

export function insertSearchContainer(headerRow: HTMLElement, container: HTMLElement): void {
  const insertion = findSearchInsertionPoint(headerRow);
  insertion.parent.classList.add('codered-search-host');
  insertion.parent.insertBefore(container, insertion.before);
  container.dataset.insertionReason = insertion.reason;

  const previous = container.previousElementSibling;
  const next = container.nextElementSibling;
  console.debug('[CodeRED] Posición del buscador', { previous, next });

  if (insertion.reason === 'before-navigation' && next instanceof HTMLElement && next.classList.contains('mdl-navigation')) {
    console.debug('[CodeRED] Buscador confirmado antes de .mdl-navigation');
  }
}

export function findSearchInsertionPoint(headerRow: HTMLElement): SearchInsertionPoint {
  const navigation = headerRow.querySelector<HTMLElement>(':scope > .mdl-navigation, :scope > nav.mdl-navigation');
  if (navigation) {
    console.debug('[CodeRED] Buscador insertado antes de .mdl-navigation');
    return { parent: headerRow, before: navigation, reason: 'before-navigation' };
  }

  const menuButton = headerRow.querySelector<HTMLElement>(':scope > #demo-menu-lower-right');
  if (menuButton) {
    console.debug('[CodeRED] Buscador insertado antes del menú');
    return { parent: headerRow, before: menuButton, reason: 'before-menu' };
  }

  const spacer = headerRow.querySelector<HTMLElement>(':scope > .mdl-layout-spacer');
  if (spacer) {
    console.debug('[CodeRED] Buscador insertado después del spacer');
    return { parent: headerRow, before: spacer.nextElementSibling, reason: 'after-spacer' };
  }

  console.debug('[CodeRED] Fallback appendChild');
  return { parent: headerRow, before: null, reason: 'append' };
}

function positionOpenPanel(container: HTMLElement): void {
  const panel = container.querySelector<HTMLElement>(`.${RESULTS_PANEL_CLASS}`);
  if (panel && !panel.hidden) positionResultsPanel(container, panel);
}

export function positionResultsPanel(container: HTMLElement | null, panel: HTMLElement): void {
  if (!container) return;
  panel.style.left = 'auto';
  panel.style.right = '0';
  panel.style.transform = 'none';

  const schedule = window.requestAnimationFrame ?? ((callback: FrameRequestCallback) => window.setTimeout(() => callback(Date.now()), 0));
  schedule(() => {
    if (window.innerWidth <= 720) {
      panel.style.transform = 'none';
      return;
    }

    const rect = panel.getBoundingClientRect();
    const viewportPadding = 16;
    let correction = 0;
    if (rect.left < viewportPadding) correction += viewportPadding - rect.left;
    if (rect.right > window.innerWidth - viewportPadding) correction -= rect.right - (window.innerWidth - viewportPadding);
    panel.style.transform = correction === 0 ? 'none' : `translateX(${correction}px)`;
  });
}

function closeResults(container: HTMLElement): void {
  const panel = container.querySelector<HTMLElement>(`.${RESULTS_PANEL_CLASS}`);
  if (panel) panel.hidden = true;
}

  function updateChannelBadge(container: HTMLElement): void {
    const badge = container.querySelector<HTMLElement>(`.${CHANNEL_BADGE_CLASS}`);
    if (badge) badge.textContent = activeChannelText(container.ownerDocument);
  }

  function activeChannelText(root: ParentNode): string {
    const channel = detectActiveShalomChannelState(root).channel;
    if (!channel) return isNeutralShalomSearchPath(window.location.pathname) ? '🌐 Modo neutral' : '⌛ Canal pendiente';
    return channel === 'AEREO' ? '✈️ Aéreo' : '🚚 Terrestre';
  }

function channelLabel(channel: Exclude<ShalomChannel, 'AUTO'>): string {
  return channel === 'AEREO' ? 'Aéreo' : 'Terrestre';
}

function searchStyles(): string {
  return `
    #${CONTAINER_ID}.codered-shalom-search { position: relative !important; display: flex !important; align-items: center !important; flex: 0 0 auto !important; min-width: 0 !important; z-index: 1200 !important; margin-right: 24px !important; }
    #${CONTAINER_ID} .codered-search-wrapper { width: clamp(300px, 24vw, 420px) !important; min-width: 280px !important; max-width: 42vw !important; height: 40px !important; display: flex !important; align-items: center !important; gap: 8px !important; background: #242424 !important; border: 2px solid #ff414d !important; border-radius: 24px !important; overflow: hidden !important; box-shadow: 0 8px 18px rgba(0,0,0,.22) !important; }
    #${CONTAINER_ID} .codered-search-icon { color: #ff737b !important; font-size: 18px !important; padding-left: 14px !important; }
    #${CONTAINER_ID} .codered-search-input { width: 100% !important; min-width: 0 !important; border: 0 !important; outline: 0 !important; background: transparent !important; color: #fff !important; padding: 10px 6px !important; font-size: 14px !important; }
    #${CONTAINER_ID} .codered-search-input::placeholder { color: rgba(255,255,255,.65) !important; }
    #${CONTAINER_ID} .${CHANNEL_BADGE_CLASS} { color: #fff !important; background: rgba(255,255,255,.1) !important; border-radius: 999px !important; padding: 4px 10px !important; margin-right: 8px !important; white-space: nowrap !important; font-size: 12px !important; }
    #${CONTAINER_ID} .${RESULTS_PANEL_CLASS} { position: absolute !important; top: calc(100% + 12px) !important; left: auto !important; right: 0 !important; transform: none; width: min(1000px, calc(100vw - 32px)) !important; max-height: 550px !important; overflow-y: auto !important; padding: 16px !important; background: #202020 !important; border: 1px solid #343434 !important; border-radius: 16px !important; box-shadow: 0 16px 50px rgba(0,0,0,.4) !important; color: #fff !important; }
    #${CONTAINER_ID} .${RESULTS_PANEL_CLASS}[hidden] { display: none !important; }
    #${CONTAINER_ID} .${MESSAGE_CLASS} { color: #f5f5f5 !important; font-size: 14px !important; padding: 4px 2px 10px !important; }
    #${CONTAINER_ID} .${RESULTS_GRID_CLASS} { display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 15px !important; }
    #${CONTAINER_ID} .codered-agency-card { min-height: 210px !important; padding: 18px !important; background: #252525 !important; border: 1px solid #454545 !important; border-radius: 14px !important; color: #fff !important; text-align: left !important; cursor: pointer !important; display: flex !important; flex-direction: column !important; gap: 10px !important; font: inherit !important; }
    #${CONTAINER_ID} .codered-agency-card:hover, #${CONTAINER_ID} .codered-agency-card:focus { border-color: #ff414d !important; box-shadow: 0 0 0 2px rgba(255,65,77,.22) !important; outline: 0 !important; }
    #${CONTAINER_ID} .codered-card-head { display: flex !important; align-items: flex-start !important; justify-content: space-between !important; gap: 12px !important; }
    #${CONTAINER_ID} .codered-card-head strong { color: #fff !important; font-size: 16px !important; line-height: 1.25 !important; }
    #${CONTAINER_ID} .btn-mapa-mini { color: #fff !important; background: #ff414d !important; border-radius: 999px !important; padding: 5px 9px !important; text-decoration: none !important; font-size: 11px !important; font-weight: 700 !important; }
    #${CONTAINER_ID} .codered-card-code, #${CONTAINER_ID} .ubicacion, #${CONTAINER_ID} .direccion { color: rgba(255,255,255,.78) !important; font-size: 12px !important; line-height: 1.35 !important; }
    #${CONTAINER_ID} .codered-badges { display: flex !important; flex-wrap: wrap !important; gap: 6px !important; }
    #${CONTAINER_ID} .codered-badge { color: #fff !important; background: #383838 !important; border: 1px solid #555 !important; border-radius: 999px !important; padding: 4px 8px !important; font-size: 11px !important; }
    #${CONTAINER_ID} .codered-badge-service { border-color: #ff414d !important; }
    #${CONTAINER_ID} .codered-badge-co { background: #552328 !important; border-color: #ff414d !important; }
    #${CONTAINER_ID} .codered-badge-muted { color: rgba(255,255,255,.65) !important; }
    #${CONTAINER_ID} .codered-notice { border-radius: 8px !important; padding: 8px !important; font-size: 12px !important; line-height: 1.35 !important; }
    #${CONTAINER_ID} .codered-notice-warning { background: rgba(245,158,11,.16) !important; color: #fde68a !important; }
    #${CONTAINER_ID} .codered-notice-danger { background: rgba(239,68,68,.16) !important; color: #fecaca !important; }
    .codered-search-host { overflow: visible !important; }
    @media (max-width: 1200px) { #${CONTAINER_ID} .codered-search-wrapper { width: 320px !important; } }
    @media (max-width: 1100px) { #${CONTAINER_ID} .${RESULTS_PANEL_CLASS} { width: min(760px, calc(100vw - 24px)) !important; } #${CONTAINER_ID} .${RESULTS_GRID_CLASS} { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; } }
    @media (max-width: 900px) { #${CONTAINER_ID} { margin-right: 12px !important; } #${CONTAINER_ID} .codered-search-wrapper { width: 280px !important; max-width: 55vw !important; } }
    @media (max-width: 720px) { #${CONTAINER_ID} { margin: 8px 0 !important; width: 100% !important; } #${CONTAINER_ID} .codered-search-wrapper { width: 100% !important; max-width: 100% !important; } #${CONTAINER_ID} .${RESULTS_PANEL_CLASS} { position: fixed !important; left: 12px !important; right: 12px !important; top: 70px !important; width: auto !important; max-height: calc(100vh - 90px) !important; transform: none !important; } #${CONTAINER_ID} .${RESULTS_GRID_CLASS} { grid-template-columns: 1fr !important; } }
  `;
}

function hasRequiredContentGlobals(): boolean {
  if (typeof document === 'undefined' || typeof window === 'undefined') return false;
  if (!document.body) return false;
  if (typeof chrome === 'undefined') {
    console.error('[CodeRED Shalom] chrome no está disponible para content.js');
    return false;
  }
  if (typeof chrome.storage === 'undefined') {
    console.error('[CodeRED Shalom] chrome.storage no está disponible para content.js');
    return false;
  }
  return true;
}

function isElementVisible(element: HTMLElement): boolean {
  for (let current: HTMLElement | null = element; current; current = current.parentElement) {
    if (current.hidden || current.getAttribute('aria-hidden') === 'true') return false;
    const style = window.getComputedStyle(current);
    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') return false;
  }
  return true;
}

export function serializeSafeError(error: unknown): Record<string, unknown> {
  if (error instanceof Error) return { name: error.name, message: error.message, stack: error.stack };
  return { message: String(error) };
}

function escapeHtml(value: string): string {
  return value.replace(/[&<>"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[character] ?? character);
}

function escapeAttribute(value: string): string {
  return escapeHtml(value).replace(/`/g, '&#96;');
}

function main(): void {
  if (typeof document === 'undefined') return;
  const controller = createShalomContentController();
  const bootstrap = () => {
    controller.initializeContentScript().catch((error) => console.error('[CodeRED Shalom] Error de inicialización:', serializeSafeError(error)));
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
  else bootstrap();
}

main();
