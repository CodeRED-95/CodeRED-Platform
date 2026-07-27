import { signedHeaders, stableJson } from './RequestSigner.js';
export class CodeREDClient {
    config;
    storage;
    constructor(config, storage) {
        this.config = config;
        this.storage = storage;
    }
    async pair(pairCode) { const body = stableJson({ pair_code: pairCode, instance_name: this.config.name, instance_url: this.config.publicUrl, environment: this.config.environment, n8n_version: null, connector_version: 'codered-agent/1.0.0', protocol_version: '1.0' }); return this.raw('POST', '/api/v1/integrations/n8n/pair', body, false); }
    async signed(method, path, payload = {}) { const integration = await this.storage.readIntegration(); if (!integration)
        throw new Error('Agent is unpaired'); const body = method === 'GET' ? '' : stableJson(payload); return this.raw(method, path, body, true); }
    async raw(method, path, body, signed) { const integration = signed ? await this.storage.readIntegration() : null; const controller = new AbortController(); const timer = setTimeout(() => controller.abort(), this.config.requestTimeoutMs); try {
        const res = await fetch(this.config.platformUrl + path, { method, body: body || undefined, headers: signed && integration ? signedHeaders(integration, method, path, body) : { 'Content-Type': 'application/json' }, signal: controller.signal });
        const text = await res.text();
        const json = text ? JSON.parse(text) : {};
        if (!res.ok) {
            const e = new Error('CodeRED request failed ' + res.status);
            e.status = res.status;
            e.retryAfter = res.headers.get('retry-after') || undefined;
            throw e;
        }
        return json;
    }
    finally {
        clearTimeout(timer);
    } }
}
