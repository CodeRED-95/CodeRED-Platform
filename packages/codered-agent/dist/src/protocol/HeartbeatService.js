import { AgentUnpairedError } from '../errors/AgentUnpairedError.js';
import { Logger } from '../logging/Logger.js';
export class HeartbeatService {
    config;
    client;
    logger;
    lastHeartbeatAt = null;
    failures = 0;
    latencyMs = null;
    status = 'unpaired';
    lastError = null;
    running = false;
    constructor(config, client, logger = new Logger(config.logLevel)) {
        this.config = config;
        this.client = client;
        this.logger = logger;
    }
    async send() {
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
        }
        catch (error) {
            this.failures += 1;
            this.lastError = error instanceof Error ? error.message : 'Unknown heartbeat error';
            if (error instanceof AgentUnpairedError) {
                this.status = 'unpaired';
                this.logger.warn('heartbeat.skipped', { reason: 'agent_unpaired' });
                return false;
            }
            const status = error.status;
            if (status === 401 || status === 403) {
                this.status = 'requires_repairing';
                this.logger.error('platform.unauthorized', { statusCode: status, retry: this.failures });
            }
            else if (status === 410) {
                this.status = 'revoked';
            }
            else {
                this.status = this.failures > 3 ? 'disconnected' : 'degraded';
            }
            this.logger.error('heartbeat.failed', { statusCode: status, retry: this.failures, error: this.lastError });
            return false;
        }
        finally {
            this.running = false;
        }
    }
}
