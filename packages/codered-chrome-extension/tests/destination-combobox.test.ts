import { beforeEach, describe, expect, it } from 'vitest';
import { JSDOM } from 'jsdom';
import { adaptAgency } from '../src/models/agency';
import {
  buildDestinationLabel,
  buildFilterQueries,
  detectComboboxChannel,
  findDestinationCombobox,
  selectAgencyInCombobox,
} from '../src/content/destination-combobox';
import { getShalomPageCapabilities, isServiceOrderItemsPath, isSupportedShalomPath, resolvePageContext } from '../src/content/shalom-host';

const pocollay = adaptAgency({
  external_id: 643,
  code: 'PCLLY',
  name: 'POCOLLAY',
  department: 'TACNA',
  province: 'TACNA',
  district: 'TACNA',
  status: 'active',
});

const chachapoyas = adaptAgency({
  external_id: 3,
  code: 'CHH',
  name: 'CHACHAPOYAS CO DOS DE MAYO',
  department: 'AMAZONAS',
  province: 'CHACHAPOYAS',
  district: 'CHACHAPOYAS',
  status: 'active',
});

/**
 * Doble del combobox Vue de sysnewos, con su semantica real:
 * - la lista se cuelga de <body> y solo existe si hay resultados;
 * - el filtro compara `startsWith` contra departamento, provincia y distrito,
 *   nunca contra el nombre de la agencia;
 * - el commit ocurre en `mousedown`, no en `click`.
 */
function mountFakeCombobox(dom: JSDOM, options: Array<{ id: number; department: string; province: string; district: string; name: string }>) {
  const doc = dom.window.document;
  doc.body.innerHTML = '<div class="card"><h2>Agencia de destino</h2><input id="destination-agency" type="text" autocomplete="off" aria-invalid="true" /></div>';
  const input = doc.getElementById('destination-agency') as HTMLInputElement;
  const state = { mousedowns: 0, clicks: 0, selectedId: null as number | null };

  const label = (o: (typeof options)[number]) => `Dpt.${o.department}, Prov.${o.province}, Dist.${o.district}, Ag.${o.name}`;

  const render = (query: string) => {
    doc.querySelectorAll('ul.bg-white').forEach((ul) => ul.remove());
    const q = query.toLowerCase();
    const matches = q === ''
      ? options
      : options.filter((o) => [o.department, o.province, o.district].some((field) => field.toLowerCase().startsWith(q)));
    if (matches.length === 0) return;

    const ul = doc.createElement('ul');
    ul.className = 'bg-white border border-gray-300 rounded-md';
    for (const option of matches) {
      const li = doc.createElement('li');
      li.dataset.key = String(option.id);
      li.textContent = label(option);
      if (state.selectedId === option.id) li.classList.add('bg-emerald-500');
      li.addEventListener('mousedown', () => {
        state.mousedowns += 1;
        state.selectedId = option.id;
        input.value = label(option);
        input.setAttribute('aria-invalid', 'false');
        doc.querySelectorAll('ul.bg-white').forEach((node) => node.remove());
      });
      li.addEventListener('click', () => { state.clicks += 1; });
      ul.appendChild(li);
    }
    doc.body.appendChild(ul);
  };

  input.addEventListener('focus', () => { input.value = ''; render(''); });
  input.addEventListener('input', () => render(input.value));

  return { input, state };
}

describe('alcance de las rutas', () => {
  it('reconoce /service-order/items sin abrir la mano con rutas anidadas', () => {
    expect(isSupportedShalomPath('/service-order/items')).toBe(true);
    expect(isSupportedShalomPath('/service-order/items/')).toBe(true);
    expect(isSupportedShalomPath('/service-order/items/extra')).toBe(false);
    expect(isServiceOrderItemsPath('/service-order/items')).toBe(true);
    expect(resolvePageContext('/service-order/items')).toEqual({ site: 'sysnewos', module: 'service-order-items', mode: 'interactive' });
  });

  it('usa el combobox en la SPA nueva y deja el camino clasico intacto', () => {
    const items = getShalomPageCapabilities('/service-order/items');
    expect(items.agencySelection).toBe(true);
    expect(items.destinationSelector).toBe('combobox');

    // Lo de siempre en la ruta legacy: Chosen y deteccion de canal por pestanas.
    const legacy = getShalomPageCapabilities('/ordenservicio/listar');
    expect(legacy.destinationSelector).toBe('chosen');
    expect(legacy.agencySelection).toBe(true);
    expect(legacy.mode).toBe('interactive');

    // Las paginas de solo consulta siguen sin seleccionar.
    const neutral = getShalomPageCapabilities('/service-order');
    expect(neutral.agencySelection).toBe(false);
    expect(neutral.destinationSelector).toBe('none');
  });
});

