import { describe, expect, it, vi } from 'vitest';
import { adaptAgency, isPublicAgency, statusNotice } from '../src/models/agency';
import { searchAgencies } from '../src/search/agency-search';
import { buildMapsUrl, maskToken, normalizeText } from '../src/utils/format';
import { createSyncService } from '../src/background/sync-service';
import { isRuntimeRequest } from '../src/background/messages';

const activeAgency = adaptAgency({
  external_id: 101,
  code: 'HU001',
  name: 'SALAVERRY HUACHO CO',
  old_name: 'HUACHO ANTIGUO',
  short_name: 'Huacho',
  department: 'LIMA',
  province: 'HUAURA',
  district: 'HUACHO',
  zone: 'ZONA ERRADA',
  address: 'Av. Salaverry 123',
  reference: 'Frente al terminal',
  ubigeo_id: 150801,
  classification: { category: 'GRANDE / CO', sends_category: 'Envia', receives_category: 'Recibe' },
  chosen_terrestre: '101 - TERRESTRE',
  chosen_aereo: '101 - AEREO',
  status: 'active',
  centro_operaciones: true,
  updated_at: '2026-07-29T20:30:00-05:00',
});

const movedAgency = adaptAgency({
  id: 202,
  codigo: 'MV202',
  agencia: 'VIÑANIS',
  agencia_anterior: 'TACNA SUR',
  departamento: 'TACNA',
  provincia: 'TACNA',
  distrito: 'CORONEL GREGORIO ALBARRACIN LANCHIPA',
  direccion: 'Av. Antigua 456',
  link_mapa: 'https://maps.example.test/vinanis',
  estado: 'Trasladada',
  has_moved: true,
  moved_to_agency_id: 303,
  moved_to_agency_name: 'NUEVA TACNA',
  moved_to_address: 'Av. Nueva 789',
});

const closedAgency = adaptAgency({
  external_id: 404,
  code: 'CL404',
  name: 'CUSCO CENTRO',
  department: 'CUSCO',
  province: 'CUSCO',
  district: 'CUSCO',
  status: 'temporarily_closed',
  observations: 'Cierre publico por mantenimiento.',
});

describe('agency adapter', () => {
  it('normalizes English and Spanish fields without using zone as district', () => {
    expect(activeAgency.district).toBe('HUACHO');
    expect(movedAgency.name).toBe('VIÑANIS');
    expect(movedAgency.oldName).toBe('TACNA SUR');
    expect(movedAgency.mapUrl).toBe('https://maps.example.test/vinanis');
    expect(adaptAgency({ name: 'Sin distrito', zone: 'NO USAR' }).district).toBeNull();
  });

  it('handles null fields and public status filtering', () => {
    expect(adaptAgency({ name: null, latitude: null, longitude: null }).name).toBe('Agencia sin nombre');
    expect(isPublicAgency(activeAgency)).toBe(true);
    expect(isPublicAgency(movedAgency)).toBe(true);
    expect(isPublicAgency(closedAgency)).toBe(true);
    expect(isPublicAgency(adaptAgency({ name: 'Interna', status: 'under_review' }))).toBe(false);
    expect(isPublicAgency(adaptAgency({ name: 'Inactiva', estado: 'Inactiva' }))).toBe(false);
  });

  it('builds compact public notices for moved and temporarily closed agencies', () => {
    expect(statusNotice(movedAgency)).toEqual({ tone: 'warning', message: 'Esta agencia fue trasladada', details: ['Destino: NUEVA TACNA', 'Nueva direccion: Av. Nueva 789'] });
    expect(statusNotice(closedAgency)).toEqual({ tone: 'danger', message: 'Esta agencia se encuentra cerrada temporalmente', details: ['Cierre publico por mantenimiento.'] });
    expect(activeAgency.isOperationsCenter).toBe(true);
  });
});

