import type { IExecuteFunctions } from 'n8n-workflow';
import type { CodeREDCredentials } from './GenericFunctions';
export declare class ConnectionManager {
    private ctx;
    private credentials;
    constructor(ctx: IExecuteFunctions, credentials: CodeREDCredentials);
    connect(input: { pairCode: string }): Promise<Record<string, unknown>>;
    disconnect(): Promise<Record<string, unknown>>;
    rotateSecret(): Promise<Record<string, unknown>>;
    status(): Promise<Record<string, unknown>>;
    private request;
}