describe('consultas y etiqueta', () => {
  it('construye la etiqueta con el formato del sitio', () => {
    expect(buildDestinationLabel(pocollay)).toBe('Dpt.TACNA, Prov.TACNA, Dist.TACNA, Ag.POCOLLAY');
  });

  it('propone departamento, provincia y distrito, que es lo unico que filtra el sitio', () => {
    expect(buildFilterQueries(chachapoyas)).toEqual(['amazonas', 'chachapoyas', 'chachapoyas']);
  });
});

describe('seleccion en el combobox', () => {
  let dom: JSDOM;

  beforeEach(() => {
    dom = new JSDOM('<!doctype html><html><body></body></html>', { url: 'https://sysnewos.shalomcontrol.com/service-order/items' });
    const globals = globalThis as Record<string, unknown>;
    globals.HTMLInputElement = dom.window.HTMLInputElement;
    globals.MouseEvent = dom.window.MouseEvent;
    globals.KeyboardEvent = dom.window.KeyboardEvent;
    globals.Event = dom.window.Event;
  });

  const wait = async () => undefined;

  it('selecciona la agencia por su id y confirma con la etiqueta del sitio', async () => {
    const { input, state } = mountFakeCombobox(dom, [
      { id: 643, department: 'TACNA', province: 'TACNA', district: 'TACNA', name: 'POCOLLAY' },
      { id: 641, department: 'TACNA', province: 'TACNA', district: 'TACNA', name: 'AV EJERCITO' },
    ]);

    const result = await selectAgencyInCombobox(dom.window.document, pocollay, { wait, timeoutMs: 50 });

    expect(result).toMatchObject({ success: true, value: '643', alreadySelected: false });
    expect(input.value).toBe('Dpt.TACNA, Prov.TACNA, Dist.TACNA, Ag.POCOLLAY');
    expect(input.getAttribute('aria-invalid')).toBe('false');
    // El sitio no escucha `click`: si lo usaramos, no pasaria nada.
    expect(state.mousedowns).toBe(1);
    expect(state.clicks).toBe(0);
  });

  it('no vuelve a pulsar una agencia ya seleccionada, porque el sitio la deseleccionaria', async () => {
    const { input, state } = mountFakeCombobox(dom, [{ id: 643, department: 'TACNA', province: 'TACNA', district: 'TACNA', name: 'POCOLLAY' }]);
    input.value = buildDestinationLabel(pocollay);

    const result = await selectAgencyInCombobox(dom.window.document, pocollay, { wait, timeoutMs: 50 });

    expect(result).toMatchObject({ success: true, alreadySelected: true });
    expect(state.mousedowns).toBe(0);
    expect(input.value).toBe('Dpt.TACNA, Prov.TACNA, Dist.TACNA, Ag.POCOLLAY');
  });

  it('informa cuando la agencia no esta entre los destinos disponibles', async () => {
    mountFakeCombobox(dom, [{ id: 641, department: 'TACNA', province: 'TACNA', district: 'TACNA', name: 'AV EJERCITO' }]);

    const result = await selectAgencyInCombobox(dom.window.document, chachapoyas, { wait, timeoutMs: 30 });

    expect(result).toMatchObject({ success: false, reason: 'option-not-found' });
  });

  it('falla con un mensaje claro si no hay campo de destino', async () => {
    dom.window.document.body.innerHTML = '<div></div>';

    const result = await selectAgencyInCombobox(dom.window.document, pocollay, { wait, timeoutMs: 30 });

    expect(result).toMatchObject({ success: false, reason: 'no-destination-input' });
    expect(findDestinationCombobox(dom.window.document)).toBeNull();
  });
});

describe('canal por radios', () => {
  it('lee el canal activo de la SPA nueva', () => {
    const dom = new JSDOM(`<!doctype html><html><body>
      <input type="radio" name="transport-type" id="transport-type-terrestrial" value="terrestrial" checked />
      <input type="radio" name="transport-type" id="transport-type-aerial" value="aerial" />
    </body></html>`);

    expect(detectComboboxChannel(dom.window.document)).toBe('TERRESTRE');

    const aerial = dom.window.document.getElementById('transport-type-aerial') as HTMLInputElement;
    const terrestrial = dom.window.document.getElementById('transport-type-terrestrial') as HTMLInputElement;
    terrestrial.checked = false;
    aerial.checked = true;

    expect(detectComboboxChannel(dom.window.document)).toBe('AEREO');
  });

  it('devuelve null si el bloque de modalidad aun no esta en el DOM', () => {
    const dom = new JSDOM('<!doctype html><html><body></body></html>');
    expect(detectComboboxChannel(dom.window.document)).toBeNull();
  });
});
