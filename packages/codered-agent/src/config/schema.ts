import { AgentError } from '../errors/AgentError.js';

export interface Config {
  name: string;
  platformUrl: string;
  publicUrl: string;
  environment: string;
  port: number;
  dataPath: string;
  encryptionKey: string;
  localApiToken: string;
  heartbeatSeconds: number;
  discoverySeconds: number;
  requestTimeoutMs: number;
  logLevel: string;
}

function required(env: NodeJS.ProcessEnv, key: string): string {
  const value = env[key];

  if (!value) {
    throw new AgentError(`Missing required configuration: ${key}`, 'CONFIG_MISSING');
  }

  return value;
}

function hasStrongKeyMaterial(value: string): boolean {
  if (/^[0-9a-f]{64}$/i.test(value)) {
    return true;
  }

  try {
    return Buffer.from(value, 'base64').length >= 32;
  } catch {
    return false;
  }
}

function positiveInteger(value: string | undefined, fallback: number, key: string): number {
  const parsed = Number(value ?? fallback);

  if (!Number.isInteger(parsed) || parsed <= 0) {
    throw new AgentError(`${key} must be a positive integer.`, 'CONFIG_INVALID_NUMBER');
  }

  return parsed;
}

export function loadConfig(env = process.env): Config {
  const encryptionKey = required(env, 'CODERED_AGENT_ENCRYPTION_KEY');
  const localApiToken = required(env, 'CODERED_AGENT_LOCAL_API_TOKEN');

  if (!hasStrongKeyMaterial(encryptionKey)) {
    throw new AgentError('CODERED_AGENT_ENCRYPTION_KEY must contain at least 32 bytes of entropy.', 'CONFIG_WEAK_KEY');
  }

  if (!/^[0-9a-f]{64}$/i.test(localApiToken)) {
    throw new AgentError('CODERED_AGENT_LOCAL_API_TOKEN must be 64 hexadecimal characters.', 'CONFIG_WEAK_TOKEN');
  }

  if (encryptionKey === localApiToken) {
    throw new AgentError('CODERED_AGENT_ENCRYPTION_KEY and CODERED_AGENT_LOCAL_API_TOKEN must be different.', 'CONFIG_SECRET_REUSE');
  }

  return {
    name: env.CODERED_AGENT_NAME || 'CodeRED n8n Agent',
    platformUrl: required(env, 'CODERED_PLATFORM_URL').replace(/\/$/, ''),
    publicUrl: required(env, 'CODERED_AGENT_PUBLIC_URL').replace(/\/$/, ''),
    environment: env.CODERED_AGENT_ENVIRONMENT || 'production',
    port: positiveInteger(env.CODERED_AGENT_PORT, 5680, 'CODERED_AGENT_PORT'),
    dataPath: env.CODERED_AGENT_DATA_PATH || '/data',
    encryptionKey,
    localApiToken,
    heartbeatSeconds: positiveInteger(env.CODERED_AGENT_HEARTBEAT_SECONDS, 30, 'CODERED_AGENT_HEARTBEAT_SECONDS'),
    discoverySeconds: positiveInteger(env.CODERED_AGENT_DISCOVERY_SECONDS, 300, 'CODERED_AGENT_DISCOVERY_SECONDS'),
    requestTimeoutMs: positiveInteger(env.CODERED_AGENT_REQUEST_TIMEOUT_MS, 15_000, 'CODERED_AGENT_REQUEST_TIMEOUT_MS'),
    logLevel: env.CODERED_AGENT_LOG_LEVEL || 'info',
  };
}