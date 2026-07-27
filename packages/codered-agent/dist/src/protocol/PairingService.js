import { Logger } from '../logging/Logger.js';
export class PairingService {
    config;
    storage;
    client;
    discovery;
    heartbeat;
    logger;
    constructor(config, storage, client, discovery, heartbeat, logger = new Logger(config.logLevel)) {
        this.config = config;
        this.storage = storage;
        this.client = client;
        this.discovery = discovery;
        this.heartbeat = heartbeat;
        this.logger = logger;
    }
    async pair(pairCode) {
        if (!pairCode) {
            throw new Error('pair_code is required');
        }
        this.logger.info('pairing.started');
        const response = await this.client.pair(pairCode);
        const data = response.data;
        if (!data?.integration_uuid || !data.shared_secret) {
            this.logger.error('pairing.failed', { reason: 'invalid_platform_response' });
            throw new Error('Invalid pairing response');
        }
        const pairedAt = data.paired_at || new Date().toISOString();
        const integration = {
            integration_uuid: data.integration_uuid,
            shared_secret: data.shared_secret,
            protocol_version: data.protocol_version || '1.0',
            paired_at: pairedAt,
            platform_url: this.config.platformUrl,
            agent_name: this.config.name,
            environment: this.config.environment,
            secret_version: 1,
        };
        try {
            await this.storage.saveIntegration(integration);
            this.client.setPairing(integration);
        }
        catch (error) {
            this.client.clearPairing();
            this.logger.error('pairing.persistence_failed', { error: error instanceof Error ? error.message : 'Unknown persistence error' });
            throw error;
        }
        const heartbeatSent = await this.heartbeat.send();
        const discoveryCompleted = await this.discovery.sync(true);
        this.logger.info('pairing.completed', { instanceId: data.integration_uuid, heartbeatSent, discoveryCompleted });
        return {
            success: true,
            paired: true,
            instanceId: data.integration_uuid,
            integration_uuid: data.integration_uuid,
            paired_at: pairedAt,
            platformConnected: heartbeatSent,
            discoveryCompleted,
            capabilities: this.discovery.capabilityCount,
            workflows: this.discovery.workflowCount,
        };
    }
}
