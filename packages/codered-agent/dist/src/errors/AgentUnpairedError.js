export class AgentUnpairedError extends Error {
    constructor(message = 'Agent is unpaired') {
        super(message);
        this.name = 'AgentUnpairedError';
    }
}
