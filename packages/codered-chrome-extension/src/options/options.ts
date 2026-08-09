import './options.css';
import { getTokenRequestUrl } from '../models/configuration';

type StateResponse = {
  configuration?: { tokenMasked?: string | null };
  metadata?: { lastSyncedAt?: string | null; catalogRevision?: string | null; message?: string | null; status?: string | null };
  agencyCount?: number;
};

const token = document.querySelector<HTMLInputElement>('#token')!;
const masked = document.querySelector<HTMLParagraphElement>('#masked')!;
const message = document.querySelector<HTMLParagraphElement>('#message')!;
const requestButton = document.querySelector<HTMLButtonElement>('#request')!;
const actionButtons = ['#save', '#test', '#sync', '#delete', '#request'].map((selector) => document.querySelector<HTMLButtonElement>(selector)!);
const fields = {
  connection: document.querySelector<HTMLParagraphElement>('#connection')!,
  lastSync: document.querySelector<HTMLElement>('#lastSync')!,
  version: document.querySelector<HTMLElement>('#version')!,
  count: document.querySelector<HTMLElement>('#count')!,
  state: document.querySelector<HTMLElement>('#state')!,
};

void load();
document.querySelector('#save')?.addEventListener('click', () => void saveToken());
document.querySelector('#test')?.addEventListener('click', () => void testConnection());
document.querySelector('#sync')?.addEventListener('click', () => void sync());
document.querySelector('#delete')?.addEventListener('click', () => void deleteToken());
requestButton.addEventListener('click', () => void requestToken());

async function load(): Promise<void> {
  const state = await chrome.runtime.sendMessage({ type: 'CONFIG_GET' }) as StateResponse;
  masked.textContent = state.configuration?.tokenMasked ? `Token configurado: ${state.configuration.tokenMasked}` : 'No hay token guardado';
  renderState(state);
}

async function saveToken(): Promise<void> {
  const value = token.value.trim();
  if (!value) {
    message.dataset.tone = 'warning';
    message.textContent = 'Escribe un token nuevo para guardarlo o usa Eliminar token.';
    return;
  }

  setBusy(true, 'Guardando token...');
  try {
    const response = await chrome.runtime.sendMessage({ type: 'CONFIG_SAVE', token: value });
    if (!response.success) throw new Error(response.message || 'No fue posible guardar el token.');
    token.value = '';
    message.dataset.tone = 'success';
    message.textContent = response.message || 'Token guardado';
    await load();
  } catch (error) {
    message.dataset.tone = 'error';
    message.textContent = error instanceof Error ? error.message : 'No fue posible guardar el token.';
  } finally {
    setBusy(false);
  }
}

async function testConnection(): Promise<void> {
  setBusy(true, 'Probando conexión...');
  try {
    const response = await chrome.runtime.sendMessage({ type: 'API_TEST_CONNECTION' });
    if (!response.success) throw new Error(response.message || 'No fue posible probar la conexión.');
    message.dataset.tone = 'success';
    message.textContent = response.message || 'Conexión validada correctamente.';
  } catch (error) {
    message.dataset.tone = 'error';
    message.textContent = error instanceof Error ? error.message : 'No fue posible probar la conexión.';
  } finally {
    setBusy(false);
    await load();
  }
}

async function sync(): Promise<void> {
  setBusy(true, 'Sincronizando...');
  try {
    const response = await chrome.runtime.sendMessage({ type: 'CATALOG_SYNC' });
    message.dataset.tone = response.sync?.status === 'updated' || response.sync?.status === 'unchanged' ? 'success' : 'warning';
    message.textContent = response.sync?.message ?? response.message ?? 'Sincronización solicitada';
  } catch (error) {
    message.dataset.tone = 'error';
    message.textContent = error instanceof Error ? error.message : 'No fue posible sincronizar.';
  } finally {
    setBusy(false);
    await load();
  }
}

async function deleteToken(): Promise<void> {
  if (!window.confirm('Eliminar el token no borra la caché local de agencias. ¿Deseas continuar?')) return;
  await chrome.runtime.sendMessage({ type: 'TOKEN_DELETE' });
  token.value = '';
  message.dataset.tone = 'warning';
  message.textContent = 'Token eliminado. La caché local se conserva.';
  await load();
}

async function requestToken(): Promise<void> {
  // El dominio se deriva de models/configuration.ts (única fuente de verdad).
  const url = new URL(getTokenRequestUrl());
  url.searchParams.set('source', 'shalom-extension');
  url.searchParams.set('installation_name', 'Buscador Shalom Control');
  await chrome.tabs.create({ url: url.toString() });
}

function renderState(state: StateResponse): void {
  fields.connection.textContent = state.configuration?.tokenMasked ? 'Token configurado' : 'Sin token configurado';
  fields.lastSync.textContent = state.metadata?.lastSyncedAt ? new Date(state.metadata.lastSyncedAt).toLocaleString('es-PE') : '-';
  fields.version.textContent = state.metadata?.catalogRevision ?? '-';
  fields.count.textContent = String(state.agencyCount ?? 0);
  fields.state.textContent = state.metadata?.message ?? state.metadata?.status ?? '-';
}

function setBusy(busy: boolean, text = ''): void {
  for (const button of actionButtons) button.disabled = busy;
  if (text) {
    message.dataset.tone = 'warning';
    message.textContent = text;
  }
}
