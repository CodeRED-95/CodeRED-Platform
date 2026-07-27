import { CapabilityRegistry } from '../services/CapabilityRegistry.js';
import { ServiceRegistry } from '../services/ServiceRegistry.js';
import { PluginRegistry } from '../services/PluginRegistry.js';
export class DiscoveryService {
    config;
    client;
    caps;
    services;
    plugins;
    lastDiscoveryAt = null;
    lastChecksum = '';
    constructor(config, client, caps = new CapabilityRegistry(), services = new ServiceRegistry(), plugins = new PluginRegistry()) {
        this.config = config;
        this.client = client;
        this.caps = caps;
        this.services = services;
        this.plugins = plugins;
    }
    async sync(force = false) { const checksum = this.caps.checksum(this.config.publicUrl); if (!force && checksum === this.lastChecksum)
        return false; await this.client.signed('POST', '/api/v1/integrations/n8n/discovery', { protocol_version: '1.0', agent_version: '1.0.0', connector_version: 'codered-agent/1.0.0', capabilities: this.caps.capabilities(this.config.publicUrl), services: this.services.services(), plugins: this.plugins.plugins() }); this.lastChecksum = checksum; this.lastDiscoveryAt = new Date().toISOString(); return true; }
}
