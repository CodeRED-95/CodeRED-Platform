import type { IExecuteFunctions, INodeExecutionData, INodeType, INodeTypeDescription } from 'n8n-workflow';
import { NodeOperationError } from 'n8n-workflow';
import { assertUrl, type CodeREDCredentials } from './GenericFunctions';
import { ConnectionManager } from './ConnectionManager';


export class CodeRED implements INodeType {
  description: INodeTypeDescription = {
    displayName: 'CodeRED', name: 'codeRed', icon: 'file:codered.svg', group: ['output'], version: 1, subtitle: '={{$parameter["operation"]}}', description: 'Official CodeRED Platform connector', defaults: { name: 'CodeRED' }, inputs: ['main'], outputs: ['main'], credentials: [{ name: 'CodeREDApi', required: false }], properties: [
      { displayName: 'Operation', name: 'operation', type: 'options', default: 'pairInstance', options: [
        { name: 'Pair Instance', value: 'pairInstance' }, { name: 'Get Connection Status', value: 'agentStatus' }, { name: 'Rotate Secret', value: 'rotateSecret' }, { name: 'Disconnect', value: 'disconnect' }
      ]},
      { displayName: 'Pair Code', name: 'pairCode', type: 'string', default: '', required: true, displayOptions: { show: { operation: ['pairInstance'] } } },
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

async function runOperation(this: IExecuteFunctions, c: CodeREDCredentials, op: string, i: number): Promise<any> {
  const connectionManager = new ConnectionManager(this, c);

  if (op === 'pairInstance') {
    const result = await connectionManager.connect({ pairCode: this.getNodeParameter('pairCode', i) as string });

    if (!result.paired) {
      throw new Error('Pair Instance no dejó el agente emparejado.');
    }

    if (!result.challengeCompleted) {
      throw new Error('Pair Instance cancelado: Challenge falló.');
    }

    if (!result.discoveryCompleted || !result.heartbeatCompleted) {
      throw new Error('Pair Instance incompleto: el agente quedó emparejado, pero Discovery o Heartbeat no terminaron correctamente.');
    }

    return result;
  }

  if (op === 'agentStatus') return connectionManager.status();
  if (op === 'rotateSecret') return connectionManager.rotateSecret();
  if (op === 'disconnect') return connectionManager.disconnect();

  throw new Error('Operación no soportada por el asistente de conexión CodeRED.');
}

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
