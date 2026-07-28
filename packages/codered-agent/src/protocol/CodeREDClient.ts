import type { Config } from '../config/Config.js';
import type { AgentStorage } from '../storage/AgentStorage.js';
import type { StoredIntegration } from '../storage/types.js';
import { AgentUnpairedError } from '../errors/AgentUnpairedError.js';
import { signedHeaders, stableJson } from './RequestSigner.js';

export type HttpError = Error & { status?: number; retryAfter?: string };

export class CodeREDClient {
  private integration: StoredIntegration | null = null;

  public constructor(private config: Config, private storage: AgentStorage) {}

  public async restorePairing(): Promise<boolean> {
    this.integration = await this.storage.readIntegration();

    return this.integration !== null;
  }

  public isPaired(): boolean {
    return this.integration !== null;
  }

  public currentIntegration(): StoredIntegration | null {
    return this.integration;
  }

  public setPairing(integration: StoredIntegration): void {
    this.integration = integration;
  }

  public clearPairing(): void {
    this.integration = null;
  }

  public async pair(input: { pairCode: string; instanceName?: string; publicUrl?: string; environment?: string }): Promise<Record<string, unknown>> {
    const body = stableJson({
      pair_code: input.pairCode,
      instance_name: input.instanceName || this.config.name,
      instance_url: input.publicUrl || this.config.publicUrl,
      environment: input.environment || this.config.environment,
      n8n_version: process.env.N8N_VERSION || null,
      connector_version: 'codered-agent/1.0.0',
      protocol_version: '1.0',
    });

    return this.raw('POST', '/api/v1/integrations/n8n/pair', body, null);
  }

  public async signed(method: string, path: string, payload: unknown = {}): Promise<Record<string, unknown>> {
    const integration = this.integration ?? await this.storage.readIntegration();

    if (!integration) {
      throw new AgentUnpairedError();
    }

    this.integration = integration;
    const body = method === 'GET' ? '' : stableJson(payload);

    return this.raw(method, path, body, integration);
  }

  private async raw(
    method: string,
    path: string,
    body: string,
    integration: StoredIntegration | null,
  ): Promise<Record<string, unknown>> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.config.requestTimeoutMs);

    try {
      const response = await fetch(this.config.platformUrl + path, {
        method,
        body: body || undefined,
        headers: integration ? signedHeaders(integration, method, path, body) : { 'Content-Type': 'application/json' },
        signal: controller.signal,
      });
      const text = await response.text();
      const json = text ? JSON.parse(text) as Record<string, unknown> : {};

      if (!response.ok) {
        const error = new Error(`CodeRED request failed ${response.status}`) as HttpError;
        error.status = response.status;
        error.retryAfter = response.headers.get('retry-after') || undefined;
        throw error;
      }

      return json;
    } finally {
      clearTimeout(timer);
    }
  }
}
