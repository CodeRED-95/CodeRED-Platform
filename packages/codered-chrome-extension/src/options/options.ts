import './options.css';

type StateResponse = {
  configuration?: { tokenMasked?: string | null };
  metadata?: { lastSyncedAt?: string | null; catalogRevision?: string | null; message?: string | null };
  agencyCount?: number;
  apiBaseUrl?: string;
  tokenRequestUrl?: string;
};

const token = document.querySelector<HTMLInputElement>('#token')!;
const masked = document.querySelector<HTMLParagraphElement>('#masked')!;
const message = document.querySelector<HTMLParagraphElement>('#message')!;
const requestForm = document.querySelector<HTMLElement>('#request-form')!;
const form = document.querySelector<HTMLFormElement>('#token-request-form')!;
const sendRequest = document.querySelector<HTMLButtonElement>('#send-request')!;
const cancelRequest = document.querySelector<HTMLButtonElement>('#cancel-request')!;
const requestButton = document.querySelector<HTMLButtonElement>('#request')!;
const deliveryChannel = document.querySelector<HTMLSelectElement>('#delivery_channel')!;
const deliveryDestination = document.querySelector<HTMLInputElement>('#delivery_destination')!;
const deliveryLabel = document.querySelector<HTMLLabelElement>('#delivery_label')!;
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
requestButton.addEventListener('click', () => showRequestForm());
document.querySelector('#sync')?.addEventListener('click', () => void sync());
document.querySelector('#delete')?.addEventListener('click', () => void deleteToken());
cancelRequest.addEventListener('click', () => hideRequestForm());
deliveryChannel.addEventListener('change', () => renderDeliveryField());
form.addEventListener('submit', (event) => {
  event.preventDefault();
  void sendRequestNow();
});

async function load() {
  const state = await chrome.runtime.sendMessage({ type: 'CONFIG_GET' }) as StateResponse;
  masked.textContent = state.configuration?.tokenMasked ? `Token configurado: ${state.configuration.tokenMasked}` : 'No hay token guardado';
  renderState(state);
  renderDeliveryField();
}

async function saveToken() {
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
    setBusy(false, '');
  }
}

async function testConnection() {
  setBusy(true, 'Probando conexion...');
  try {
    const response = await chrome.runtime.sendMessage({ type: 'API_TEST_CONNECTION' });
    if (!response.success) throw new Error(response.message || 'No fue posible probar la conexion.');
    message.dataset.tone = 'success';
    message.textContent = response.message || 'Conexion validada correctamente.';
  } catch (error) {
    message.dataset.tone = 'error';
    message.textContent = error instanceof Error ? error.message : 'No fue posible probar la conexion.';
  } finally {
    setBusy(false, '');
  }
}

async function sync() {
  setBusy(true, 'Sincronizando...');
  try {
    const response = await chrome.runtime.sendMessage({ type: 'CATALOG_SYNC' });
    message.dataset.tone = response.sync?.status === 'updated' ? 'success' : 'warning';
    message.textContent = response.sync?.message ?? response.message ?? 'Sincronizacion solicitada';
    await load();
  } catch (error) {
    message.dataset.tone = 'error';
    message.textContent = error instanceof Error ? error.message : 'No fue posible sincronizar.';
  } finally {
    setBusy(false, '');
  }
}

async function deleteToken() {
  if (!window.confirm('Eliminar el token no borra la cache local de agencias. ¿Deseas continuar?')) return;
  await chrome.runtime.sendMessage({ type: 'TOKEN_DELETE' });
  token.value = '';
  message.dataset.tone = 'warning';
  message.textContent = 'Token eliminado. La cache local se conserva.';
  await load();
}

function showRequestForm() {
  requestForm.hidden = false;
  message.textContent = '';
}

function hideRequestForm() {
  requestForm.hidden = true;
  form.reset();
  form.instance_name.value = 'Buscador Shalom Control';
  renderDeliveryField();
}

function renderDeliveryField() {
  const channel = deliveryChannel.value;
  if (channel === 'telegram') {
    deliveryLabel.firstChild!.textContent = 'Usuario de Telegram';
    deliveryDestination.type = 'text';
    deliveryDestination.placeholder = '@usuario';
  } else if (channel === 'email') {
    deliveryLabel.firstChild!.textContent = 'Correo electronico';
    deliveryDestination.type = 'email';
    deliveryDestination.placeholder = 'usuario@dominio.com';
  } else {
    deliveryLabel.firstChild!.textContent = 'Numero de WhatsApp';
    deliveryDestination.type = 'tel';
    deliveryDestination.placeholder = '+51987654321';
  }
}

