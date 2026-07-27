import type { IExecuteFunctions, INodeExecutionData, INodeType, INodeTypeDescription } from 'n8n-workflow';
import { NodeOperationError } from 'n8n-workflow';
import { assertUrl, joinUrl, signedHeaders, stableJson, type CodeREDCredentials } from './GenericFunctions';

const CONNECTOR_VERSION = '1.0.0';

export class CodeRED implements INodeType {
  description: INodeTypeDescription = {
    displayName: 'CodeRED', name: 'codeRed', icon: 'file:codered.svg', group: ['output'], version: 1, subtitle: '={{$parameter["operation"]}}', description: 'Official CodeRED Platform connector', defaults: { name: 'CodeRED' }, inputs: ['main'], outputs: ['main'], credentials: [{ name: 'CodeREDApi', required: false }], properties: [
      { displayName: 'Operation', name: 'operation', type: 'options', default: 'testConnection', options: [
        { name: 'Get Agent Status', value: 'agentStatus' }, { name: 'Pair Agent', value: 'pairAgent' }, { name: 'Sync Discovery', value: 'agentDiscovery' }, { name: 'Send Heartbeat', value: 'agentHeartbeat' }, { name: 'Reconnect Agent', value: 'agentReconnect' }, { name: 'Pair Instance (Legacy)', value: 'pairInstance' }, { name: 'Test Connection', value: 'testConnection' }, { name: 'Register Discovery', value: 'registerDiscovery' }, { name: 'Send Heartbeat', value: 'sendHeartbeat' }, { name: 'Create Token Request', value: 'createTokenRequest' }, { name: 'Get Token Request Status', value: 'getTokenRequestStatus' }, { name: 'Retrieve Approved Token', value: 'retrieveApprovedToken' }, { name: 'Confirm Token Delivery', value: 'confirmTokenDelivery' }, { name: 'Cancel Token Request', value: 'cancelTokenRequest' }, { name: 'Send Custom Service Event', value: 'sendCustomServiceEvent' }
      ]},
      { displayName: 'Pair Code', name: 'pairCode', type: 'string', default: '', displayOptions: { show: { operation: ['pairInstance','pairAgent'] } } },
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
    if ((credentials.connectionMode || 'agent') === 'agent') assertUrl(credentials.agentUrl || ''); else assertUrl(credentials.baseUrl);
    const out: INodeExecutionData[] = [];
    for (let i = 0; i < this.getInputData().length || i === 0; i++) {
      try { out.push({ json: await runOperation.call(this, credentials, operation, i) }); } catch (error) { throw new NodeOperationError(this.getNode(), error as Error, { itemIndex: i }); }
      if (this.getInputData().length === 0) break;
    }
    return [out];
  }
}

async function runOperation(this: IExecuteFunctions, c: CodeREDCredentials, op: string, i: number): Promise<any> {
  if (op === 'agentStatus') return this.helpers.httpRequest({ method: 'GET', url: joinUrl(c.agentUrl || '', '/v1/status'), headers: { Authorization: 'Bearer '+(c.agentLocalApiToken || '') }, json: true });
  if (op === 'pairAgent') return this.helpers.httpRequest({ method: 'POST', url: joinUrl(c.agentUrl || '', '/v1/pair'), body: stableJson({ pair_code: this.getNodeParameter('pairCode', i) || c.pairCode }), headers: { 'Content-Type': 'application/json', Authorization: 'Bearer '+(c.agentLocalApiToken || '') }, json: true });
  if (op === 'agentDiscovery') return this.helpers.httpRequest({ method: 'POST', url: joinUrl(c.agentUrl || '', '/v1/discovery/sync'), headers: { Authorization: 'Bearer '+(c.agentLocalApiToken || '') }, json: true });
  if (op === 'agentHeartbeat') return this.helpers.httpRequest({ method: 'POST', url: joinUrl(c.agentUrl || '', '/v1/heartbeat/send'), headers: { Authorization: 'Bearer '+(c.agentLocalApiToken || '') }, json: true });
  if (op === 'agentReconnect') return this.helpers.httpRequest({ method: 'POST', url: joinUrl(c.agentUrl || '', '/v1/reconnect'), headers: { Authorization: 'Bearer '+(c.agentLocalApiToken || '') }, json: true });
  if (op === 'pairInstance') {
    const body = stableJson({ pair_code: this.getNodeParameter('pairCode', i) || c['pairCode'], instance_name: c.instanceName, instance_url: c.instanceUrl, environment: c.environment, n8n_version: process.env.N8N_VERSION || '2.x', connector_version: CONNECTOR_VERSION, protocol_version: c.protocolVersion || '1.0' });
    const response = await this.helpers.httpRequest({ method: 'POST', url: joinUrl(c.baseUrl, '/api/v1/integrations/n8n/pair'), body, headers: { 'Content-Type': 'application/json' }, json: true, timeout: 10000 });
    return { success: !!response.success, integration_uuid: response.data?.integration_uuid, protocol_version: response.data?.protocol_version, paired_at: response.data?.paired_at, warning: 'Legacy direct pairing returned a secret once, but it is redacted from node output. Rotate this integration and migrate to CodeRED Agent.' };
  }
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
  return this.helpers.httpRequest({ method: def.method as any, url: joinUrl(c.baseUrl, requestPath), body: body || undefined, headers: signedHeaders(c, def.method, requestPath, body), json: true, timeout: 10000 });
}
function cryptoRandom(): string { return Math.random().toString(36).slice(2)+Date.now().toString(36); }
