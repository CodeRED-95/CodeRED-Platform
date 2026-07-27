import crypto from 'crypto';

export interface CodeREDCredentials {
  baseUrl: string;
  integrationUuid?: string;
  sharedSecret?: string;
  instanceName?: string;
  instanceUrl?: string;
  environment?: string;
  protocolVersion?: string;
  connectorVersion?: string;
  pairCode?: string;
  connectionMode?: string;
  agentUrl?: string;
  agentLocalApiToken?: string;
  timeoutMs?: number | string;
}


export function stableJson(value: unknown): string { return JSON.stringify(sortValue(value)); }
function sortValue(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(sortValue);
  if (value && typeof value === 'object') {
    const record = value as Record<string, unknown>;
    return Object.fromEntries(Object.keys(record).sort().map((key) => [key, sortValue(record[key])]));
  }
  return value;
}
export function sha256Hex(body: string): string { return crypto.createHash('sha256').update(body).digest('hex'); }
export function canonicalPayload(method: string, requestPath: string, timestamp: string, nonce: string, body: string): string { return [method.toUpperCase(), requestPath, timestamp, nonce, sha256Hex(body)].join('\n'); }
export function hmacSignature(secret: string, canonical: string): string { return crypto.createHmac('sha256', secret).update(canonical).digest('hex'); }
export function signedHeaders(credentials: CodeREDCredentials, method: string, requestPath: string, body: string): Record<string, string> {
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const nonce = crypto.randomUUID();
  const canonical = canonicalPayload(method, requestPath, timestamp, nonce, body);
  return { 'Content-Type': 'application/json', 'X-CodeRED-Integration': credentials.integrationUuid || '', 'X-CodeRED-Timestamp': timestamp, 'X-CodeRED-Nonce': nonce, 'X-CodeRED-Protocol-Version': credentials.protocolVersion || '1.0', 'X-CodeRED-Signature': hmacSignature(credentials.sharedSecret || '', canonical) };
}
export function joinUrl(baseUrl: string, requestPath: string): string { return baseUrl.replace(/\/$/, '') + requestPath; }
export function assertUrl(url: string): void { const parsed = new URL(url); if (!['http:', 'https:'].includes(parsed.protocol)) throw new Error('CodeRED URL must use HTTP or HTTPS.'); }
