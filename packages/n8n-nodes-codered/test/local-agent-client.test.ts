import test from 'node:test';
import assert from 'node:assert/strict';
import { buildNodeError } from '../nodes/CodeRED/CodeRED.node';
import { ConnectionManager } from '../nodes/CodeRED/ConnectionManager';
import { callLocalAgent, LocalAgentHttpError, sanitizeOutput, toN8nValue } from '../nodes/CodeRED/LocalAgentClient';
import type { INodeExecutionData } from 'n8n-workflow';
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
  assert.equal((result as Record<string, unknown>).shared_secret, 'must-not-leak');
  const nodeOutput: INodeExecutionData = { json: sanitizeOutput(result) };
  assert.equal(nodeOutput.json.shared_secret, '[redacted]');
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


test('local agent client normalizes base URL and parses text responses', async () => {
  process.env.CODERED_AGENT_LOCAL_URL = 'http://codered-agent:5680/';
  process.env.CODERED_AGENT_LOCAL_API_TOKEN = 'local-token';
  const urls: string[] = [];

  globalThis.fetch = (async (url: string | URL | Request) => {
    urls.push(String(url));
    return new Response('plain text response', { status: 200 });
  }) as typeof fetch;

  const result = await callLocalAgent<string>('api/v1/status');

  assert.equal(urls[0], 'http://codered-agent:5680/api/v1/status');
  assert.equal(result, 'plain text response');
});

test('local agent client redacts HTTP error bodies', async () => {
  process.env.CODERED_AGENT_LOCAL_URL = 'http://codered-agent:5680';
  process.env.CODERED_AGENT_LOCAL_API_TOKEN = 'local-token';

  globalThis.fetch = (async () => new Response(JSON.stringify({
    message: 'invalid',
    shared_secret: 'secret',
    pair_code: 'CRD-TEST',
  }), { status: 422 })) as typeof fetch;

  await assert.rejects(
    callLocalAgent('/api/v1/pair', { method: 'POST', body: { pair_code: 'CRD-TEST' } }),
    (error: unknown) => {
      assert.ok(error instanceof LocalAgentHttpError);
      assert.equal(error.statusCode, 422);
      assert.deepEqual(error.responseBody, {
        message: 'invalid',
        shared_secret: '[redacted]',
        pair_code: '[redacted]',
      });
      return true;
    },
  );
});


test('sanitizeOutput returns IDataObject-compatible serializable values', () => {
  const date = new Date('2026-07-28T00:00:00.000Z');
  const error = new Error('boom') as Error & { statusCode?: number };
  error.statusCode = 422;

  const output: INodeExecutionData = {
    json: sanitizeOutput({
      ok: true,
      shared_secret: 'secret',
      pair_code: 'CRD-SECRET',
      removed: undefined,
      date,
      count: BigInt(7),
      error,
      values: [1, undefined, date, BigInt(3), { token: 'hidden' }],
    }),
  };

  assert.deepEqual(output.json, {
    ok: true,
    shared_secret: '[redacted]',
    pair_code: '[redacted]',
    date: '2026-07-28T00:00:00.000Z',
    count: '7',
    error: { name: 'Error', message: 'boom', statusCode: 422 },
    values: [1, null, '2026-07-28T00:00:00.000Z', '3', { token: '[redacted]' }],
  });
  assert.equal(Object.hasOwn(output.json, 'removed'), false);
});

test('sanitizeOutput wraps root scalar and root array for n8n json output', () => {
  const scalar: INodeExecutionData = { json: sanitizeOutput('ready') };
  const array: INodeExecutionData = { json: sanitizeOutput(['a', undefined, { apiKey: 'secret' }]) };

  assert.deepEqual(scalar.json, { result: 'ready' });
  assert.deepEqual(array.json, { result: ['a', null, { apiKey: '[redacted]' }] });
});

test('toN8nValue converts unsupported root values safely', () => {
  assert.equal(toN8nValue(BigInt(9)), '9');
  assert.equal(toN8nValue(undefined), null);
  assert.match(String(toN8nValue(Symbol('x'))), /Symbol\(x\)/);
});

