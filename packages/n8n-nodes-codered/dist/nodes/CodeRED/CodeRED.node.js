"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.CodeRED = void 0;
const n8n_workflow_1 = require("n8n-workflow");
const GenericFunctions_1 = require("./GenericFunctions");
const ConnectionManager_1 = require("./ConnectionManager");
class CodeRED {
    description = {
        displayName: 'CodeRED', name: 'codeRed', icon: 'file:codered.svg', group: ['output'], version: 1, subtitle: '={{$parameter["operation"]}}', description: 'Official CodeRED Platform connector', defaults: { name: 'CodeRED' }, inputs: ['main'], outputs: ['main'], credentials: [{ name: 'CodeREDApi', required: false }], properties: [
            { displayName: 'Operation', name: 'operation', type: 'options', default: 'pairInstance', options: [
                    { name: 'Pair Instance', value: 'pairInstance' }, { name: 'Get Connection Status', value: 'agentStatus' }, { name: 'Rotate Secret', value: 'rotateSecret' }, { name: 'Disconnect', value: 'disconnect' }
                ] },
            { displayName: 'Pair Code', name: 'pairCode', type: 'string', default: '', required: true, displayOptions: { show: { operation: ['pairInstance'] } } },
        ]
    };
    async execute() {
        const credentials = await this.getCredentials('CodeREDApi');
        const operation = this.getNodeParameter('operation', 0);
        (0, GenericFunctions_1.assertUrl)(credentials.agentBaseUrl || '');
        const out = [];
        for (let i = 0; i < this.getInputData().length || i === 0; i++) {
            try { out.push({ json: await runOperation.call(this, credentials, operation, i) }); }
            catch (error) { throw new n8n_workflow_1.NodeOperationError(this.getNode(), buildNodeError(error, operation), { itemIndex: i }); }
            if (this.getInputData().length === 0) break;
        }
        return [out];
    }
}
exports.CodeRED = CodeRED;
async function runOperation(c, op, i) {
    const connectionManager = new ConnectionManager_1.ConnectionManager(this, c);
    if (op === 'pairInstance') {
        const result = await connectionManager.connect({ pairCode: this.getNodeParameter('pairCode', i) });
        if (!result.paired) throw new Error('Pair Instance no dejó el agente emparejado.');
        if (!result.challengeCompleted) throw new Error('Pair Instance cancelado: Challenge falló.');
        if (!result.discoveryCompleted || !result.heartbeatCompleted) throw new Error('Pair Instance incompleto: el agente quedó emparejado, pero Discovery o Heartbeat no terminaron correctamente.');
        return result;
    }
    if (op === 'agentStatus') return connectionManager.status();
    if (op === 'rotateSecret') return connectionManager.rotateSecret();
    if (op === 'disconnect') return connectionManager.disconnect();
    throw new Error('Operación no soportada por el asistente de conexión CodeRED.');
}
function sanitizeBody(value) {
    if (!value || typeof value !== 'object') return value;
    const output = {};
    for (const [key, item] of Object.entries(value)) output[key] = /secret|token|signature|authorization/i.test(key) ? '[redacted]' : sanitizeBody(item);
    return output;
}
function buildNodeError(error, operation, endpoint) {
    const anyError = error;
    const statusCode = anyError?.statusCode || anyError?.status || anyError?.response?.statusCode || anyError?.response?.status;
    const body = sanitizeBody(anyError?.response?.body || anyError?.response?.data || anyError?.error || anyError?.description);
    const errorCode = anyError?.errorCode || anyError?.code || body?.errorCode || body?.code;
    const message = error instanceof Error ? error.message : String(error || 'Error desconocido');
    const pieces = [message, statusCode ? 'HTTP ' + statusCode : null, errorCode, endpoint ? 'endpoint ' + endpoint : null, 'operacion ' + operation].filter(Boolean);
    const detail = body ? '\nRespuesta saneada: ' + JSON.stringify(body) : '';
    const built = new Error(pieces.join(' - ') + detail);
    built.cause = error;
    return built;
}
