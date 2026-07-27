import type { StoredIntegration } from './types.js';
export interface AgentStorage { hasIntegration(): Promise<boolean>; readIntegration(): Promise<StoredIntegration|null>; saveIntegration(value:StoredIntegration): Promise<void>; clearIntegration(): Promise<void>; updateSecret(secret:string): Promise<void>; getIntegrationUuid(): Promise<string|null>; }
