import { CodeRedClient } from '../api/codered-client';
import { searchAgencies } from '../search/agency-search';
import { ChromeStorageService } from '../storage/storage-service';
import { getNextAllowedServiceOrderDate, getRestrictedPeriodId } from '../shared/lima-time';
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

  if (message.type === 'SERVICE_ORDER_LOCK_GET') {
    await storage.clearExpiredServiceOrderForcedUnlock();
    return { success: true, locked: await storage.getServiceOrderLock(), forcedUnlock: await storage.getServiceOrderForcedUnlock() };
  }

  if (message.type === 'SERVICE_ORDER_LOCK_SET') {
    await storage.setServiceOrderLock(message.locked);
    return { success: true, locked: message.locked };
  }

  if (message.type === 'SERVICE_ORDER_FORCED_UNLOCK_GET') {
    await storage.clearExpiredServiceOrderForcedUnlock();
    return { success: true, forcedUnlock: await storage.getServiceOrderForcedUnlock() };
  }

  if (message.type === 'SERVICE_ORDER_FORCED_UNLOCK_SET') {
    const active = message.active;
    if (active) {
      const now = new Date();
      const restrictedPeriodId = getRestrictedPeriodId(now);
      if (!restrictedPeriodId) {
        await storage.setServiceOrderForcedUnlock(null);
        return { success: false, message: 'El horario permitido ya está activo.' };
      }
      const forcedUnlock = {
        active: true,
        createdAt: now.toISOString(),
        expiresAt: getNextAllowedServiceOrderDate(now).toISOString(),
        restrictedPeriodId,
      };
      await storage.setServiceOrderForcedUnlock(forcedUnlock);
      await logForcedUnlock('forced_unlock_started', forcedUnlock);
      return { success: true, forcedUnlock };
    }

    const forcedUnlock = await storage.getServiceOrderForcedUnlock();
    if (forcedUnlock) await logForcedUnlock('forced_unlock_ended', forcedUnlock);
    await storage.setServiceOrderForcedUnlock(null);
    return { success: true };
  }

  if (message.type === 'OPEN_TOKEN_REQUEST') {
    await chrome.tabs.create({ url: getTokenRequestUrl() + '?source=shalom-extension&installation_name=Buscador%20Shalom%20Control' });
    return { success: true };
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
  try {
    const client = new CodeRedClient(apiBaseUrl, configuration.token);
    const sync = createSyncService(client, storage);
    const result = await sync.syncNow(options);
    await storage.setSyncMetadata({ status: result.status, message: result.message });
    return result;
  } catch (error) {
    const result = { status: 'error', message: error instanceof Error ? error.message : 'No fue posible sincronizar', agencyCount: (await storage.getAgencies()).length };
    await storage.setSyncMetadata({ status: result.status, message: result.message });
    return result;
  }
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

async function logForcedUnlock(type: 'forced_unlock_started' | 'forced_unlock_ended' | 'forced_unlock_expired', forcedUnlock: { createdAt: string; expiresAt: string; restrictedPeriodId: string }) {
  const key = 'codered_service_order_forced_unlock_log';
  const entry = { type, at: new Date().toISOString(), createdAt: forcedUnlock.createdAt, expiresAt: forcedUnlock.expiresAt, restrictedPeriodId: forcedUnlock.restrictedPeriodId };
  const data = await chrome.storage.local.get([key]);
  const log = Array.isArray(data[key]) ? data[key] : [];
  await chrome.storage.local.set({ [key]: [...log, entry].slice(-50) });
}
