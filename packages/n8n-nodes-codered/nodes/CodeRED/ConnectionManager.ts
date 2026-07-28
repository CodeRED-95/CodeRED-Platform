import type { IExecuteFunctions } from 'n8n-workflow';
import { joinUrl, stableJson, type CodeREDCredentials } from './GenericFunctions';

const DEFAULT_AGENT_URL = 'http://codered-agent:5680';

export class ConnectionManager {
  public constructor(private ctx: IExecuteFunctions, private credentials: CodeREDCredentials) {}

  public async connect(input: { pairCode: string }): Promise<Record<string, unknown>> {
    return this.callLocalAgent('/api/v1/pair', {
      method: 'POST',
      body: {
        pair_code: input.pairCode,
        instance_name: this.credentials.instanceName,
        instance_url: this.credentials.instanceUrl || this.credentials.publicUrl,
        environment: this.credentials.environment,
        version: process.env.N8N_VERSION || 'unknown',
        platform_url: this.credentials.baseUrl,
      },
    });
  }

  public async disconnect(): Promise<Record<string, unknown>> {
    return this.callLocalAgent('/api/v1/disconnect', { method: 'POST' });
  }

  public async rotateSecret(): Promise<Record<string, unknown>> {
    return this.callLocalAgent('/api/v1/rotate-secret', { method: 'POST' });
  }

  public async status(): Promise<Record<string, unknown>> {
    return this.callLocalAgent('/api/v1/status');
  }

  private async callLocalAgent(
    path: string,
    options: { method?: 'GET' | 'POST'; body?: unknown } = {},
  ): Promise<Record<string, unknown>> {
    const baseUrl = process.env.CODERED_AGENT_LOCAL_URL || this.credentials.agentBaseUrl || DEFAULT_AGENT_URL;
    const token = process.env.CODERED_AGENT_LOCAL_API_TOKEN || this.credentials.localApiToken;

    if (!token) {
      throw new Error('CODERED_AGENT_LOCAL_API_TOKEN no está configurado en n8n.');
    }

    try {
      return await this.ctx.helpers.httpRequest({
        method: options.method || 'GET',
        url: joinUrl(baseUrl, path),
        body: options.body === undefined ? undefined : stableJson(options.body),
        headers: {
          Authorization: 'Bearer ' + token,
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        json: true,
        timeout: Number(this.credentials.timeoutMs || 15000),
      }) as Promise<Record<string, unknown>>;
    } catch (error) {
      const details = error as { statusCode?: number; status?: number; message?: string };
      const status = details.statusCode || details.status;

      if (!status) {
        throw new Error('CodeRED Agent no está disponible en ' + baseUrl + '.');
      }

      throw error;
    }
  }
}
