import type { IExecuteFunctions, INodeExecutionData, INodeType, INodeTypeDescription } from 'n8n-workflow';
import { NodeOperationError } from 'n8n-workflow';
import { assertUrl, type CodeREDCredentials } from './GenericFunctions';
import { ConnectionManager } from './ConnectionManager';
import { LocalAgentHttpError, localAgentBaseUrl, sanitizeOutput } from './LocalAgentClient';

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
      { displayName: 'Operation', name: 'operation', type: 'options', default: 'pairInstance', options: [
        { name: 'Pair Instance', value: 'pairInstance' },
        { name: 'Test Connection', value: 'testConnection' },
        { name: 'Reconnect', value: 'reconnect' },
        { name: 'Get Agent Status', value: 'agentStatus' },
        { name: 'Refresh Discovery', value: 'refreshDiscovery' },
        { name: 'Rotate Secret', value: 'rotateSecret' },
        { name: 'Disconnect', value: 'disconnect' },
      ]},
      { displayName: 'Pair Code', name: 'pairCode', type: 'string', typeOptions: { password: true }, default: '', required: true, displayOptions: { show: { operation: ['pairInstance', 'reconnect'] } } },
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
        out.push({ json: sanitizeOutput(await runOperation.call(this, credentials, operation, i)) });
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

  throw new Error('Operación no soportada por el asistente de conexión CodeRED.');
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
    if (error.statusCode === 404) return 'El endpoint solicitado no existe en CodeRED Agent. Verifica que agente y nodo estén en versiones compatibles.';
    if (error.statusCode === 409) return 'La instancia ya está emparejada. Usa Reconnect si deseas restablecer la conexión.';
    if (error.statusCode === 410) return 'El código de pairing expiró o ya fue utilizado.';
    if (error.statusCode === 422) return 'CodeRED Agent rechazó los datos del pairing: ' + details + '.';
    if (error.statusCode >= 500) return 'CodeRED Agent encontró un error interno durante el pairing.';

    return 'CodeRED Agent respondió HTTP ' + error.statusCode + ': ' + details + '.';
  }

  if (error instanceof Error) {
    if (error.name === 'LocalAgentTimeoutError') return error.message;
    if (/ECONNREFUSED|ENOTFOUND|EAI_AGAIN|fetch failed|no está disponible/i.test(error.message)) return 'CodeRED Agent no está disponible en ' + baseUrl + '.';
    if (/CODERED_AGENT_LOCAL_API_TOKEN/.test(error.message)) return 'CODERED_AGENT_LOCAL_API_TOKEN no está configurado en n8n.';
    return error.message.replace(/^NodeOperationError:\s*/i, '');
  }

  return 'Error desconocido en operación CodeRED ' + operation + '.';
}