async function sendRequestNow() {
  if (sendRequest.disabled) return;
  const payload = buildRequestPayload();
  if (!payload) return;
  sendRequest.disabled = true;
  sendRequest.textContent = 'Enviando...';
  try {
    const response = await chrome.runtime.sendMessage({ type: 'TOKEN_REQUEST_CREATE', ...payload });
    if (!response.success) throw new Error(response.message || 'No fue posible enviar la solicitud.');
    message.dataset.tone = 'success';
    message.textContent = 'Solicitud enviada correctamente' + ` por ${prettyChannel(payload.delivery_channel)}` + (response.data?.request_id ? ` · ${response.data.request_id}` : '.');
    form.reset();
    form.instance_name.value = 'Buscador Shalom Control';
    renderDeliveryField();
    hideRequestForm();
  } catch (error) {
    message.dataset.tone = 'error';
    message.textContent = error instanceof Error ? error.message : 'No fue posible enviar la solicitud.';
  } finally {
    sendRequest.disabled = false;
    sendRequest.textContent = 'Enviar solicitud';
  }
}

function buildRequestPayload() {
  const channel = deliveryChannel.value as 'whatsapp' | 'telegram' | 'email';
  const destination = normalizeDestination(channel, deliveryDestination.value);
  if (!destination) {
    message.dataset.tone = 'error';
    message.textContent = validationMessage(channel);
    return null;
  }
  return {
    requester_name: form.requester_name.value.trim(),
    delivery_channel: channel,
    delivery_destination: destination,
    instance_name: form.instance_name.value.trim() || 'Buscador Shalom Control',
    source: 'chrome_extension',
    requested_scopes: ['agencies:read'],
    notes: form.notes.value.trim() || undefined,
  };
}

function normalizeDestination(channel: 'whatsapp' | 'telegram' | 'email', value: string): string {
  const trimmed = value.trim();
  if (!trimmed) return '';
  if (channel === 'whatsapp') {
    const normalized = trimmed.replace(/[\s()-]/g, '');
    return /^\+\d{8,15}$/.test(normalized) ? normalized : '';
  }
  if (channel === 'telegram') {
    if (/^https?:\/\//i.test(trimmed)) return '';
    const username = trimmed.replace(/^@/, '').replace(/[^a-zA-Z0-9_]/g, '');
    return /^[a-zA-Z0-9_]{5,32}$/.test(username) ? '@' + username : '';
  }
  const email = trimmed.toLowerCase();
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? email : '';
}

function validationMessage(channel: 'whatsapp' | 'telegram' | 'email'): string {
  if (channel === 'whatsapp') return 'Escribe un numero internacional valido, por ejemplo +51987654321.';
  if (channel === 'telegram') return 'Escribe un usuario de Telegram valido, sin enlace.';
  return 'Escribe un correo electronico valido.';
}

function prettyChannel(channel: 'whatsapp' | 'telegram' | 'email'): string {
  return { whatsapp: 'WhatsApp', telegram: 'Telegram', email: 'correo electronico' }[channel];
}

function renderState(state: StateResponse) {
  fields.connection.textContent = state.configuration?.tokenMasked ? 'Token configurado' : 'Sin token configurado';
  fields.lastSync.textContent = state.metadata?.lastSyncedAt ? new Date(state.metadata.lastSyncedAt).toLocaleString('es-PE') : '-';
  fields.version.textContent = state.metadata?.catalogRevision ?? '-';
  fields.count.textContent = String(state.agencyCount ?? 0);
  fields.state.textContent = state.metadata?.message ?? '-';
}

function setBusy(busy: boolean, text = '') {
  [document.querySelector('#save'), document.querySelector('#test'), document.querySelector('#sync'), document.querySelector('#delete'), requestButton, sendRequest, cancelRequest].forEach((el) => {
    if (el instanceof HTMLButtonElement) el.disabled = busy && el !== cancelRequest;
  });
  message.dataset.tone = busy ? 'warning' : message.dataset.tone || '';
  message.textContent = text;
}
