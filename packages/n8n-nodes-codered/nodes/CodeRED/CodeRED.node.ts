import type { IExecuteFunctions, INodeExecutionData, INodeType, INodeTypeDescription } from 'n8n-workflow';
import { NodeOperationError } from 'n8n-workflow';
import { assertUrl, type CodeREDCredentials } from './GenericFunctions';
import { ConnectionManager } from './ConnectionManager';
import { LocalAgentHttpError, localAgentBaseUrl, sanitizeOutput } from './LocalAgentClient';

const CONNECTION_OPERATIONS = ['pairInstance', 'testConnection', 'reconnect', 'agentStatus', 'refreshDiscovery', 'rotateSecret', 'disconnect'];
const TOKEN_REQUEST_OPERATIONS = ['createTokenRequest', 'getTokenRequestStatus', 'retrieveApprovedToken', 'confirmTokenDelivery', 'cancelTokenRequest'];

export class CodeRED implements INodeType {
  description: INodeTypeDescription = {
    displayName: 'CodeRED',
    name: 'codeRed',
    icon: 'file:codered.svg',
    group: ['output'],
    version: 1,
    subtitle: '={{$parameter["operation"]}}',
    description: 'Official CodeRED Platform connector',
    defaults: { name: 'CodeRED' },
    inputs: ['main'],
    outputs: ['main'],
    credentials: [{ name: 'CodeREDApi', required: true }],
    properties: [
      { displayName: 'Resource', name: 'resource', type: 'options', default: 'connection', options: [
        { name: 'Connection', value: 'connection' },
        { name: 'Token Requests', value: 'tokenRequests' },
      ]},
      { displayName: 'Operation', name: 'operation', type: 'options', default: 'pairInstance', options: [
        { name: 'Pair Instance', value: 'pairInstance', description: 'Pair this n8n instance through CodeRED Agent' },
        { name: 'Test Connection', value: 'testConnection', description: 'Check CodeRED Agent health and pairing status' },
        { name: 'Reconnect', value: 'reconnect', description: 'Reconnect this instance with a fresh pair code' },
        { name: 'Get Agent Status', value: 'agentStatus', description: 'Read current CodeRED Agent status' },
        { name: 'Refresh Discovery', value: 'refreshDiscovery', description: 'Ask CodeRED Agent to publish discovery again' },
        { name: 'Rotate Secret', value: 'rotateSecret', description: 'Rotate the platform integration secret through CodeRED Agent' },
        { name: 'Disconnect', value: 'disconnect', description: 'Disconnect the current integration' },
      ], displayOptions: { show: { resource: ['connection'] } } },
      { displayName: 'Operation', name: 'operation', type: 'options', default: 'createTokenRequest', options: [
        { name: 'Create Token Request', value: 'createTokenRequest', description: 'Create a request for an API token approval' },
        { name: 'Get Token Request Status', value: 'getTokenRequestStatus', description: 'Read status and safe metadata for a token request' },
        { name: 'Retrieve Approved Token', value: 'retrieveApprovedToken', description: 'Retrieve an approved token once' },
        { name: 'Confirm Token Delivery', value: 'confirmTokenDelivery', description: 'Mark a retrieved token as delivered' },
        { name: 'Cancel Token Request', value: 'cancelTokenRequest', description: 'Cancel a pending token request' },
      ], displayOptions: { show: { resource: ['tokenRequests'] } } },
      { displayName: 'Pair Code', name: 'pairCode', type: 'string', typeOptions: { password: true }, default: '', required: true, displayOptions: { show: { operation: ['pairInstance', 'reconnect'] } } },
      { displayName: 'Requester Name', name: 'requesterName', type: 'string', default: '', required: true, displayOptions: { show: { operation: ['createTokenRequest'] } } },
      { displayName: 'Requester Email', name: 'requesterEmail', type: 'string', default: '', placeholder: 'person@example.com', displayOptions: { show: { operation: ['createTokenRequest'] } } },
      { displayName: 'Requester Phone', name: 'requesterPhone', type: 'string', default: '', displayOptions: { show: { operation: ['createTokenRequest'] } } },
      { displayName: 'Application Name', name: 'applicationName', type: 'string', default: '', required: true, displayOptions: { show: { operation: ['createTokenRequest'] } } },
      { displayName: 'Purpose', name: 'purpose', type: 'string', default: '', required: true, displayOptions: { show: { operation: ['createTokenRequest'] } } },
      { displayName: 'Requested Scopes', name: 'requestedScopes', type: 'string', default: 'agencies:read', required: true, description: 'Comma-separated permissions requested for the token', displayOptions: { show: { operation: ['createTokenRequest'] } } },
      { displayName: 'Expiration Days', name: 'expirationDays', type: 'number', default: 1, typeOptions: { minValue: 1 }, displayOptions: { show: { operation: ['createTokenRequest'] } } },
      { displayName: 'Source', name: 'source', type: 'string', default: 'n8n', displayOptions: { show: { operation: ['createTokenRequest'] } } },
      { displayName: 'Metadata', name: 'metadata', type: 'json', default: '{}', displayOptions: { show: { operation: ['createTokenRequest'] } } },
      { displayName: 'Request ID', name: 'requestId', type: 'string', default: '', required: true, displayOptions: { show: { operation: ['getTokenRequestStatus', 'retrieveApprovedToken', 'confirmTokenDelivery', 'cancelTokenRequest'] } } },
      { displayName: 'Delivery Channel', name: 'deliveryChannel', type: 'options', default: 'manual', options: [
        { name: 'Manual', value: 'manual' },
        { name: 'Telegram', value: 'telegram' },
        { name: 'WhatsApp', value: 'whatsapp' },
        { name: 'Email', value: 'email' },
      ], displayOptions: { show: { operation: ['confirmTokenDelivery'] } } },
      { displayName: 'Delivered To', name: 'deliveredTo', type: 'string', default: '', displayOptions: { show: { operation: ['confirmTokenDelivery'] } } },
      { displayName: 'Delivery Metadata', name: 'deliveryMetadata', type: 'json', default: '{}', displayOptions: { show: { operation: ['confirmTokenDelivery'] } } },
      { displayName: 'Cancellation Reason', name: 'cancellationReason', type: 'string', default: '', displayOptions: { show: { operation: ['cancelTokenRequest'] } } },
    ],
  };

