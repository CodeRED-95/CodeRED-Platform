import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import manifest from '../manifest.json' with { type: 'json' };
import { JSDOM } from 'jsdom';
import { adaptAgency } from '../src/models/agency';
import { isRuntimeRequest } from '../src/background/messages';
import { createShalomContentController, findSearchInsertionPoint, insertSearchContainer, positionResultsPanel } from '../src/content/content';
import { detectActiveChannel, detectActiveShalomChannelState } from '../src/content/shalom-page-adapter';
import { findActiveDestinationSelect, selectAgencyInDestination } from '../src/content/agency-selector';
import { getShalomPageCapabilities, hostnameMatchesAllowedDomain, isNeutralShalomSearchPath, isSupportedShalomHost, isSupportedShalomLocation, isSupportedShalomPath } from '../src/content/shalom-host';
import { searchAgencies } from '../src/search/agency-search';

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
  it('accepts any subdomain of shalomcontrol.com, including future and multi-level ones', () => {
    expect(isSupportedShalomHost('app.shalomcontrol.com')).toBe(true);
    expect(isSupportedShalomHost('sysprovincia2.shalomcontrol.com')).toBe(true);
    expect(isSupportedShalomHost('syslima.shalomcontrol.com')).toBe(true);
    expect(isSupportedShalomHost('cualquier-subdominio.shalomcontrol.com')).toBe(true);
    expect(isSupportedShalomHost('nuevo.servicio.shalomcontrol.com.')).toBe(true);
  });

  it('rejects the bare domain and any host outside shalomcontrol.com', () => {
    expect(isSupportedShalomHost('shalomcontrol.com')).toBe(false);
    expect(isSupportedShalomHost('shalom.pe')).toBe(false);
    expect(isSupportedShalomHost('ventas.shalom.pe')).toBe(false);
  });

  it('accepts exact allowed domains and subdomains without first-label matching', () => {
    expect(hostnameMatchesAllowedDomain('shalom.pe', 'shalom.pe')).toBe(true);
    expect(hostnameMatchesAllowedDomain('control.shalom.pe', 'shalom.pe')).toBe(true);
    expect(hostnameMatchesAllowedDomain('www.shalom.pe', 'www.shalom.pe')).toBe(true);
    expect(hostnameMatchesAllowedDomain('platform.codered.lat', 'codered.lat')).toBe(true);
  });

  it('rejects lookalike malicious domains and platform injection', () => {
    expect(isSupportedShalomHost('shalomcontrol.com.evil.example')).toBe(false);
    expect(isSupportedShalomHost('fake-shalomcontrol.com')).toBe(false);
    expect(isSupportedShalomHost('evil-shalomcontrol.com')).toBe(false);
    expect(isSupportedShalomHost('shalomcontrol.example')).toBe(false);
    expect(isSupportedShalomHost('platform.codered.lat')).toBe(false);
  });
});