test('Token request operations call codered-agent with safe payloads', async () => {
  process.env.CODERED_AGENT_LOCAL_URL = 'http://codered-agent:5680';
  process.env.CODERED_AGENT_LOCAL_API_TOKEN = 'local-token';
  const calls: Array<{ url: string; method: string; body: Record<string, unknown> | null }> = [];

  globalThis.fetch = (async (url: string | URL | Request, init?: RequestInit) => {
    calls.push({
      url: String(url),
      method: String(init?.method || 'GET'),
      body: init?.body ? JSON.parse(String(init.body)) as Record<string, unknown> : null,
    });

    return new Response(JSON.stringify({ success: true, data: { request_id: 'req-1', status: 'pending' } }), { status: 200 });
  }) as typeof fetch;

  const manager = new ConnectionManager(credentials());
  await manager.getPersonalCode({ telegram_user_id: '123456789', telegram_chat_id: '123456789' });
  await manager.createTokenRequest({ requester_name: 'Ada', application_name: 'Bot', purpose: 'Read agencies', requested_token_type: 'agencies', requested_token_expires_in_days: 30, requested_scopes: ['agencies:read'], source: 'n8n' });
  await manager.requestTokenRotation({ person_code: 'a6759c4f-f6cc-4a1a-b639-3869f6894ada', reason: 'Preventive rotation', telegram_user_id: '123456789', telegram_chat_id: '123456789', idempotency_key: 'rotation-1', source: 'telegram' });
  await manager.getTokenRequestStatus('00000000-0000-4000-8000-000000000111');
  await manager.retrieveApprovedToken('00000000-0000-4000-8000-000000000111');
  await manager.confirmTokenDelivery('00000000-0000-4000-8000-000000000111', { delivery_channel: 'manual' });
  await manager.cancelTokenRequest('00000000-0000-4000-8000-000000000111', { cancellation_reason: 'No longer needed' });

  assert.equal(calls[0]?.url, 'http://codered-agent:5680/api/v1/personal-code');
  assert.equal(calls[0]?.method, 'POST');
  assert.equal(calls[0]?.body?.telegram_user_id, '123456789');
  assert.equal(calls[1]?.url, 'http://codered-agent:5680/api/v1/token-requests');
  assert.equal(calls[1]?.method, 'POST');
  assert.deepEqual(calls[1]?.body?.requested_scopes, ['agencies:read']);
  assert.equal(calls[1]?.body?.requested_token_type, 'agencies');
  assert.equal(calls[1]?.body?.requested_token_expires_in_days, 30);
  assert.equal(Object.hasOwn(calls[1]?.body || {}, 'shared_secret'), false);
  assert.equal(Object.hasOwn(calls[1]?.body || {}, 'token_type'), false);
  assert.equal(calls[2]?.url, 'http://codered-agent:5680/api/v1/token-requests/rotation-by-code');
  assert.equal(calls[2]?.method, 'POST');
  assert.equal(calls[2]?.body?.person_code, 'a6759c4f-f6cc-4a1a-b639-3869f6894ada');
  assert.equal(calls[2]?.body?.telegram_user_id, '123456789');
  assert.equal(calls[2]?.body?.current_api_token, undefined);
  assert.equal(calls[2]?.body?.idempotency_key, 'rotation-1');
  assert.equal(calls[3]?.url, 'http://codered-agent:5680/api/v1/token-requests/00000000-0000-4000-8000-000000000111');
  assert.equal(calls[3]?.method, 'GET');
  assert.equal(calls[4]?.url, 'http://codered-agent:5680/api/v1/token-requests/00000000-0000-4000-8000-000000000111/retrieve');
  assert.equal(calls[5]?.url, 'http://codered-agent:5680/api/v1/token-requests/00000000-0000-4000-8000-000000000111/delivery');
  assert.equal(calls[6]?.url, 'http://codered-agent:5680/api/v1/token-requests/00000000-0000-4000-8000-000000000111/cancel');
});

test('sanitizeOutput allows approved token only when explicitly requested', () => {
  assert.equal(sanitizeOutput({ token: 'plain-token' }).token, '[redacted]');
  assert.equal(sanitizeOutput({ token: 'plain-token' }, { allowToken: true }).token, 'plain-token');
  assert.equal(sanitizeOutput({ shared_secret: 'secret' }, { allowToken: true }).shared_secret, '[redacted]');
});
