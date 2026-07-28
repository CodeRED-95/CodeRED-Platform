export interface LocalPairRequest {
  pair_code: string;
  instance_name: string;
  instance_url: string;
  environment: string;
  version?: string;
}

export interface PlatformPairRequest extends LocalPairRequest {
  instance_uuid: string;
  agent_version?: string;
}
