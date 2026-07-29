import crypto from 'node:crypto';
import type { IncomingMessage, ServerResponse } from 'node:http';
import type { Config } from '../config/Config.js';
import { Logger } from '../logging/Logger.js';
import { canonicalPayload, sign } from '../protocol/RequestSigner.js';
import type { CodeREDClient, HttpError } from '../protocol/CodeREDClient.js';
import type { DiscoveryService } from '../protocol/DiscoveryService.js';
import type { HeartbeatService } from '../protocol/HeartbeatService.js';
import type { ConnectionManager } from '../protocol/ConnectionManager.js';
import type { AgentStorage } from '../storage/AgentStorage.js';
import type { StoredIntegration } from '../storage/types.js';

const nonces = new Map<string, number>();
let pairHits: number[] = [];

async function readBody(req: IncomingMessage): Promise<{ raw: string; json: Record<string, unknown> }> {
  const chunks: Buffer[] = [];

  for await (const chunk of req) {
    chunks.push(Buffer.from(chunk));
  }

  const raw = chunks.length ? Buffer.concat(chunks).toString('utf8') : '{}';

  try {
    return { raw, json: JSON.parse(raw) as Record<string, unknown> };
  } catch {
    return { raw, json: {} };
  }
}

function json(res: ServerResponse, status: number, payload: unknown): void {
  res.writeHead(status, { 'content-type': 'application/json' });
  res.end(JSON.stringify(payload));
}

function consumeNonce(nonce: string): boolean {
  const now = Date.now();

  for (const [key, expiresAt] of nonces.entries()) {
    if (expiresAt <= now) {
      nonces.delete(key);
    }
  }

  if (nonces.has(nonce)) {
    return false;
  }

  nonces.set(nonce, now + 300_000);

  return true;
}

function header(req: IncomingMessage, name: string): string {
  const value = req.headers[name.toLowerCase()];

  return Array.isArray(value) ? (value[0] ?? '') : (value ?? '');
}

function validSignedRequest(req: IncomingMessage, integration: StoredIntegration, rawBody: string, path: string): boolean {
  const integrationUuid = header(req, 'X-CodeRED-Integration');
  const timestamp = header(req, 'X-CodeRED-Timestamp');
  const nonce = header(req, 'X-CodeRED-Nonce');
  const signature = header(req, 'X-CodeRED-Signature');
  const protocolVersion = header(req, 'X-CodeRED-Protocol-Version');

  if (!integrationUuid || !timestamp || !nonce || !signature || !protocolVersion) {
    return false;
  }

  if (integrationUuid !== integration.integration_uuid || protocolVersion !== integration.protocol_version) {
    return false;
  }

  const timestampMs = Number(timestamp) * 1000;

  if (!Number.isFinite(timestampMs) || Math.abs(Date.now() - timestampMs) > 300_000) {
    return false;
  }

  if (!consumeNonce(nonce)) {
    return false;
  }

  if (!/^[0-9a-f]{64}$/.test(signature)) {
    return false;
  }

  const expected = sign(
    integration.shared_secret,
    canonicalPayload(req.method || 'POST', path, timestamp, nonce, rawBody),
  );

  return crypto.timingSafeEqual(Buffer.from(expected, 'hex'), Buffer.from(signature, 'hex'));
}

