export type RuntimeRequest =
  | { type: 'GET_STATE' }
  | { type: 'SYNC_NOW' }
  | { type: 'SEARCH_AGENCIES'; query: string }
  | { type: 'SAVE_CONFIGURATION'; apiBaseUrl: string; token: string }
  | { type: 'DELETE_TOKEN' }
  | { type: 'OPEN_TOKEN_REQUEST' }
  | { type: 'CATALOG_GET' }
  | { type: 'CATALOG_SYNC' }
  | { type: 'CATALOG_STATUS' }
  | { type: 'API_TEST_CONNECTION'; apiBaseUrl: string; token: string }
  | { type: 'CONFIG_GET' }
  | { type: 'CONFIG_SAVE'; apiBaseUrl: string; token: string };

export function isRuntimeRequest(value: unknown): value is RuntimeRequest {
  if (value === null || typeof value !== 'object') return false;
  const message = value as Record<string, unknown>;
  if ('token' in message && !['SAVE_CONFIGURATION', 'CONFIG_SAVE', 'API_TEST_CONNECTION'].includes(String(message.type))) return false;
  switch (message.type) {
    case 'GET_STATE':
    case 'SYNC_NOW':
    case 'DELETE_TOKEN':
    case 'OPEN_TOKEN_REQUEST':
    case 'CATALOG_GET':
    case 'CATALOG_SYNC':
    case 'CATALOG_STATUS':
    case 'CONFIG_GET':
      return true;
    case 'SEARCH_AGENCIES':
      return typeof message.query === 'string';
    case 'SAVE_CONFIGURATION':
    case 'CONFIG_SAVE':
    case 'API_TEST_CONNECTION':
      return typeof message.apiBaseUrl === 'string' && typeof message.token === 'string';
    default:
      return false;
  }
}
