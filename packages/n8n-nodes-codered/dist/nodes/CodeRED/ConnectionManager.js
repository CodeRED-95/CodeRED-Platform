"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.ConnectionManager = void 0;
const GenericFunctions_1 = require("./GenericFunctions");
class ConnectionManager {
    ctx;
    credentials;
    constructor(ctx, credentials) { this.ctx = ctx; this.credentials = credentials; }
    async connect(input) {
        return this.request('POST', '/api/v1/pair', { pairCode: input.pairCode, instanceName: this.credentials.instanceName, publicUrl: this.credentials.publicUrl, environment: this.credentials.environment });
    }
    async disconnect() { return this.request('POST', '/v1/integration/disconnect'); }
    async rotateSecret() { return this.request('POST', '/api/v1/secret/rotate'); }
    async status() { return this.request('GET', '/api/v1/status'); }
    async request(method, path, body) {
        return this.ctx.helpers.httpRequest({ method, url: (0, GenericFunctions_1.joinUrl)(this.credentials.agentBaseUrl || '', path), body: body === undefined ? undefined : (0, GenericFunctions_1.stableJson)(body), headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + (this.credentials.localApiToken || '') }, json: true, timeout: Number(this.credentials.timeoutMs || 15000) });
    }
}
exports.ConnectionManager = ConnectionManager;
