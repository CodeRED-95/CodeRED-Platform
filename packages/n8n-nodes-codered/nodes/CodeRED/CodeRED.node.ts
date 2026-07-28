import type { IExecuteFunctions, INodeExecutionData, INodeType, INodeTypeDescription } from 'n8n-workflow';
import { NodeOperationError } from 'n8n-workflow';
import { assertUrl, joinUrl, signedHeaders, stableJson, type CodeREDCredentials } from './GenericFunctions';

const CONNECTOR_VERSION = '1.0.0';

export class CodeRED implements INodeType {
  description: INodeTypeDescription = {
    displayName: 'CodeRED', name: 'codeRed', icon: 'file:codered.svg', group: ['output'], version: 1, subtitle: '={{$parameter["operation"]}}', description: 'Official CodeRED Platform connector', defaults: { name: 'CodeRED' }, inputs: ['main'], outputs: ['main'], credentials: [{ name: 'CodeREDApi', required: false }], properties: [
      { displayName: 'Operation', name: 'operation', type: 'options', default: 'testConnection', options: [
        { name: 'Get Agent Status', value: 'agentStatus' }, { name: 'Pair Instance', value: 'pairInstance' }, { name: 'Pair Agent', value: 'pairAgent' }, { name: 'Sync Discovery', value: 'agentDiscovery' }, { name: 'Send Heartbeat', value: 'agentHeartbeat' }, { name: 'Reconnect Agent', value: 'agentReconnect' }, { name: 'Test Connection', value: 'testConnection' }, { name: 'Register Discovery', value: 'registerDiscovery' }, { name: 'Send Heartbeat', value: 'sendHeartbeat' }, { name: 'Create Token Request', value: 'createTokenRequest' }, { name: 'Get Token Request Status', value: 'getTokenRequestStatus' }, { name: 'Retrieve Approved Token', value: 'retrieveApprovedToken' }, { name: 'Confirm Token Delivery', value: 'confirmTokenDelivery' }, { name: 'Cancel Token Request', value: 'cancelTokenRequest' }, { name: 'Send Custom Service Event', value: 'sendCustomServiceEvent' }
      ]},
      { displayName: 'Pair Code', name: 'pairCode', type: 'string', default: '', displayOptions: { show: { operation: ['pairInstance','pairAgent','agentReconnect'] } } },
      { displayName: 'Capabilities JSON', name: 'capabilitiesJson', type: 'json', default: '[]', displayOptions: { show: { operation: ['registerDiscovery'] } } },
      { displayName: 'Services JSON', name: 'servicesJson', type: 'json', default: '{"token_requests":{"enabled":true,"version":"1.0"}}', displayOptions: { show: { operation: ['registerDiscovery'] } } },
      { displayName: 'Plugins JSON', name: 'pluginsJson', type: 'json', default: '[]', displayOptions: { show: { operation: ['registerDiscovery'] } } },
      { displayName: 'Request UUID', name: 'requestUuid', type: 'string', default: '', displayOptions: { show: { operation: ['getTokenRequestStatus','retrieveApprovedToken','confirmTokenDelivery','cancelTokenRequest'] } } },
      { displayName: 'Payload JSON', name: 'payloadJson', type: 'json', default: '{}', displayOptions: { show: { operation: ['createTokenRequest','confirmTokenDelivery','sendCustomServiceEvent'] } } },
      { displayName: 'Service', name: 'service', type: 'string', default: 'custom.event', displayOptions: { show: { operation: ['sendCustomServiceEvent'] } } }
    ]
  };

  async execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]> {
    const credentials = await this.getCredentials('CodeREDApi') as CodeREDCredentials;
    const operation = this.getNodeParameter('operation', 0) as string;
    assertUrl(credentials.agentBaseUrl || '');
    const out: INodeExecutionData[] = [];
    for (let i = 0; i < this.getInputData().length || i === 0; i++) {
      try { out.push({ json: await runOperation.call(this, credentials, operation, i) }); } catch (error) { throw new NodeOperationError(this.getNode(), buildNodeError(error, operation), { itemIndex: i }); }
      if (this.getInputData().length === 0) break;
    }
    return [out];
  }
}

async function agentRequest(this: IExecuteFunctions, c: CodeREDCredentials, method: 'GET' | 'POST', path: string, body?: unknown): Promise<any> {
  return this.helpers.httpRequest({
    method,
    url: joinUrl(c.agentBaseUrl || '', path),
    body: body === undefined ? undefined : stableJson(body),
    headers: { 'Content-Type': 'application/json', Authorization: 'Bearer '+(c.localApiToken || '') },
    json: true,
    timeout: Number(c.timeoutMs || 15000),
  });
}

