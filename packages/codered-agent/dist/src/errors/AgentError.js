export class AgentError extends Error {
    code;
    constructor(message, code = 'AGENT_ERROR') {
        super(message);
        this.code = code;
    }
}
