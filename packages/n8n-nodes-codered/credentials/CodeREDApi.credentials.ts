import type { ICredentialType, INodeProperties } from 'n8n-workflow';

export class CodeREDApi implements ICredentialType {
  name = 'CodeREDApi';
  displayName = 'CodeRED API';
  documentationUrl = 'https://docs.codered.local/integrations/n8n-connector';
  properties: INodeProperties[] = [
    { displayName: 'CodeRED Platform URL', name: 'baseUrl', type: 'string', default: 'https://platform.codered.host', required: true },
    { displayName: 'Agent Base URL', name: 'agentBaseUrl', type: 'string', default: 'http://codered-agent:5680', required: false },
    { displayName: 'Local API Token', name: 'localApiToken', type: 'string', typeOptions: { password: true }, default: '', required: false },
    { displayName: 'Timeout (ms)', name: 'timeoutMs', type: 'number', default: 15000 },
    { displayName: 'Instance Name', name: 'instanceName', type: 'string', default: 'n8n Production', required: true },
    { displayName: 'Public n8n URL', name: 'instanceUrl', type: 'string', default: '', placeholder: 'https://n8n.example.com', required: true },
    { displayName: 'Environment', name: 'environment', type: 'options', default: 'production', options: [
      { name: 'Production', value: 'production' }, { name: 'Development', value: 'development' }, { name: 'Testing', value: 'testing' }, { name: 'Lab', value: 'lab' }
    ]},
    { displayName: 'Allow Unauthorized Certs', name: 'allowUnauthorizedCerts', type: 'boolean', default: false }
  ];
}
