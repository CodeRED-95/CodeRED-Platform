import http from 'node:http';
import { requireAuth } from './authentication.js';
export class LocalApiServer {
    config;
    router;
    server;
    constructor(config, router) {
        this.config = config;
        this.router = router;
    }
    start() { this.server = http.createServer((req, res) => { if (!requireAuth(req, res, this.config))
        return; void this.router(req, res); }); this.server.listen(this.config.port, '0.0.0.0'); }
    stop() { this.server?.close(); }
}
