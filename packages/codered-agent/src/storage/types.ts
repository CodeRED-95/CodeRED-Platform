export interface AgentIdentity {
  instance_uuid: string;
  created_at: string;
  agent_name: string;
}

export interface StoredIntegration {
  instance_uuid: string;
  integration_uuid: string;
  shared_secret: string;
  protocol_version: string;
  paired_at: string;
  platform_url: string;
  agent_name: string;
  instance_name: string;
  instance_url: string;
  environment: string;
  secret_version: number;
  discovery_url?: string;
  heartbeat_url?: string;
  challenge_url?: string;
}
