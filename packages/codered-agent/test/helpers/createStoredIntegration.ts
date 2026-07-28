import type { StoredIntegration } from '../../src/storage/types.js';

export function createStoredIntegration(overrides: Partial<StoredIntegration> = {}): StoredIntegration {
  return {
    instance_uuid: '00000000-0000-4000-8000-000000000010',
    integration_uuid: '00000000-0000-4000-8000-000000000001',
    shared_secret: 'test-shared-secret',
    protocol_version: '1.0',
    paired_at: '2026-07-28T00:00:00.000Z',
    platform_url: 'https://platform.example.test',
    agent_name: 'CodeRED n8n Agent',
    instance_name: 'n8n Production',
    instance_url: 'https://n8n.example.test',
    environment: 'test',
    secret_version: 1,
    discovery_url: '/api/v1/integrations/n8n/discovery',
    heartbeat_url: '/api/v1/integrations/n8n/heartbeat',
    challenge_url: '/api/v1/integrations/n8n/challenge',
    ...overrides,
  };
}
