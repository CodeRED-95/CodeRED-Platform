export function requireAuth(req, res, config) { if (req.url === '/v1/health')
    return true; const h = req.headers.authorization || ''; if (h !== ('Bearer ' + config.localApiToken)) {
    res.writeHead(401, { 'content-type': 'application/json' });
    res.end(JSON.stringify({ success: false, message: 'Unauthorized' }));
    return false;
} return true; }
