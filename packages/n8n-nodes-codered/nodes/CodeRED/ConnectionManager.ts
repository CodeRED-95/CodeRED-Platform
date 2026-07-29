import type { CodeREDCredentials } from './GenericFunctions';
import { callLocalAgent } from './LocalAgentClient';

export class ConnectionManager {
  public constructor(private credentials: CodeREDCredentials) {}

  public async connect(input: { pairCode: string }): Promise<Record<string, unknown>> {
    return callLocalAgent<Record<string, unknown>>('/api/v1/pair', {
      method: 'POST',
      operation: 'pair',
      timeoutMs: Number(this.credentials.timeoutMs || 15000),
      body: {
        pair_code: input.pairCode.trim(),
        instance_name: String(this.credentials.instanceName || '').trim(),
        instance_url: normalizeUrl(String(this.credentials.instanceUrl || this.credentials.publicUrl || '')),
        environment: normalizeEnvironment(String(this.credentials.environment || 'production')),
        version: process.env.N8N_VERSION?.trim() || 'unknown',
        platform_url: normalizeUrl(String(this.credentials.baseUrl || '')),
      },
    });
  }

  public async testConnection(): Promise<Record<string, unknown>> {
    const timeoutMs = Number(this.credentials.timeoutMs || 15000);
    const health = await callLocalAgent<Record<string, unknown>>('/healthz', { timeoutMs, operation: 'healthz' });
    const status = await callLocalAgent<Record<string, unknown>>('/api/v1/status', { timeoutMs, operation: 'status' });

    if (health.protocol_version && health.protocol_version !== '1.0') {
      throw new Error('El nodo CodeRED y CodeRED Agent utilizan versiones de protocolo incompatibles.');
    }

    return { success: true, agentReachable: true, health, status };
  }

  public async reconnect(input: { pairCode: string }): Promise<Record<string, unknown>> {
    return callLocalAgent<Record<string, unknown>>('/api/v1/reconnect', {
      method: 'POST',
      operation: 'reconnect',
      timeoutMs: Number(this.credentials.timeoutMs || 15000),
      body: {
        pair_code: input.pairCode.trim(),
        instance_name: String(this.credentials.instanceName || '').trim(),
        instance_url: normalizeUrl(String(this.credentials.instanceUrl || this.credentials.publicUrl || '')),
        environment: normalizeEnvironment(String(this.credentials.environment || 'production')),
        version: process.env.N8N_VERSION?.trim() || 'unknown',
        platform_url: normalizeUrl(String(this.credentials.baseUrl || '')),
      },
    });
  }

  public async disconnect(): Promise<Record<string, unknown>> {
    return callLocalAgent<Record<string, unknown>>('/api/v1/disconnect', { method: 'POST', operation: 'disconnect', timeoutMs: Number(this.credentials.timeoutMs || 15000) });
  }

  public async rotateSecret(): Promise<Record<string, unknown>> {
    return callLocalAgent<Record<string, unknown>>('/api/v1/rotate-secret', { method: 'POST', operation: 'rotateSecret', timeoutMs: Number(this.credentials.timeoutMs || 15000) });
  }

  public async refreshDiscovery(): Promise<Record<string, unknown>> {
    return callLocalAgent<Record<string, unknown>>('/api/v1/discovery/refresh', { method: 'POST', operation: 'refreshDiscovery', timeoutMs: Number(this.credentials.timeoutMs || 15000) });
  }

  public async status(): Promise<Record<string, unknown>> {
    return callLocalAgent<Record<string, unknown>>('/api/v1/status', { operation: 'status', timeoutMs: Number(this.credentials.timeoutMs || 15000) });
  }
  public async createTokenRequest(input: Record<string, unknown>): Promise<Record<string, unknown>> {
    return callLocalAgent<Record<string, unknown>>('/api/v1/token-requests', {
      method: 'POST',
      operation: 'createTokenRequest',
      timeoutMs: Number(this.credentials.timeoutMs || 15000),
      body: input,
    });
  }

  public async getTokenRequestStatus(requestId: string): Promise<Record<string, unknown>> {
    return callLocalAgent<Record<string, unknown>>('/api/v1/token-requests/' + encodeURIComponent(requestId), {
      method: 'GET',
      operation: 'getTokenRequestStatus',
      timeoutMs: Number(this.credentials.timeoutMs || 15000),
    });
  }

  public async retrieveApprovedToken(requestId: string): Promise<Record<string, unknown>> {
    return callLocalAgent<Record<string, unknown>>('/api/v1/token-requests/' + encodeURIComponent(requestId) + '/retrieve', {
      method: 'POST',
      operation: 'retrieveApprovedToken',
      timeoutMs: Number(this.credentials.timeoutMs || 15000),
      body: {},
    });
  }

  public async confirmTokenDelivery(requestId: string, input: Record<string, unknown>): Promise<Record<string, unknown>> {
    return callLocalAgent<Record<string, unknown>>('/api/v1/token-requests/' + encodeURIComponent(requestId) + '/delivery', {
      method: 'POST',
      operation: 'confirmTokenDelivery',
      timeoutMs: Number(this.credentials.timeoutMs || 15000),
      body: input,
    });
  }

  public async cancelTokenRequest(requestId: string, input: Record<string, unknown>): Promise<Record<string, unknown>> {
    return callLocalAgent<Record<string, unknown>>('/api/v1/token-requests/' + encodeURIComponent(requestId) + '/cancel', {
      method: 'POST',
      operation: 'cancelTokenRequest',
      timeoutMs: Number(this.credentials.timeoutMs || 15000),
      body: input,
    });
  }
}

function normalizeUrl(value: string): string {
  const trimmed = value.trim();

  if (!trimmed) {
    return trimmed;
  }

  return new URL(trimmed).toString();
}

function normalizeEnvironment(value: string): string {
  const normalized = value.trim().toLowerCase();

  return normalized || 'production';
}