  async execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]> {
    const credentials = await this.getCredentials('CodeREDApi') as CodeREDCredentials;
    const operation = this.getNodeParameter('operation', 0) as string;
    assertUrl(localAgentBaseUrl());
    assertUrl(String(credentials.baseUrl || ''));

    const out: INodeExecutionData[] = [];

    for (let i = 0; i < this.getInputData().length || i === 0; i++) {
      try {
        out.push({ json: sanitizeOutput(await runOperation.call(this, credentials, operation, i), { allowToken: operation === 'retrieveApprovedToken' }) });
      } catch (error) {
        if (isNodeOperationError(error)) {
          throw error;
        }

        throw new NodeOperationError(this.getNode(), buildNodeError(error, operation), { itemIndex: i });
      }

      if (this.getInputData().length === 0) break;
    }

    return [out];
  }
}

async function runOperation(this: IExecuteFunctions, c: CodeREDCredentials, op: string, i: number): Promise<unknown> {
  const connectionManager = new ConnectionManager(c);

  if (op === 'pairInstance') {
    const pairCode = pairCodeForOperation(this, c, i);
    const result = await connectionManager.connect({ pairCode });

    if (result.paired === false) {
      throw new Error('Pair Instance no dejó el agente emparejado.');
    }

    return result;
  }

  if (op === 'testConnection') return connectionManager.testConnection();
  if (op === 'reconnect') return connectionManager.reconnect({ pairCode: pairCodeForOperation(this, c, i) });
  if (op === 'agentStatus') return connectionManager.status();
  if (op === 'refreshDiscovery') return connectionManager.refreshDiscovery();
  if (op === 'rotateSecret') return connectionManager.rotateSecret();
  if (op === 'disconnect') return connectionManager.disconnect();
  if (op === 'createTokenRequest') return connectionManager.createTokenRequest(tokenRequestPayload(this, i));
  if (op === 'getTokenRequestStatus') return connectionManager.getTokenRequestStatus(requestId(this, i));
  if (op === 'retrieveApprovedToken') return connectionManager.retrieveApprovedToken(requestId(this, i));
  if (op === 'confirmTokenDelivery') return connectionManager.confirmTokenDelivery(requestId(this, i), deliveryPayload(this, i));
  if (op === 'cancelTokenRequest') return connectionManager.cancelTokenRequest(requestId(this, i), cancellationPayload(this, i));

  throw new Error('Operación no soportada por el asistente de conexión CodeRED.');
}

function tokenRequestPayload(ctx: IExecuteFunctions, itemIndex: number): Record<string, unknown> {
  const scopes = String(ctx.getNodeParameter('requestedScopes', itemIndex, '') || '')
    .split(',')
    .map((scope) => scope.trim())
    .filter(Boolean);

  return {
    requester_name: String(ctx.getNodeParameter('requesterName', itemIndex, '') || '').trim(),
    requester_email: optionalString(ctx.getNodeParameter('requesterEmail', itemIndex, '')),
    requester_phone: optionalString(ctx.getNodeParameter('requesterPhone', itemIndex, '')),
    application_name: String(ctx.getNodeParameter('applicationName', itemIndex, '') || '').trim(),
    purpose: String(ctx.getNodeParameter('purpose', itemIndex, '') || '').trim(),
    requested_scopes: scopes,
    expiration_days: Number(ctx.getNodeParameter('expirationDays', itemIndex, 1)),
    source: String(ctx.getNodeParameter('source', itemIndex, 'n8n') || 'n8n').trim() || 'n8n',
    metadata: jsonParameter(ctx.getNodeParameter('metadata', itemIndex, '{}')),
  };
}

