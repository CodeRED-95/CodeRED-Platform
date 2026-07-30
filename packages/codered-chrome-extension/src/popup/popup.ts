import './popup.css';
import type { Agency } from '../models/agency';
import { statusNotice } from '../models/agency';
import { buildMapsUrl } from '../utils/format';

const query = document.querySelector<HTMLInputElement>('#query')!;
const results = document.querySelector<HTMLDivElement>('#results')!;
const counter = document.querySelector<HTMLSpanElement>('#counter')!;
const updated = document.querySelector<HTMLSpanElement>('#updated')!;
const status = document.querySelector<HTMLSpanElement>('#status')!;
const welcome = document.querySelector<HTMLElement>('#welcome')!;
const panel = document.querySelector<HTMLElement>('#search-panel')!;
const more = document.querySelector<HTMLButtonElement>('#more')!;
let lastResults: Array<{ agency: Agency; score: number }> = [];
let visible = 8;
let timer = 0;

void init();

async function init() {
  document.querySelector('#options')?.addEventListener('click', () => chrome.runtime.openOptionsPage());
  document.querySelector('#configure-token')?.addEventListener('click', () => chrome.runtime.openOptionsPage());
  document.querySelector('#request-token')?.addEventListener('click', () => chrome.runtime.sendMessage({ type: 'OPEN_TOKEN_REQUEST' }));
  document.querySelector('#clear')?.addEventListener('click', () => { query.value = ''; void search(); query.focus(); });
  more.addEventListener('click', () => { visible += 8; render(); });
  query.addEventListener('input', () => { clearTimeout(timer); timer = window.setTimeout(() => void search(), 160); });

  const state = await chrome.runtime.sendMessage({ type: 'GET_STATE' });
  const configured = Boolean(state.configuration?.tokenMasked);
  welcome.hidden = configured;
  panel.hidden = !configured && state.agencyCount === 0;
  status.textContent = state.metadata?.message ?? (configured ? 'Listo' : 'Sin configurar');
  updated.textContent = state.metadata?.lastSyncedAt ? new Date(state.metadata.lastSyncedAt).toLocaleString('es-PE') : 'Sin sincronizar';
  if (configured) void chrome.runtime.sendMessage({ type: 'SYNC_NOW' });
  await search();
}

async function search() {
  visible = 8;
  const response = await chrome.runtime.sendMessage({ type: 'SEARCH_AGENCIES', query: query.value });
  lastResults = response.results ?? [];
  render();
}

function render() {
  results.replaceChildren();
  counter.textContent = `${lastResults.length} resultado${lastResults.length === 1 ? '' : 's'}`;
  if (lastResults.length === 0) {
    const empty = document.createElement('p');
    empty.className = 'old';
    empty.textContent = 'No encontramos agencias que coincidan con la busqueda';
    results.append(empty);
    more.hidden = true;
    return;
  }
  for (const { agency } of lastResults.slice(0, visible)) results.append(card(agency));
  more.hidden = visible >= lastResults.length;
}

function card(agency: Agency): HTMLElement {
  const article = document.createElement('article');
  article.className = 'card';
  const title = document.createElement('h2');
  title.textContent = agency.name;
  article.append(title);
  if (agency.oldName) appendText(article, 'old', `Antes: ${agency.oldName}`);
  const badges = document.createElement('div');
  badges.className = 'badges';
  addBadge(badges, agency.statusLabel, agency.status === 'active' ? 'green' : agency.status === 'moved' ? 'amber' : 'red');
  if (agency.code) addBadge(badges, agency.code);
  if (agency.isOperationsCenter) addBadge(badges, 'CO');
  if (agency.category) addBadge(badges, agency.category);
  if (agency.airText) addBadge(badges, 'AEREO');
  if (agency.terrestrialText) addBadge(badges, 'TERRESTRE');
  article.append(badges);
  appendText(article, 'location', [agency.department, agency.province, agency.district].filter(Boolean).join(' / '));
  appendText(article, 'address', agency.address);
  appendText(article, 'reference', agency.reference ? `Referencia: ${agency.reference}` : null);
  appendText(article, 'schedule', agency.scheduleGeneral);
  const notice = statusNotice(agency);
  if (notice) appendText(article, 'notice', [notice.message, ...notice.details].join('. '));
  const actions = document.createElement('div');
  actions.className = 'actions';
  const maps = document.createElement('button');
  maps.textContent = 'Abrir en Maps';
  maps.addEventListener('click', () => chrome.tabs.create({ url: buildMapsUrl(agency) }));
  const copy = document.createElement('button');
  copy.textContent = 'Copiar direccion';
  copy.addEventListener('click', () => navigator.clipboard.writeText(agency.address ?? agency.name));
  actions.append(maps, copy);
  article.append(actions);
  return article;
}

function appendText(parent: HTMLElement, className: string, text: string | null) {
  if (!text) return;
  const element = document.createElement('p');
  element.className = className;
  element.textContent = text;
  parent.append(element);
}

function addBadge(parent: HTMLElement, text: string, tone = '') {
  const badge = document.createElement('span');
  badge.className = `badge ${tone}`.trim();
  badge.textContent = text;
  parent.append(badge);
}
