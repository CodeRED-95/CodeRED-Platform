import { AgentError } from '../errors/AgentError.js';
export interface Config { name:string; platformUrl:string; publicUrl:string; environment:string; port:number; dataPath:string; encryptionKey:string; localApiToken:string; heartbeatSeconds:number; discoverySeconds:number; requestTimeoutMs:number; logLevel:string; }
export function loadConfig(env=process.env): Config {
  const req=(k:string)=>{const v=env[k]; if(!v) throw new AgentError('Missing required configuration: '+k,'CONFIG_MISSING'); return v};
  const key=req('CODERED_AGENT_ENCRYPTION_KEY');
  if(Buffer.from(key,'base64').length<32 && key.length<32) throw new AgentError('CODERED_AGENT_ENCRYPTION_KEY must contain at least 32 bytes of entropy.','CONFIG_WEAK_KEY');
  return {name:env.CODERED_AGENT_NAME||'CodeRED n8n Agent',platformUrl:req('CODERED_PLATFORM_URL').replace(/\/$/,''),publicUrl:req('CODERED_AGENT_PUBLIC_URL').replace(/\/$/,''),environment:env.CODERED_AGENT_ENVIRONMENT||'production',port:Number(env.CODERED_AGENT_PORT||5680),dataPath:env.CODERED_AGENT_DATA_PATH||'/data',encryptionKey:key,localApiToken:req('CODERED_AGENT_LOCAL_API_TOKEN'),heartbeatSeconds:Number(env.CODERED_AGENT_HEARTBEAT_SECONDS||30),discoverySeconds:Number(env.CODERED_AGENT_DISCOVERY_SECONDS||300),requestTimeoutMs:Number(env.CODERED_AGENT_REQUEST_TIMEOUT_MS||15000),logLevel:env.CODERED_AGENT_LOG_LEVEL||'info'};
}