function deliveryPayload(ctx: IExecuteFunctions, itemIndex: number): Record<string, unknown> {
  return {
    delivered: true,
    delivery_channel: String(ctx.getNodeParameter('deliveryChannel', itemIndex, 'manual') || 'manual'),
    delivered_to: optionalString(ctx.getNodeParameter('deliveredTo', itemIndex, '')),
    delivery_metadata: jsonParameter(ctx.getNodeParameter('deliveryMetadata', itemIndex, '{}')),
  };
}

function cancellationPayload(ctx: IExecuteFunctions, itemIndex: number): Record<string, unknown> {
  return { cancellation_reason: optionalString(ctx.getNodeParameter('cancellationReason', itemIndex, '')) };
}

function requestId(ctx: IExecuteFunctions, itemIndex: number): string {
  const value = String(ctx.getNodeParameter('requestId', itemIndex, '') || '').trim();

  if (!value) {
    throw new Error('Request ID es obligatorio.');
  }

  return value;
}

function optionalString(value: unknown): string | null {
  const normalized = String(value || '').trim();

  return normalized === '' ? null : normalized;
}

function jsonParameter(value: unknown): Record<string, unknown> {
  if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
    return value as Record<string, unknown>;
  }

  const text = String(value || '{}').trim() || '{}';
  const parsed = JSON.parse(text) as unknown;

  if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
    throw new Error('El campo JSON debe contener un objeto.');
  }

  return parsed as Record<string, unknown>;
}

function pairCodeForOperation(ctx: IExecuteFunctions, credentials: CodeREDCredentials, itemIndex: number): string {
  const pairCode = String(ctx.getNodeParameter('pairCode', itemIndex, '') || '').trim();

  if (pairCode) {
    return pairCode;
  }

  const legacyPairCode = String(credentials.pairCode || '').trim();

  if (legacyPairCode) {
    console.warn(JSON.stringify({ event: 'codered.pair_code.deprecated_credential', message: 'Pair Code debe configurarse como parámetro temporal de la operación.' }));
    return legacyPairCode;
  }

  throw new Error('Pair Code es obligatorio para esta operación.');
}

function isNodeOperationError(error: unknown): error is Error {
  return error instanceof NodeOperationError || (typeof error === 'object' && error !== null && (error as { name?: unknown }).name === 'NodeOperationError');
}

export function buildNodeError(error: unknown, operation: string): string {
  const baseUrl = localAgentBaseUrl();

  if (error instanceof LocalAgentHttpError) {
    const details = JSON.stringify(sanitizeOutput(error.responseBody));

    if (error.statusCode === 401) return 'CodeRED Agent rechazó el token local. Verifica CODERED_AGENT_LOCAL_API_TOKEN.';
    if (error.statusCode === 403) return 'CodeRED Platform rechazó la operación por autorización: ' + details + '.';
    if (error.statusCode === 404) return 'La solicitud o endpoint solicitado no existe en CodeRED. Verifica el Request ID y versiones compatibles.';
    if (error.statusCode === 409) return 'CodeRED no pudo completar la operación por el estado actual: ' + details + '.';
    if (error.statusCode === 410) return 'El código de pairing expiró o ya fue utilizado.';
    if (error.statusCode === 422) return 'CodeRED rechazó los datos de la operación: ' + details + '.';
    if (error.statusCode === 429) return 'CodeRED limitó temporalmente la operación: ' + details + '.';
    if (error.statusCode >= 500) return 'CodeRED Agent o Platform encontró un error interno durante ' + operation + '.';

    return 'CodeRED respondió HTTP ' + error.statusCode + ': ' + details + '.';
  }

  if (error instanceof Error) {
    if (error.name === 'LocalAgentTimeoutError') return error.message;
    if (/ECONNREFUSED|ENOTFOUND|EAI_AGAIN|fetch failed|no está disponible/i.test(error.message)) return 'CodeRED Agent no está disponible en ' + baseUrl + '.';
    if (/CODERED_AGENT_LOCAL_API_TOKEN/.test(error.message)) return 'CODERED_AGENT_LOCAL_API_TOKEN no está configurado en n8n.';
    return error.message.replace(/^NodeOperationError:\s*/i, '');
  }

  return 'Error desconocido en operación CodeRED ' + operation + '.';
}
