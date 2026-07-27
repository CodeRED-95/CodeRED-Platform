import { Logger } from '../logging/Logger.js';
export class ReconnectionService {
    storage;
    client;
    discovery;
    heartbeat;
    logger;
    constructor(storage, client, discovery, heartbeat, logger = new Logger()) {
        this.storage = storage;
        this.client = client;
        this.discovery = discovery;
        this.heartbeat = heartbeat;
        this.logger = logger;
    }
    async start() {
        const integration = await this.storage.readIntegration();
        if (!integration) {
            this.client.clearPairing();
            this.heartbeat.status = 'unpaired';
            this.logger.warn('agent.unpaired');
            return 'unpaired';
        }
        this.client.setPairing(integration);
        this.logger.info('pairing.restored', { instanceId: integration.integration_uuid, pairedAt: integration.paired_at });
        await this.heartbeat.send();
        if (this.heartbeat.status === 'connected') {
            await this.discovery.sync(true);
        }
        return this.heartbeat.status;
    }
}
