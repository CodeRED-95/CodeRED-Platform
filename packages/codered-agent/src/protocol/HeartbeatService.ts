import type { Config } from '../config/Config.js';
import { AgentUnpairedError } from '../errors/AgentUnpairedError.js';
import { Logger } from '../logging/Logger.js';
import { CodeREDClient, type HttpError } from './CodeREDClient.js';

export type AgentStatus = 'connected' | 'degraded' | 'disconnected' | 'revoked' | 'unpaired' | 'requires_repairing';

export class HeartbeatService {
  public lastHeartbeatAt: string | null = null;
  public failures = 0;
  public latencyMs: number | null = null;
  public status: AgentStatus = 'unpaired';
  public lastError: string | null = null;
  private running = false;

  public constructor(private config: Config, private client: CodeREDClient, private logger = new Logger(config.logLevel)) {}

  public async send(): Promise<boolean> {
    if (this.running) {
      this.logger.warn('heartbeat.skipped', { reason: 'already_running' });

      return false;
    }

    if (!this.client.isPaired()) {
      await this.client.restorePairing();
    }

    const integration = this.client.currentIntegration();

    if (!integration) {
      this.status = 'unpaired';
      this.logger.warn('heartbeat.skipped', { reason: 'agent_unpaired' });

      return false;
    }

    this.running = true;
    const start = Date.now();

    try {
      await this.client.signed('POST', integration.heartbeat_url || '/api/v1/integrations/n8n/heartbeat', {
        instance_uuid: integration.integration_uuid,
        agent_version: '1.0.0',
        connector_version: 'codered-agent/1.0.0',
        protocol_version: integration.protocol_version,
        environment: this.config.environment,
        timestamp: new Date().toISOString(),
        sent_at: new Date().toISOString(),
        status: this.status,
        uptime: Math.round(process.uptime()),
        latency: this.latencyMs,
        memory: process.memoryUsage().rss,
        workflow_count: 0,
        active_executions: 0,
        version: process.env.N8N_VERSION || null,
        capabilities: 0,
        workflows: 0,
        services: ['n8n', 'agent'],
      });

      this.latencyMs = Date.now() - start;
      this.lastHeartbeatAt = new Date().toISOString();
      this.failures = 0;
      this.status = 'connected';
      this.lastError = null;
      this.logger.info('heartbeat.sent', { instanceId: integration.integration_uuid, durationMs: this.latencyMs });

      return true;
    } catch (error) {
      this.failures += 1;
      this.lastError = error instanceof Error ? error.message : 'Unknown heartbeat error';

      if (error instanceof AgentUnpairedError) {
        this.status = 'unpaired';
        this.logger.warn('heartbeat.skipped', { reason: 'agent_unpaired' });

        return false;
      }

      const status = (error as HttpError).status;

      if (status === 401 || status === 403) {
        this.status = 'requires_repairing';
        this.logger.error('platform.unauthorized', { statusCode: status, retry: this.failures });
      } else if (status === 410) {
        this.status = 'revoked';
      } else {
        this.status = this.failures > 3 ? 'disconnected' : 'degraded';
      }

      this.logger.error('heartbeat.failed', { statusCode: status, retry: this.failures, error: this.lastError });

      return false;
    } finally {
      this.running = false;
    }
  }
}
