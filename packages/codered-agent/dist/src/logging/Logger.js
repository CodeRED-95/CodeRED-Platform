const SECRET_KEYS = ['secret', 'shared_secret', 'sharedSecret', 'authorization', 'x-codered-signature', 'pair_code', 'pairCode', 'token'];
export class Logger {
    level;
    constructor(level = 'info') {
        this.level = level;
    }
    info(event, data = {}) { this.write('info', event, data); }
    warn(event, data = {}) { this.write('warn', event, data); }
    error(event, data = {}) { this.write('error', event, data); }
    write(level, event, data) { console.log(JSON.stringify({ ts: new Date().toISOString(), level, event, ...sanitize(data) })); }
}
export function sanitize(value) { if (Array.isArray(value))
    return value.map(sanitize); if (value && typeof value === 'object') {
    const out = {};
    for (const [k, v] of Object.entries(value)) {
        out[k] = SECRET_KEYS.includes(k.toLowerCase()) ? '[redacted]' : sanitize(v);
    }
    return out;
} return value; }
