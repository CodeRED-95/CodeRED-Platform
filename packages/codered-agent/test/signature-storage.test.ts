import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { sanitize } from '../src/logging/Logger.js';
import { canonicalPayload, sign, stableJson } from '../src/protocol/RequestSigner.js';
import { CodeREDClient } from '../src/protocol/CodeREDClient.js';
import { DiscoveryService } from '../src/protocol/DiscoveryService.js';
import { HeartbeatService } from '../src/protocol/HeartbeatService.js';
import { PairingService } from '../src/protocol/PairingService.js';
import type { PlatformPairRequest } from '../src/protocol/PairRequests.js';
import { EncryptedFileStorage } from '../src/storage/EncryptedFileStorage.js';
import { createStoredIntegration } from './helpers/createStoredIntegration.js';

const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

function config() {
  return {
    name: 'CodeRED n8n Agent',
    platformUrl: 'https://platform.example.test',
    publicUrl: 'https://agent.example.test',
    environment: 'test',
    port: 5680,
    dataPath: '/data',
    encryptionKey: 'x'.repeat(32),
    localApiToken: 'b'.repeat(64),
    heartbeatSeconds: 30,
    discoverySeconds: 300,
    requestTimeoutMs: 15000,
    logLevel: 'silent',
  };
}

test('shared signature vector matches connector', () => {
  const body = stableJson({ b: 2, a: 1 });
  const canonical = canonicalPayload('post', '/api/v1/x', '100', 'nonce', body);

  assert.equal(sign('secret', canonical), 'c8db8fe60cd0321457a422a3428f52e83ca0ade911ddf8ecac632d2ef7966ac1');
});

test('agent identity is generated once and survives pairing cleanup', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'codered-agent-'));
  const st = new EncryptedFileStorage(dir, 'x'.repeat(32));

  const first = await st.ensureIdentity('CodeRED n8n Agent');
  const second = await st.ensureIdentity('CodeRED n8n Agent');

  assert.match(first.instance_uuid, uuidPattern);
  assert.equal(second.instance_uuid, first.instance_uuid);

  await st.saveIntegration(createStoredIntegration({ instance_uuid: first.instance_uuid }));
  await st.clearIntegration();

  const afterClear = await st.ensureIdentity('CodeRED n8n Agent');
  assert.equal(afterClear.instance_uuid, first.instance_uuid);
});

test('encrypted storage roundtrip preserves identity and file mode', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'codered-agent-'));
  const st = new EncryptedFileStorage(dir, 'x'.repeat(32));
  const integration = createStoredIntegration({
    instance_uuid: '00000000-0000-4000-8000-000000000020',
    integration_uuid: '00000000-0000-4000-8000-000000000021',
    shared_secret: 's',
    protocol_version: '1.0',
    paired_at: '2026-07-28T00:00:00.000Z',
    platform_url: 'https://platform.example.test',
    agent_name: 'a',
    instance_name: 'n8n Test',
    instance_url: 'https://n8n.test',
    environment: 'test',
    secret_version: 1,
  });

  await st.saveIntegration(integration);
  const restored = await st.readIntegration();
  const identity = await st.readIdentity();

  assert.equal(restored?.shared_secret, 's');
  assert.equal(restored?.instance_uuid, integration.instance_uuid);
  assert.equal(restored?.instance_name, integration.instance_name);
  assert.equal(restored?.instance_url, integration.instance_url);
  assert.equal(identity?.instance_uuid, integration.instance_uuid);

  const stat = await fs.stat(path.join(dir, 'integration.json'));
  assert.equal(stat.mode & 0o777, 0o600);
});

test('agent pairing adds persisted instance_uuid before calling Platform', async () => {
  const cfg = config();
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'codered-agent-'));
  const st = new EncryptedFileStorage(dir, cfg.encryptionKey);
  const identity = await st.ensureIdentity('CodeRED n8n Agent');

  class CapturingClient extends CodeREDClient {
    public payloads: PlatformPairRequest[] = [];

    public override async pair(payload: PlatformPairRequest): Promise<Record<string, unknown>> {
      this.payloads.push(payload);

      return {
        success: true,
        data: {
          integration_uuid: '00000000-0000-4000-8000-000000000031',
          shared_secret: 'secret-from-platform',
          protocol_version: '1.0',
          paired_at: '2026-07-28T00:00:00.000Z',
        },
      };
    }
  }

  const client = new CapturingClient(cfg, st);
  const discovery = new DiscoveryService(cfg, client);
  const heartbeat = new HeartbeatService(cfg, client);
  const pairing = new PairingService(cfg, st, client, discovery, heartbeat);

  await pairing.pair({
    pair_code: 'CRD-TEST',
    instance_name: 'n8n Production',
    instance_url: 'https://n8n.codered.lat/',
    environment: 'production',
    version: '2.31.4',
  });

  assert.equal(client.payloads[0]?.instance_uuid, identity.instance_uuid);
  assert.equal(client.payloads[0]?.pair_code, 'CRD-TEST');
  assert.equal(client.payloads[0]?.instance_name, 'n8n Production');
  assert.equal(client.payloads[0]?.instance_url, 'https://n8n.codered.lat/');
  assert.equal(client.payloads[0]?.environment, 'production');
  assert.equal(client.payloads[0]?.agent_version, '1.0.0');
});

