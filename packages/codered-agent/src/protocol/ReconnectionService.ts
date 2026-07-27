import type { AgentStorage } from '../storage/AgentStorage.js';
import { Logger } from '../logging/Logger.js';
import { CodeREDClient } from './CodeREDClient.js';
import { DiscoveryService } from './DiscoveryService.js';
import { HeartbeatService } from './HeartbeatService.js';

export class ReconnectionService {
  public constructor(
    private storage: AgentStorage,
    private client: CodeREDClient,
    private discovery: DiscoveryService,
    private heartbeat: HeartbeatService,
    private logger = new Logger(),
  ) {}

  public async start(): Promise<string> {
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
