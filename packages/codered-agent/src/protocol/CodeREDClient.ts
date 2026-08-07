import type { Config } from '../config/Config.js';
import type { AgentStorage } from '../storage/AgentStorage.js';
import type { StoredIntegration } from '../storage/types.js';
import { Logger } from '../logging/Logger.js';
import type { PlatformPairRequest } from './PairRequests.js';
import { AgentUnpairedError } from '../errors/AgentUnpairedError.js';
import { signedHeaders, stableJson } from './RequestSigner.js';

export type HttpError = Error & { status?: number; retryAfter?: string; responseBody?: unknown };

export class CodeREDClient {
  private integration: StoredIntegration | null = null;

  public constructor(private config: Config, private storage: AgentStorage, private logger = new Logger(config.logLevel)) {}

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

  public async pair(payload: PlatformPairRequest): Promise<Record<string, unknown>> {
    const body = stableJson({
      pair_code: payload.pair_code,
      instance_uuid: payload.instance_uuid,
      instance_name: payload.instance_name,
      instance_url: payload.instance_url,
      environment: payload.environment,
      n8n_version: payload.version || process.env.N8N_VERSION || null,
      version: payload.version || process.env.N8N_VERSION || null,
      agent_version: payload.agent_version || '1.0.0',
      connector_version: 'codered-agent/1.0.0',
      protocol_version: '1.0',
    });
    const bodyKeys = Object.keys(JSON.parse(body) as Record<string, unknown>);

    this.logger.info('pair.platform_request', {
      instance_uuid: payload.instance_uuid,
      instance_name: payload.instance_name,
      instance_url: payload.instance_url,
      environment: payload.environment,
      pair_code_present: Boolean(payload.pair_code),
      payload_keys: bodyKeys,
    });

    return this.raw('POST', '/api/v1/integrations/n8n/pair', body, null);
  }

  public async bearer(method: string, path: string, bearerToken: string, payload: unknown = {}): Promise<Record<string, unknown>> {
    const body = method === 'GET' ? '' : stableJson(payload);
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.config.requestTimeoutMs);

    try {
      const response = await fetch(this.config.platformUrl + path, {
        method,
        body: body || undefined,
        headers: {
          Authorization: 'Bearer ' + bearerToken,
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        signal: controller.signal,
      });
      const text = await response.text();
      let json: Record<string, unknown> = {};

      try {
        json = text ? JSON.parse(text) as Record<string, unknown> : {};
      } catch (parseError) {
        // Response is not JSON (likely HTML error page from upstream)
        const contentType = response.headers.get('content-type') || 'unknown';
        const error = new Error(`CodeRED request failed ${response.status}: ${contentType}`) as HttpError;
        error.status = response.status;
        error.retryAfter = response.headers.get('retry-after') || undefined;
        error.responseBody = { error: 'Non-JSON response', contentType, sample: text.substring(0, 200) };
        throw error;
      }

      if (!response.ok) {
        const error = new Error(`CodeRED request failed ${response.status}`) as HttpError;
        error.status = response.status;
        error.retryAfter = response.headers.get('retry-after') || undefined;
        error.responseBody = json;
        throw error;
      }

      return json;
    } finally {
      clearTimeout(timer);
    }
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
      let json: Record<string, unknown> = {};

      try {
        json = text ? JSON.parse(text) as Record<string, unknown> : {};
      } catch (parseError) {
        // Response is not JSON (likely HTML error page from upstream)
        const contentType = response.headers.get('content-type') || 'unknown';
        const error = new Error(`CodeRED request failed ${response.status}: ${contentType}`) as HttpError;
        error.status = response.status;
        error.retryAfter = response.headers.get('retry-after') || undefined;
        error.responseBody = { error: 'Non-JSON response', contentType, sample: text.substring(0, 200) };
        throw error;
      }

      if (!response.ok) {
        const error = new Error(`CodeRED request failed ${response.status}`) as HttpError;
        error.status = response.status;
        error.retryAfter = response.headers.get('retry-after') || undefined;
        error.responseBody = json;
        throw error;
      }

      return json;
    } finally {
      clearTimeout(timer);
    }
  }
}
