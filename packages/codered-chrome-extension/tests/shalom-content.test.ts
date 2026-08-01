import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import manifest from '../manifest.json' with { type: 'json' };
import { JSDOM } from 'jsdom';
import { adaptAgency } from '../src/models/agency';
import { isRuntimeRequest } from '../src/background/messages';
import { createShalomContentController } from '../src/content/content';
import { detectActiveChannel } from '../src/content/shalom-page-adapter';
import { findActiveDestinationSelect, selectAgencyInDestination } from '../src/content/agency-selector';
import { hostnameMatchesAllowedDomain, isSupportedShalomHost } from '../src/content/shalom-host';

const terrestrialAgency = adaptAgency({
  external_id: 1001,
  code: 'CHH',
  name: 'CHICLAYO HUB',
  department: 'LAMBAYEQUE',
  province: 'CHICLAYO',
  district: 'CHICLAYO',
  texto_chosen_terrestre: '1001 - CHICLAYO HUB - TERRESTRE',
  texto_chosen_aereo: '1001 - CHICLAYO HUB - AÉREO',
  status: 'active',
});

const duplicateCodeAgency = adaptAgency({
  external_id: 1002,
  code: 'CHH',
  name: 'CHACHAPOYAS HUB',
  department: 'AMAZONAS',
  province: 'CHACHAPOYAS',
  district: 'CHACHAPOYAS',
  texto_chosen_terrestre: '1002 - CHACHAPOYAS HUB - TERRESTRE',
  status: 'active',
});

describe('supported Shalom hosts', () => {
  it('accepts shalomcontrol root, known subdomains, future subdomains, multi-level subdomains, and shalom.pe', () => {
    expect(isSupportedShalomHost('shalomcontrol.com')).toBe(true);
    expect(isSupportedShalomHost('sysprovincia2.shalomcontrol.com')).toBe(true);
    expect(isSupportedShalomHost('syslima.shalomcontrol.com')).toBe(true);
    expect(isSupportedShalomHost('nuevo.servicio.shalomcontrol.com.')).toBe(true);
    expect(isSupportedShalomHost('shalom.pe')).toBe(true);
    expect(isSupportedShalomHost('ventas.shalom.pe')).toBe(true);
  });

  it('accepts exact allowed domains and subdomains without first-label matching', () => {
    expect(hostnameMatchesAllowedDomain('shalom.pe', 'shalom.pe')).toBe(true);
    expect(hostnameMatchesAllowedDomain('control.shalom.pe', 'shalom.pe')).toBe(true);
    expect(hostnameMatchesAllowedDomain('www.shalom.pe', 'www.shalom.pe')).toBe(true);
    expect(hostnameMatchesAllowedDomain('platform.codered.host', 'codered.host')).toBe(true);
  });

  it('rejects lookalike malicious domains and platform injection', () => {
    expect(isSupportedShalomHost('shalomcontrol.com.evil.example')).toBe(false);
    expect(isSupportedShalomHost('fake-shalomcontrol.com')).toBe(false);
    expect(isSupportedShalomHost('shalomcontrol.example')).toBe(false);
    expect(isSupportedShalomHost('platform.codered.host')).toBe(false);
  });
});

describe('manifest injection scope', () => {
  it('injects only on Shalom hosts while keeping CodeRED Platform as host permission', () => {
    expect(manifest.host_permissions).toContain('https://platform.codered.host/*');
    expect(manifest.content_scripts[0].matches).toEqual([
      'https://shalom.pe/*',
      'https://*.shalom.pe/*',
      'https://shalomcontrol.com/*',
      'https://*.shalomcontrol.com/*',
    ]);
    expect(manifest.content_scripts[0].matches).not.toContain('https://platform.codered.host/*');
    expect(manifest.content_scripts[0].run_at).toBe('document_idle');
  });
});

