export interface ExtensionConfiguration {
  token: string | null;
  tokenMasked: string | null;
  syncIntervalHours: number;
}

export interface SyncMetadata {
  catalogRevision: string | null;
  cursor: string | null;
  lastSyncedAt: string | null;
  status: string | null;
  message: string | null;
}

const DEFAULT_API_BASE_URL = 'https://platform.codered.host/api/v1';

export function getPlatformApiBaseUrl(): string {
  return (import.meta.env.VITE_CODERED_API_BASE_URL || DEFAULT_API_BASE_URL).replace(/\/+$/, '');
}

export function getTokenRequestPath(): string {
  const configured = import.meta.env.VITE_CODERED_TOKEN_REQUEST_PATH || '/solicitar-token';
  return configured.startsWith('/') ? configured : '/' + configured;
}

export function getTokenRequestUrl(): string {
  const baseUrl = new URL(getPlatformApiBaseUrl());
  return new URL(getTokenRequestPath(), baseUrl.origin).toString();
}
