import type { ICredentialType, INodeProperties } from 'n8n-workflow';

export class CodeREDApi implements ICredentialType {
  name = 'CodeREDApi';
  displayName = 'CodeRED API';
  documentationUrl = 'https://docs.codered.local/integrations/n8n-connector';
  properties: INodeProperties[] = [
    { displayName: 'CodeRED Platform URL', name: 'baseUrl', type: 'string', default: 'https://platform.codered.lat/', required: true },
    { displayName: 'Instance Name', name: 'instanceName', type: 'string', default: 'n8n Production', required: true },
    { displayName: 'Public n8n URL', name: 'instanceUrl', type: 'string', default: '', placeholder: 'https://n8n.example.com/', required: true },
    { displayName: 'Environment', name: 'environment', type: 'options', default: 'production', options: [
      { name: 'Production', value: 'production' },
      { name: 'Staging', value: 'staging' },
      { name: 'Development', value: 'development' },
    ]},
  ];
}