describe('Shalom Control DOM integration', () => {
  beforeEach(() => {
    vi.useRealTimers();
    const dom = new JSDOM('<!doctype html><html><body><div class="mdl-layout__header-row"></div><main></main></body></html>', { url: 'https://sysprovincia2.shalomcontrol.com/' });
    globalThis.window = dom.window as unknown as Window & typeof globalThis;
    globalThis.document = dom.window.document;
    globalThis.MutationObserver = dom.window.MutationObserver;
    globalThis.HTMLElement = dom.window.HTMLElement;
    globalThis.HTMLSelectElement = dom.window.HTMLSelectElement;
    globalThis.Event = dom.window.Event;
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('injects immediately when the header already exists', async () => {
    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency], requestStatus: async () => ({ agencyCount: 1 }) });
    await controller.mount();
    expect(document.getElementById('mi-buscador-contenedor')).toBeInstanceOf(HTMLElement);
    expect(document.querySelector<HTMLInputElement>('#codered-search-input')).toBeInstanceOf(HTMLElement);
  });

  it('injects when the header appears after the observer starts', async () => {
    vi.useFakeTimers();
    document.body.innerHTML = '<main></main>';
    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency], requestStatus: async () => ({ agencyCount: 1 }) });
    controller.startInjectionObserver();
    const header = document.createElement('header');
    document.body.prepend(header);
    await Promise.resolve();
    vi.advanceTimersByTime(120);
    await Promise.resolve();
    expect(document.querySelectorAll('#mi-buscador-contenedor')).toHaveLength(1);
  });

  it('injects the search container once and reinjects when header is replaced', async () => {
    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency], requestStatus: async () => ({ agencyCount: 1 }) });
    await controller.mount();
    await controller.mount();
    expect(document.querySelectorAll('#mi-buscador-contenedor')).toHaveLength(1);

    document.querySelector('.mdl-layout__header-row')?.remove();
    expect(document.querySelectorAll('#mi-buscador-contenedor')).toHaveLength(0);
    const header = document.createElement('div');
    header.className = 'mdl-layout__header-row';
    document.body.prepend(header);
    await controller.mount();
    expect(document.querySelectorAll('#mi-buscador-contenedor')).toHaveLength(1);
  });

  it('falls back to a generic header target', async () => {
    document.body.innerHTML = '<header><nav></nav></header>';
    const controller = createShalomContentController({ requestCatalog: async () => [] });
    expect(await controller.mount()).toMatchObject({ success: true, reason: 'mounted' });
    expect(document.querySelector('header #mi-buscador-contenedor')).toBeInstanceOf(HTMLElement);
  });

  it('keeps the interface visible without catalog and shows the empty catalog message on input', async () => {
    const controller = createShalomContentController({ requestCatalog: async () => [] });
    await controller.mount();
    const input = document.querySelector<HTMLInputElement>('#codered-search-input')!;
    input.value = 'chi';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 180));
    expect(document.getElementById('mi-buscador-contenedor')).toBeInstanceOf(HTMLElement);
    expect(document.body.textContent).toContain('No hay agencias sincronizadas. Abre la configuración y pulsa Sincronizar ahora');
  });

  it('does not inject on CodeRED Platform', async () => {
    const dom = new JSDOM('<!doctype html><html><body><header></header></body></html>', { url: 'https://platform.codered.host/' });
    globalThis.window = dom.window as unknown as Window & typeof globalThis;
    globalThis.document = dom.window.document;
    globalThis.HTMLElement = dom.window.HTMLElement;
    const controller = createShalomContentController({ requestCatalog: async () => [] });
    expect(await controller.mount()).toMatchObject({ success: false, reason: 'unsupported-page' });
    expect(document.getElementById('mi-buscador-contenedor')).toBeNull();
  });

  it('does not duplicate channel listeners across repeated mounts', async () => {
    document.body.insertAdjacentHTML('beforeend', '<button id="tab-t" title="Terrestre">Terrestre</button><button id="tab-a" title="Aéreo">Aéreo</button>');
    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency], requestStatus: async () => ({ agencyCount: 1 }) });
    await controller.mount();
    await controller.mount();
    expect(document.querySelector('#tab-t')?.getAttribute('data-codered-channel-bound')).toBe('true');
    expect(document.querySelector('#tab-a')?.getAttribute('data-codered-channel-bound')).toBe('true');
  });

  it('detects Terrestre and Aereo with title, text, onclick, active classes, and aria-selected', () => {
    document.body.innerHTML = '<button title="Enviar Terrestre" class="active">T</button><button title="Servicio Aéreo">A</button>';
    expect(detectActiveChannel(document)).toBe('TERRESTRE');
    document.body.innerHTML = '<button onclick="cambiar(\'AEREO\')" aria-selected="true">Aereo</button>';
    expect(detectActiveChannel(document)).toBe('AEREO');
  });

  it('finds visible enabled osProDestino select and ignores hidden or disabled candidates', () => {
    document.body.innerHTML = '<select id="x_osProDestino_hidden" style="display:none"><option>A</option></select><select id="x_osProDestino_disabled" disabled><option>B</option></select><section><select id="x_osProDestino_active"><option>C</option></select></section>';
    const select = findActiveDestinationSelect(document);
    expect(select).toBeInstanceOf(HTMLSelectElement);
    expect((select as HTMLSelectElement).id).toBe('x_osProDestino_active');
  });

  it('returns ambiguous when multiple active destination selects remain', () => {
    document.body.innerHTML = '<select id="a_osProDestino"><option>A</option></select><select id="b_osProDestino"><option>B</option></select>';
    expect(findActiveDestinationSelect(document)).toEqual({ reason: 'multiple-active-selects', count: 2 });
  });

  it('selects terrestrial and air Chosen text with normalized comparison and dispatches input/change', () => {
    document.body.innerHTML = '<select id="x_osProDestino"><option value="">Seleccione</option><option value="t"> 1001 - chiclayo hub - terrestre </option><option value="a">1001 - CHICLAYO HUB - AEREO</option></select>';
    const select = document.querySelector('select')!;
    const input = vi.fn();
    const change = vi.fn();
    select.addEventListener('input', input);
    select.addEventListener('change', change);

    expect(selectAgencyInDestination(document, terrestrialAgency, 'TERRESTRE')).toMatchObject({ success: true, value: 't' });
    expect(select.value).toBe('t');
    expect(input).toHaveBeenCalledTimes(1);
    expect(change).toHaveBeenCalledTimes(1);
    expect(selectAgencyInDestination(document, terrestrialAgency, 'AEREO')).toMatchObject({ success: true, value: 'a' });
    expect(select.value).toBe('a');
  });

  it('does not change select when option is missing or ambiguous', () => {
    document.body.innerHTML = '<select id="x_osProDestino"><option value="old">Anterior</option><option value="a">1001 - CHICLAYO HUB - TERRESTRE</option><option value="b">1001 - CHICLAYO HUB - TERRESTRE</option></select>';
    const select = document.querySelector('select')!;
    select.value = 'old';
    expect(selectAgencyInDestination(document, terrestrialAgency, 'TERRESTRE')).toMatchObject({ success: false, reason: 'multiple-matching-options' });
    expect(select.value).toBe('old');

    expect(selectAgencyInDestination(document, duplicateCodeAgency, 'AEREO')).toMatchObject({ success: false, reason: 'missing-channel-text' });
    expect(select.value).toBe('old');
  });
});

