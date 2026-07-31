import { CodeRedClient } from '../api/codered-client';
import { searchAgencies } from '../search/agency-search';
import { ChromeStorageService } from '../storage/storage-service';
import { getPlatformApiBaseUrl, getTokenRequestUrl } from '../models/configuration';
import { isRuntimeRequest } from './messages';
import { createSyncService } from './sync-service';

const storage = new ChromeStorageService();
const ALARM_NAME = 'codered-agency-sync';
const apiBaseUrl = getPlatformApiBaseUrl();

chrome.runtime.onInstalled.addListener(() => {
  chrome.alarms.create(ALARM_NAME, { periodInMinutes: 24 * 60 });
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === ALARM_NAME) void syncNow();
});

chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
  if (!isRuntimeRequest(message)) {
    sendResponse({ success: false, message: 'Solicitud invalida' });
    return false;
  }

  void handleMessage(message).then(sendResponse).catch((error: unknown) => {
    sendResponse({ success: false, message: error instanceof Error ? error.message : 'Error interno' });
  });

  return true;
});

async function handleMessage(message: Parameters<typeof isRuntimeRequest>[0]) {
  if (!isRuntimeRequest(message)) return { success: false };

  if (message.type === 'GET_STATE' || message.type === 'CONFIG_GET') {
    return {
      success: true,
      configuration: publicConfiguration(await storage.getConfiguration()),
      metadata: await storage.getSyncMetadata(),
      agencyCount: (await storage.getAgencies()).length,
      apiBaseUrl,
      tokenRequestUrl: getTokenRequestUrl(),
    };
  }

  if (message.type === 'SEARCH_AGENCIES') {
    return { success: true, results: searchAgencies(await storage.getAgencies(), message.query, 30) };
  }

  if (message.type === 'CATALOG_GET') {
    console.log('[CodeRED] CATALOG_GET solicitado');
    const agencies = await storage.getAgencies();
    console.log(`[CodeRED] Agencias cargadas: ${agencies.length}`);
    return { success: true, agencies };
  }

  if (message.type === 'CATALOG_STATUS') {
    return { success: true, metadata: await storage.getSyncMetadata(), agencyCount: (await storage.getAgencies()).length };
  }

  if (message.type === 'CATALOG_SYNC' || message.type === 'SYNC_NOW') return { success: true, sync: await syncNow({ forceFull: true }) };

  if (message.type === 'SAVE_CONFIGURATION' || message.type === 'CONFIG_SAVE') {
    const token = message.token.trim();
    if (!token) {
      return { success: false, message: 'El token no puede estar vacio.' };
    }
    await storage.saveToken(token);
    await scheduleAlarm();
    return { success: true, message: 'Token guardado' };
  }

  if (message.type === 'API_TEST_CONNECTION') {
    const configuration = await storage.getConfiguration();
    if (!configuration.token) {
      return { success: false, status: 400, message: 'Primero guarda un token valido.' };
    }
    const client = new CodeRedClient(apiBaseUrl, configuration.token);
    const profile = await client.validateToken();
    const abilities = new Set(profile.abilities);
    if (!abilities.has('*') && !abilities.has('agencies:read')) {
      return { success: false, status: 403, message: 'El token no tiene permisos para consultar agencias' };
    }
    return { success: true, message: 'Conexion validada correctamente.' };
  }

  if (message.type === 'DELETE_TOKEN' || message.type === 'TOKEN_DELETE') {
    await storage.deleteToken();
    return { success: true };
  }

  if (message.type === 'OPEN_TOKEN_REQUEST') {
    await chrome.tabs.create({ url: getTokenRequestUrl() });
    return { success: true };
  }

  if (message.type === 'TOKEN_REQUEST_CREATE') {
    const response = await fetch(`${apiBaseUrl}/token-requests`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({
        requester_name: message.requester_name,
        delivery_channel: message.delivery_channel,
        delivery_destination: message.delivery_destination,
        instance_name: message.instance_name,
        source: message.source,
        requested_scopes: message.requested_scopes,
        notes: message.notes ?? null,
      }),
    });
    if (!response.ok) {
      const payload = await response.json().catch(() => ({}));
      return { success: false, status: response.status, message: payload.message ?? 'No fue posible enviar la solicitud.' };
    }
    return { success: true, ...(await response.json()) };
  }
}

async function syncNow(options: { forceFull?: boolean } = {}) {
  console.log('[CodeRED] Iniciando sincronización...');
  const configuration = await storage.getConfiguration();
  if (!configuration.token) {
    console.log('[CodeRED] Sincronización fallida: token no configurado.');
    return { status: 'error', message: 'Configura un token para sincronizar', agencyCount: (await storage.getAgencies()).length };
  }
  console.log('[CodeRED] Token válido');

  await storage.setSyncMetadata({ status: 'updating', message: 'Actualizando' });
  const client = new CodeRedClient(apiBaseUrl, configuration.token);
  const sync = createSyncService(client, storage);
  const result = await sync.syncNow(options);
  await storage.setSyncMetadata({ status: result.status, message: result.message });
  return result;
}

async function scheduleAlarm(): Promise<void> {
  const configuration = await storage.getConfiguration();
  await chrome.alarms.create(ALARM_NAME, { periodInMinutes: Math.max(1, configuration.syncIntervalHours) * 60 });
}

function publicConfiguration(configuration: Awaited<ReturnType<ChromeStorageService['getConfiguration']>>) {
  return {
    tokenMasked: configuration.tokenMasked,
    syncIntervalHours: configuration.syncIntervalHours,
  };
}