describe('supported Shalom paths', () => {
  it('accepts the two authorized routes with an optional trailing slash', () => {
    expect(isSupportedShalomPath('/listaordenservicio')).toBe(true);
    expect(isSupportedShalomPath('/listaordenservicio/')).toBe(true);
    expect(isSupportedShalomPath('/ordenservicio/listar')).toBe(true);
    expect(isSupportedShalomPath('/ordenservicio/listar/')).toBe(true);
  });

  it('rejects nested, partial and lookalike routes', () => {
    expect(isSupportedShalomPath('/listaordenservicio/otra')).toBe(false);
    expect(isSupportedShalomPath('/ordenservicio')).toBe(false);
    expect(isSupportedShalomPath('/ordenservicio/')).toBe(false);
    expect(isSupportedShalomPath('/ordenservicio/listar/otra')).toBe(false);
    expect(isSupportedShalomPath('/listaordenservicio2')).toBe(false);
    expect(isSupportedShalomPath('/otra/listaordenservicio')).toBe(false);
    expect(isSupportedShalomPath('/')).toBe(false);
    expect(isSupportedShalomPath('')).toBe(false);
  });

  it('ignores query string and fragment when they are present', () => {
    expect(isSupportedShalomPath('/listaordenservicio?page=2')).toBe(true);
    expect(isSupportedShalomPath('/ordenservicio/listar/#top')).toBe(true);
  });

  it('combines host and path in a single gate', () => {
    expect(isSupportedShalomLocation('app.shalomcontrol.com', '/listaordenservicio')).toBe(true);
    expect(isSupportedShalomLocation('cualquier-subdominio.shalomcontrol.com', '/ordenservicio/listar')).toBe(true);
    // Host valido pero ruta no autorizada
    expect(isSupportedShalomLocation('app.shalomcontrol.com', '/ordenservicio/listar/otra')).toBe(false);
    expect(isSupportedShalomLocation('app.shalomcontrol.com', '/inicio')).toBe(false);
    // Ruta autorizada pero host no permitido
    expect(isSupportedShalomLocation('shalomcontrol.com', '/listaordenservicio')).toBe(false);
    expect(isSupportedShalomLocation('ventas.shalom.pe', '/listaordenservicio')).toBe(false);
  });

  it('identifies listaordenservicio as a neutral search path', () => {
    expect(isNeutralShalomSearchPath('/listaordenservicio')).toBe(true);
    expect(isNeutralShalomSearchPath('/listaordenservicio/')).toBe(true);
    expect(isNeutralShalomSearchPath('/service-order')).toBe(true);
    expect(isNeutralShalomSearchPath('/service-order/')).toBe(true);
    expect(isNeutralShalomSearchPath('/ordenservicio/listar')).toBe(false);
  });

  it('marks listaordenservicio as consultation-only without destination selection', () => {
    expect(getShalomPageCapabilities('/listaordenservicio')).toMatchObject({
      mode: 'neutral',
      search: true,
      neutralChannel: true,
      agencySelection: false,
      channelDetection: false,
    });
  });
});

describe('manifest injection scope', () => {
  it('injects only on Shalom hosts while keeping CodeRED Platform as host permission', () => {
    expect(manifest.host_permissions).toContain('https://platform.codered.lat/*');
    // Compatibilidad legacy: se mantiene mientras dure la transicion desde
    // codered.host, para no romper instalaciones ya publicadas en la Web Store
    // que sigan apuntando al dominio anterior. Eliminar cuando codered.lat
    // este validado al 100% y se publique una nueva version de la extension.
    expect(manifest.host_permissions).toContain('https://platform.codered.host/*');
    // La inyeccion cubre todo shalomcontrol.com porque el bloqueo horario se
    // configura desde el panel de la Plataforma y puede apuntar a cualquier
    // ruta del dominio. El buscador sigue acotado por isSupportedShalomPage().
    expect(manifest.content_scripts[0].matches).toEqual(['https://*.shalomcontrol.com/*']);
    expect(manifest.content_scripts[0].matches.some((m) => m.includes('shalom.pe'))).toBe(false);
    expect(manifest.host_permissions.some((h) => h.includes('shalom.pe'))).toBe(false);
    // El alcance sigue limitado al dominio corporativo: nada de <all_urls>.
    expect(manifest.host_permissions).not.toContain('<all_urls>');
    expect(manifest.host_permissions.every((h) => h.includes('shalomcontrol.com') || h.includes('codered'))).toBe(true);
    expect(manifest.content_scripts[0].matches).not.toContain('https://platform.codered.lat/*');
    expect(manifest.content_scripts[0].matches).not.toContain('https://platform.codered.host/*');
    expect(manifest.content_scripts[0].run_at).toBe('document_idle');
  });
});

