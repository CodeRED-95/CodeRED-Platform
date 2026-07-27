export class PairingService {
    config;
    storage;
    client;
    discovery;
    heartbeat;
    constructor(config, storage, client, discovery, heartbeat) {
        this.config = config;
        this.storage = storage;
        this.client = client;
        this.discovery = discovery;
        this.heartbeat = heartbeat;
    }
    async pair(pairCode) { if (!pairCode)
        throw new Error('pair_code is required'); const response = await this.client.pair(pairCode); const data = response.data; if (!data?.integration_uuid || !data?.shared_secret)
        throw new Error('Invalid pairing response'); const pairedAt = data.paired_at || new Date().toISOString(); await this.storage.saveIntegration({ integration_uuid: data.integration_uuid, shared_secret: data.shared_secret, protocol_version: data.protocol_version || '1.0', paired_at: pairedAt, platform_url: this.config.platformUrl, agent_name: this.config.name, environment: this.config.environment, secret_version: 1 }); const discovery_registered = await this.discovery.sync(true); const heartbeat_sent = await this.heartbeat.send(); return { success: true, integration_uuid: data.integration_uuid, paired_at: pairedAt, discovery_registered, heartbeat_sent }; }
}
