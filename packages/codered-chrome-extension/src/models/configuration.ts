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

// Dominio productivo: codered.lat (migrado desde codered.host).
// Se puede sobrescribir en build time con VITE_CODERED_API_BASE_URL.
const DEFAULT_API_BASE_URL = 'https://platform.codered.lat/api/v1';

export function getPlatformApiBaseUrl(): string {
  return (import.meta.env.VITE_CODERED_API_BASE_URL || DEFAULT_API_BASE_URL).replace(/\/+$/, '');
}

export function getTokenRequestPath(): string {
  const configured = import.meta.env.VITE_CODERED_TOKEN_REQUEST_PATH || '/solicitar-token';
  return configured.startsWith('/') ? configured : '/' + configured;
}

/** Origen publico de la Plataforma, sin la parte /api/v1. */
export function getPlatformOrigin(): string {
  return new URL(getPlatformApiBaseUrl()).origin;
}

/** Paginas publicas enlazadas desde el pie del popup. */
export function getSupportUrl(): string {
  return `${getPlatformOrigin()}/support/buscador-shalom`;
}

export function getPrivacyUrl(): string {
  return `${getPlatformOrigin()}/privacy/buscador-shalom`;
}

export function getTokenRequestUrl(): string {
  const baseUrl = new URL(getPlatformApiBaseUrl());
  return new URL(getTokenRequestPath(), baseUrl.origin).toString();
}
