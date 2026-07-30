import { adaptAgency, type Agency } from '../models/agency';

export class CodeRedApiError extends Error {
  constructor(public status: number, message: string) {
    super(message);
  }
}

export class CodeRedClient {
  constructor(private readonly apiBaseUrl: string, private readonly token: string, private readonly timeoutMs = 12000) {}

  async fetchPublicConfig(platformBaseUrl: string) {
    const response = await fetch(`${platformBaseUrl.replace(/\/+$/, '')}/api/v1/extension/chrome/config`, { headers: { Accept: 'application/json' } });
    return response.json();
  }

  async validateToken(): Promise<{ abilities: string[] }> {
    const data = await this.request<{ abilities: string[] }>('/me');
    return data;
  }

  async getMetadata(): Promise<{ catalogRevision: string | null; currentCursor: string | null }> {
    const data = await this.request<Record<string, unknown>>('/catalog/metadata');
    return { catalogRevision: String(data.catalog_revision ?? ''), currentCursor: typeof data.current_cursor === 'string' ? data.current_cursor : null };
  }

  async fetchAllAgencies(): Promise<{ agencies: Agency[]; cursor: string | null; catalogRevision: string | null }> {
    const agencies: Agency[] = [];
    let page = 1;
    let cursor: string | null = null;
    let catalogRevision: string | null = null;

    while (page <= 200) {
      const data = await this.request<{ data: Record<string, unknown>[]; meta?: Record<string, unknown>; links?: { next?: string | null } }>(`/agencies?per_page=100&page=${page}`);
      agencies.push(...(data.data ?? []).map(adaptAgency));
      cursor = typeof data.meta?.current_cursor === 'string' ? data.meta.current_cursor : cursor;
      catalogRevision = typeof data.meta?.catalog_revision === 'string' ? data.meta.catalog_revision : catalogRevision;
      if (!data.links?.next) break;
      page += 1;
    }

    return { agencies, cursor, catalogRevision };
  }

  async fetchChanges(cursor: string) {
    const data = await this.request<{ data: { upserted: Record<string, unknown>[]; deleted: Array<{ id?: string | number | null; code?: string | null }> }; meta: { next_cursor: string | null; has_more: boolean } }>(`/agencies/changes?cursor=${encodeURIComponent(cursor)}`);
    return { upserted: data.data.upserted, deleted: data.data.deleted, nextCursor: data.meta.next_cursor, hasMore: data.meta.has_more };
  }

  private async request<T>(path: string): Promise<T> {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), this.timeoutMs);
    try {
      const response = await fetch(`${this.apiBaseUrl}${path}`, {
        signal: controller.signal,
        headers: { Accept: 'application/json', Authorization: `Bearer ${this.token}` },
      });
      if (!response.ok) throw new CodeRedApiError(response.status, response.statusText);
      return (await response.json()) as T;
    } finally {
      clearTimeout(timeout);
    }
  }
}
