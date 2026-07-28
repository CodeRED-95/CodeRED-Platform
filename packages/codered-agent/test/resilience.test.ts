import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { loadConfig } from '../src/config/Config.js';
import { CodeREDClient } from '../src/protocol/CodeREDClient.js';
import { DiscoveryService } from '../src/protocol/DiscoveryService.js';
import { HeartbeatService } from '../src/protocol/HeartbeatService.js';
import { EncryptedFileStorage } from '../src/storage/EncryptedFileStorage.js';
import { createStoredIntegration } from './helpers/createStoredIntegration.js';

function config() {
  return loadConfig({
    CODERED_PLATFORM_URL: 'https://platform.example.test',
    CODERED_AGENT_PUBLIC_URL: 'https://agent.example.test',
    CODERED_AGENT_ENCRYPTION_KEY: 'a'.repeat(64),
    CODERED_AGENT_LOCAL_API_TOKEN: 'b'.repeat(64),
    CODERED_AGENT_PORT: '5680',
  });
}

async function tempStorage() {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'codered-agent-resilience-'));

  return { dir, storage: new EncryptedFileStorage(dir, 'a'.repeat(64)) };
}

test('heartbeat and discovery skip unpaired state without throwing', async () => {
  const cfg = config();
  const { storage } = await tempStorage();
  const client = new CodeREDClient(cfg, storage);
  const heartbeat = new HeartbeatService(cfg, client);
  const discovery = new DiscoveryService(cfg, client);

  assert.equal(await heartbeat.send(), false);
  assert.equal(heartbeat.status, 'unpaired');
  assert.equal(await discovery.sync(true), false);
});

test('persisted pairing is restored after a new client is created', async () => {
  const cfg = config();
  const { storage } = await tempStorage();
  await storage.saveIntegration(createStoredIntegration({
    integration_uuid: '00000000-0000-4000-8000-000000000001',
    shared_secret: 'secret',
    paired_at: new Date().toISOString(),
    platform_url: cfg.platformUrl,
    agent_name: cfg.name,
    environment: cfg.environment,
  }));
  const client = new CodeREDClient(cfg, storage);

  assert.equal(await client.restorePairing(), true);
  assert.equal(client.isPaired(), true);
  assert.equal(client.currentIntegration()?.shared_secret, 'secret');
});

test('corrupt integration file is controlled and does not throw', async () => {
  const { dir, storage } = await tempStorage();
  await fs.mkdir(dir, { recursive: true });
  await fs.writeFile(path.join(dir, 'integration.enc'), 'not-json', { mode: 0o600 });

  assert.equal(await storage.readIntegration(), null);
});
