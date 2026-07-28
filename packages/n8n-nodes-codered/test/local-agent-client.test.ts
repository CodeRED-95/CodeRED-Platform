import test from 'node:test';
import assert from 'node:assert/strict';
import { buildNodeError } from '../nodes/CodeRED/CodeRED.node';
import { ConnectionManager } from '../nodes/CodeRED/ConnectionManager';
import { callLocalAgent, LocalAgentHttpError, sanitizeOutput } from '../nodes/CodeRED/LocalAgentClient';
import type { CodeREDCredentials } from '../nodes/CodeRED/GenericFunctions';

const originalFetch = globalThis.fetch;
const originalUrl = process.env.CODERED_AGENT_LOCAL_URL;
const originalToken = process.env.CODERED_AGENT_LOCAL_API_TOKEN;
const originalVersion = process.env.N8N_VERSION;

function restoreEnv(): void {
  setOptionalEnv('CODERED_AGENT_LOCAL_URL', originalUrl);
  setOptionalEnv('CODERED_AGENT_LOCAL_API_TOKEN', originalToken);
  setOptionalEnv('N8N_VERSION', originalVersion);
  globalThis.fetch = originalFetch;
}

function setOptionalEnv(key: string, value: string | undefined): void {
  if (value === undefined) {
    delete process.env[key];
  } else {
    process.env[key] = value;
  }
}

function credentials(): CodeREDCredentials {
  return {
    baseUrl: 'https://platform.test/',
    instanceName: 'n8n Test',
    instanceUrl: 'https://n8n.test/',
    environment: 'production',
  };
}

test.afterEach(() => restoreEnv());

test('Pair Instance calls codered-agent and never sends identifiers or secrets from n8n', async () => {
  process.env.CODERED_AGENT_LOCAL_URL = 'http://codered-agent:5680';
  process.env.CODERED_AGENT_LOCAL_API_TOKEN = 'local-token';
  process.env.N8N_VERSION = '2.31.4';
  const calls: Array<{ url: string; headers: Headers; body: Record<string, unknown> }> = [];

  globalThis.fetch = (async (url: string | URL | Request, init?: RequestInit) => {
    calls.push({
      url: String(url),
      headers: new Headers(init?.headers),
      body: JSON.parse(String(init?.body)) as Record<string, unknown>,
    });

    return new Response(JSON.stringify({
      success: true,
      state: 'connected',
      integration_uuid: '00000000-0000-4000-8000-000000000001',
      instance_uuid: '00000000-0000-4000-8000-000000000002',
      shared_secret: 'must-not-leak',
    }), { status: 200 });
  }) as typeof fetch;

  const result = await new ConnectionManager(credentials()).connect({ pairCode: 'CRD-TEST01' });

  assert.equal(calls[0]?.url, 'http://codered-agent:5680/api/v1/pair');
  assert.equal(calls[0]?.headers.get('authorization'), 'Bearer local-token');
  assert.deepEqual(calls[0]?.body, {
    pair_code: 'CRD-TEST01',
    instance_name: 'n8n Test',
    instance_url: 'https://n8n.test/',
    environment: 'production',
    version: '2.31.4',
    platform_url: 'https://platform.test/',
  });
  assert.equal(Object.hasOwn(calls[0]?.body || {}, 'instance_uuid'), false);
  assert.equal(Object.hasOwn(calls[0]?.body || {}, 'integration_uuid'), false);
  assert.equal(Object.hasOwn(calls[0]?.body || {}, 'shared_secret'), false);
  assert.equal((result as Record<string, unknown>).shared_secret, '[redacted]');
});

test('local agent client rejects missing bearer token before calling fetch', async () => {
  process.env.CODERED_AGENT_LOCAL_URL = 'http://codered-agent:5680';
  delete process.env.CODERED_AGENT_LOCAL_API_TOKEN;
  let called = false;
  globalThis.fetch = (async () => {
    called = true;
    return new Response('{}', { status: 200 });
  }) as typeof fetch;

  await assert.rejects(callLocalAgent('/api/v1/status'), /CODERED_AGENT_LOCAL_API_TOKEN no está configurado/);
  assert.equal(called, false);
});

test('buildNodeError maps local agent HTTP failures without leaking secrets', () => {
  assert.equal(buildNodeError(new LocalAgentHttpError(401, { message: 'bad token' }), 'pair'), 'CodeRED Agent rechazó el token local. Verifica CODERED_AGENT_LOCAL_API_TOKEN.');
  assert.equal(buildNodeError(new LocalAgentHttpError(410, { pair_code: 'CRD-SECRET' }), 'pair'), 'El código de pairing expiró o ya fue utilizado.');
  assert.match(buildNodeError(new LocalAgentHttpError(422, { errors: { shared_secret: ['x'], instance_uuid: ['required'] } }), 'pair'), /[redacted]/);
});

test('buildNodeError maps timeout and connection failures', () => {
  const timeout = new Error('La solicitud a CodeRED Agent superó el tiempo límite de 15 segundos.');
  timeout.name = 'LocalAgentTimeoutError';
  assert.equal(buildNodeError(timeout, 'pair'), 'La solicitud a CodeRED Agent superó el tiempo límite de 15 segundos.');
  assert.equal(buildNodeError(new Error('fetch failed'), 'pair'), 'CodeRED Agent no está disponible en http://codered-agent:5680.');
});

test('sanitizeOutput removes secrets recursively', () => {
  assert.deepEqual(sanitizeOutput({ ok: true, shared_secret: 'x', nested: { token: 'y', pairCode: 'z', value: 1 } }), {
    ok: true,
    shared_secret: '[redacted]',
    nested: { token: '[redacted]', pairCode: '[redacted]', value: 1 },
  });
});

test('Test Connection checks healthz and authenticated status on the agent', async () => {
  process.env.CODERED_AGENT_LOCAL_URL = 'http://codered-agent:5680';
  process.env.CODERED_AGENT_LOCAL_API_TOKEN = 'local-token';
  const urls: string[] = [];

  globalThis.fetch = (async (url: string | URL | Request) => {
    urls.push(String(url));
    return new Response(JSON.stringify({ version: '1.0.0', protocol_version: '1.0', paired: false, state: 'unpaired' }), { status: 200 });
  }) as typeof fetch;

  const result = await new ConnectionManager(credentials()).testConnection();

  assert.equal((result.status as Record<string, unknown>).state, 'unpaired');
  assert.deepEqual(urls, ['http://codered-agent:5680/healthz', 'http://codered-agent:5680/api/v1/status']);
});
