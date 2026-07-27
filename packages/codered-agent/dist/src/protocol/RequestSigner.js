import crypto from 'node:crypto';
export function stableJson(value) { return JSON.stringify(sort(value)); }
function sort(v) { if (Array.isArray(v))
    return v.map(sort); if (v && typeof v === 'object')
    return Object.fromEntries(Object.keys(v).sort().map(k => [k, sort(v[k])])); return v; }
export function canonicalPayload(method, path, timestamp, nonce, body) { return [method.toUpperCase(), path, timestamp, nonce, crypto.createHash('sha256').update(body).digest('hex')].join('\n'); }
export function sign(secret, canonical) { return crypto.createHmac('sha256', secret).update(canonical).digest('hex'); }
export function signedHeaders(integration, method, requestPath, body) { const timestamp = Math.floor(Date.now() / 1000).toString(); const nonce = crypto.randomUUID(); return { 'Content-Type': 'application/json', 'X-CodeRED-Integration': integration.integration_uuid, 'X-CodeRED-Timestamp': timestamp, 'X-CodeRED-Nonce': nonce, 'X-CodeRED-Protocol-Version': integration.protocol_version, 'X-CodeRED-Signature': sign(integration.shared_secret, canonicalPayload(method, requestPath, timestamp, nonce, body)) }; }
