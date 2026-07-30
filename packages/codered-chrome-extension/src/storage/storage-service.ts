import type { Agency } from '../models/agency';
import { type ExtensionConfiguration, type SyncMetadata } from '../models/configuration';
import { maskToken } from '../utils/format';

const KEYS = {
  auth: 'auth',
  agencies: 'agencies',
  syncMetadata: 'syncMetadata',
};

export class ChromeStorageService {
  async getConfiguration(): Promise<ExtensionConfiguration> {
    const data = await chrome.storage.local.get(KEYS.auth);
    return {
      token: null,
      tokenMasked: null,
      syncIntervalHours: 24,
      ...(data[KEYS.auth] ?? {}),
    };
  }

  async saveToken(tokenValue: string): Promise<void> {
    const token = tokenValue.trim();
    await chrome.storage.local.set({
      [KEYS.auth]: {
        token,
        tokenMasked: maskToken(token),
        syncIntervalHours: 24,
      },
    });
  }

  async deleteToken(): Promise<void> {
    await chrome.storage.local.set({ [KEYS.auth]: { token: null, tokenMasked: null, syncIntervalHours: 24 } });
  }

  async getAgencies(): Promise<Agency[]> {
    const data = await chrome.storage.local.get(KEYS.agencies);
    return Array.isArray(data[KEYS.agencies]) ? (data[KEYS.agencies] as Agency[]) : [];
  }

  async replaceAgencies(agencies: Agency[], meta: { catalogRevision: string | null; cursor: string | null }): Promise<void> {
    await chrome.storage.local.set({
      [KEYS.agencies]: agencies,
      [KEYS.syncMetadata]: { ...(await this.getSyncMetadata()), catalogRevision: meta.catalogRevision, cursor: meta.cursor, lastSyncedAt: new Date().toISOString(), status: 'updated', message: 'Actualizado' },
    });
  }

  async getSyncMetadata(): Promise<SyncMetadata> {
    const data = await chrome.storage.local.get(KEYS.syncMetadata);
    return { catalogRevision: null, cursor: null, lastSyncedAt: null, status: null, message: null, ...(data[KEYS.syncMetadata] ?? {}) };
  }

  async setSyncMetadata(meta: Partial<SyncMetadata>): Promise<void> {
    await chrome.storage.local.set({ [KEYS.syncMetadata]: { ...(await this.getSyncMetadata()), ...meta } });
  }
}
