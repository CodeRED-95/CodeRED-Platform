export class AgentError extends Error { constructor(message: string, public code = 'AGENT_ERROR') { super(message); } }
