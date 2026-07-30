import type { Agency } from '../models/agency';
import { searchAgencies } from '../search/agency-search';
import { buildMapsUrl } from '../utils/format';
import { findActiveDestinationSelect, selectAgencyInDestination } from './agency-selector';
import { bindChannelButtons, detectActiveChannel, findHeader, type ShalomChannel } from './shalom-page-adapter';

const CONTAINER_ID = 'mi-buscador-contenedor';

interface ContentServices {
  requestCatalog(): Promise<Agency[]>;
  requestStatus(): Promise<{ agencyCount: number; message?: string }>;
}

export function createShalomContentController(services: ContentServices) {
  let channel: Exclude<ShalomChannel, 'AUTO'> = 'TERRESTRE';
  let agencies: Agency[] = [];
  let observer: MutationObserver | null = null;
  let pending = false;

  async function mount(): Promise<void> {
    if (!document.body) return;
    channel = detectActiveChannel(document);
    bindChannelButtons(document, (next) => {
      channel = next;
      clearResults();
    });
    await inject();
    if (!observer) {
      observer = new MutationObserver(() => {
        if (pending) return;
        pending = true;
        window.setTimeout(() => {
          pending = false;
          void inject();
          bindChannelButtons(document, (next) => {
            channel = next;
            clearResults();
          });
        }, 80);
      });
      observer.observe(document.body, { childList: true, subtree: true });
    }
  }

  async function inject(): Promise<void> {
    if (document.getElementById(CONTAINER_ID)) return;
    const header = findHeader(document);
    if (!header) return;
    agencies = agencies.length > 0 ? agencies : await services.requestCatalog();
    header.append(createSearchUi());
  }

  function createSearchUi(): HTMLElement {
    const container = document.createElement('div');
    container.id = CONTAINER_ID;
    container.className = 'codered-shalom-search';
    const input = document.createElement('input');
    input.type = 'search';
    input.placeholder = 'Buscar agencia';
    input.autocomplete = 'off';
    const status = document.createElement('span');
    status.className = 'codered-shalom-status';
    const results = document.createElement('div');
    results.className = 'codered-shalom-results';
    let debounce = 0;
    input.addEventListener('input', () => {
      window.clearTimeout(debounce);
      debounce = window.setTimeout(() => renderResults(input, results, status), 140);
    });
    container.append(input, status, results);
    return container;
  }

  function renderResults(input: HTMLInputElement, results: HTMLElement, status: HTMLElement): void {
    results.replaceChildren();
    const query = input.value.trim();
    if (query.length < 2) return;
    const destination = findActiveDestinationSelect(document);
    status.textContent = destination instanceof HTMLSelectElement ? `Canal ${channel}` : 'Sin selector de destino en esta pantalla';
    for (const { agency } of searchAgencies(agencies, query, 8)) {
      results.append(createCard(agency, input, results, status));
    }
  }

  function createCard(agency: Agency, input: HTMLInputElement, results: HTMLElement, status: HTMLElement): HTMLElement {
    const card = document.createElement('button');
    card.type = 'button';
    card.className = 'codered-shalom-card';
    card.textContent = [agency.name, agency.code, agency.isOperationsCenter ? 'CO' : null, agency.airText ? 'AEREO' : null, agency.terrestrialText ? 'TERRESTRE' : null, [agency.department, agency.province, agency.district].filter(Boolean).join(' / ')].filter(Boolean).join(' · ');
    card.addEventListener('click', () => {
      const selected = selectAgencyInDestination(document, agency, channel);
      if (selected.success) {
        input.value = '';
        results.replaceChildren();
        status.textContent = 'Agencia seleccionada';
      } else {
        status.textContent = selected.message;
      }
    });
    const maps = document.createElement('a');
    maps.href = buildMapsUrl(agency);
    maps.target = '_blank';
    maps.rel = 'noopener noreferrer';
    maps.textContent = 'Maps';
    card.append(' ', maps);
    return card;
  }

  function clearResults(): void {
    const container = document.getElementById(CONTAINER_ID);
    const input = container?.querySelector('input');
    if (input instanceof HTMLInputElement) input.value = '';
    container?.querySelector('.codered-shalom-results')?.replaceChildren();
  }

  function disconnect(): void {
    observer?.disconnect();
    observer = null;
  }

  return { mount, disconnect };
}

if (typeof chrome !== 'undefined' && chrome.runtime?.sendMessage) {
  const controller = createShalomContentController({
    requestCatalog: async () => {
      const response = await chrome.runtime.sendMessage({ type: 'CATALOG_GET' });
      return response?.agencies ?? [];
    },
    requestStatus: async () => {
      const response = await chrome.runtime.sendMessage({ type: 'CATALOG_STATUS' });
      return { agencyCount: response?.agencyCount ?? 0, message: response?.message };
    },
  });
  void controller.mount();
}