export function createRouter(
  config: Config,
  storage: AgentStorage,
  connectionManager: ConnectionManager,
  discovery: DiscoveryService,
  heartbeat: HeartbeatService,
  client: CodeREDClient,
) {
  const logger = new Logger(config.logLevel);

  return async (req: IncomingMessage, res: ServerResponse) => {
    try {
      const url = req.url?.split('?')[0] || '/';

      if (req.method === 'GET' && (url === '/healthz' || url === '/v1/health')) {
        return json(res, 200, { status: 'ok', version: '1.0.0' });
      }

      if (req.method === 'GET' && url === '/readyz') {
        const integration = client.currentIntegration() ?? await storage.readIntegration();

        return json(res, 200, {
          status: 'ready',
          paired: !!integration,
          platformConnected: heartbeat.status === 'connected',
          degraded: heartbeat.status === 'degraded',
        });
      }

      if (req.method === 'GET' && (url === '/v1/status' || url === '/api/v1/status')) {
        if (!client.isPaired()) {
          await client.restorePairing();
        }

        const snapshot = connectionManager.status();
        const identity = await storage.ensureIdentity(config.name);

        return json(res, 200, {
          status: snapshot.state.toLowerCase(),
          state: snapshot.state.toLowerCase(),
          paired: snapshot.paired,
          platform_reachable: snapshot.platformConnected,
          platformReachable: snapshot.platformConnected,
          integration_uuid: snapshot.instanceId,
          instance_uuid: identity.instance_uuid,
          instanceId: snapshot.instanceId,
          last_heartbeat_at: snapshot.lastHeartbeatAt,
          last_discovery_at: snapshot.lastDiscoveryAt,
          heartbeat_failures: heartbeat.failures,
          heartbeatAgeSeconds: snapshot.heartbeatAgeSeconds,
          latencyMs: snapshot.latencyMs,
          capabilities: snapshot.capabilities,
          workflows: snapshot.workflows,
          lastError: snapshot.lastError,
        });
      }

      if (req.method === 'POST' && (url === '/v1/pair' || url === '/api/v1/pair')) {
        const now = Date.now();
        pairHits = pairHits.filter((hit) => now - hit < 60_000);

        if (pairHits.length >= 5) {
          return json(res, 429, { success: false, message: 'Rate limit exceeded' });
        }

        pairHits.push(now);
        const { json: payload } = await readBody(req);

        return json(res, 200, await connectionManager.connect({
          pair_code: String(payload.pairCode || payload.pair_code || ''),
          instance_name: typeof payload.instanceName === 'string' ? payload.instanceName : typeof payload.instance_name === 'string' ? payload.instance_name : config.name,
          instance_url: typeof payload.publicUrl === 'string' ? payload.publicUrl : typeof payload.instance_url === 'string' ? payload.instance_url : config.publicUrl,
          version: typeof payload.version === 'string' ? payload.version : undefined,
          environment: typeof payload.environment === 'string' ? payload.environment : config.environment,
        }));
      }

      if (req.method === 'POST' && (url === '/v1/discovery/sync' || url === '/api/v1/discovery/sync')) {
        const registered = await discovery.sync(true);

        return json(res, 200, { success: true, registered, capabilities: discovery.capabilityCount, workflows: discovery.workflowCount });
      }

      if (req.method === 'POST' && (url === '/v1/heartbeat/send' || url === '/api/v1/heartbeat/send')) {
        return json(res, 200, { success: await heartbeat.send(), status: heartbeat.status, latencyMs: heartbeat.latencyMs });
      }

      if (req.method === 'POST' && (url === '/v1/reconnect' || url === '/api/v1/reconnect')) {
        const { json: payload } = await readBody(req);
        const pairCode = String(payload.pairCode || payload.pair_code || '').trim();
        if (pairCode) {
          return json(res, 200, await connectionManager.reconnectWithPairCode({
            pair_code: pairCode,
            instance_name: typeof payload.instanceName === 'string' ? payload.instanceName : typeof payload.instance_name === 'string' ? payload.instance_name : config.name,
            instance_url: typeof payload.publicUrl === 'string' ? payload.publicUrl : typeof payload.instance_url === 'string' ? payload.instance_url : config.publicUrl,
            version: typeof payload.version === 'string' ? payload.version : undefined,
            environment: typeof payload.environment === 'string' ? payload.environment : config.environment,
          }));
        }

        return json(res, 409, { success: false, message: 'Reconnect requires a Pair Code.' });
      }

      if (url === '/api/v1/token-requests' && req.method === 'POST') {
        const { json: payload } = await readBody(req);
        logger.info('token_request.local_create', { source: payload.source || 'n8n', requester_present: Boolean(payload.requester_name) });

        return json(res, 200, await client.signed('POST', '/api/v1/integrations/n8n/token-requests', payload));
      }

      const tokenRequestMatch = url.match(/^\/api\/v1\/token-requests\/([^/]+)(?:\/(retrieve|delivery|cancel))?$/);
      if (tokenRequestMatch) {
        const requestUuid = encodeURIComponent(tokenRequestMatch[1] ?? '');
        const action = tokenRequestMatch[2] ?? '';
        const platformPath = '/api/v1/integrations/n8n/token-requests/' + requestUuid + (action ? '/' + action : '');

        if (!action && req.method === 'GET') {
          logger.info('token_request.local_status', { request_uuid: requestUuid });

          return json(res, 200, await client.signed('GET', platformPath));
        }

        if (action && req.method === 'POST') {
          const { json: payload } = await readBody(req);
          logger.info('token_request.local_action', { request_uuid: requestUuid, action });

          return json(res, 200, await client.signed('POST', platformPath, payload));
        }
      }
      if (req.method === 'POST' && url === '/api/v1/test-connection') {
        return json(res, 200, await connectionManager.testConnection());
      }

      if (req.method === 'POST' && (url === '/api/v1/secret/rotate' || url === '/api/v1/rotate-secret')) {
        return json(res, 200, await connectionManager.rotateSecret());
      }

      if (req.method === 'POST' && (url === '/v1/integration/disconnect' || url === '/api/v1/disconnect')) {
        await connectionManager.disconnect();

        return json(res, 200, { success: true });
      }

      if (req.method === 'POST' && url === '/v1/challenge') {
        const integration = client.currentIntegration() ?? await storage.readIntegration();

        if (!integration) {
          logger.warn('challenge.failed', { reason: 'agent_unpaired' });

          return json(res, 409, { success: false, message: 'Unpaired' });
        }

        const { raw, json: payload } = await readBody(req);

        if (!validSignedRequest(req, integration, raw, url)) {
          logger.warn('challenge.failed', { instanceId: integration.integration_uuid, reason: 'invalid_signature' });

          return json(res, 401, { success: false, message: 'Invalid signature' });
        }

        if (!payload.challenge_id || !payload.challenge) {
          logger.warn('challenge.failed', { instanceId: integration.integration_uuid, reason: 'invalid_payload' });

          return json(res, 422, { success: false, message: 'Invalid challenge' });
        }

        if (payload.expires_at && Date.parse(String(payload.expires_at)) < Date.now()) {
          logger.warn('challenge.failed', { instanceId: integration.integration_uuid, reason: 'expired' });

          return json(res, 422, { success: false, message: 'Challenge expired' });
        }

        const challengeId = String(payload.challenge_id);

        if (!consumeNonce(`challenge:${challengeId}`)) {
          logger.warn('challenge.failed', { instanceId: integration.integration_uuid, reason: 'replay' });

          return json(res, 409, { success: false, message: 'Challenge already used' });
        }

        const challenge = String(payload.challenge);
        logger.info('challenge.completed', { instanceId: integration.integration_uuid, challengeId });

        return json(res, 200, {
          challenge_id: challengeId,
          challenge,
          signature: sign(integration.shared_secret, challenge),
          responded_at: new Date().toISOString(),
        });
      }

      return json(res, 404, { success: false, message: 'Not found' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Agent error';
      const httpError = error as HttpError;
      logger.error('agent.request_failed', { error: message, status: httpError.status });

      if (typeof httpError.status === 'number') {
        return json(res, httpError.status, httpError.responseBody ?? { success: false, message });
      }

      return json(res, 500, { success: false, message });
    }
  };
}