test('pair request sends persistent instance_uuid using snake_case', async () => {
  const cfg = config();
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'codered-agent-'));
  const st = new EncryptedFileStorage(dir, cfg.encryptionKey);
  const client = new CodeREDClient(cfg, st);
  const calls: Array<{ url: string; body: Record<string, unknown> }> = [];
  const originalFetch = globalThis.fetch;

  globalThis.fetch = (async (url: string | URL | Request, init?: RequestInit) => {
    calls.push({ url: String(url), body: JSON.parse(String(init?.body)) as Record<string, unknown> });

    return new Response(JSON.stringify({ success: true, data: {} }), { status: 200, headers: { 'content-type': 'application/json' } });
  }) as typeof fetch;

  try {
    await client.pair({
      pair_code: 'CRD-TEST',
      instance_uuid: '00000000-0000-4000-8000-000000000030',
      instance_name: 'n8n Production',
      instance_url: 'https://n8n.example.test',
      environment: 'production',
      version: '2.31.4',
      agent_version: '1.0.0',
    });
  } finally {
    globalThis.fetch = originalFetch;
  }

  assert.equal(calls[0]?.url, 'https://platform.example.test/api/v1/integrations/n8n/pair');
  assert.equal(calls[0]?.body.instance_uuid, '00000000-0000-4000-8000-000000000030');
  assert.equal(calls[0]?.body.instanceUuid, undefined);
  assert.equal(calls[0]?.body.instance_name, 'n8n Production');
  assert.equal(calls[0]?.body.instance_url, 'https://n8n.example.test');
  assert.equal(calls[0]?.body.agent_version, '1.0.0');
});

test('logger sanitizer redacts secrets', () => {
  assert.deepEqual(sanitize({ shared_secret: 'x', ok: 1 }), { shared_secret: '[redacted]', ok: 1 });
});

test('capability registry keeps core capabilities and adds token request operations', async () => {
  const { CapabilityRegistry } = await import('../src/services/CapabilityRegistry.js');
  const capabilities = new CapabilityRegistry().capabilities('https://agent.example.test');
  const services = capabilities.map((capability) => capability.service);

  assert.ok(services.includes('integration.challenge'));
  assert.ok(services.includes('integration.discovery'));
  assert.ok(services.includes('integration.heartbeat'));
  assert.ok(services.includes('integration.status'));
  assert.ok(services.includes('token_requests.create'));
  assert.ok(services.includes('token_requests.status'));
  assert.ok(services.includes('token_requests.retrieve'));
  assert.ok(services.includes('token_requests.delivery.confirm'));
  assert.ok(services.includes('token_requests.cancel'));
});

test('signed token request calls include integration headers and safe body', async () => {
  const cfg = config();
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'codered-agent-token-requests-'));
  const st = new EncryptedFileStorage(dir, cfg.encryptionKey);
  await st.saveIntegration(createStoredIntegration({
    integration_uuid: '00000000-0000-4000-8000-000000000041',
    instance_uuid: '00000000-0000-4000-8000-000000000042',
    shared_secret: 'signed-secret',
    platform_url: cfg.platformUrl,
  }));
  const client = new CodeREDClient(cfg, st);
  const calls: Array<{ url: string; headers: Headers; body: Record<string, unknown> }> = [];
  const originalFetch = globalThis.fetch;

  globalThis.fetch = (async (url: string | URL | Request, init?: RequestInit) => {
    calls.push({
      url: String(url),
      headers: new Headers(init?.headers),
      body: JSON.parse(String(init?.body)) as Record<string, unknown>,
    });

    return new Response(JSON.stringify({ success: true, data: { request_id: 'req-1' } }), { status: 200, headers: { 'content-type': 'application/json' } });
  }) as typeof fetch;

  try {
    await client.signed('POST', '/api/v1/integrations/n8n/token-requests', {
      requester_name: 'Ada',
      requested_scopes: ['agencies:read'],
    });
  } finally {
    globalThis.fetch = originalFetch;
  }

  assert.equal(calls[0]?.url, 'https://platform.example.test/api/v1/integrations/n8n/token-requests');
  assert.equal(calls[0]?.headers.get('x-codered-integration'), '00000000-0000-4000-8000-000000000041');
  assert.equal(calls[0]?.headers.get('x-codered-signature')?.length, 64);
  assert.equal(calls[0]?.body.requester_name, 'Ada');
  assert.equal(calls[0]?.body.shared_secret, undefined);
});
