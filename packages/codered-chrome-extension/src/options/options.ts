import './options.css';
import { DEFAULT_API_BASE_URL } from '../models/configuration';

const apiBaseUrl = document.querySelector<HTMLInputElement>('#apiBaseUrl')!;
const token = document.querySelector<HTMLInputElement>('#token')!;
const masked = document.querySelector<HTMLParagraphElement>('#masked')!;
const message = document.querySelector<HTMLParagraphElement>('#message')!;
const fields = {
  connection: document.querySelector<HTMLParagraphElement>('#connection')!,
  lastSync: document.querySelector<HTMLElement>('#lastSync')!,
  version: document.querySelector<HTMLElement>('#version')!,
  count: document.querySelector<HTMLElement>('#count')!,
  state: document.querySelector<HTMLElement>('#state')!,
};

void load();
document.querySelector('#save')?.addEventListener('click', () => void save());
document.querySelector('#test')?.addEventListener('click', () => void save(false));
document.querySelector('#request')?.addEventListener('click', () => chrome.runtime.sendMessage({ type: 'OPEN_TOKEN_REQUEST' }));
document.querySelector('#sync')?.addEventListener('click', () => void sync());
document.querySelector('#delete')?.addEventListener('click', () => void deleteToken());

async function load() {
  const state = await chrome.runtime.sendMessage({ type: 'GET_STATE' });
  apiBaseUrl.value = state.configuration?.apiBaseUrl ?? DEFAULT_API_BASE_URL;
  masked.textContent = state.configuration?.tokenMasked ? `Token guardado: ${state.configuration.tokenMasked}` : 'No hay token guardado';
  renderState(state);
}

async function save(syncAfter = true) {
  const response = await chrome.runtime.sendMessage({ type: 'SAVE_CONFIGURATION', apiBaseUrl: apiBaseUrl.value.trim(), token: token.value.trim() });
  message.textContent = response.success ? 'Token validado y guardado' : response.message;
  token.value = '';
  if (response.success && syncAfter) await load();
}

async function sync() {
  const response = await chrome.runtime.sendMessage({ type: 'SYNC_NOW' });
  message.textContent = response.sync?.message ?? response.message ?? 'Sincronizacion solicitada';
  await load();
}

async function deleteToken() {
  await chrome.runtime.sendMessage({ type: 'DELETE_TOKEN' });
  message.textContent = 'Token eliminado. La cache local se conserva.';
  await load();
}

function renderState(state: Record<string, any>) {
  fields.connection.textContent = state.configuration?.tokenMasked ? 'Token configurado' : 'Sin token configurado';
  fields.lastSync.textContent = state.metadata?.lastSyncedAt ? new Date(state.metadata.lastSyncedAt).toLocaleString('es-PE') : '-';
  fields.version.textContent = state.metadata?.catalogRevision ?? '-';
  fields.count.textContent = String(state.agencyCount ?? 0);
  fields.state.textContent = state.metadata?.message ?? '-';
}
