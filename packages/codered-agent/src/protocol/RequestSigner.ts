import crypto from 'node:crypto';
import type { StoredIntegration } from '../storage/types.js';

type JsonValue = null | boolean | number | string | JsonValue[] | { [key: string]: JsonValue };

export function stableJson(value: unknown): string {
  return JSON.stringify(sort(value as JsonValue));
}

function sort(value: JsonValue): JsonValue {
  if (Array.isArray(value)) {
    return value.map(sort);
  }

  if (value && typeof value === 'object') {
    return Object.fromEntries(Object.keys(value).sort().map((key) => [key, sort(value[key])])) as JsonValue;
  }

  return value;
}

export function canonicalPayload(method: string, path: string, timestamp: string, nonce: string, body: string): string {
  return [method.toUpperCase(), path, timestamp, nonce, crypto.createHash('sha256').update(body).digest('hex')].join('\n');
}

export function sign(secret: string, canonical: string): string {
  return crypto.createHmac('sha256', secret).update(canonical).digest('hex');
}

export function signedHeaders(
  integration: StoredIntegration,
  method: string,
  requestPath: string,
  body: string,
): Record<string, string> {
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const nonce = crypto.randomUUID();

  return {
    'Content-Type': 'application/json',
    'X-CodeRED-Integration': integration.integration_uuid,
    'X-CodeRED-Timestamp': timestamp,
    'X-CodeRED-Nonce': nonce,
    'X-CodeRED-Protocol-Version': integration.protocol_version,
    'X-CodeRED-Signature': sign(integration.shared_secret, canonicalPayload(method, requestPath, timestamp, nonce, body)),
  };
}