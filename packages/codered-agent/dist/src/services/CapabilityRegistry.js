import crypto from 'node:crypto';
export class CapabilityRegistry {
    capabilities(publicUrl) { return [{ service: 'agent.health', method: 'GET', url: publicUrl + '/v1/health', version: '1.0' }, { service: 'integration.challenge', method: 'POST', url: publicUrl + '/v1/challenge', version: '1.0' }]; }
    checksum(publicUrl) { return crypto.createHash('sha256').update(JSON.stringify(this.capabilities(publicUrl))).digest('hex'); }
}
