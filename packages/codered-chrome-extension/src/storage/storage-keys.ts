export const STORAGE_KEYS = {
  API_TOKEN: 'codered_api_token',
  TOKEN_METADATA: 'codered_token_metadata',
  CATALOG: 'codered_agency_catalog',
  CATALOG_VERSION: 'codered_catalog_version',
  LAST_SYNC_AT: 'codered_last_sync_at',
  LAST_SYNC_STATUS: 'codered_last_sync_status',
  SYNC_METADATA: 'codered_sync_metadata',
  SERVICE_ORDER_LOCK: 'codered_service_order_lock',
} as const;

export const LEGACY_TOKEN_KEYS = ['auth', 'token', 'apiToken', 'coderedToken', 'accessToken', 'platformToken', 'catalogToken'] as const;
export const LEGACY_CATALOG_KEYS = ['agencies', 'agencyCache', 'catalog'] as const;
export const LEGACY_SYNC_METADATA_KEYS = ['syncMetadata'] as const;