async function testAgentConnection(this: IExecuteFunctions, c: CodeREDCredentials): Promise<any> {
  const started = Date.now();
  let status: any;

  try {
    status = await agentRequest.call(this, c, 'GET', '/api/v1/status');
  } catch (error) {
    throw buildNodeError(error, 'testConnection', '/api/v1/status');
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

async function runOperation(this: IExecuteFunctions, c: CodeREDCredentials, op: string, i: number): Promise<any> {
  if (op === 'testConnection') {
    await agentRequest.call(this, c, 'GET', '/api/v1/status');
    return agentRequest.call(this, c, 'POST', '/api/v1/test-connection');
  }
  if (op === 'agentStatus') return agentRequest.call(this, c, 'GET', '/api/v1/status');
  if (op === 'pairAgent' || op === 'pairInstance') return agentRequest.call(this, c, 'POST', '/api/v1/pair', {
    pairCode: this.getNodeParameter('pairCode', i),
    instanceName: c.instanceName,
    publicUrl: c.publicUrl,
    environment: c.environment,
  });
  if (op === 'agentDiscovery') return agentRequest.call(this, c, 'POST', '/api/v1/discovery/sync');
  if (op === 'agentHeartbeat') return agentRequest.call(this, c, 'POST', '/api/v1/heartbeat/send');
  if (op === 'agentReconnect') return agentRequest.call(this, c, 'POST', '/api/v1/reconnect', {
    pairCode: this.getNodeParameter('pairCode', i),
    instanceName: c.instanceName,
    publicUrl: c.publicUrl,
    environment: c.environment,
  });
  const map: Record<string,{method:string,path:(i:number)=>string,body:(i:number)=>any}> = {
    testConnection: { method:'POST', path:()=>'/api/v1/integrations/n8n/challenge', body:()=>({ challenge: cryptoRandom(), sent_at: new Date().toISOString() }) },
    registerDiscovery: { method:'POST', path:()=>'/api/v1/integrations/n8n/discovery', body:(i)=>({ protocol_version: c.protocolVersion || '1.0', connector_version: CONNECTOR_VERSION, n8n_version: process.env.N8N_VERSION || '2.x', capabilities: JSON.parse(this.getNodeParameter('capabilitiesJson', i) as string), services: JSON.parse(this.getNodeParameter('servicesJson', i) as string), plugins: JSON.parse(this.getNodeParameter('pluginsJson', i) as string) }) },
    sendHeartbeat: { method:'POST', path:()=>'/api/v1/integrations/n8n/heartbeat', body:()=>({ instance_uuid: c.integrationUuid, n8n_version: process.env.N8N_VERSION || '2.x', connector_version: CONNECTOR_VERSION, protocol_version: c.protocolVersion || '1.0', environment: c.environment, sent_at: new Date().toISOString() }) },
    createTokenRequest: { method:'POST', path:()=>'/api/v1/integrations/n8n/token-requests', body:(i)=>JSON.parse(this.getNodeParameter('payloadJson', i) as string) },
    getTokenRequestStatus: { method:'GET', path:(i)=>'/api/v1/integrations/n8n/token-requests/'+this.getNodeParameter('requestUuid', i), body:()=>({}) },
    retrieveApprovedToken: { method:'POST', path:(i)=>'/api/v1/integrations/n8n/token-requests/'+this.getNodeParameter('requestUuid', i)+'/retrieve', body:(i)=>JSON.parse(this.getNodeParameter('payloadJson', i) as string || '{}') },
    confirmTokenDelivery: { method:'POST', path:(i)=>'/api/v1/integrations/n8n/token-requests/'+this.getNodeParameter('requestUuid', i)+'/delivery', body:(i)=>JSON.parse(this.getNodeParameter('payloadJson', i) as string) },
    cancelTokenRequest: { method:'POST', path:(i)=>'/api/v1/integrations/n8n/token-requests/'+this.getNodeParameter('requestUuid', i)+'/cancel', body:()=>({}) },
    sendCustomServiceEvent: { method:'POST', path:()=>'/api/v1/integrations/n8n/services/events', body:(i)=>({ service: this.getNodeParameter('service', i), payload: JSON.parse(this.getNodeParameter('payloadJson', i) as string) }) }
  };
  const def = map[op]; const requestPath = def.path(i); const body = def.method === 'GET' ? '' : stableJson(def.body(i));
  return this.helpers.httpRequest({ method: def.method as any, url: joinUrl(c.baseUrl || '', requestPath), body: body || undefined, headers: signedHeaders(c, def.method, requestPath, body), json: true, timeout: 10000 });
}
function cryptoRandom(): string { return Math.random().toString(36).slice(2)+Date.now().toString(36); }

function sanitizeBody(value: unknown): unknown {
  if (!value || typeof value !== 'object') return value;
  const input = value as Record<string, unknown>;
  const output: Record<string, unknown> = {};
  for (const [key, item] of Object.entries(input)) {
    output[key] = /secret|token|signature|authorization/i.test(key) ? '[redacted]' : sanitizeBody(item);
  }
  return output;
}

function buildNodeError(error: unknown, operation: string, endpoint?: string): Error {
  const anyError = error as any;
  const statusCode = anyError?.statusCode || anyError?.status || anyError?.response?.statusCode || anyError?.response?.status;
  const body = sanitizeBody(anyError?.response?.body || anyError?.response?.data || anyError?.error || anyError?.description);
  const errorCode = anyError?.errorCode || anyError?.code || (body as any)?.errorCode || (body as any)?.code;
  const message = anyError instanceof Error ? anyError.message : String(error || 'Error desconocido');
  const pieces = [message, statusCode ? 'HTTP '+statusCode : null, errorCode, endpoint ? 'endpoint '+endpoint : null, 'operacion '+operation].filter(Boolean);
  const detail = body ? '\nRespuesta saneada: '+JSON.stringify(body) : '';
  const built = new Error(pieces.join(' - ')+detail);
  (built as any).cause = error;
  return built;
}
