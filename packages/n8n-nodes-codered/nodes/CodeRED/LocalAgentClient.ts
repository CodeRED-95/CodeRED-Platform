const DEFAULT_AGENT_URL = 'http://codered-agent:5680';
const SECRET_KEY_PATTERN = /secret|token|api_key|apiKey|authorization|pair_code|pairCode|signature/i;

export interface LocalAgentRequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
  timeoutMs?: number;
  operation?: string;
}

export class LocalAgentHttpError extends Error {
  public constructor(public statusCode: number, public responseBody: unknown) {
    super('CodeRED Agent responded HTTP ' + statusCode);
    this.name = 'LocalAgentHttpError';
  }
}

export function localAgentBaseUrl(): string {
  return process.env.CODERED_AGENT_LOCAL_URL?.trim() || DEFAULT_AGENT_URL;
}

function localAgentToken(): string {
  const token = process.env.CODERED_AGENT_LOCAL_API_TOKEN?.trim();

  if (!token) {
    throw new Error('CODERED_AGENT_LOCAL_API_TOKEN no está configurado en n8n.');
  }

  return token;
}

function ensureTrailingSlash(value: string): string {
  return value.endsWith('/') ? value : value + '/';
}

export function sanitizeOutput(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.map((item) => sanitizeOutput(item));
  }

  if (!value || typeof value !== 'object') {
    return value;
  }

  const output: Record<string, unknown> = {};

  for (const [key, item] of Object.entries(value as Record<string, unknown>)) {
    output[key] = SECRET_KEY_PATTERN.test(key) ? '[redacted]' : sanitizeOutput(item);
  }

  return output;
}

export async function callLocalAgent<T>(path: string, options: LocalAgentRequestOptions = {}): Promise<T> {
  const baseUrl = localAgentBaseUrl();
  const token = localAgentToken();
  const controller = new AbortController();
  const timeoutMs = options.timeoutMs ?? 15000;
  const timeout = setTimeout(() => controller.abort(), timeoutMs);
  const url = new URL(path.replace(/^//, ''), ensureTrailingSlash(baseUrl));

  console.info(JSON.stringify({
    event: 'codered.agent.request',
    operation: options.operation || path,
    agent_url: baseUrl,
    pair_code_present: typeof options.body === 'object' && !!options.body && 'pair_code' in options.body,
    instance_name: typeof options.body === 'object' && !!options.body ? (options.body as Record<string, unknown>).instance_name : undefined,
  }));

  try {
    const response = await fetch(url, {
      method: options.method ?? 'GET',
      headers: {
        Authorization: 'Bearer ' + token,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
      signal: controller.signal,
    });
    const raw = await response.text();
    let parsed: unknown = null;

    if (raw !== '') {
      try {
        parsed = JSON.parse(raw) as unknown;
      } catch {
        parsed = raw;
      }
    }

    if (!response.ok) {
      throw new LocalAgentHttpError(response.status, sanitizeOutput(parsed));
    }

    return sanitizeOutput(parsed) as T;
  } catch (error) {
    if (error instanceof LocalAgentHttpError) {
      throw error;
    }

    if (error instanceof Error && error.name === 'AbortError') {
      const timeoutError = new Error('La solicitud a CodeRED Agent superó el tiempo límite de ' + Math.round(timeoutMs / 1000) + ' segundos.');
      timeoutError.name = 'LocalAgentTimeoutError';
      throw timeoutError;
    }

    const unavailable = new Error('CodeRED Agent no está disponible en ' + baseUrl + '.');
    (unavailable as Error & { cause?: unknown }).cause = error;
    throw unavailable;
  } finally {
    clearTimeout(timeout);
  }
}
