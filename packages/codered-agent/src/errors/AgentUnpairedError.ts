export class AgentUnpairedError extends Error {
  public constructor(message = 'Agent is unpaired') {
    super(message);
    this.name = 'AgentUnpairedError';
  }
}
