import { AgentUnpairedError } from '../errors/AgentUnpairedError.js';
import { signedHeaders, stableJson } from './RequestSigner.js';
export class CodeREDClient {
    config;
    storage;
    integration = null;
    constructor(config, storage) {
        this.config = config;
        this.storage = storage;
    }
    async restorePairing() {
        this.integration = await this.storage.readIntegration();
        return this.integration !== null;
    }
    isPaired() {
        return this.integration !== null;
    }
    currentIntegration() {
        return this.integration;
    }
    setPairing(integration) {
        this.integration = integration;
    }
    clearPairing() {
        this.integration = null;
    }
    async pair(pairCode) {
        const body = stableJson({
            pair_code: pairCode,
            instance_name: this.config.name,
            instance_url: this.config.publicUrl,
            environment: this.config.environment,
            n8n_version: null,
            connector_version: 'codered-agent/1.0.0',
            protocol_version: '1.0',
        });
        return this.raw('POST', '/api/v1/integrations/n8n/pair', body, null);
    }
    async signed(method, path, payload = {}) {
        const integration = this.integration ?? await this.storage.readIntegration();
        if (!integration) {
            throw new AgentUnpairedError();
        }
        this.integration = integration;
        const body = method === 'GET' ? '' : stableJson(payload);
        return this.raw(method, path, body, integration);
    }
    async raw(method, path, body, integration) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), this.config.requestTimeoutMs);
        try {
            const response = await fetch(this.config.platformUrl + path, {
                method,
                body: body || undefined,
                headers: integration ? signedHeaders(integration, method, path, body) : { 'Content-Type': 'application/json' },
                signal: controller.signal,
            });
            const text = await response.text();
            const json = text ? JSON.parse(text) : {};
            if (!response.ok) {
                const error = new Error(`CodeRED request failed ${response.status}`);
                error.status = response.status;
                error.retryAfter = response.headers.get('retry-after') || undefined;
                throw error;
            }
            return json;
        }
        finally {
            clearTimeout(timer);
        }
    }
}
