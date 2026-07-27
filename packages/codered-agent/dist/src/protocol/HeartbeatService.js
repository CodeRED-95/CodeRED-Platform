export class HeartbeatService {
    config;
    storage;
    client;
    lastHeartbeatAt = null;
    failures = 0;
    latencyMs = null;
    status = 'unpaired';
    constructor(config, storage, client) {
        this.config = config;
        this.storage = storage;
        this.client = client;
    }
    async send() {
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
        }
        catch (error) {
            const status = error.status;
            this.failures += 1;
            if (status === 401 || status === 403) {
                this.status = 'requires_repairing';
            }
            else if (status === 410) {
                this.status = 'revoked';
            }
            else {
                this.status = this.failures > 3 ? 'disconnected' : 'degraded';
            }
            return false;
        }
    }
}
