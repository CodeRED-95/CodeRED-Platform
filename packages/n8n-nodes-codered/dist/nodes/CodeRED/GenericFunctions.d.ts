export interface CodeREDCredentials {
    baseUrl: string;
    integrationUuid?: string;
    sharedSecret?: string;
    instanceName?: string;
    instanceUrl?: string;
    environment?: string;
    protocolVersion?: string;
    connectorVersion?: string;
    pairCode?: string;
    connectionMode?: string;
    agentUrl?: string;
    agentLocalApiToken?: string;
}
export declare function stableJson(value: unknown): string;
export declare function sha256Hex(body: string): string;
export declare function canonicalPayload(method: string, requestPath: string, timestamp: string, nonce: string, body: string): string;
export declare function hmacSignature(secret: string, canonical: string): string;
export declare function signedHeaders(credentials: CodeREDCredentials, method: string, requestPath: string, body: string): Record<string, string>;
export declare function joinUrl(baseUrl: string, requestPath: string): string;
export declare function assertUrl(url: string): void;
