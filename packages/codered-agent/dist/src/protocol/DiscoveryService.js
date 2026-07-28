import { AgentUnpairedError } from '../errors/AgentUnpairedError.js';
import { Logger } from '../logging/Logger.js';
import { CapabilityRegistry } from '../services/CapabilityRegistry.js';
import { PluginRegistry } from '../services/PluginRegistry.js';
import { ServiceRegistry } from '../services/ServiceRegistry.js';
export class DiscoveryService {
    config;
    client;
    logger;
    caps;
    services;
    plugins;
    lastDiscoveryAt = null;
    lastError = null;
    capabilityCount = 0;
    workflowCount = 0;
    lastChecksum = '';
    running = false;
    constructor(config, client, logger = new Logger(config.logLevel), caps = new CapabilityRegistry(), services = new ServiceRegistry(), plugins = new PluginRegistry()) {
        this.config = config;
        this.client = client;
        this.logger = logger;
        this.caps = caps;
        this.services = services;
        this.plugins = plugins;
    }
    async sync(force = false) {
        if (this.running) {
            this.logger.warn('discovery.skipped', { reason: 'already_running' });
            return false;
        }
        if (!this.client.isPaired()) {
            await this.client.restorePairing();
        }
        if (!this.client.isPaired()) {
            this.logger.warn('discovery.skipped', { reason: 'agent_unpaired' });
            return false;
        }
        this.running = true;
        const started = Date.now();
        this.logger.info('discovery.started', { instanceId: this.client.currentIntegration()?.integration_uuid });
        try {
            const capabilities = this.caps.capabilities(this.config.publicUrl);
            const services = this.services.services();
            const plugins = this.plugins.plugins();
            const checksum = this.caps.checksum(this.config.publicUrl);
            if (!force && checksum === this.lastChecksum) {
                this.logger.info('discovery.skipped', { reason: 'checksum_unchanged', capabilities: this.capabilityCount });
                return false;
            }
            const integration = this.client.currentIntegration();
            await this.client.signed('POST', integration?.discovery_url || '/api/v1/integrations/n8n/discovery', {
                protocol_version: '1.0',
                hostname: process.env.HOSTNAME || null,
                instance_url: this.config.publicUrl,
                environment: this.config.environment,
                version: process.env.N8N_VERSION || 'codered-agent/1.0.0',
                agent_version: '1.0.0',
                connector_version: 'codered-agent/1.0.0',
                n8n_version: process.env.N8N_VERSION || null,
                capabilities,
                services,
                plugins,
                credentials: [],
                workflows: [],
                workflows_count: this.workflowCount,
                workflow_count: this.workflowCount,
            });
            this.lastChecksum = checksum;
            this.lastDiscoveryAt = new Date().toISOString();
            this.lastError = null;
            this.capabilityCount = capabilities.length;
            this.logger.info('capabilities.published', { count: capabilities.length });
            this.logger.info('workflows.published', { count: this.workflowCount });
            this.logger.info('discovery.completed', { durationMs: Date.now() - started, capabilities: capabilities.length });
            return true;
        }
        catch (error) {
            this.lastError = error instanceof Error ? error.message : 'Unknown discovery error';
            if (error instanceof AgentUnpairedError) {
                this.logger.warn('discovery.skipped', { reason: 'agent_unpaired' });
                return false;
            }
            this.logger.error('discovery.failed', { durationMs: Date.now() - started, error: this.lastError });
            return false;
        }
        finally {
            this.running = false;
        }
    }
}
