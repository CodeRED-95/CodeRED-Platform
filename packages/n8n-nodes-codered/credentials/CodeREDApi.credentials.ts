import type { ICredentialType, INodeProperties } from 'n8n-workflow';

export class CodeREDApi implements ICredentialType {
  name = 'CodeREDApi';
  displayName = 'CodeRED API';
  documentationUrl = 'https://docs.codered.local/integrations/n8n-connector';
  properties: INodeProperties[] = [
    { displayName: 'CodeRED Platform URL', name: 'baseUrl', type: 'string', default: '', placeholder: 'https://codered.example.com', required: true },
    { displayName: 'Instance Name', name: 'instanceName', type: 'string', default: 'n8n Production', required: true },
    { displayName: 'Public n8n URL', name: 'instanceUrl', type: 'string', default: '', placeholder: 'https://n8n.example.com', required: true },
    { displayName: 'Environment', name: 'environment', type: 'options', default: 'production', options: [
      { name: 'Production', value: 'production' }, { name: 'Development', value: 'development' }, { name: 'Testing', value: 'testing' }, { name: 'Lab', value: 'lab' }
    ]},
    { displayName: 'Pair Code', name: 'pairCode', type: 'string', default: '', placeholder: 'CRD-72FK91', typeOptions: { password: true }, description: 'Only used for the initial pairing. It is cleared after pairing.' },
    { displayName: 'Integration UUID', name: 'integrationUuid', type: 'hidden', default: '' },
    { displayName: 'Shared Secret', name: 'sharedSecret', type: 'hidden', typeOptions: { password: true }, default: '' },
    { displayName: 'Protocol Version', name: 'protocolVersion', type: 'hidden', default: '1.0' },
    { displayName: 'Connector Version', name: 'connectorVersion', type: 'hidden', default: '1.0.0' }
  ];
}
