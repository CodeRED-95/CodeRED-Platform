const PUBLIC_ENDPOINTS = new Set(['/healthz', '/readyz', '/v1/health', '/v1/challenge']);
export function requireAuth(req, res, config) {
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
