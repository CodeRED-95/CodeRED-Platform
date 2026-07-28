import crypto from 'node:crypto';
import type { Config } from '../config/Config.js';
import { Logger } from '../logging/Logger.js';
import type { AgentStorage } from '../storage/AgentStorage.js';
import type { StoredIntegration } from '../storage/types.js';
import { CodeREDClient, type HttpError } from './CodeREDClient.js';
import { DiscoveryService } from './DiscoveryService.js';
import { HeartbeatService } from './HeartbeatService.js';
import { PairingService, type PairingInput } from './PairingService.js';
import { ReconnectionService } from './ReconnectionService.js';
import type { ConnectionState } from './ConnectionState.js';

export interface ConnectionSnapshot {
  state: ConnectionState;
  paired: boolean;
  platformConnected: boolean;
  instanceId: string | null;
  protocolVersion: string;
  lastHeartbeatAt: string | null;
  lastDiscoveryAt: string | null;
  heartbeatAgeSeconds: number | null;
  latencyMs: number | null;
  capabilities: number;
  workflows: number;
  lastError: string | null;
}

export class ConnectionManager {
  private state: ConnectionState = 'UNPAIRED';
  private heartbeatTimer: NodeJS.Timeout | null = null;
  private lastError: string | null = null;

  public constructor(
    private config: Config,
    private storage: AgentStorage,
    private client: CodeREDClient,
    private pairing: PairingService,
    private discovery: DiscoveryService,
    private heartbeat: HeartbeatService,
    private reconnect: ReconnectionService,
    private logger = new Logger(config.logLevel),
  ) {}

  public async start(): Promise<void> {
    const restored = await this.client.restorePairing();
    this.transition(restored ? 'DEGRADED' : 'UNPAIRED');

    if (restored) {
      this.logger.info('connection.restored', { instanceId: this.client.currentIntegration()?.integration_uuid });
      await this.reconnect.start();
      await this.discovery.sync(true);
      await this.sendHeartbeatCycle();
      this.startHeartbeatLoop();
    }
  }

  public stop(): void {
    if (this.heartbeatTimer) {
      clearInterval(this.heartbeatTimer);
      this.heartbeatTimer = null;
    }
  }

  public async connect(input: PairingInput): Promise<Record<string, unknown>> {
    this.transition('PAIRING');
    this.logger.info('pair.started');
    const result = await this.pairing.pair(input);
    this.logger.info('pair.credentials_saved', { instanceId: result.instanceId });

    try {
      this.transition('CHALLENGING');
      const challengeCompleted = await this.challenge();

      if (!challengeCompleted) {
        await this.storage.clearIntegration();
        this.client.clearPairing();
        this.transition('UNPAIRED');
        throw new Error('Challenge failed after pairing. Pairing was cancelled.');
      }

      this.transition('DISCOVERING');
      const discoveryCompleted = await this.discovery.sync(true);

      if (!discoveryCompleted) {
        this.transition('DEGRADED');
        this.logger.warn('discovery.incomplete', { reason: this.discovery.lastError || 'not_completed' });
      }

      this.transition('CONNECTING');
      const heartbeatCompleted = await this.sendHeartbeatCycle();
      this.startHeartbeatLoop();

      if (heartbeatCompleted && discoveryCompleted) {
        this.transition('CONNECTED');
        this.logger.info('connected.confirmed', { instanceId: this.client.currentIntegration()?.integration_uuid });
      } else {
        this.transition('DEGRADED');
      }

      const snapshot = this.status();

      return {
        success: snapshot.state === 'CONNECTED' || snapshot.state === 'DEGRADED',
        ready: snapshot.state === 'CONNECTED',
        state: snapshot.state,
        paired: snapshot.paired,
        instanceId: snapshot.instanceId,
        protocolVersion: snapshot.protocolVersion,
        pairedAt: result.pairedAt,
        challengeCompleted,
        discoveryCompleted,
        heartbeatCompleted,
        platformConnected: snapshot.platformConnected,
        capabilities: snapshot.capabilities,
        workflows: snapshot.workflows,
        lastHeartbeatAt: snapshot.lastHeartbeatAt,
        lastDiscoveryAt: snapshot.lastDiscoveryAt,
        latencyMs: snapshot.latencyMs,
      };
    } catch (error) {
      this.lastError = error instanceof Error ? error.message : 'Unknown connection error';
      this.logger.error('connection.failed', { stage: this.state, error: this.lastError });
      throw error;
    }
  }

  public async disconnect(): Promise<void> {
    this.stop();
    await this.storage.clearIntegration();
    this.client.clearPairing();
    this.heartbeat.status = 'unpaired';
    this.transition('UNPAIRED');
  }

  public async reconnectWithPairCode(input: PairingInput): Promise<Record<string, unknown>> {
    return this.connect(input);
  }

