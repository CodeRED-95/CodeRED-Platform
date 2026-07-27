export class ReconnectionService {
    storage;
    discovery;
    heartbeat;
    constructor(storage, discovery, heartbeat) {
        this.storage = storage;
        this.discovery = discovery;
        this.heartbeat = heartbeat;
    }
    async start() { if (!await this.storage.hasIntegration())
        return 'unpaired'; await this.heartbeat.send(); if (this.heartbeat.status === 'connected')
        await this.discovery.sync(true); return this.heartbeat.status; }
}
