"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.CodeRED = void 0;
const n8n_workflow_1 = require("n8n-workflow");
const GenericFunctions_1 = require("./GenericFunctions");
const CONNECTOR_VERSION = '1.0.0';
class CodeRED {
    description = {
        displayName: 'CodeRED', name: 'codeRed', icon: 'file:codered.svg', group: ['output'], version: 1, subtitle: '={{$parameter["operation"]}}', description: 'Official CodeRED Platform connector', defaults: { name: 'CodeRED' }, inputs: ['main'], outputs: ['main'], credentials: [{ name: 'CodeREDApi', required: false }], properties: [
            { displayName: 'Operation', name: 'operation', type: 'options', default: 'testConnection', options: [
                    { name: 'Get Agent Status', value: 'agentStatus' }, { name: 'Pair Agent', value: 'pairAgent' }, { name: 'Sync Discovery', value: 'agentDiscovery' }, { name: 'Send Heartbeat', value: 'agentHeartbeat' }, { name: 'Reconnect Agent', value: 'agentReconnect' }, { name: 'Pair Instance (Legacy)', value: 'pairInstance' }, { name: 'Test Connection', value: 'testConnection' }, { name: 'Register Discovery', value: 'registerDiscovery' }, { name: 'Send Heartbeat', value: 'sendHeartbeat' }, { name: 'Create Token Request', value: 'createTokenRequest' }, { name: 'Get Token Request Status', value: 'getTokenRequestStatus' }, { name: 'Retrieve Approved Token', value: 'retrieveApprovedToken' }, { name: 'Confirm Token Delivery', value: 'confirmTokenDelivery' }, { name: 'Cancel Token Request', value: 'cancelTokenRequest' }, { name: 'Send Custom Service Event', value: 'sendCustomServiceEvent' }
                ] },
            { displayName: 'Pair Code', name: 'pairCode', type: 'string', default: '', displayOptions: { show: { operation: ['pairInstance', 'pairAgent'] } } },
            { displayName: 'Capabilities JSON', name: 'capabilitiesJson', type: 'json', default: '[]', displayOptions: { show: { operation: ['registerDiscovery'] } } },
            { displayName: 'Services JSON', name: 'servicesJson', type: 'json', default: '{"token_requests":{"enabled":true,"version":"1.0"}}', displayOptions: { show: { operation: ['registerDiscovery'] } } },
            { displayName: 'Plugins JSON', name: 'pluginsJson', type: 'json', default: '[]', displayOptions: { show: { operation: ['registerDiscovery'] } } },
            { displayName: 'Request UUID', name: 'requestUuid', type: 'string', default: '', displayOptions: { show: { operation: ['getTokenRequestStatus', 'retrieveApprovedToken', 'confirmTokenDelivery', 'cancelTokenRequest'] } } },
            { displayName: 'Payload JSON', name: 'payloadJson', type: 'json', default: '{}', displayOptions: { show: { operation: ['createTokenRequest', 'confirmTokenDelivery', 'sendCustomServiceEvent'] } } },
            { displayName: 'Service', name: 'service', type: 'string', default: 'custom.event', displayOptions: { show: { operation: ['sendCustomServiceEvent'] } } }
        ]
    };
    async execute() {
        const credentials = await this.getCredentials('CodeREDApi');
        const operation = this.getNodeParameter('operation', 0);
        if ((credentials.connectionMode || 'agent') === 'agent')
            (0, GenericFunctions_1.assertUrl)(credentials.agentUrl || '');
        else
            (0, GenericFunctions_1.assertUrl)(credentials.baseUrl);
        const out = [];
        for (let i = 0; i < this.getInputData().length || i === 0; i++) {
            try {
                out.push({ json: await runOperation.call(this, credentials, operation, i) });
            }
            catch (error) {
                throw new n8n_workflow_1.NodeOperationError(this.getNode(), error, { itemIndex: i });
            }
            if (this.getInputData().length === 0)
                break;
        }
        return [out];
    }
}
exports.CodeRED = CodeRED;
async function agentRequest(c, method, path, body) {
    return this.helpers.httpRequest({
        method,
        url: (0, GenericFunctions_1.joinUrl)(c.agentUrl || '', path),
        body: body === undefined ? undefined : (0, GenericFunctions_1.stableJson)(body),
        headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + (c.agentLocalApiToken || '') },
        json: true,
        timeout: Number(c.timeoutMs || 15000),
    });
}
async function testAgentConnection(c) {
    const started = Date.now();
    let status;
    try {
        status = await agentRequest.call(this, c, 'GET', '/api/v1/status');
    }
    catch (error) {
        throw new Error('No se puede acceder al agente o el token local es inválido.');
    }
    if (!status.paired) {
        return { success: false, paired: false, message: 'El agente todavía no está emparejado.', status };
    }
    if (!status.platformConnected) {
        return { success: false, paired: true, message: 'El agente está emparejado, pero Platform no confirmó conexión reciente.', status };
    }
    return {
        success: true,
        paired: true,
        latencyMs: Date.now() - started,
        challenge: status.capabilities > 0,
        capabilities: status.capabilities,
        workflows: status.workflows,
        instanceId: status.instanceId,
        status,
    };
}
async function runOperation(c, op, i) {
    if ((c.connectionMode || 'agent') === 'agent' && op === 'testConnection')
        return testAgentConnection.call(this, c);
    if (op === 'agentStatus')
        return agentRequest.call(this, c, 'GET', '/api/v1/status');
    if (op === 'pairAgent')
        return agentRequest.call(this, c, 'POST', '/v1/pair', { pair_code: this.getNodeParameter('pairCode', i) || c.pairCode });
    if (op === 'agentDiscovery')
        return agentRequest.call(this, c, 'POST', '/v1/discovery/sync');
    if (op === 'agentHeartbeat')
        return agentRequest.call(this, c, 'POST', '/v1/heartbeat/send');
    if (op === 'agentReconnect')
        return agentRequest.call(this, c, 'POST', '/v1/reconnect');
    if (op === 'pairInstance') {
        const body = (0, GenericFunctions_1.stableJson)({ pair_code: this.getNodeParameter('pairCode', i) || c['pairCode'], instance_name: c.instanceName, instance_url: c.instanceUrl, environment: c.environment, n8n_version: process.env.N8N_VERSION || '2.x', connector_version: CONNECTOR_VERSION, protocol_version: c.protocolVersion || '1.0' });
        const response = await this.helpers.httpRequest({ method: 'POST', url: (0, GenericFunctions_1.joinUrl)(c.baseUrl, '/api/v1/integrations/n8n/pair'), body, headers: { 'Content-Type': 'application/json' }, json: true, timeout: 10000 });
        return { success: !!response.success, integration_uuid: response.data?.integration_uuid, protocol_version: response.data?.protocol_version, paired_at: response.data?.paired_at, warning: 'Legacy direct pairing returned a secret once, but it is redacted from node output. Rotate this integration and migrate to CodeRED Agent.' };
    }
    const map = {
        testConnection: { method: 'POST', path: () => '/api/v1/integrations/n8n/challenge', body: () => ({ challenge: cryptoRandom(), sent_at: new Date().toISOString() }) },
        registerDiscovery: { method: 'POST', path: () => '/api/v1/integrations/n8n/discovery', body: (i) => ({ protocol_version: c.protocolVersion || '1.0', connector_version: CONNECTOR_VERSION, n8n_version: process.env.N8N_VERSION || '2.x', capabilities: JSON.parse(this.getNodeParameter('capabilitiesJson', i)), services: JSON.parse(this.getNodeParameter('servicesJson', i)), plugins: JSON.parse(this.getNodeParameter('pluginsJson', i)) }) },
        sendHeartbeat: { method: 'POST', path: () => '/api/v1/integrations/n8n/heartbeat', body: () => ({ instance_uuid: c.integrationUuid, n8n_version: process.env.N8N_VERSION || '2.x', connector_version: CONNECTOR_VERSION, protocol_version: c.protocolVersion || '1.0', environment: c.environment, sent_at: new Date().toISOString() }) },
        createTokenRequest: { method: 'POST', path: () => '/api/v1/integrations/n8n/token-requests', body: (i) => JSON.parse(this.getNodeParameter('payloadJson', i)) },
        getTokenRequestStatus: { method: 'GET', path: (i) => '/api/v1/integrations/n8n/token-requests/' + this.getNodeParameter('requestUuid', i), body: () => ({}) },
        retrieveApprovedToken: { method: 'POST', path: (i) => '/api/v1/integrations/n8n/token-requests/' + this.getNodeParameter('requestUuid', i) + '/retrieve', body: (i) => JSON.parse(this.getNodeParameter('payloadJson', i) || '{}') },
        confirmTokenDelivery: { method: 'POST', path: (i) => '/api/v1/integrations/n8n/token-requests/' + this.getNodeParameter('requestUuid', i) + '/delivery', body: (i) => JSON.parse(this.getNodeParameter('payloadJson', i)) },
        cancelTokenRequest: { method: 'POST', path: (i) => '/api/v1/integrations/n8n/token-requests/' + this.getNodeParameter('requestUuid', i) + '/cancel', body: () => ({}) },
        sendCustomServiceEvent: { method: 'POST', path: () => '/api/v1/integrations/n8n/services/events', body: (i) => ({ service: this.getNodeParameter('service', i), payload: JSON.parse(this.getNodeParameter('payloadJson', i)) }) }
    };
    const def = map[op];
    const requestPath = def.path(i);
    const body = def.method === 'GET' ? '' : (0, GenericFunctions_1.stableJson)(def.body(i));
    return this.helpers.httpRequest({ method: def.method, url: (0, GenericFunctions_1.joinUrl)(c.baseUrl, requestPath), body: body || undefined, headers: (0, GenericFunctions_1.signedHeaders)(c, def.method, requestPath, body), json: true, timeout: 10000 });
}
function cryptoRandom() { return Math.random().toString(36).slice(2) + Date.now().toString(36); }
