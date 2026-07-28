"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.stableJson = stableJson;
exports.sha256Hex = sha256Hex;
exports.canonicalPayload = canonicalPayload;
exports.hmacSignature = hmacSignature;
exports.joinUrl = joinUrl;
exports.assertUrl = assertUrl;
const crypto_1 = __importDefault(require("crypto"));
function stableJson(value) { return JSON.stringify(sortValue(value)); }
function sortValue(value) {
    if (Array.isArray(value))
        return value.map(sortValue);
    if (value && typeof value === 'object') {
        const record = value;
        return Object.fromEntries(Object.keys(record).sort().map((key) => [key, sortValue(record[key])]));
    }
    return value;
}
function sha256Hex(body) { return crypto_1.default.createHash('sha256').update(body).digest('hex'); }
function canonicalPayload(method, requestPath, timestamp, nonce, body) { return [method.toUpperCase(), requestPath, timestamp, nonce, sha256Hex(body)].join('\n'); }
function hmacSignature(secret, canonical) { return crypto_1.default.createHmac('sha256', secret).update(canonical).digest('hex'); }
function joinUrl(baseUrl, requestPath) { return baseUrl.replace(/\/$/, '') + requestPath; }
function assertUrl(url) { const parsed = new URL(url); if (!['http:', 'https:'].includes(parsed.protocol))
    throw new Error('CodeRED URL must use HTTP or HTTPS.'); }
