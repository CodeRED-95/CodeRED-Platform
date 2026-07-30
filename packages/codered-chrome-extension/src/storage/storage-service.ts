import type { Agency } from '../models/agency';
import { DEFAULT_API_BASE_URL, DEFAULT_TOKEN_REQUEST_URL, type ExtensionConfiguration, type SyncMetadata } from '../models/configuration';
import { maskToken } from '../utils/format';

const KEYS = {
  configuration: 'configuration',
  agencies: 'agencies',
  syncMetadata: 'syncMetadata',
};

export class ChromeStorageService {
  async getConfiguration(): Promise<ExtensionConfiguration> {
    const data = await chrome.storage.local.get(KEYS.configuration);
    return {
      apiBaseUrl: DEFAULT_API_BASE_URL,
      token: null,
      tokenMasked: null,
      tokenRequestUrl: DEFAULT_TOKEN_REQUEST_URL,
      syncIntervalHours: 24,
      ...(data[KEYS.configuration] ?? {}),
    };
  }

  async saveConfiguration(configuration: { apiBaseUrl: string; token: string; tokenRequestUrl?: string | null; syncIntervalHours?: number }): Promise<void> {
    const token = configuration.token.trim();
    await chrome.storage.local.set({
      [KEYS.configuration]: {
        apiBaseUrl: configuration.apiBaseUrl.replace(/\/+$/, ''),
        token,
        tokenMasked: maskToken(token),
        tokenRequestUrl: configuration.tokenRequestUrl ?? DEFAULT_TOKEN_REQUEST_URL,
        syncIntervalHours: configuration.syncIntervalHours ?? 24,
      },
    });
  }

  async deleteToken(): Promise<void> {
    const configuration = await this.getConfiguration();
    await chrome.storage.local.set({ [KEYS.configuration]: { ...configuration, token: null, tokenMasked: null } });
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