describe('message contract', () => {
  it('accepts catalog messages and rejects token exposure to content scripts', () => {
    expect(isRuntimeRequest({ type: 'CATALOG_GET' })).toBe(true);
    expect(isRuntimeRequest({ type: 'CATALOG_SYNC' })).toBe(true);
    expect(isRuntimeRequest({ type: 'CATALOG_STATUS' })).toBe(true);
    expect(isRuntimeRequest({ type: 'CONFIG_GET' })).toBe(true);
    expect(isRuntimeRequest({ type: 'CONFIG_SAVE', apiBaseUrl: 'https://platform.codered.host/api/v1', token: 'crd_test' })).toBe(true);
    expect(isRuntimeRequest({ type: 'CATALOG_GET', token: 'crd_secret' })).toBe(false);
  });
});

describe('extension build artifacts', () => {
  it('uses stable classic content script and module background entries', async () => {
    const { readFileSync, existsSync } = await import('node:fs');
    const { spawnSync } = await import('node:child_process');
    const manifestText = readFileSync(new URL('../dist/manifest.json', import.meta.url), 'utf8');
    const builtManifest = JSON.parse(manifestText);

    expect(builtManifest.background).toEqual({ service_worker: 'background.js', type: 'module' });
    expect(builtManifest.content_scripts[0].js).toEqual(['content.js']);
    expect(existsSync(new URL('../dist/content.js', import.meta.url))).toBe(true);
    expect(existsSync(new URL('../dist/background.js', import.meta.url))).toBe(true);

    const content = readFileSync(new URL('../dist/content.js', import.meta.url), 'utf8');
    expect(content).not.toMatch(/^[ \t]*(import|export)[ \t]/m);
    expect(content).not.toMatch(/\bimport\s*\(/);
    expect(content).not.toMatch(/\brequire\s*\(/);

    const check = spawnSync(process.execPath, ['--check', new URL('../dist/content.js', import.meta.url).pathname], { encoding: 'utf8' });
    expect(check.stderr + check.stdout).toBe('');
    expect(check.status).toBe(0);
  });

  it('builds popup and options HTML with extension-safe relative resources', async () => {
    const { readFileSync } = await import('node:fs');
    for (const file of ['../dist/popup.html', '../dist/options.html']) {
      const html = readFileSync(new URL(file, import.meta.url), 'utf8');
      expect(html).not.toMatch(/href="\/assets|src="\/assets/);
      expect(html).not.toContain('modulepreload-polyfill');
    }
  });
});
