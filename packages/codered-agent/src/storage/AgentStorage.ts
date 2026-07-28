import type { AgentIdentity, StoredIntegration } from './types.js';

export interface AgentStorage {
  ensure(): Promise<void>;
  hasIntegration(): Promise<boolean>;
  readIdentity(): Promise<AgentIdentity | null>;
  ensureIdentity(agentName: string): Promise<AgentIdentity>;
  readIntegration(): Promise<StoredIntegration | null>;
  saveIntegration(value: StoredIntegration): Promise<void>;
  clearIntegration(): Promise<void>;
  updateSecret(secret: string): Promise<void>;
  getIntegrationUuid(): Promise<string | null>;
}
