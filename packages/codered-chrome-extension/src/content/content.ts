/**
 * Content script para el Buscador Shalom Control
 *
 * Responsabilidades:
 * 1.  Verificar si la página actual es una instancia soportada de Shalom Control.
 * 2.  Inyectar la barra de búsqueda en el lugar apropiado del DOM.
 * 3.  Mantener la barra de búsqueda presente durante navegaciones SPA.
 * 4.  Cargar el catálogo de agencias desde el service worker.
 * 5.  Manejar la interacción del usuario con la búsqueda y la selección de agencias.
 * 6.  Actualizar el selector de destino de Shalom (componente Chosen).
 */
import type { Agency } from '../models/agency';
import { searchAgencies } from '../search/agency-search';
import { buildMapsUrl } from '../utils/format';
import { findActiveDestinationSelect, selectAgencyInDestination } from './agency-selector';
import { bindChannelButtons, detectActiveChannel, type ShalomChannel } from './shalom-page-adapter';

const CONTAINER_ID = 'mi-buscador-contenedor';
const SEARCH_INPUT_ID = 'codered-search-input';
let agencies: Agency[] = [];
let activeChannel: Exclude<ShalomChannel, 'AUTO'> = 'TERRESTRE';
let injectionObserver: MutationObserver | null = null;
let injectionDebounceTimer: number | null = null;

// --- 1. Inicialización ---

/**
 * Punto de entrada principal para el content script.
 */
async function initializeContentScript(): Promise<void> {
  console.log('[Shalom Pro] Content script iniciado');

  // Carga el catálogo de agencias.
  await requestCatalog();

  // Inicia la inyección del buscador.
  await injectSearchIfPossible();
  startInjectionObserver();

  // Escucha cambios en el catálogo para recargar.
  listenForCatalogChanges();
}

/**
 * Envuelve el inicio en un try-catch y maneja el estado de carga del documento.
 */
function main() {
  const bootstrap = () => {
    initializeContentScript().catch((error) => {
      console.error('[Shalom Pro] Falló la inicialización del content script', error);
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
  } else {
    bootstrap();
  }
}

// --- 2. Lógica de Inyección ---

/**
 * Orquesta la inyección del buscador. Es idempotente y seguro llamarla múltiples veces.
 */
async function injectSearchIfPossible(): Promise<{ success: boolean; reason: string; element?: HTMLElement }> {
  if (!(await isSupportedShalomPage())) {
    return { success: false, reason: 'unsupported-page' };
  }

  const existing = document.getElementById(CONTAINER_ID);
  if (existing?.isConnected) {
    bindChannelButtons(document, (nextChannel) => (activeChannel = nextChannel));
    return { success: true, reason: 'already-mounted', element: existing };
  }

  const target = findInjectionTarget();
  if (!target) {
    console.log('[Shalom Pro] Target no encontrado');
    return { success: false, reason: 'target-not-found' };
  }

  console.log(`[Shalom Pro] Target encontrado con selector: ${target.selector}`);
  const container = createSearchUi();
  const spacer = target.element.querySelector('.mdl-layout-spacer');
  if (spacer) {
    spacer.before(container);
  } else {
    target.element.appendChild(container);
  }

  bindChannelButtons(document, (nextChannel) => (activeChannel = nextChannel));
  console.log('[Shalom Pro] Buscador inyectado');
  return { success: true, reason: 'mounted', element: container };
}

/**
 * Verifica si el dominio actual está en la lista de dominios permitidos.
 */
async function isSupportedShalomPage(): Promise<boolean> {
  const hostname = window.location.hostname.toLowerCase();
  console.log(`[Shalom Pro] URL actual: ${hostname}`);

  // En el futuro, esta lista podría venir de la configuración de la extensión.
  const allowedDomains = ['shalom.pe', 'shalomcontrol.com'];

  if (allowedDomains.length === 0) {
    console.log('[Shalom Pro] Dominio permitido (lista de permitidos vacía).');
    return true;
  }

  const isAllowed = allowedDomains.some((domain) => hostname === domain || hostname.endsWith(`.${domain}`));

  if (isAllowed) {
    console.log('[Shalom Pro] Dominio permitido.');
    return true;
  }

  console.warn('[Shalom Pro] Inyección omitida', {
    reason: 'domain-not-allowed',
    hostname,
    allowedDomains,
  });
  return false;
}

/**
 * Busca el mejor lugar para inyectar el buscador, probando una lista de selectores.
 */
function findInjectionTarget(): { element: HTMLElement; selector: string } | null {
  console.log('[Shalom Pro] Buscando punto de inyección');
  const selectors = ['.mdl-layout__header-row', 'header .mdl-layout__header-row', '.mdl-layout__header', 'header', '[role="banner"]', '.navbar', '.topbar', '.header'];

  for (const selector of selectors) {
    const element = document.querySelector<HTMLElement>(selector);
    if (element && isElementVisible(element) && !element.closest(`#${CONTAINER_ID}`)) {
      return { element, selector };
    }
  }
  return null;
}

function isElementVisible(el: HTMLElement): boolean {
  return el.offsetWidth > 0 && el.offsetHeight > 0 && !el.hasAttribute('hidden') && el.getAttribute('aria-hidden') !== 'true';
}

// --- 3. Creación de la UI del Buscador ---

function createSearchUi(): HTMLElement {
  const container = document.createElement('div');
  container.id = CONTAINER_ID;
  const style = document.createElement('style');
  style.textContent = `
    #${CONTAINER_ID} { display: flex !important; visibility: visible !important; opacity: 1 !important; min-width: 250px; flex-shrink: 0; position: relative; z-index: 1001; margin: 0 16px; }
    #${SEARCH_INPUT_ID} { width: 100%; padding: 8px 12px; border-radius: 4px; border: 1px solid #ccc; }
    .${CONTAINER_ID}-results { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ccc; border-radius: 4px; max-height: 300px; overflow-y: auto; z-index: 1002; }
    .${CONTAINER_ID}-results li { list-style: none; padding: 0; margin: 0; }
    .${CONTAINER_ID}-results button { display: block; width: 100%; text-align: left; padding: 8px 12px; border: none; background: none; cursor: pointer; }
    .${CONTAINER_ID}-results button:hover { background-color: #f0f0f0; }
    .${CONTAINER_ID}-status { margin-left: 8px; white-space: nowrap; align-self: center; font-size: 12px; color: #666; }
  `;
  container.appendChild(style);

  const input = document.createElement('input');
  input.id = SEARCH_INPUT_ID;
  input.type = 'search';
  input.placeholder = 'Buscar agencia Shalom...';
  input.autocomplete = 'off';

  const status = document.createElement('span');
  status.className = `${CONTAINER_ID}-status`;
  const results = document.createElement('ul');
  results.className = `${CONTAINER_ID}-results`;
  results.style.display = 'none';

  let debounce: number;
  input.addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = window.setTimeout(() => renderResults(input, results, status), 150);
  });

  document.addEventListener('click', (e) => {
    if (!container.contains(e.target as Node)) {
      results.style.display = 'none';
    }
  });

  input.addEventListener('focus', () => {
    results.style.display = 'block';
  });

  container.append(input, status, results);
  return container;
}

