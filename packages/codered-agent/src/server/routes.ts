import crypto from 'node:crypto';
import type { IncomingMessage, ServerResponse } from 'node:http';
import type { Config } from '../config/Config.js';
import { canonicalPayload, sign } from '../protocol/RequestSigner.js';
import type { PairingService } from '../protocol/PairingService.js';
import type { DiscoveryService } from '../protocol/DiscoveryService.js';
import type { HeartbeatService } from '../protocol/HeartbeatService.js';
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

  const expected = sign(
    integration.shared_secret,
    canonicalPayload(req.method || 'POST', path, timestamp, nonce, rawBody),
  );

  if (!/^[0-9a-f]{64}$/.test(signature)) {
    return false;
  }

  return crypto.timingSafeEqual(Buffer.from(expected, 'hex'), Buffer.from(signature, 'hex'));
}

export function createRouter(
  config: Config,
  storage: AgentStorage,
  pairing: PairingService,
  discovery: DiscoveryService,
  heartbeat: HeartbeatService,
  reconnect: ReconnectionService,
) {
  return async (req: IncomingMessage, res: ServerResponse) => {
    try {
      const url = req.url?.split('?')[0] || '/';

      if (req.method === 'GET' && url === '/v1/health') {
        return json(res, 200, { status: 'ok', version: '1.0.0' });
      }

      if (req.method === 'GET' && url === '/v1/status') {
        const integration = await storage.readIntegration();

        return json(res, 200, {
          status: heartbeat.status,
          paired: !!integration,
          integration_uuid: integration?.integration_uuid || null,
          platform_url: config.platformUrl,
          agent_version: '1.0.0',
          protocol_version: integration?.protocol_version || '1.0',
          last_heartbeat_at: heartbeat.lastHeartbeatAt,
          last_discovery_at: discovery.lastDiscoveryAt,
        });
      }

      if (req.method === 'POST' && url === '/v1/pair') {
        const now = Date.now();
        pairHits = pairHits.filter((hit) => now - hit < 60_000);

        if (pairHits.length >= 5) {
          return json(res, 429, { success: false, message: 'Rate limit exceeded' });
        }

        pairHits.push(now);
        const { json: payload } = await readBody(req);

        return json(res, 200, await pairing.pair(String(payload.pair_code || '')));
      }

      if (req.method === 'POST' && url === '/v1/discovery/sync') {
        return json(res, 200, { success: true, registered: await discovery.sync(true) });
      }

      if (req.method === 'POST' && url === '/v1/heartbeat/send') {
        return json(res, 200, { success: await heartbeat.send(), status: heartbeat.status });
      }

      if (req.method === 'POST' && url === '/v1/reconnect') {
        return json(res, 200, { success: true, status: await reconnect.start() });
      }

      if (req.method === 'POST' && url === '/v1/integration/disconnect') {
        await storage.clearIntegration();
        heartbeat.status = 'unpaired';

        return json(res, 200, { success: true });
      }

      if (req.method === 'POST' && url === '/v1/challenge') {
        const integration = await storage.readIntegration();

        if (!integration) {
          return json(res, 409, { success: false, message: 'Unpaired' });
        }

        const { raw, json: payload } = await readBody(req);

        if (!validSignedRequest(req, integration, raw, url)) {
          return json(res, 401, { success: false, message: 'Invalid signature' });
        }

        if (!payload.challenge_id || !payload.challenge) {
          return json(res, 422, { success: false, message: 'Invalid challenge' });
        }

        if (payload.expires_at && Date.parse(String(payload.expires_at)) < Date.now()) {
          return json(res, 422, { success: false, message: 'Challenge expired' });
        }

        const challengeId = String(payload.challenge_id);

        if (!consumeNonce(`challenge:${challengeId}`)) {
          return json(res, 409, { success: false, message: 'Challenge already used' });
        }

        const challenge = String(payload.challenge);

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

      return json(res, 500, { success: false, message });
    }
  };
}