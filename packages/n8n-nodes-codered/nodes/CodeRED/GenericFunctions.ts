import crypto from 'crypto';

export interface CodeREDCredentials {
  agentBaseUrl?: string;
  localApiToken?: string;
  timeoutMs?: number | string;
  allowUnauthorizedCerts?: boolean;
  instanceName?: string;
  publicUrl?: string;
  environment?: string;
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
export function joinUrl(baseUrl: string, requestPath: string): string { return baseUrl.replace(/\/$/, '') + requestPath; }
export function assertUrl(url: string): void { const parsed = new URL(url); if (!['http:', 'https:'].includes(parsed.protocol)) throw new Error('CodeRED URL must use HTTP or HTTPS.'); }
