import { adaptAgency, type Agency } from '../models/agency';

interface MetadataResult {
  catalogRevision: string | null;
  currentCursor: string | null;
}

interface FullSyncResult {
  agencies: Agency[] | Record<string, unknown>[];
  cursor: string | null;
  catalogRevision: string | null;
}

interface Client {
  getMetadata(): Promise<MetadataResult>;
  fetchAllAgencies(): Promise<FullSyncResult>;
  fetchChanges(cursor: string): Promise<{ upserted: Record<string, unknown>[]; deleted: Array<{ id?: string | number | null; code?: string | null }>; nextCursor: string | null; hasMore: boolean }>;
}

interface Storage {
  getAgencies(): Promise<Agency[]>;
  replaceAgencies(agencies: Agency[], meta: { catalogRevision: string | null; cursor: string | null }): Promise<void>;
  getSyncMetadata(): Promise<{ catalogRevision: string | null; cursor: string | null; lastSyncedAt: string | null }>;
  setSyncMetadata(meta: Partial<{ catalogRevision: string | null; cursor: string | null; lastSyncedAt: string | null }>): Promise<void>;
}

export type SyncResult = { status: 'updated' | 'unchanged' | 'error' | 'token_expired' | 'forbidden'; message: string; agencyCount: number };

export function createSyncService(client: Client, storage: Storage) {
  let running: Promise<SyncResult> | null = null;

  async function syncNow(options: { forceFull?: boolean } = {}): Promise<SyncResult> {
    if (running) return running;
    running = performSync(options).finally(() => {
      running = null;
    });
    return running;
  }

  async function performSync(options: { forceFull?: boolean }): Promise<SyncResult> {
    const current = await storage.getSyncMetadata();
    const cached = await storage.getAgencies();

    try {
      const metadata = await client.getMetadata();
      if (!options.forceFull && current.catalogRevision && current.catalogRevision === metadata.catalogRevision) {
        return { status: 'unchanged', message: 'Actualizado', agencyCount: cached.length };
      }

      const full = await client.fetchAllAgencies();
      const agencies = full.agencies.map((agency) => ('status' in agency && 'statusLabel' in agency ? (agency as Agency) : adaptAgency(agency as Record<string, unknown>)));
      if (agencies.length === 0) {
        return { status: 'error', message: 'La API devolvio cero agencias. Se conserva la copia local.', agencyCount: cached.length };
      }

      await storage.replaceAgencies(dedupeAgencies(agencies), { catalogRevision: full.catalogRevision ?? metadata.catalogRevision, cursor: full.cursor ?? metadata.currentCursor });
      const next = await storage.getAgencies();
      return { status: 'updated', message: 'Actualizado', agencyCount: next.length };
    } catch (error) {
      const status = typeof error === 'object' && error !== null && 'status' in error ? Number((error as { status: unknown }).status) : 0;
      if (status === 401) return { status: 'token_expired', message: 'El token no es valido o ha vencido', agencyCount: cached.length };
      if (status === 403) return { status: 'forbidden', message: 'El token no tiene permisos para consultar agencias', agencyCount: cached.length };
      return { status: 'error', message: 'Sin conexion: usando datos guardados', agencyCount: cached.length };
    }
  }

  return { syncNow };
}

function dedupeAgencies(agencies: Agency[]): Agency[] {
  const seen = new Set<string>();
  const result: Agency[] = [];
  for (const agency of agencies) {
    const key = String(agency.externalId ?? agency.id ?? agency.code ?? agency.name);
    if (seen.has(key)) continue;
    seen.add(key);
    result.push(agency);
  }
  return result;
}
