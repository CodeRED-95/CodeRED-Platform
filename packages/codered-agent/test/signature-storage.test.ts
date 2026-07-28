import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { sanitize } from '../src/logging/Logger.js';
import { canonicalPayload, sign, stableJson } from '../src/protocol/RequestSigner.js';
import { EncryptedFileStorage } from '../src/storage/EncryptedFileStorage.js';
import { createStoredIntegration } from './helpers/createStoredIntegration.js';

test('shared signature vector matches connector', () => {
  const body = stableJson({ b: 2, a: 1 });
  const canonical = canonicalPayload('post', '/api/v1/x', '100', 'nonce', body);

  assert.equal(sign('secret', canonical), 'c8db8fe60cd0321457a422a3428f52e83ca0ade911ddf8ecac632d2ef7966ac1');
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

  assert.equal(restored?.shared_secret, 's');
  assert.equal(restored?.instance_uuid, integration.instance_uuid);
  assert.equal(restored?.instance_name, integration.instance_name);
  assert.equal(restored?.instance_url, integration.instance_url);

  const stat = await fs.stat(path.join(dir, 'integration.enc'));
  assert.equal(stat.mode & 0o777, 0o600);
});

test('logger sanitizer redacts secrets', () => {
  assert.deepEqual(sanitize({ shared_secret: 'x', ok: 1 }), { shared_secret: '[redacted]', ok: 1 });
});
