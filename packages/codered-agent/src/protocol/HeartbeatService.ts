import type { Config } from '../config/Config.js';
import type { AgentStorage } from '../storage/AgentStorage.js';
import { CodeREDClient } from './CodeREDClient.js';

export type AgentStatus = 'connected' | 'degraded' | 'disconnected' | 'revoked' | 'unpaired' | 'requires_repairing';

type HttpLikeError = Error & { status?: number };

export class HeartbeatService {
  public lastHeartbeatAt: string | null = null;
  public failures = 0;
  public latencyMs: number | null = null;
  public status: AgentStatus = 'unpaired';

  public constructor(private config: Config, private storage: AgentStorage, private client: CodeREDClient) {}

  public async send(): Promise<boolean> {
    const integration = await this.storage.readIntegration();

    if (!integration) {
      this.status = 'unpaired';

      return false;
    }

    const start = Date.now();

    try {
      await this.client.signed('POST', '/api/v1/integrations/n8n/heartbeat', {
        instance_uuid: integration.integration_uuid,
        agent_version: '1.0.0',
        connector_version: 'codered-agent/1.0.0',
        protocol_version: '1.0',
        environment: this.config.environment,
        sent_at: new Date().toISOString(),
        services: ['n8n'],
      });

      this.latencyMs = Date.now() - start;
      this.lastHeartbeatAt = new Date().toISOString();
      this.failures = 0;
      this.status = 'connected';

      return true;
    } catch (error) {
      const status = (error as HttpLikeError).status;
      this.failures += 1;

      if (status === 401 || status === 403) {
        this.status = 'requires_repairing';
      } else if (status === 410) {
        this.status = 'revoked';
      } else {
        this.status = this.failures > 3 ? 'disconnected' : 'degraded';
      }

      return false;
    }
  }
}