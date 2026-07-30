export interface ExtensionConfiguration {
  apiBaseUrl: string;
  token: string | null;
  tokenMasked: string | null;
  tokenRequestUrl: string | null;
  syncIntervalHours: number;
}

export interface SyncMetadata {
  catalogRevision: string | null;
  cursor: string | null;
  lastSyncedAt: string | null;
  status: string | null;
  message: string | null;
}

export const DEFAULT_API_BASE_URL = 'https://platform.codered.host/api/v1';
export const DEFAULT_TOKEN_REQUEST_URL = 'https://platform.codered.host/solicitar-token';
