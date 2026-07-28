import crypto from 'node:crypto';
import type { IncomingMessage, ServerResponse } from 'node:http';
import type { Config } from '../config/Config.js';
import { Logger } from '../logging/Logger.js';
import { canonicalPayload, sign } from '../protocol/RequestSigner.js';
import type { CodeREDClient } from '../protocol/CodeREDClient.js';
import type { DiscoveryService } from '../protocol/DiscoveryService.js';
import type { HeartbeatService } from '../protocol/HeartbeatService.js';
import type { PairingService } from '../protocol/PairingService.js';
import type { ReconnectionService } from '../protocol/ReconnectionService.js';
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

function statusPayload(
  config: Config,
  integration: StoredIntegration | null,
  heartbeat: HeartbeatService,
  discovery: DiscoveryService,
  client: CodeREDClient,
): Record<string, unknown> {
  return {
    status: client.isPaired() ? heartbeat.status : 'unpaired',
    paired: client.isPaired(),
    platformConnected: heartbeat.status === 'connected',
    instanceId: integration?.integration_uuid || null,
    agent_version: '1.0.0',
    protocol_version: integration?.protocol_version || '1.0',
    lastHeartbeatAt: heartbeat.lastHeartbeatAt,
    lastDiscoveryAt: discovery.lastDiscoveryAt,
    heartbeatAgeSeconds: heartbeat.lastHeartbeatAt ? Math.max(0, Math.round((Date.now() - Date.parse(heartbeat.lastHeartbeatAt)) / 1000)) : null,
    latencyMs: heartbeat.latencyMs,
    capabilities: discovery.capabilityCount,
    workflows: discovery.workflowCount,
    lastError: heartbeat.lastError || discovery.lastError,
  };
}

export function createRouter(
  config: Config,
  storage: AgentStorage,
  pairing: PairingService,
  discovery: DiscoveryService,
  heartbeat: HeartbeatService,
  reconnect: ReconnectionService,
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

        return json(res, 200, statusPayload(config, client.currentIntegration(), heartbeat, discovery, client));
      }

      if (req.method === 'POST' && (url === '/v1/pair' || url === '/api/v1/pair')) {
        const now = Date.now();
        pairHits = pairHits.filter((hit) => now - hit < 60_000);

        if (pairHits.length >= 5) {
          return json(res, 429, { success: false, message: 'Rate limit exceeded' });
        }

        pairHits.push(now);
        const { json: payload } = await readBody(req);

        return json(res, 200, await pairing.pair({
          pairCode: String(payload.pairCode || payload.pair_code || ''),
          instanceName: typeof payload.instanceName === 'string' ? payload.instanceName : undefined,
          publicUrl: typeof payload.publicUrl === 'string' ? payload.publicUrl : undefined,
          environment: typeof payload.environment === 'string' ? payload.environment : undefined,
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
          return json(res, 200, await pairing.pair({
            pairCode,
            instanceName: typeof payload.instanceName === 'string' ? payload.instanceName : undefined,
            publicUrl: typeof payload.publicUrl === 'string' ? payload.publicUrl : undefined,
            environment: typeof payload.environment === 'string' ? payload.environment : undefined,
          }));
        }

        return json(res, 200, { success: true, status: await reconnect.start() });
      }

      if (req.method === 'POST' && url === '/api/v1/test-connection') {
        const integration = client.currentIntegration() ?? await storage.readIntegration();

        if (!integration) {
          return json(res, 409, { success: false, paired: false, message: 'El agente todavía no está emparejado.' });
        }

        client.setPairing(integration);
        const started = Date.now();
        let challengeCompleted = false;
        let heartbeatCompleted = false;

        try {
          await client.signed('POST', integration.challenge_url || '/api/v1/integrations/n8n/challenge', { challenge: crypto.randomUUID(), sent_at: new Date().toISOString() });
          challengeCompleted = true;
        } catch (error) {
          logger.warn('test_connection.challenge_failed', { error: error instanceof Error ? error.message : 'Unknown challenge error' });
        }

        heartbeatCompleted = await heartbeat.send();

        return json(res, 200, {
          success: challengeCompleted && heartbeatCompleted && discovery.capabilityCount > 0,
          paired: true,
          platformConnected: heartbeat.status === 'connected',
          latencyMs: Date.now() - started,
          challengeCompleted,
          heartbeatCompleted,
          capabilities: discovery.capabilityCount,
          workflows: discovery.workflowCount,
          lastError: heartbeat.lastError || discovery.lastError,
        });
      }

      if (req.method === 'POST' && url === '/v1/integration/disconnect') {
        await storage.clearIntegration();
        client.clearPairing();
        heartbeat.status = 'unpaired';

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
      logger.error('agent.request_failed', { error: message });

      return json(res, 500, { success: false, message });
    }
  };
}
