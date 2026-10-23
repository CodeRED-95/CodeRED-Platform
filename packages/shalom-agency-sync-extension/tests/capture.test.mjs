import {test} from 'node:test';
import assert from 'node:assert/strict';
import {JSDOM} from 'jsdom';
import {readSnapshot, fingerprint, SOURCE_URL} from '../src/capture.mjs';

function fixture() {
  const dom = new JSDOM(`<main class="service-order-module"><button></button><div><h1>Empresarial</h1><div><div>AV. ARIAS ARAGUEZ</div><div>Cuenta Shalom</div></div></div></main><input id="transport-type-terrestrial" type="radio" checked><section><div><h3>Seleccionar agencia de destino</h3><button>Cerrar</button></div><input placeholder="Ingresa departamento, provincia o distrito"><ul role="listbox">${Array.from({length: 100}, (_,i) => `<li role="option" data-index="${i}"><div><div>ÁNCASH, HUARAZ, HUARAZ - <span>AG. AGENCIA ${i}</span></div><div>DIRECCIÓN NO DEBE SER LEÍDA</div></div></li>`).join('')}</ul></section>`);
  const doc = dom.window.document;
  const list = doc.querySelector('ul');
  Object.defineProperties(list, {clientHeight:{value:240},scrollHeight:{value:4000}});
  list.scrollTop = 3760;
  return doc;
}
test('captura lista completa con origen, índices y nombres sin direcciones', () => {
  const snapshot = readSnapshot(fixture(), SOURCE_URL, new Date('2026-10-22T12:00:00Z'));
  assert.equal(snapshot.total, 100);
  assert.equal(snapshot.origin_name, 'AV. ARIAS ARAGUEZ');
  assert.equal(snapshot.entries[0].label, 'ÁNCASH, HUARAZ, HUARAZ - AG. AGENCIA 0');
  assert.equal(snapshot.entries.at(-1).index, 99);
  assert.equal(snapshot.channel, 'terrestrial');
  assert.equal(snapshot.captured_at, '2026-10-22T12:00:00.000Z');
});
test('huella ignora fecha pero distingue origen y lista', () => {
  const snapshot = readSnapshot(fixture(), SOURCE_URL);
  assert.equal(fingerprint(snapshot), fingerprint({...snapshot, captured_at:'otra fecha'}));
  assert.notEqual(fingerprint(snapshot), fingerprint({...snapshot, origin_name:'OTRO'}));
});
for (const [name, change] of [
  ['lista aérea', d => {d.querySelector('#transport-type-terrestrial').checked = false;}],
  ['lista filtrada', d => {d.querySelector('section input').value = 'lima';}],
  ['lista filtrada con espacios', d => {d.querySelector('section input').value = ' ';}],
  ['modal cerrado', d => d.querySelector('section').remove()],
  ['lista truncada', d => d.querySelector('li').remove()],
  ['índice no consecutivo', d => d.querySelector('li').setAttribute('data-index','1')],
  ['sin índice', d => d.querySelector('li').removeAttribute('data-index')],
  ['formato desconocido', d => {d.querySelector('li > div > div').textContent = 'AGENCIA SIN UBIGEO';}],
  ['sin origen', d => d.querySelector('main').remove()],
  ['final de lista no observado', d => {d.querySelector('ul').scrollTop = 0;}],
  ['lista cargando', d => {d.querySelector('ul').setAttribute('aria-busy','true');}],
]) test(`bloquea ${name}`, () => {const doc = fixture(); change(doc); assert.throws(() => readSnapshot(doc, SOURCE_URL));});
test('bloquea otras páginas y conserva formulario sin cambios', () => {
  const doc = fixture(); const before = doc.documentElement.outerHTML;
  assert.throws(() => readSnapshot(doc, 'https://otro.example/service-order/items'));
  readSnapshot(doc, SOURCE_URL);
  assert.equal(doc.documentElement.outerHTML, before);
});