  public async rotateSecret(): Promise<Record<string, unknown>> {
    this.transition('SECRET_ROTATION_PENDING');
    this.logger.info('secret_rotation.started');
    const integration = this.client.currentIntegration() ?? await this.storage.readIntegration();

    if (!integration) {
      this.transition('UNPAIRED');
      throw new Error('Agent is unpaired. Cannot rotate secret.');
    }

    const response = await this.client.signed('POST', '/api/v1/integrations/n8n/secret/rotate');
    const data = response.data as { shared_secret?: string } | undefined;

    if (!data?.shared_secret) {
      throw new Error('Platform did not return a pending secret.');
    }

    integration.shared_secret = data.shared_secret;
    integration.secret_version += 1;
    await this.storage.saveIntegration(integration);
    this.client.setPairing(integration);
    await this.client.signed('POST', '/api/v1/integrations/n8n/secret/confirm');
    const heartbeatCompleted = await this.sendHeartbeatCycle();
    this.transition(heartbeatCompleted ? 'CONNECTED' : 'DEGRADED');
    this.startHeartbeatLoop();
    this.logger.info('secret_rotation.completed', { instanceId: integration.integration_uuid });

    return { success: true, state: this.state, heartbeatCompleted };
  }

  public async testConnection(): Promise<Record<string, unknown>> {
    const challengeCompleted = await this.challenge();
    const heartbeatCompleted = await this.sendHeartbeatCycle();
    const snapshot = this.status();

    return {
      success: challengeCompleted && heartbeatCompleted && snapshot.capabilities > 0,
      state: snapshot.state,
      paired: snapshot.paired,
      platformConnected: snapshot.platformConnected,
      challengeCompleted,
      heartbeatCompleted,
      capabilities: snapshot.capabilities,
      workflows: snapshot.workflows,
      latencyMs: snapshot.latencyMs,
      lastError: snapshot.lastError,
    };
  }

  public status(): ConnectionSnapshot {
    const integration = this.client.currentIntegration();
    const heartbeatAgeSeconds = this.heartbeat.lastHeartbeatAt
      ? Math.max(0, Math.round((Date.now() - Date.parse(this.heartbeat.lastHeartbeatAt)) / 1000))
      : null;

    return {
      state: this.stateForCurrentHealth(),
      paired: this.client.isPaired(),
      platformConnected: this.heartbeat.status === 'connected',
      instanceId: integration?.integration_uuid || null,
      protocolVersion: integration?.protocol_version || '1.0',
      lastHeartbeatAt: this.heartbeat.lastHeartbeatAt,
      lastDiscoveryAt: this.discovery.lastDiscoveryAt,
      heartbeatAgeSeconds,
      latencyMs: this.heartbeat.latencyMs,
      capabilities: this.discovery.capabilityCount,
      workflows: this.discovery.workflowCount,
      lastError: this.lastError || this.heartbeat.lastError || this.discovery.lastError,
    };
  }

  private async challenge(): Promise<boolean> {
    const integration = this.client.currentIntegration() ?? await this.storage.readIntegration();

    if (!integration) {
      this.lastError = 'Agent is unpaired.';
      return false;
    }

    this.client.setPairing(integration);
    const challenge = crypto.randomUUID();

    try {
      await this.client.signed('POST', integration.challenge_url || '/api/v1/integrations/n8n/challenge', {
        challenge,
        sent_at: new Date().toISOString(),
      });
      this.logger.info('challenge.validated', { instanceId: integration.integration_uuid });
      this.lastError = null;

      return true;
    } catch (error) {
      this.lastError = error instanceof Error ? error.message : 'Unknown challenge error';
      this.logger.error('challenge.failed', { instanceId: integration.integration_uuid, error: this.lastError });

      return false;
    }
  }

  private async sendHeartbeatCycle(): Promise<boolean> {
    const ok = await this.heartbeat.send();

    if (ok) {
      this.transition('CONNECTED');
      this.logger.info('heartbeat.ok', { latencyMs: this.heartbeat.latencyMs });
      return true;
    }

    if (this.heartbeat.status === 'requires_repairing') {
      this.transition('UNAUTHORIZED');
      this.stop();
    } else if (this.heartbeat.status === 'disconnected') {
      this.transition('DISCONNECTED');
    } else if (this.client.isPaired()) {
      this.transition('DEGRADED');
    }

    return false;
  }

  private startHeartbeatLoop(): void {
    if (this.heartbeatTimer) {
      return;
    }

    this.heartbeatTimer = setInterval(() => {
      void this.sendHeartbeatCycle();
    }, this.config.heartbeatSeconds * 1000);
  }

  private stateForCurrentHealth(): ConnectionState {
    if (!this.client.isPaired()) {
      return 'UNPAIRED';
    }

    if (this.heartbeat.status === 'requires_repairing') {
      return 'UNAUTHORIZED';
    }

    if (this.heartbeat.status === 'connected') {
      return 'CONNECTED';
    }

    if (this.heartbeat.status === 'disconnected') {
      return 'DISCONNECTED';
    }

    return this.state;
  }

  private transition(next: ConnectionState): void {
    if (this.state === next) {
      return;
    }

    const previous = this.state;
    this.state = next;
    this.logger.info('connection.state_changed', { previous, next });
  }
}