describe('Shalom Control DOM integration', () => {
  beforeEach(() => {
    vi.useRealTimers();
    const dom = new JSDOM('<!doctype html><html><body><div class="mdl-layout__header-row"></div><main></main></body></html>', { url: 'https://sysprovincia2.shalomcontrol.com/listaordenservicio' });
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


  it('inserts the search immediately before .mdl-navigation', async () => {
    document.body.innerHTML = '<div class="mdl-layout__header-row"><span class="mdl-layout-title"></span><span>Empresarial: ADM_TERMINAL</span><div class="mdl-layout-spacer"></div><nav class="mdl-navigation"></nav><button id="demo-menu-lower-right"></button></div>';
    const header = document.querySelector<HTMLElement>('.mdl-layout__header-row')!;
    const insertion = findSearchInsertionPoint(header);
    expect(insertion.reason).toBe('before-navigation');
    expect(insertion.before).toBe(document.querySelector('.mdl-navigation'));

    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency] });
    await controller.mount();
    const spacer = document.querySelector<HTMLElement>('.mdl-layout-spacer')!;
    const container = document.getElementById('mi-buscador-contenedor')!;
    const navigation = document.querySelector<HTMLElement>('.mdl-navigation')!;
    const menuButton = document.querySelector<HTMLElement>('#demo-menu-lower-right')!;

    expect(container.dataset.insertionReason).toBe('before-navigation');
    expect(container.previousElementSibling).toBe(spacer);
    expect(container.nextElementSibling).toBe(navigation);
    expect(container.nextElementSibling?.classList.contains('mdl-navigation')).toBe(true);
    expect(navigation.nextElementSibling).toBe(menuButton);
  });

  it('inserts before the menu button when .mdl-navigation is not present', () => {
    document.body.innerHTML = '<div class="mdl-layout__header-row"><div class="mdl-layout-spacer"></div><button id="demo-menu-lower-right"></button></div>';
    const header = document.querySelector<HTMLElement>('.mdl-layout__header-row')!;
    const container = document.createElement('div');
    container.id = 'mi-buscador-contenedor';

    insertSearchContainer(header, container);

    expect(container.dataset.insertionReason).toBe('before-menu');
    expect(container.nextElementSibling).toBe(document.querySelector('#demo-menu-lower-right'));
  });

  it('falls back to inserting after the spacer when there is no navigation or menu button', async () => {
    document.body.innerHTML = '<div class="mdl-layout__header-row"><span>Empresarial: ADM_TERMINAL</span><div class="mdl-layout-spacer"></div></div>';
    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency] });
    await controller.mount();
    const header = document.querySelector<HTMLElement>('.mdl-layout__header-row')!;
    const spacer = document.querySelector<HTMLElement>('.mdl-layout-spacer')!;
    const container = document.getElementById('mi-buscador-contenedor')!;
    expect(container.dataset.insertionReason).toBe('after-spacer');
    expect(Array.from(header.children).indexOf(spacer)).toBeLessThan(Array.from(header.children).indexOf(container));
  });

  it('aligns the panel to the right of the search and corrects left viewport overflow', async () => {
    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency] });
    await controller.mount();
    const container = document.getElementById('mi-buscador-contenedor')!;
    const panel = document.querySelector<HTMLElement>('.codered-results-panel')!;
    Object.defineProperty(window, 'innerWidth', { value: 1200, configurable: true });
    vi.spyOn(panel, 'getBoundingClientRect').mockReturnValue({ left: -80, right: 920, top: 0, bottom: 550, width: 1000, height: 550, x: -80, y: 0, toJSON: () => ({}) });

    positionResultsPanel(container, panel);
    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(panel.style.left).toBe('auto');
    expect(panel.style.right).toBe('0px');
    expect(panel.style.transform).toBe('translateX(146px)');
  });

  it('corrects right viewport overflow and recalculates on resize without duplicate listeners', async () => {
    const addEventSpy = vi.spyOn(window, 'addEventListener');
    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency] });
    await controller.mount();
    await controller.mount();
    const container = document.getElementById('mi-buscador-contenedor')!;
    const input = document.querySelector<HTMLInputElement>('#codered-search-input')!;
    const panel = document.querySelector<HTMLElement>('.codered-results-panel')!;
    Object.defineProperty(window, 'innerWidth', { value: 1000, configurable: true });
    vi.spyOn(panel, 'getBoundingClientRect').mockReturnValue({ left: 300, right: 1300, top: 0, bottom: 550, width: 1000, height: 550, x: 300, y: 0, toJSON: () => ({}) });

    input.value = 'chiclayo';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 180));
    positionResultsPanel(container, panel);
    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(panel.style.transform).toBe('translateX(-266px)');
    expect(addEventSpy.mock.calls.filter(([type]) => type === 'resize')).toHaveLength(1);

    panel.style.transform = 'none';
    window.dispatchEvent(new Event('resize'));
    await new Promise((resolve) => setTimeout(resolve, 130));
    expect(panel.style.transform).toBe('translateX(-266px)');
  });

  it('keeps responsive panel columns at desktop, tablet, and mobile breakpoints', async () => {
    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency] });
    await controller.mount();
    const style = document.querySelector('#mi-buscador-contenedor style')?.textContent ?? '';
    expect(style).toContain('grid-template-columns: repeat(3, minmax(0, 1fr))');
    expect(style).toContain('@media (max-width: 1100px)');
    expect(style).toContain('grid-template-columns: repeat(2, minmax(0, 1fr))');
    expect(style).toContain('@media (max-width: 720px)');
    expect(style).toContain('position: fixed');
    expect(style).toContain('grid-template-columns: 1fr');
  });

  it('injects immediately when the header already exists', async () => {
    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency], requestStatus: async () => ({ agencyCount: 1 }) });
    await controller.mount();
    expect(document.getElementById('mi-buscador-contenedor')).toBeInstanceOf(HTMLElement);
    expect(document.querySelector<HTMLInputElement>('#codered-search-input')).toBeInstanceOf(HTMLElement);
  });

  it('keeps listaordenservicio neutral when the DOM exposes both channels but none can be confirmed', async () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    document.body.innerHTML = '<div class="mdl-layout__header-row"><button type="button" title="Terrestre"></button><button type="button" title="Aéreo"></button></div><main></main>';

    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency] });
    await controller.mount();
    expect(detectActiveShalomChannelState(document)).toMatchObject({ channel: null, reason: 'ambiguous' });
    expect(warnSpy.mock.calls.filter(([message]) => String(message).includes('Canal activo no confirmado todavía'))).toHaveLength(0);

    await controller.mount();
    expect(warnSpy.mock.calls.filter(([message]) => String(message).includes('Canal activo no confirmado todavía'))).toHaveLength(0);
    warnSpy.mockRestore();
  });

  it('treats listaordenservicio as a neutral page without blocking the search when the channel is ambiguous', async () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const infoSpy = vi.spyOn(console, 'info').mockImplementation(() => undefined);
    document.body.innerHTML = '<div class="mdl-layout__header-row"><button type="button" title="Terrestre"></button><button type="button" title="Aéreo"></button></div><main></main>';

    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency] });
    await controller.mount();
    const input = document.querySelector<HTMLInputElement>('#codered-search-input')!;
    input.value = 'chiclayo';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 180));

    expect(warnSpy.mock.calls.some(([message]) => String(message).includes('Canal activo no confirmado todavía'))).toBe(false);
    expect(warnSpy.mock.calls.some(([message]) => String(message).includes('La detección del canal sigue ambigua'))).toBe(false);
    expect(document.body.textContent).toContain('Canal no identificado. Buscando en todas las agencias.');
    expect(document.querySelectorAll('.codered-agency-card')).toHaveLength(1);
    expect(infoSpy).not.toHaveBeenCalled();

    warnSpy.mockRestore();
    infoSpy.mockRestore();
  });

  it('does not try to select a destination on listaordenservicio', async () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    document.body.innerHTML = '<div class="mdl-layout__header-row"><button type="button" title="Terrestre"></button><button type="button" title="Aéreo"></button></div><main></main>';

    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency] });
    await controller.mount();
    const input = document.querySelector<HTMLInputElement>('#codered-search-input')!;
    input.value = 'chiclayo';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 180));

    document.querySelector<HTMLButtonElement>('.codered-agency-card')?.click();

    expect(warnSpy.mock.calls.some(([message]) => String(message).includes('No se pudo seleccionar agencia'))).toBe(false);
    expect(warnSpy.mock.calls.some(([message]) => String(message).includes('No se encontró el selector de destino activo de Shalom'))).toBe(false);
    expect(document.body.textContent).toContain('Esta página de Shalom solo permite consultar agencias.');
    warnSpy.mockRestore();
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
    document.body.insertAdjacentHTML('afterbegin', '<button id="truck" title="Terrestre" class="active">Camión</button><button id="plane" title="Aéreo">Avión</button>');
    const controller = createShalomContentController({ requestCatalog: async () => [] });
    await controller.mount();
    const input = document.querySelector<HTMLInputElement>('#codered-search-input')!;
    input.value = 'CHI';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 180));
    expect(document.getElementById('mi-buscador-contenedor')).toBeInstanceOf(HTMLElement);
    expect(document.body.textContent).toContain('No hay agencias sincronizadas. Abre la configuración de la extensión y pulsa Sincronizar ahora.');
  });

  it('does not inject on CodeRED Platform', async () => {
    const dom = new JSDOM('<!doctype html><html><body><header></header></body></html>', { url: 'https://platform.codered.lat/' });
    globalThis.window = dom.window as unknown as Window & typeof globalThis;
    globalThis.document = dom.window.document;
    globalThis.HTMLElement = dom.window.HTMLElement;
    const controller = createShalomContentController({ requestCatalog: async () => [] });
    expect(await controller.mount()).toMatchObject({ success: false, reason: 'unsupported-page' });
    expect(document.getElementById('mi-buscador-contenedor')).toBeNull();
  });

  it('does not inject on a Shalom host outside the authorized routes', async () => {
    const unsupportedPaths = ['/', '/inicio', '/ordenservicio', '/listaordenservicio/otra', '/service-order/otra', '/ordenservicio/listar/otra'];
    for (const path of unsupportedPaths) {
      const dom = new JSDOM('<!doctype html><html><body><div class="mdl-layout__header-row"></div></body></html>', { url: `https://app.shalomcontrol.com${path}` });
      globalThis.window = dom.window as unknown as Window & typeof globalThis;
      globalThis.document = dom.window.document;
      globalThis.HTMLElement = dom.window.HTMLElement;
      const requestCatalog = vi.fn(async () => [terrestrialAgency]);
      const controller = createShalomContentController({ requestCatalog });
      expect(await controller.mount()).toMatchObject({ success: false, reason: 'unsupported-page' });
      expect(document.getElementById('mi-buscador-contenedor')).toBeNull();
      // Ni siquiera se solicita el catalogo fuera de las rutas autorizadas.
      expect(requestCatalog).not.toHaveBeenCalled();
    }
  });

  it('injects on the authorized routes, with and without trailing slash', async () => {
    const supportedPaths = ['/listaordenservicio', '/listaordenservicio/', '/service-order', '/service-order/', '/ordenservicio/listar', '/ordenservicio/listar/'];
    for (const path of supportedPaths) {
      const html = path.includes('service-order')
        ? '<!doctype html><html><body><main class="mx-2 md:mx-5 lg:mx-10 xl:mx-auto xl:max-w-7xl flex flex-col md:h-screen service-order-module"><div class="flex lg:justify-between max-lg:flex-col py-3 lg:items-center gap-y-2 gap-x-20"><div class="flex gap-2 max-lg:flex-col flex-1"><div>Empresarial: ADM_TERMINAL</div></div><div class="flex items-center lg:gap-x-12 gap-x-4 justify-end"><div>AV. ARIAS ARAGUEZ</div><div class="flex items-center gap-x-2.5"><div>VICTOR SANTIAGO ARROYO BILBAO</div></div></div></div></main></body></html>'
        : '<!doctype html><html><body><div class="mdl-layout__header-row"></div></body></html>';
      const dom = new JSDOM(html, { url: `https://app.shalomcontrol.com${path}` });
      globalThis.window = dom.window as unknown as Window & typeof globalThis;
      globalThis.document = dom.window.document;
      globalThis.HTMLElement = dom.window.HTMLElement;
      const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency] });
      if (path.includes('service-order')) {
        expect(await controller.mount()).toMatchObject({ success: true });
        expect(document.querySelectorAll('#mi-buscador-contenedor')).toHaveLength(1);
        expect(document.querySelector('#mi-buscador-contenedor')?.nextElementSibling).not.toBeNull();
      } else {
        expect(await controller.mount()).toMatchObject({ success: true });
        expect(document.getElementById('mi-buscador-contenedor')).not.toBeNull();
      }
    }
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

  it('does not treat an unavailable agency as an error and returns an option-not-found result', () => {
    const infoSpy = vi.spyOn(console, 'info').mockImplementation(() => undefined);
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    document.body.innerHTML = '<select id="x_osProDestino"><option value="">Seleccione</option><option value="t">1001 - CHICLAYO HUB - TERRESTRE</option></select>';
    const unavailableAgency = adaptAgency({
      external_id: 2003,
      code: 'ARE03',
      name: 'AREQUIPA SUR',
      department: 'AREQUIPA',
      province: 'AREQUIPA',
      district: 'SACHACA',
      texto_chosen_terrestre: '2003 - AREQUIPA SUR - TERRESTRE',
      status: 'active',
    });

    expect(selectAgencyInDestination(document, unavailableAgency, 'TERRESTRE')).toMatchObject({ success: false, reason: 'option-not-found' });
    expect(infoSpy).not.toHaveBeenCalled();
    expect(warnSpy).not.toHaveBeenCalled();
    infoSpy.mockRestore();
    warnSpy.mockRestore();
  });

  it('selects agencies in neutral mode on listaordenservicio when the channel is unknown', () => {
    document.body.innerHTML = '<select id="x_osProDestino"><option value="">Seleccione</option><option value="t">1001 - CHICLAYO HUB - TERRESTRE</option><option value="a">1001 - CHICLAYO HUB - AEREO</option></select>';
    const select = document.querySelector('select')!;

    expect(selectAgencyInDestination(document, terrestrialAgency, 'AUTO')).toMatchObject({ success: true });
    expect(['t', 'a']).toContain(select.value);
  });

  it('keeps service-order neutral and never selects a destination', async () => {
    const dom = new JSDOM('<!doctype html><html><body><main class="mx-2 md:mx-5 lg:mx-10 xl:mx-auto xl:max-w-7xl flex flex-col md:h-screen service-order-module"><div class="flex lg:justify-between max-lg:flex-col py-3 lg:items-center gap-y-2 gap-x-20"><div class="flex gap-2 max-lg:flex-col flex-1"><div>Empresarial: ADM_TERMINAL</div></div><div class="flex items-center lg:gap-x-12 gap-x-4 justify-end"><div>AV. ARIAS ARAGUEZ</div><div class="flex items-center gap-x-2.5"><div>VICTOR SANTIAGO ARROYO BILBAO</div></div></div></div></main></body></html>', { url: 'https://app.shalomcontrol.com/service-order/' });
    globalThis.window = dom.window as unknown as Window & typeof globalThis;
    globalThis.document = dom.window.document;
    globalThis.MutationObserver = dom.window.MutationObserver;
    globalThis.HTMLElement = dom.window.HTMLElement;
    globalThis.HTMLSelectElement = dom.window.HTMLSelectElement;
    globalThis.Event = dom.window.Event;
    document.body.insertAdjacentHTML('beforeend', '<section><select id="x_osProDestino"><option value="">Seleccione</option><option value="t">1001 - CHICLAYO HUB - TERRESTRE</option></select></section>');
    const select = document.querySelector('select')!;
    const inputSpy = vi.fn();
    const changeSpy = vi.fn();
    select.addEventListener('input', inputSpy);
    select.addEventListener('change', changeSpy);

    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency] });
    await controller.mount();
    const input = document.querySelector<HTMLInputElement>('#codered-search-input')!;
    input.value = 'chiclayo';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 180));
    document.querySelector<HTMLButtonElement>('.codered-agency-card')?.click();

    expect(select.value).toBe('');
    expect(inputSpy).not.toHaveBeenCalled();
    expect(changeSpy).not.toHaveBeenCalled();
    expect(document.body.textContent).toContain('Modo neutral');
  });

  it('keeps technical failures as warnings with structured context', async () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => undefined);
    document.body.innerHTML = '<select id="a_osProDestino"><option value="">Seleccione</option><option value="t">1001 - CHICLAYO HUB - TERRESTRE</option></select><select id="b_osProDestino"><option value="">Seleccione</option><option value="u">1001 - CHICLAYO HUB - TERRESTRE</option></select>';

    const result = selectAgencyInDestination(document, terrestrialAgency, 'TERRESTRE');
    expect(result).toMatchObject({ success: false, reason: 'multiple-active-selects' });
    expect(warnSpy).not.toHaveBeenCalledWith(expect.stringContaining('[object Object]'));
    expect(errorSpy).not.toHaveBeenCalled();
    warnSpy.mockRestore();
    errorSpy.mockRestore();
  });
});

