import type { Config } from '../config/Config.js';
import { AgentUnpairedError } from '../errors/AgentUnpairedError.js';
import { Logger } from '../logging/Logger.js';
import { CapabilityRegistry } from '../services/CapabilityRegistry.js';
import { PluginRegistry } from '../services/PluginRegistry.js';
import { ServiceRegistry } from '../services/ServiceRegistry.js';
import { CodeREDClient } from './CodeREDClient.js';

export class DiscoveryService {
  public lastDiscoveryAt: string | null = null;
  public lastError: string | null = null;
  public capabilityCount = 0;
  public workflowCount = 0;
  private lastChecksum = '';
  private running = false;

  public constructor(
    private config: Config,
    private client: CodeREDClient,
    private logger = new Logger(config.logLevel),
    private caps = new CapabilityRegistry(),
    private services = new ServiceRegistry(),
    private plugins = new PluginRegistry(),
  ) {}

  public async sync(force = false): Promise<boolean> {
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
        instance_uuid: integration?.instance_uuid || null,
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
    } catch (error) {
      this.lastError = error instanceof Error ? error.message : 'Unknown discovery error';

      if (error instanceof AgentUnpairedError) {
        this.logger.warn('discovery.skipped', { reason: 'agent_unpaired' });

        return false;
      }

      this.logger.error('discovery.failed', { durationMs: Date.now() - started, error: this.lastError });

      return false;
    } finally {
      this.running = false;
    }
  }
}
