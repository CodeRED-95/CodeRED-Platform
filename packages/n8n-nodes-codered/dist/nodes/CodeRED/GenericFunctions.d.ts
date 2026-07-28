export interface CodeREDCredentials {
    agentBaseUrl?: string;
    localApiToken?: string;
    timeoutMs?: number | string;
    allowUnauthorizedCerts?: boolean;
    instanceName?: string;
    publicUrl?: string;
    environment?: string;
}
export declare function stableJson(value: unknown): string;
export declare function sha256Hex(body: string): string;
export declare function canonicalPayload(method: string, requestPath: string, timestamp: string, nonce: string, body: string): string;
export declare function hmacSignature(secret: string, canonical: string): string;
export declare function joinUrl(baseUrl: string, requestPath: string): string;
export declare function assertUrl(url: string): void;
