import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { sanitize } from '../src/logging/Logger.js';
import { canonicalPayload, sign, stableJson } from '../src/protocol/RequestSigner.js';
import { CodeREDClient } from '../src/protocol/CodeREDClient.js';
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
      pairCode: 'CRD-TEST',
      instanceUuid: '00000000-0000-4000-8000-000000000030',
      instanceName: 'n8n Production',
      publicUrl: 'https://n8n.example.test',
      environment: 'production',
      version: '2.31.4',
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