describe('local search', () => {
  it('normalizes accents, repeated spaces, punctuation, hyphens, and CO synonyms', () => {
    expect(normalizeText('Centro   de-Operaciones ÁÉÍÓÚ')).toBe('co aeiou');
  });

  it('finds by name, old_name, code, geography, address, reference, ubigeo, and category while ignoring non-public agencies', () => {
    const agencies = [activeAgency, movedAgency, closedAgency, adaptAgency({ code: 'XX', name: 'Oculta', status: 'under_review' })];

    expect(searchAgencies(agencies, 'HU001')[0]?.agency.code).toBe('HU001');
    expect(searchAgencies(agencies, 'salaverry huacho')[0]?.agency.code).toBe('HU001');
    expect(searchAgencies(agencies, 'huacho antiguo')[0]?.agency.code).toBe('HU001');
    expect(searchAgencies(agencies, 'coronel gregorio albarracin')[0]?.agency.code).toBe('MV202');
    expect(searchAgencies(agencies, 'frente terminal')[0]?.agency.code).toBe('HU001');
    expect(searchAgencies(agencies, '150801')[0]?.agency.code).toBe('HU001');
    expect(searchAgencies(agencies, 'grande co')[0]?.agency.code).toBe('HU001');
    expect(searchAgencies(agencies, 'Oculta')).toHaveLength(0);
  });
});

describe('maps and tokens', () => {
  it('prioritizes map_url, then coordinates, then text search', () => {
    expect(buildMapsUrl(movedAgency)).toBe('https://maps.example.test/vinanis');
    expect(buildMapsUrl({ ...activeAgency, mapUrl: null, latitude: -12.1, longitude: -77.2 })).toBe('https://www.google.com/maps/search/?api=1&query=-12.1%2C-77.2');
    expect(buildMapsUrl({ ...activeAgency, mapUrl: 'javascript:alert(1)', latitude: null, longitude: null })).toContain('SALAVERRY%20HUACHO%20CO');
  });

  it('masks tokens without exposing secrets', () => {
    expect(maskToken('  crd_123456789X2A  ')).toBe('crd_••••••••••••9X2A');
    expect(maskToken('short')).toBe('•••••');
  });
});

describe('sync service', () => {
  it('creates initial cache and skips download when metadata revision is unchanged', async () => {
    const storage = memoryStorage();
    const client = {
      getMetadata: vi.fn().mockResolvedValue({ catalogRevision: 'rev-1', currentCursor: 'cursor-1' }),
      fetchAllAgencies: vi.fn().mockResolvedValue({ agencies: [activeAgency], cursor: 'cursor-1', catalogRevision: 'rev-1' }),
      fetchChanges: vi.fn(),
    };

    const sync = createSyncService(client, storage);
    await expect(sync.syncNow()).resolves.toMatchObject({ status: 'updated', agencyCount: 1 });
    await expect(sync.syncNow()).resolves.toMatchObject({ status: 'unchanged', agencyCount: 1 });
    expect(client.fetchAllAgencies).toHaveBeenCalledTimes(1);
  });

  it('preserves cache on network error, empty response, 401, and 403', async () => {
    const storage = memoryStorage({ agencies: [activeAgency], catalogRevision: 'rev-ok', cursor: 'cursor-ok' });
    const client = {
      getMetadata: vi.fn().mockResolvedValue({ catalogRevision: 'rev-2', currentCursor: 'cursor-2' }),
      fetchAllAgencies: vi.fn().mockRejectedValueOnce(Object.assign(new Error('offline'), { status: 0 })).mockResolvedValueOnce({ agencies: [], cursor: 'cursor-2', catalogRevision: 'rev-2' }).mockRejectedValueOnce(Object.assign(new Error('401'), { status: 401 })).mockRejectedValueOnce(Object.assign(new Error('403'), { status: 403 })),
      fetchChanges: vi.fn().mockResolvedValue({ upserted: [], deleted: [], nextCursor: 'cursor-2', hasMore: false }),
    };

    const sync = createSyncService(client, storage);
    await expect(sync.syncNow({ forceFull: true })).resolves.toMatchObject({ status: 'error', message: 'Sin conexion: usando datos guardados' });
    await expect(sync.syncNow({ forceFull: true })).resolves.toMatchObject({ status: 'error', message: 'La API devolvio cero agencias. Se conserva la copia local.' });
    await expect(sync.syncNow({ forceFull: true })).resolves.toMatchObject({ status: 'token_expired' });
    await expect(sync.syncNow({ forceFull: true })).resolves.toMatchObject({ status: 'forbidden' });
    await expect(storage.getAgencies()).resolves.toHaveLength(1);
  });
});

describe('runtime message contract', () => {
  it('accepts only known popup and options requests', () => {
    expect(isRuntimeRequest({ type: 'SYNC_NOW' })).toBe(true);
    expect(isRuntimeRequest({ type: 'SEARCH_AGENCIES', query: 'Tacna' })).toBe(true);
    expect(isRuntimeRequest({ type: 'CONFIG_SAVE', token: 'crd_abc' })).toBe(true);
    expect(isRuntimeRequest({ type: 'UNKNOWN' })).toBe(false);
  });
});