describe('message contract', () => {
  it('accepts catalog messages and rejects token exposure to content scripts', () => {
    expect(isRuntimeRequest({ type: 'CATALOG_GET' })).toBe(true);
    expect(isRuntimeRequest({ type: 'CATALOG_SYNC' })).toBe(true);
    expect(isRuntimeRequest({ type: 'CATALOG_STATUS' })).toBe(true);
    expect(isRuntimeRequest({ type: 'CONFIG_GET' })).toBe(true);
    expect(isRuntimeRequest({ type: 'CONFIG_SAVE', apiBaseUrl: 'https://platform.codered.lat/api/v1', token: 'crd_test' })).toBe(true);
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

    // fileURLToPath, no .pathname: en Windows .pathname devuelve "/E:/..." y
    // Node lo resuelve contra la unidad actual, produciendo "E:\E:\..." y un
    // MODULE_NOT_FOUND. fileURLToPath da la ruta nativa correcta en todos los SO.
    const { fileURLToPath } = await import('node:url');
    const contentPath = fileURLToPath(new URL('../dist/content.js', import.meta.url));
    const check = spawnSync(process.execPath, ['--check', contentPath], { encoding: 'utf8' });
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

describe('restored injected search experience', () => {
  beforeEach(() => {
    vi.useRealTimers();
    const dom = new JSDOM('<!doctype html><html><body><div class="mdl-layout__header-row"><div class="mdl-layout-spacer"></div></div></body></html>', { url: 'https://sysprovincia2.shalomcontrol.com/listaordenservicio' });
    globalThis.window = dom.window as unknown as Window & typeof globalThis;
    globalThis.document = dom.window.document;
    globalThis.MutationObserver = dom.window.MutationObserver;
    globalThis.HTMLElement = dom.window.HTMLElement;
    globalThis.HTMLSelectElement = dom.window.HTMLSelectElement;
    globalThis.Event = dom.window.Event;
  });

  it('renders the dark floating three-column grid and complete cards without inventing category', async () => {
    const fullAgency = adaptAgency({
      external_id: 2001,
      code: 'AREQ',
      name: 'AREQUIPA CENTRO',
      old_name: 'AREQUIPA ANTIGUA',
      department: 'AREQUIPA',
      province: 'AREQUIPA',
      district: 'YANAHUARA',
      address: 'Av. Ejercito 123',
      reference: 'Frente al mall',
      status: 'active',
      classification: { category: 'Grande', sends_category: 'Envia', receives_category: 'Recibe' },
      centro_operaciones: true,
      texto_chosen_terrestre: '2001 - AREQUIPA CENTRO - TERRESTRE',
    });
    const noCategory = adaptAgency({ name: 'AREQUIPA NORTE', department: 'AREQUIPA', texto_chosen_terrestre: '2002 - AREQUIPA NORTE - TERRESTRE', status: 'active' });
    const controller = createShalomContentController({ requestCatalog: async () => [fullAgency, noCategory] });
    await controller.mount();
    const input = document.querySelector<HTMLInputElement>('#codered-search-input')!;
    input.value = 'AREQ';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 180));

    const grid = document.querySelector<HTMLElement>('.codered-results-grid')!;
    const style = grid.closest('#mi-buscador-contenedor')?.querySelector('style')?.textContent ?? '';
    expect(style).toContain('grid-template-columns: repeat(3, minmax(0, 1fr))');
    expect(searchAgencies([fullAgency, noCategory], 'AREQUIPA')).toHaveLength(2);
    expect(fullAgency.category).toBe('Grande');
    expect(fullAgency.isOperationsCenter).toBe(true);
    expect(fullAgency.sendsCategory).toBe('Envia');
    expect(noCategory.category).toBeNull();
  });

  it('filters by active channel and switches from truck to plane without an Auto selector', async () => {
    document.body.insertAdjacentHTML('afterbegin', '<button id="truck" title="Terrestre" class="active">Camión</button><button id="plane" title="Aéreo">Avión</button>');
    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency] });
    await controller.mount();
    expect(document.querySelector('#mi-buscador-contenedor select')).toBeNull();
    expect(document.querySelector('.codered-channel-badge')?.textContent).toContain('Terrestre');

    const input = document.querySelector<HTMLInputElement>('#codered-search-input')!;
    input.value = 'CHH';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 180));
    expect(searchAgencies([terrestrialAgency], 'CHH')).toHaveLength(1);

    document.querySelector('#truck')?.classList.remove('active');
    document.querySelector('#plane')?.classList.add('active');
    (document.querySelector('#plane') as HTMLElement).click();
    await new Promise((resolve) => setTimeout(resolve, 10));
    expect(document.querySelector('.codered-channel-badge')?.textContent).toContain('Aéreo');
    expect(input.value).toBe('');
  });

  it('selects terrestrial and air chosen independently and does not select when clicking map', async () => {
    const dom = new JSDOM('<!doctype html><html><body></body></html>', { url: 'https://sysprovincia2.shalomcontrol.com/ordenservicio/listar' });
    globalThis.window = dom.window as unknown as Window & typeof globalThis;
    globalThis.document = dom.window.document;
    globalThis.MutationObserver = dom.window.MutationObserver;
    globalThis.HTMLElement = dom.window.HTMLElement;
    globalThis.HTMLSelectElement = dom.window.HTMLSelectElement;
    globalThis.Event = dom.window.Event;
    document.body.innerHTML = '<div class="mdl-layout__header-row"><div class="mdl-layout-spacer"></div></div>';
    document.body.insertAdjacentHTML('afterbegin', '<button id="truck" title="Terrestre" class="active">Camión</button><button id="plane" title="Aéreo">Avión</button>');
    document.body.insertAdjacentHTML('beforeend', '<section class="panel terrestre"><select id="t_osProDestino"><option value="">Seleccione</option><option value="t">1001 - CHICLAYO HUB - TERRESTRE</option></select><div id="t_osProDestino_chosen" data-visible="true"><a class="chosen-single"><span>Seleccione</span></a></div></section><section class="panel aereo" style="display:none"><select id="a_osProDestino"><option value="">Seleccione</option><option value="a">1001 - CHICLAYO HUB - AEREO</option></select><div id="a_osProDestino_chosen"><a class="chosen-single"><span>Seleccione</span></a></div></section>');
    const selectT = document.querySelector<HTMLSelectElement>('#t_osProDestino')!;
    const selectA = document.querySelector<HTMLSelectElement>('#a_osProDestino')!;
    const inputSpy = vi.fn();
    const changeSpy = vi.fn();
    selectT.addEventListener('input', inputSpy);
    selectT.addEventListener('change', changeSpy);

    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency] });
    await controller.mount();
    const input = document.querySelector<HTMLInputElement>('#codered-search-input')!;
    input.value = 'chiclayo';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 180));

    const map = document.querySelector<HTMLAnchorElement>('.btn-mapa-mini')!;
    map.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
    expect(selectT.value).toBe('');

    (document.querySelector('.codered-agency-card') as HTMLElement).click();
    expect(selectT.value).toBe('t');
    expect(selectA.value).toBe('');
    expect(inputSpy).toHaveBeenCalledTimes(1);
    expect(changeSpy).toHaveBeenCalledTimes(1);
    expect(document.querySelector('#t_osProDestino_chosen span')?.textContent).toContain('TERRESTRE');

    document.querySelector('#truck')?.classList.remove('active');
    document.querySelector('#plane')?.classList.add('active');
    (document.querySelector('.terrestre') as HTMLElement).style.display = 'none';
    (document.querySelector('.aereo') as HTMLElement).style.display = 'block';
    (document.querySelector('#a_osProDestino_chosen') as HTMLElement).setAttribute('data-visible', 'true');
    (document.querySelector('#plane') as HTMLElement).click();
    await new Promise((resolve) => setTimeout(resolve, 10));
    input.value = 'chiclayo';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 180));
    (document.querySelector('.codered-agency-card') as HTMLElement).click();
    expect(selectA.value).toBe('a');
  });

  it('closes results with Escape', async () => {
    const controller = createShalomContentController({ requestCatalog: async () => [terrestrialAgency] });
    await controller.mount();
    const input = document.querySelector<HTMLInputElement>('#codered-search-input')!;
    input.value = 'chiclayo';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 180));
    expect(document.querySelector<HTMLElement>('.codered-results-panel')?.hidden).toBe(false);
    input.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(document.querySelector<HTMLElement>('.codered-results-panel')?.hidden).toBe(true);
  });
});
