export const SOURCE_URL = 'https://sysnewos.shalomcontrol.com/service-order/items';
const clean = (text) => (text || '').replace(/\s+/g, ' ').trim();

export function nativeModal(doc) {
  const heading = [...doc.querySelectorAll('h3')].find((el) => clean(el.textContent) === 'Seleccionar agencia de destino');
  return heading?.parentElement?.parentElement ?? null;
}

export function readSnapshot(doc, url, now = new Date()) {
  if (url.split(/[?#]/)[0] !== SOURCE_URL) throw new Error('Abre PRODUCTOS en Shalom antes de capturar.');
  if (!doc.querySelector('#transport-type-terrestrial')?.checked) throw new Error('Selecciona transporte TERRESTRE en Shalom. La lista aérea no se sincroniza.');
  const modal = nativeModal(doc);
  if (!modal) throw new Error('Abre la lista nativa «Seleccionar agencia de destino».');
  const input = modal.querySelector('input[placeholder="Ingresa departamento, provincia o distrito"]');
  if (!input || input.value !== '') throw new Error('Vacía el buscador de la lista nativa para capturar todas las agencias.');
  const items = [...modal.querySelectorAll('ul[role="listbox"] > li[role="option"]')];
  const entries = items.map((item) => ({
    index: Number(item.getAttribute('data-index')),
    label: clean(item.firstElementChild?.firstElementChild?.textContent),
  }));
  const list = modal.querySelector('ul[role="listbox"]');
  if (!list || list.clientHeight <= 0 || list.scrollTop + list.clientHeight < list.scrollHeight - 2
    || modal.querySelector('[aria-busy="true"], [role="progressbar"]')) {
    throw new Error('No se observó el final de la lista cargada. No se permiten desactivaciones con una lista parcial.');
  }
  if (entries.length < 100 || entries.some((entry, i) => !items[i].hasAttribute('data-index') || entry.index !== i || !/^.+, .+, .+ - AG\. .+$/.test(entry.label))) {
    throw new Error('Lista incompleta o formato desconocido. No se realizarán modificaciones.');
  }
  const header = [...(doc.querySelector('main.service-order-module')?.children ?? [])].find((el) => el.querySelector('h1'));
  const origin = header?.lastElementChild?.firstElementChild;
  const originName = origin?.tagName === 'DIV' && !origin.querySelector('button, input') ? clean(origin.textContent) : '';
  if (!originName || originName.length > 255) throw new Error('No se pudo identificar la agencia de origen en el encabezado de Shalom.');
  return {source_url: SOURCE_URL, channel: 'terrestrial', captured_at: now.toISOString(), origin_name: originName,
    filter: '', complete: true, end_observed: true, total: entries.length, entries};
}

export function fingerprint(snapshot) {
  return JSON.stringify([snapshot.origin_name, snapshot.entries]);
}
