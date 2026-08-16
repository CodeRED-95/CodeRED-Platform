import type { Agency } from '../models/agency';
import { type ExtensionConfiguration, type SyncMetadata } from '../models/configuration';
import { maskToken } from '../utils/format';
import { LEGACY_CATALOG_KEYS, LEGACY_SYNC_METADATA_KEYS, LEGACY_TOKEN_KEYS, STORAGE_KEYS } from './storage-keys';
import { getRestrictedPeriodId } from '../shared/lima-time';

export interface ServiceOrderForcedUnlock {
  active: boolean;
  createdAt: string;
  expiresAt: string;
  restrictedPeriodId: string;
}

type LocalData = Record<string, unknown>;

export class ChromeStorageService {
  async getConfiguration(): Promise<ExtensionConfiguration> {
    await this.migrateLegacyTokenStorage();
    const data = await chrome.storage.local.get([STORAGE_KEYS.API_TOKEN, STORAGE_KEYS.TOKEN_METADATA]);
    const token = typeof data[STORAGE_KEYS.API_TOKEN] === 'string' ? data[STORAGE_KEYS.API_TOKEN] as string : null;
    const metadata = isRecord(data[STORAGE_KEYS.TOKEN_METADATA]) ? data[STORAGE_KEYS.TOKEN_METADATA] : {};
    return {
      token,
      tokenMasked: token ? String(metadata.tokenMasked ?? maskToken(token)) : null,
      syncIntervalHours: Number(metadata.syncIntervalHours ?? 24),
    };
  }

  async saveToken(tokenValue: string): Promise<void> {
    const token = tokenValue.trim();
    await chrome.storage.local.set({
      [STORAGE_KEYS.API_TOKEN]: token,
      [STORAGE_KEYS.TOKEN_METADATA]: { tokenMasked: maskToken(token), syncIntervalHours: 24, configuredAt: new Date().toISOString() },
    });
    await this.removeLegacyTokenKeys();
  }

  async deleteToken(): Promise<void> {
    await chrome.storage.local.remove([STORAGE_KEYS.API_TOKEN, ...LEGACY_TOKEN_KEYS]);
    await chrome.storage.local.set({ [STORAGE_KEYS.TOKEN_METADATA]: { tokenMasked: null, syncIntervalHours: 24 } });
  }

  async migrateLegacyTokenStorage(): Promise<void> {
    const keys = [STORAGE_KEYS.API_TOKEN, ...LEGACY_TOKEN_KEYS];
    const data = await chrome.storage.local.get(keys);
    if (typeof data[STORAGE_KEYS.API_TOKEN] === 'string' && String(data[STORAGE_KEYS.API_TOKEN]).trim() !== '') return;

    const migrated = findLegacyToken(data);
    if (!migrated) return;

    await chrome.storage.local.set({
      [STORAGE_KEYS.API_TOKEN]: migrated,
      [STORAGE_KEYS.TOKEN_METADATA]: { tokenMasked: maskToken(migrated), syncIntervalHours: 24, migratedAt: new Date().toISOString() },
    });
    await this.removeLegacyTokenKeys();
  }

  async getAgencies(): Promise<Agency[]> {
    const data = await chrome.storage.local.get([STORAGE_KEYS.CATALOG, ...LEGACY_CATALOG_KEYS]);
    const catalog = Array.isArray(data[STORAGE_KEYS.CATALOG]) ? data[STORAGE_KEYS.CATALOG] : LEGACY_CATALOG_KEYS.map((key) => data[key]).find(Array.isArray);
    return Array.isArray(catalog) ? catalog as Agency[] : [];
  }

  async replaceAgencies(agencies: Agency[], meta: { catalogRevision: string | null; cursor: string | null }): Promise<void> {
    const metadata = { ...(await this.getSyncMetadata()), catalogRevision: meta.catalogRevision, cursor: meta.cursor, lastSyncedAt: new Date().toISOString(), status: 'updated', message: 'Actualizado' };
    await chrome.storage.local.set({
      [STORAGE_KEYS.CATALOG]: agencies,
      [STORAGE_KEYS.CATALOG_VERSION]: meta.catalogRevision,
      [STORAGE_KEYS.LAST_SYNC_AT]: metadata.lastSyncedAt,
      [STORAGE_KEYS.LAST_SYNC_STATUS]: metadata.status,
      [STORAGE_KEYS.SYNC_METADATA]: metadata,
    });
  }

