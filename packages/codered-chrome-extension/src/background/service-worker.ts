import { CodeRedClient } from '../api/codered-client';
import { searchAgencies } from '../search/agency-search';
import { ChromeStorageService } from '../storage/storage-service';
import { isRuntimeRequest } from './messages';
import { createSyncService } from './sync-service';

const storage = new ChromeStorageService();
const ALARM_NAME = 'codered-agency-sync';

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

  if (message.type === 'GET_STATE') {
    return { success: true, configuration: publicConfiguration(await storage.getConfiguration()), metadata: await storage.getSyncMetadata(), agencyCount: (await storage.getAgencies()).length };
  }

  if (message.type === 'SEARCH_AGENCIES') {
    return { success: true, results: searchAgencies(await storage.getAgencies(), message.query, 30) };
  }

  if (message.type === 'CATALOG_GET') {
    return { success: true, agencies: await storage.getAgencies() };
  }

  if (message.type === 'CATALOG_STATUS') {
    return { success: true, metadata: await storage.getSyncMetadata(), agencyCount: (await storage.getAgencies()).length };
  }

  if (message.type === 'CATALOG_SYNC') return { success: true, sync: await syncNow({ forceFull: true }) };

  if (message.type === 'CONFIG_GET') {
    return { success: true, configuration: publicConfiguration(await storage.getConfiguration()) };
  }

  if (message.type === 'SAVE_CONFIGURATION' || message.type === 'CONFIG_SAVE' || message.type === 'API_TEST_CONNECTION') {
    const token = message.token.trim();
    const client = new CodeRedClient(message.apiBaseUrl.replace(/\/+$/, ''), token);
    const profile = await client.validateToken();
    const abilities = new Set(profile.abilities);
    if (!abilities.has('*') && !abilities.has('agencies:read')) {
      return { success: false, status: 403, message: 'El token no tiene permisos para consultar agencias' };
    }
    if (message.type === 'API_TEST_CONNECTION') return { success: true };
    await storage.saveConfiguration({ apiBaseUrl: message.apiBaseUrl, token });
    await scheduleAlarm();
    return { success: true, sync: await syncNow({ forceFull: true }) };
  }

  if (message.type === 'SYNC_NOW') return { success: true, sync: await syncNow({ forceFull: true }) };

  if (message.type === 'DELETE_TOKEN') {
    await storage.deleteToken();
    return { success: true };
  }

  if (message.type === 'OPEN_TOKEN_REQUEST') {
    const configuration = await storage.getConfiguration();
    if (configuration.tokenRequestUrl) await chrome.tabs.create({ url: configuration.tokenRequestUrl });
    return { success: true };
  }
}

async function syncNow(options: { forceFull?: boolean } = {}) {
  const configuration = await storage.getConfiguration();
  if (!configuration.token) {
    return { status: 'error', message: 'Configura un token para sincronizar', agencyCount: (await storage.getAgencies()).length };
  }
  await storage.setSyncMetadata({ status: 'updating', message: 'Actualizando' });
  const client = new CodeRedClient(configuration.apiBaseUrl, configuration.token);
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
    apiBaseUrl: configuration.apiBaseUrl,
    tokenMasked: configuration.tokenMasked,
    tokenRequestUrl: configuration.tokenRequestUrl,
    syncIntervalHours: configuration.syncIntervalHours,
  };
}
