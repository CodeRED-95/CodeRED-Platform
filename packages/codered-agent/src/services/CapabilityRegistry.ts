import crypto from 'node:crypto';
import { stableJson } from '../protocol/RequestSigner.js';

export interface Capability {
  service: string;
  method: string;
  url: string;
  version: string;
}

export class CapabilityRegistry {
  public capabilities(publicUrl: string): Capability[] {
    return [
      { service: 'agent.health', method: 'GET', url: `${publicUrl}/healthz`, version: '1.0' },
      { service: 'integration.status', method: 'GET', url: `${publicUrl}/api/v1/status`, version: '1.0' },
      { service: 'integration.challenge', method: 'POST', url: `${publicUrl}/v1/challenge`, version: '1.0' },
      { service: 'integration.discovery', method: 'POST', url: `${publicUrl}/api/v1/discovery/sync`, version: '1.0' },
      { service: 'integration.heartbeat', method: 'POST', url: `${publicUrl}/api/v1/heartbeat/send`, version: '1.0' },
    ];
  }

  public checksum(publicUrl: string): string {
    return crypto.createHash('sha256').update(stableJson(this.capabilities(publicUrl))).digest('hex');
  }
}