  async getSyncMetadata(): Promise<SyncMetadata> {
    const data = await chrome.storage.local.get([STORAGE_KEYS.SYNC_METADATA, ...LEGACY_SYNC_METADATA_KEYS]);
    const legacy = LEGACY_SYNC_METADATA_KEYS.map((key) => data[key]).find(isRecord);
    return { catalogRevision: null, cursor: null, lastSyncedAt: null, status: null, message: null, ...legacy, ...(isRecord(data[STORAGE_KEYS.SYNC_METADATA]) ? data[STORAGE_KEYS.SYNC_METADATA] : {}) };
  }

  async setSyncMetadata(meta: Partial<SyncMetadata>): Promise<void> {
    const next = { ...(await this.getSyncMetadata()), ...meta };
    await chrome.storage.local.set({
      [STORAGE_KEYS.SYNC_METADATA]: next,
      [STORAGE_KEYS.CATALOG_VERSION]: next.catalogRevision,
      [STORAGE_KEYS.LAST_SYNC_AT]: next.lastSyncedAt,
      [STORAGE_KEYS.LAST_SYNC_STATUS]: next.status,
    });
  }

  async getServiceOrderLock(): Promise<boolean> {
    const data = await chrome.storage.local.get([STORAGE_KEYS.SERVICE_ORDER_LOCK]);
    return data[STORAGE_KEYS.SERVICE_ORDER_LOCK] === true;
  }

  async setServiceOrderLock(locked: boolean): Promise<void> {
    await chrome.storage.local.set({ [STORAGE_KEYS.SERVICE_ORDER_LOCK]: locked });
  }

  async getServiceOrderForcedUnlock(): Promise<ServiceOrderForcedUnlock | null> {
    const data = await chrome.storage.local.get([STORAGE_KEYS.SERVICE_ORDER_FORCED_UNLOCK]);
    const value = data[STORAGE_KEYS.SERVICE_ORDER_FORCED_UNLOCK];
    if (!isRecord(value)) return null;
    const active = value.active === true;
    const createdAt = typeof value.createdAt === 'string' ? value.createdAt : '';
    const expiresAt = typeof value.expiresAt === 'string' ? value.expiresAt : '';
    const restrictedPeriodId = typeof value.restrictedPeriodId === 'string' ? value.restrictedPeriodId : '';
    if (!active || !createdAt || !expiresAt || !restrictedPeriodId) return null;
    return { active, createdAt, expiresAt, restrictedPeriodId };
  }

  async setServiceOrderForcedUnlock(value: ServiceOrderForcedUnlock | null): Promise<void> {
    if (!value) {
      await chrome.storage.local.remove([STORAGE_KEYS.SERVICE_ORDER_FORCED_UNLOCK]);
      return;
    }
    await chrome.storage.local.set({ [STORAGE_KEYS.SERVICE_ORDER_FORCED_UNLOCK]: value });
  }

  async clearExpiredServiceOrderForcedUnlock(now = new Date()): Promise<void> {
    const forcedUnlock = await this.getServiceOrderForcedUnlock();
    if (!forcedUnlock) return;
    const expiresAt = Date.parse(forcedUnlock.expiresAt);
    const activePeriodId = getRestrictedPeriodId(now);
    if (!Number.isFinite(expiresAt) || expiresAt <= now.getTime() || !activePeriodId || forcedUnlock.restrictedPeriodId !== activePeriodId) {
      await this.setServiceOrderForcedUnlock(null);
    }
  }

  private async removeLegacyTokenKeys(): Promise<void> {
    await chrome.storage.local.remove([...LEGACY_TOKEN_KEYS]);
  }
}

function findLegacyToken(data: LocalData): string | null {
  for (const key of LEGACY_TOKEN_KEYS) {
    const value = data[key];
    if (typeof value === 'string' && value.trim() !== '') return value.trim();
    if (isRecord(value)) {
      for (const nested of ['token', 'apiToken', 'coderedToken', 'accessToken', 'platformToken', 'catalogToken']) {
        const nestedValue = value[nested];
        if (typeof nestedValue === 'string' && nestedValue.trim() !== '') return nestedValue.trim();
      }
    }
  }
  return null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
