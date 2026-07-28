import crypto from 'node:crypto';
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
    async pair(input) {
        const pairCode = input.pairCode?.trim();
        if (!pairCode) {
            throw new Error('pairCode is required');
        }
        const existing = await this.storage.readIntegration();
        const instanceUuid = existing?.instance_uuid || crypto.randomUUID();
        this.logger.info('pairing.started', { instanceUuid });
        const response = await this.client.pair({ ...input, pairCode, instanceUuid });
        const data = response.data;
        if (!data?.integration_uuid || !data.shared_secret) {
            this.logger.error('pairing.failed', { reason: 'invalid_platform_response' });
            throw new Error('Invalid pairing response');
        }
        const pairedAt = data.paired_at || new Date().toISOString();
        const integration = {
            instance_uuid: instanceUuid,
            integration_uuid: data.integration_uuid,
            shared_secret: data.shared_secret,
            protocol_version: data.protocol_version || '1.0',
            paired_at: pairedAt,
            platform_url: this.config.platformUrl,
            agent_name: this.config.name,
            instance_name: input.instanceName || this.config.name,
            instance_url: input.publicUrl || this.config.publicUrl,
            environment: input.environment || this.config.environment,
            secret_version: 1,
            discovery_url: data.discovery_url || '/api/v1/integrations/n8n/discovery',
            heartbeat_url: data.heartbeat_url || '/api/v1/integrations/n8n/heartbeat',
            challenge_url: data.challenge_url || '/api/v1/integrations/n8n/challenge',
        };
        try {
            await this.storage.saveIntegration(integration);
            this.client.setPairing(integration);
            this.logger.info('identity.saved', { paired: true, integration_uuid: integration.integration_uuid, instanceUuid: integration.instance_uuid });
            this.logger.info('pairing.persisted', { instanceId: integration.integration_uuid });
        }
        catch (error) {
            this.client.clearPairing();
            this.logger.error('pairing.persistence_failed', { error: error instanceof Error ? error.message : 'Unknown persistence error' });
            throw error;
        }
        this.logger.info('pairing.completed', { instanceId: data.integration_uuid });
        return {
            success: true,
            paired: this.client.isPaired(),
            instanceId: data.integration_uuid,
            protocolVersion: integration.protocol_version,
            pairedAt,
        };
    }
}