function renderResults(input: HTMLInputElement, resultsContainer: HTMLElement, status: HTMLElement) {
  resultsContainer.innerHTML = '';
  const query = input.value.trim();

  if (agencies.length === 0) {
    resultsContainer.innerHTML = '<li>No hay agencias. Sincroniza desde la configuración.</li>';
    return;
  }

  if (query.length < 2) {
    status.textContent = '';
    return;
  }

  const destination = findActiveDestinationSelect(document);
  status.textContent = destination ? `Canal: ${activeChannel}` : 'No hay selector de destino.';

  const found = searchAgencies(agencies, query, 8);
  if (found.length === 0) {
    resultsContainer.innerHTML = '<li>No se encontraron agencias.</li>';
    return;
  }

  for (const { agency } of found) {
    resultsContainer.appendChild(createResultCard(agency, input, resultsContainer, status));
  }
}

function createResultCard(agency: Agency, input: HTMLInputElement, results: HTMLElement, status: HTMLElement): HTMLElement {
  const card = document.createElement('button');
  card.type = 'button';

  const title = [agency.name, agency.code].filter(Boolean).join(' · ');
  const details = [agency.department, agency.province, agency.district].filter(Boolean).join(' / ');
  card.innerHTML = `<strong>${title}</strong><br><small>${details}</small>`;

  card.addEventListener('click', () => {
    const selected = selectAgencyInDestination(document, agency, activeChannel);
    if (selected.success) {
      input.value = '';
      results.innerHTML = '';
      status.textContent = '¡Agencia seleccionada!';
      setTimeout(() => (status.textContent = ''), 2000);
    } else {
      status.textContent = selected.message;
    }
  });

  const mapsLink = document.createElement('a');
  mapsLink.href = buildMapsUrl(agency);
  mapsLink.target = '_blank';
  mapsLink.rel = 'noopener noreferrer';
  mapsLink.textContent = 'Ver mapa';
  mapsLink.onclick = (e) => e.stopPropagation();

  const wrapper = document.createElement('li');
  wrapper.append(card, mapsLink);
  return wrapper;
}

// --- 4. Soporte para SPA y Observadores ---

/**
 * Inicia un MutationObserver para detectar cambios en el DOM y reinyectar el buscador si es necesario.
 */
function startInjectionObserver() {
  if (injectionObserver) return;

  injectionObserver = new MutationObserver(() => {
    if (injectionDebounceTimer) return;
    injectionDebounceTimer = window.setTimeout(async () => {
      console.log('[Shalom Pro] DOM modificado, verificando inyección...');
      await injectSearchIfPossible();
      injectionDebounceTimer = null;
    }, 100);
  });

  injectionObserver.observe(document.body, { childList: true, subtree: true });
}

// --- 5. Comunicación con el Service Worker ---

async function requestCatalog(): Promise<void> {
  if (typeof chrome?.runtime?.sendMessage !== 'function') {
    console.error('[Shalom Pro] chrome.runtime.sendMessage no está disponible.');
    agencies = [];
    return;
  }
  try {
    const response = await chrome.runtime.sendMessage({ type: 'CATALOG_GET' });
    agencies = response?.agencies ?? [];
    console.log(`[Shalom Pro] Catálogo local cargado: ${agencies.length} agencias`);
  } catch (error) {
    console.error('[Shalom Pro] Falló la carga del catálogo', error);
    agencies = [];
  }
}

function listenForCatalogChanges() {
  if (typeof chrome?.storage?.onChanged?.addListener !== 'function') return;

  chrome.storage.onChanged.addListener(async (changes, areaName) => {
    if (areaName !== 'local' || !changes.agencies) return;

    console.log('[Shalom Pro] El catálogo de agencias cambió, recargando datos...');
    await requestCatalog();

    const searchInput = document.getElementById(SEARCH_INPUT_ID) as HTMLInputElement | null;
    if (searchInput?.value) {
      searchInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });
}

// Iniciar todo
main();