function memoryStorage(seed?: { agencies?: ReturnType<typeof adaptAgency>[]; catalogRevision?: string; cursor?: string }) {
  let agencies = seed?.agencies ?? [];
  let meta = { catalogRevision: seed?.catalogRevision ?? null, cursor: seed?.cursor ?? null, lastSyncedAt: null as string | null };

  return {
    getAgencies: async () => agencies,
    replaceAgencies: async (next: ReturnType<typeof adaptAgency>[], nextMeta: { catalogRevision: string | null; cursor: string | null }) => {
      agencies = next;
      meta = { ...meta, ...nextMeta, lastSyncedAt: '2026-07-30T00:00:00.000Z' };
    },
    getSyncMetadata: async () => meta,
    setSyncMetadata: async (next: Partial<typeof meta>) => {
      meta = { ...meta, ...next };
    },
  };
}

describe('token configuration flow', () => {
  it('popup is token-focused and does not render agency search or cards', async () => {
    const { readFileSync } = await import('node:fs');
    const html = readFileSync(new URL('../src/popup/popup.html', import.meta.url), 'utf8');
    const script = readFileSync(new URL('../src/popup/popup.ts', import.meta.url), 'utf8');
    expect(html).toContain('Estado de conexión');
    expect(html).toContain('Solicitar token');
    expect(html).toContain('Configurar token');
    expect(html).not.toContain('Buscar agencia');
    expect(html).not.toContain('id="query"');
    expect(html).not.toContain('id="results"');
    expect(script).not.toContain('SEARCH_AGENCIES');
    expect(script).not.toContain('buildMapsUrl');
    expect(script).not.toContain('Copiar');
  });

  it('uses a single canonical token storage key and migrates legacy auth', async () => {
    const { ChromeStorageService } = await import('../src/storage/storage-service');
    const { STORAGE_KEYS } = await import('../src/storage/storage-keys');
    const local = chromeStorageMock({ auth: { token: 'crd_legacy_1234567890', tokenMasked: 'legacy-mask' } });
    globalThis.chrome = { storage: { local } } as unknown as typeof chrome;

    const storage = new ChromeStorageService();
    const configuration = await storage.getConfiguration();

    expect(configuration.token).toBe('crd_legacy_1234567890');
    expect(configuration.tokenMasked).toBe('crd_••••••••••••7890');
    expect(local.dump()[STORAGE_KEYS.API_TOKEN]).toBe('crd_legacy_1234567890');
    expect(local.dump().auth).toBeUndefined();
  });

  it('saves and reads options and popup token from the same canonical key', async () => {
    const { ChromeStorageService } = await import('../src/storage/storage-service');
    const { STORAGE_KEYS } = await import('../src/storage/storage-keys');
    const local = chromeStorageMock();
    globalThis.chrome = { storage: { local } } as unknown as typeof chrome;

    const optionsStorage = new ChromeStorageService();
    await optionsStorage.saveToken('crd_shared_abcdef123456');
    const popupStorage = new ChromeStorageService();
    const state = await popupStorage.getConfiguration();

    expect(local.dump()[STORAGE_KEYS.API_TOKEN]).toBe('crd_shared_abcdef123456');
    expect(state.tokenMasked).toBe('crd_••••••••••••3456');
  });

  it('rejects direct token request creation messages from popup/options', () => {
    expect(isRuntimeRequest({ type: 'TOKEN_REQUEST_CREATE', requester_name: 'Ada', delivery_channel: 'whatsapp', delivery_destination: '+51987654321', instance_name: 'Ext', source: 'popup', requested_scopes: ['agencies:read'] })).toBe(false);
  });
});

function chromeStorageMock(seed: Record<string, unknown> = {}) {
  let store = { ...seed };
  return {
    async get(keys?: string | string[]) {
      if (keys === undefined) return { ...store };
      const selected = Array.isArray(keys) ? keys : [keys];
      return Object.fromEntries(selected.map((key) => [key, store[key]]));
    },
    async set(values: Record<string, unknown>) {
      store = { ...store, ...values };
    },
    async remove(keys: string | string[]) {
      for (const key of Array.isArray(keys) ? keys : [keys]) delete store[key];
    },
    dump: () => store,
  };
}
