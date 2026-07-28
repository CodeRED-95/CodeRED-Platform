import type { IExecuteFunctions } from 'n8n-workflow';
import { joinUrl, stableJson, type CodeREDCredentials } from './GenericFunctions';

export class ConnectionManager {
  public constructor(private ctx: IExecuteFunctions, private credentials: CodeREDCredentials) {}

  public async connect(input: { pairCode: string }): Promise<Record<string, unknown>> {
    return this.request('POST', '/api/v1/pair', {
      pair_code: input.pairCode,
      instance_name: this.credentials.instanceName,
      instance_url: this.credentials.publicUrl,
      version: process.env.N8N_VERSION || undefined,
      environment: this.credentials.environment,
    });
  }

  public async disconnect(): Promise<Record<string, unknown>> {
    return this.request('POST', '/api/v1/disconnect');
  }

  public async rotateSecret(): Promise<Record<string, unknown>> {
    return this.request('POST', '/api/v1/rotate-secret');
  }

  public async status(): Promise<Record<string, unknown>> {
    return this.request('GET', '/api/v1/status');
  }

  private async request(method: 'GET' | 'POST', path: string, body?: unknown): Promise<Record<string, unknown>> {
    return this.ctx.helpers.httpRequest({
      method,
      url: joinUrl(this.credentials.agentBaseUrl || '', path),
      body: body === undefined ? undefined : stableJson(body),
      headers: { 'Content-Type': 'application/json', Authorization: 'Bearer '+(this.credentials.localApiToken || '') },
      json: true,
      timeout: Number(this.credentials.timeoutMs || 15000),
    }) as Promise<Record<string, unknown>>;
  }
}
