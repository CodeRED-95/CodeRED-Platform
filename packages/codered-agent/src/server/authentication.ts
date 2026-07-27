import type { IncomingMessage, ServerResponse } from 'node:http';
import type { Config } from '../config/Config.js';

const PUBLIC_ENDPOINTS = new Set(['/v1/health', '/v1/challenge']);

export function requireAuth(req: IncomingMessage, res: ServerResponse, config: Config): boolean {
  const url = req.url?.split('?')[0] ?? '/';

  if (PUBLIC_ENDPOINTS.has(url)) {
    return true;
  }

  const authorization = req.headers.authorization || '';

  if (authorization !== `Bearer ${config.localApiToken}`) {
    res.writeHead(401, { 'content-type': 'application/json' });
    res.end(JSON.stringify({ success: false, message: 'Unauthorized' }));

    return false;
  }

  return true;
}