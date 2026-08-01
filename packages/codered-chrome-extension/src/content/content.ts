/**
 * Content script para el Buscador Shalom Control.
 */
import type { Agency } from '../models/agency';
import { searchAgencies } from '../search/agency-search';
import { buildMapsUrl } from '../utils/format';
import { findActiveDestinationSelect, selectAgencyInDestination } from './agency-selector';
import { bindChannelButtons, type ShalomChannel } from './shalom-page-adapter';
import { hostnameMatchesAllowedDomain, isSupportedShalomHost } from './shalom-host';

const CONTAINER_ID = 'mi-buscador-contenedor';
const SEARCH_INPUT_ID = 'codered-search-input';
const RESULT_LIST_CLASS = 'codered-shalom-search-results';
const STATUS_CLASS = 'codered-shalom-search-status';
const DEFAULT_ALLOWED_DOMAINS = ['shalom.pe', 'shalomcontrol.com'];
const CATALOG_STORAGE_KEYS = new Set(['agencies', 'agencyCache', 'catalog', 'catalogVersion', 'syncMetadata']);

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
  let activeChannel: Exclude<ShalomChannel, 'AUTO'> = 'TERRESTRE';
  let injectionObserver: MutationObserver | null = null;
  let injectionDebounceTimer: number | null = null;
  let storageListenerBound = false;

  async function initializeContentScript(): Promise<void> {
    console.log('[CodeRED Shalom] Content script iniciado');
    console.log(`[CodeRED Shalom] URL actual: ${window.location.href}`);

    if (!hasRequiredContentGlobals()) return;

    await cargarDatos(activeChannel);
    const result = injectSearchIfPossible();
    console.log('[CodeRED Shalom] Resultado de inyección', { reason: result.reason });
    startInjectionObserver();
    listenForCatalogChanges();
  }

  async function cargarDatos(channel: Exclude<ShalomChannel, 'AUTO'> = activeChannel): Promise<void> {
    activeChannel = channel;
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
    const allowedDomains = (dependencies.allowedDomains ?? DEFAULT_ALLOWED_DOMAINS).map((domain) => domain.trim().toLowerCase()).filter(Boolean);

    if (!isSupportedShalomHost(hostname)) {
      console.warn('[CodeRED Shalom] Inyección omitida', { reason: 'unsupported-page', hostname });
      return false;
    }

    if (allowedDomains.length === 0) {
      console.log('[CodeRED Shalom] Dominio permitido');
      return true;
    }

    const allowed = allowedDomains.some((domain) => hostnameMatchesAllowedDomain(hostname, domain));
    if (allowed) {
      console.log('[CodeRED Shalom] Dominio permitido');
      return true;
    }

    console.warn('[CodeRED Shalom] Inyección omitida', {
      reason: 'domain-not-allowed',
      hostname,
      allowedDomains,
    });
    return false;
  }

  function findInjectionTarget(): InjectionTarget | null {
    console.log('[CodeRED Shalom] Buscando punto de inyección');
    const selectors = ['.mdl-layout__header-row', 'header .mdl-layout__header-row', '.mdl-layout__header', 'header', '[role="banner"]', '.navbar', '.topbar', '.header'];

    for (const selector of selectors) {
      const elements = Array.from(document.querySelectorAll(selector)).filter((element): element is HTMLElement => element instanceof HTMLElement);
      const visible = elements.find((element) => isElementVisible(element) && !element.closest(`#${CONTAINER_ID}`));
      if (visible) {
        console.log(`[CodeRED Shalom] Target encontrado con selector: ${selector}`);
        return { element: visible, selector };
      }
    }

    console.log('[CodeRED Shalom] Target todavía no disponible');
    return null;
  }

  function createSearchContainer(): HTMLElement {
    const container = document.createElement('div');
    container.id = CONTAINER_ID;
    container.className = 'codered-shalom-search';
    container.innerHTML = `
      <style>
        #${CONTAINER_ID}.codered-shalom-search {
          align-items: center !important;
          box-sizing: border-box !important;
          display: flex !important;
          flex-shrink: 0 !important;
          gap: 8px !important;
          margin: 0 16px !important;
          min-width: 280px !important;
          opacity: 1 !important;
          position: relative !important;
          visibility: visible !important;
          z-index: 1200 !important;
        }
        #${CONTAINER_ID} #${SEARCH_INPUT_ID} {
          background: #ffffff !important;
          border: 1px solid rgba(15, 23, 42, 0.28) !important;
          border-radius: 6px !important;
          box-sizing: border-box !important;
          color: #111827 !important;
          display: block !important;
          font-size: 13px !important;
          height: 34px !important;
          min-width: 220px !important;
          outline: none !important;
          padding: 7px 10px !important;
          visibility: visible !important;
          width: 100% !important;
        }
        #${CONTAINER_ID} .${RESULT_LIST_CLASS} {
          background: #ffffff !important;
          border: 1px solid rgba(15, 23, 42, 0.18) !important;
          border-radius: 6px !important;
          box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18) !important;
          color: #111827 !important;
          display: none;
          left: 0;
          list-style: none !important;
          margin: 6px 0 0 !important;
          max-height: 320px !important;
          overflow-y: auto !important;
          padding: 4px !important;
          position: absolute !important;
          right: 0;
          top: 100%;
          z-index: 1201 !important;
        }
        #${CONTAINER_ID} .${RESULT_LIST_CLASS} li { margin: 0 !important; padding: 0 !important; }
        #${CONTAINER_ID} .codered-shalom-result,
        #${CONTAINER_ID} .codered-shalom-empty {
          background: transparent !important;
          border: 0 !important;
          border-radius: 4px !important;
          box-sizing: border-box !important;
          color: #111827 !important;
          display: block !important;
          font-size: 13px !important;
          padding: 8px 10px !important;
          text-align: left !important;
          width: 100% !important;
        }
        #${CONTAINER_ID} .codered-shalom-result { cursor: pointer !important; }
        #${CONTAINER_ID} .codered-shalom-result:hover { background: #f3f4f6 !important; }
        #${CONTAINER_ID} .${STATUS_CLASS} {
          color: #4b5563 !important;
          display: inline-block !important;
          font-size: 12px !important;
          max-width: 140px !important;
          overflow: hidden !important;
          text-overflow: ellipsis !important;
          white-space: nowrap !important;
        }
        @media (prefers-color-scheme: dark) {
          #${CONTAINER_ID} #${SEARCH_INPUT_ID},
          #${CONTAINER_ID} .${RESULT_LIST_CLASS} { background: #111827 !important; color: #f9fafb !important; border-color: rgba(249, 250, 251, 0.28) !important; }
          #${CONTAINER_ID} .codered-shalom-result,
          #${CONTAINER_ID} .codered-shalom-empty { color: #f9fafb !important; }
          #${CONTAINER_ID} .codered-shalom-result:hover { background: #1f2937 !important; }
          #${CONTAINER_ID} .${STATUS_CLASS} { color: #d1d5db !important; }
        }
      </style>
      <input id="${SEARCH_INPUT_ID}" type="search" placeholder="Buscar agencia Shalom..." autocomplete="off" />
      <span class="${STATUS_CLASS}" aria-live="polite"></span>
      <ul class="${RESULT_LIST_CLASS}" role="listbox"></ul>
    `;
    return container;
  }

  function bindSearchEvents(container: HTMLElement): void {
    if (container.dataset.coderedSearchBound === 'true') return;
    container.dataset.coderedSearchBound = 'true';

    const input = container.querySelector<HTMLInputElement>(`#${SEARCH_INPUT_ID}`);
    const results = container.querySelector<HTMLElement>(`.${RESULT_LIST_CLASS}`);
    const status = container.querySelector<HTMLElement>(`.${STATUS_CLASS}`);
    if (!input || !results || !status) return;

    let debounce: number | null = null;
    input.addEventListener('input', () => {
      if (debounce) window.clearTimeout(debounce);
      debounce = window.setTimeout(() => renderResults(input, results, status), 150);
    });

    input.addEventListener('focus', () => {
      renderResults(input, results, status);
      results.style.display = 'block';
    });

    const abortController = new window.AbortController();
    container.dataset.coderedAbortController = 'true';
    document.addEventListener('click', (event) => {
      if (!container.contains(event.target as Node)) results.style.display = 'none';
    }, { signal: abortController.signal });
  }

  function injectSearchIfPossible(): InjectionResult {
    if (!document.body) return { success: false, reason: 'body-not-ready' };
    if (!isSupportedShalomPage()) return { success: false, reason: 'unsupported-page' };

    const existing = document.getElementById(CONTAINER_ID);
    if (existing?.isConnected) {
      bindSearchEvents(existing);
      bindChannelButtons(document, setActiveChannel);
      console.log('[CodeRED Shalom] Buscador ya estaba inyectado');
      return { success: true, reason: 'already-mounted', element: existing };
    }

    const target = findInjectionTarget();
    if (!target) return { success: false, reason: 'target-not-found' };

    const container = createSearchContainer();
    const spacer = target.element.querySelector('.mdl-layout-spacer');
    if (spacer) spacer.before(container);
    else target.element.appendChild(container);
    bindSearchEvents(container);
    bindChannelButtons(document, setActiveChannel);
    console.log('[CodeRED Shalom] Buscador inyectado');
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
        const existing = document.getElementById(CONTAINER_ID);
        if (!existing?.isConnected) console.log('[CodeRED Shalom] El header cambió; reinyectando');
        injectSearchIfPossible();
      }, 100);
    });

    injectionObserver.observe(root, { childList: true, subtree: true });
  }

  function stopInjectionObserver(): void {
    if (injectionDebounceTimer) window.clearTimeout(injectionDebounceTimer);
    injectionDebounceTimer = null;
    injectionObserver?.disconnect();
    injectionObserver = null;
  }

  function mount(): Promise<InjectionResult> {
    return cargarDatos(activeChannel).then(() => injectSearchIfPossible());
  }

  function setActiveChannel(nextChannel: Exclude<ShalomChannel, 'AUTO'>): void {
    activeChannel = nextChannel;
    console.log(`[CodeRED Shalom] Segmento activo: ${activeChannel}`);
  }

  function renderResults(input: HTMLInputElement, resultsContainer: HTMLElement, status: HTMLElement): void {
    resultsContainer.innerHTML = '';
    resultsContainer.style.display = 'block';
    const query = input.value.trim();

    if (agencies.length === 0) {
      appendMessage(resultsContainer, 'No hay agencias sincronizadas. Abre la configuración y pulsa Sincronizar ahora');
      return;
    }

    if (query.length < 2) {
      status.textContent = '';
      return;
    }

    const destination = findActiveDestinationSelect(document);
    status.textContent = destination instanceof HTMLSelectElement ? `Canal: ${activeChannel}` : 'No hay selector de destino.';

    const found = searchAgencies(agencies, query, 8);
    if (found.length === 0) {
      appendMessage(resultsContainer, 'No se encontraron agencias.');
      return;
    }

    for (const { agency } of found) {
      resultsContainer.appendChild(createResultCard(agency, input, resultsContainer, status));
    }
  }

  function createResultCard(agency: Agency, input: HTMLInputElement, results: HTMLElement, status: HTMLElement): HTMLElement {
    const button = document.createElement('button');
    button.className = 'codered-shalom-result';
    button.type = 'button';

    const title = [agency.name, agency.code].filter(Boolean).join(' - ');
    const details = [agency.department, agency.province, agency.district].filter(Boolean).join(' / ');
    button.innerHTML = `<strong>${escapeHtml(title)}</strong><br><small>${escapeHtml(details)}</small>`;

    button.addEventListener('click', () => {
      const selected = selectAgencyInDestination(document, agency, activeChannel);
      if (selected.success) {
        input.value = '';
        results.innerHTML = '';
        results.style.display = 'none';
        status.textContent = 'Agencia seleccionada';
        window.setTimeout(() => (status.textContent = ''), 2000);
      } else {
        status.textContent = selected.message;
      }
    });

    const mapsLink = document.createElement('a');
    mapsLink.href = buildMapsUrl(agency);
    mapsLink.target = '_blank';
    mapsLink.rel = 'noopener noreferrer';
    mapsLink.textContent = 'Ver mapa';
    mapsLink.addEventListener('click', (event) => event.stopPropagation());

    const wrapper = document.createElement('li');
    wrapper.append(button, mapsLink);
    return wrapper;
  }

  function appendMessage(resultsContainer: HTMLElement, message: string): void {
    const item = document.createElement('li');
    item.className = 'codered-shalom-empty';
    item.textContent = message;
    resultsContainer.appendChild(item);
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
      void cargarDatos(activeChannel);
    });
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

  return {
    cargarDatos,
    createSearchContainer,
    bindSearchEvents,
    findInjectionTarget,
    injectSearchIfPossible,
    initializeContentScript,
    isSupportedShalomPage,
    mount,
    startInjectionObserver,
    stopInjectionObserver,
  };
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

function main(): void {
  if (typeof document === 'undefined') return;
  const controller = createShalomContentController();
  const bootstrap = () => {
    controller.initializeContentScript().catch((error) => {
      console.error('[CodeRED Shalom] Error de inicialización:', serializeSafeError(error));
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
  } else {
    bootstrap();
  }
}

main();
