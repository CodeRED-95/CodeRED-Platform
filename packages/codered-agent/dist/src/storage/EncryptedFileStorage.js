import crypto from 'node:crypto';
import fsSync from 'node:fs';
import fs from 'node:fs/promises';
import path from 'node:path';
function keyBytes(key) {
    if (/^[0-9a-f]{64}$/i.test(key)) {
        return Buffer.from(key, 'hex');
    }
    const fromBase64 = Buffer.from(key, 'base64');
    return fromBase64.length >= 32 ? fromBase64.subarray(0, 32) : crypto.createHash('sha256').update(key).digest();
}
function isStoredIntegration(value) {
    const record = value;
    return !!record
        && typeof record.instance_uuid === 'string'
        && typeof record.integration_uuid === 'string'
        && typeof record.shared_secret === 'string'
        && typeof record.protocol_version === 'string'
        && typeof record.paired_at === 'string'
        && typeof record.platform_url === 'string'
        && typeof record.agent_name === 'string'
        && typeof record.instance_name === 'string'
        && typeof record.instance_url === 'string'
        && typeof record.environment === 'string'
        && typeof record.secret_version === 'number';
}
export class EncryptedFileStorage {
    dir;
    key;
    file;
    constructor(dir, key) {
        this.dir = dir;
        this.key = key;
        this.file = path.join(dir, 'identity.json');
    }
    async ensure() {
        await fs.mkdir(this.dir, { recursive: true, mode: 0o700 });
        try {
            await fs.chmod(this.dir, 0o700);
        }
        catch {
            // Best effort on filesystems that do not support chmod.
        }
    }
    async hasIntegration() {
        try {
            await fs.access(this.file);
            return true;
        }
        catch {
            return false;
        }
    }
    async readIntegration() {
        try {
            const parsed = await this.readEncryptedFile(this.file);
            if (isStoredIntegration(parsed)) {
                return parsed;
            }
        }
        catch {
        }
        try {
            const legacy = path.join(this.dir, 'integration.enc');
            const parsed = await this.readEncryptedFile(legacy);
            return isStoredIntegration(parsed) ? parsed : null;
        }
        catch {
            return null;
        }
    }
    async readEncryptedFile(file) {
        const raw = JSON.parse(await fs.readFile(file, 'utf8'));
        const decipher = crypto.createDecipheriv('aes-256-gcm', keyBytes(this.key), Buffer.from(raw.iv, 'hex'));
        decipher.setAuthTag(Buffer.from(raw.tag, 'hex'));
        const decrypted = Buffer.concat([decipher.update(Buffer.from(raw.data, 'hex')), decipher.final()]).toString('utf8');
        return JSON.parse(decrypted);
    }
    async saveIntegration(value) {
        await this.ensure();
        const iv = crypto.randomBytes(12);
        const cipher = crypto.createCipheriv('aes-256-gcm', keyBytes(this.key), iv);
        const data = Buffer.concat([cipher.update(JSON.stringify(value), 'utf8'), cipher.final()]);
        const payload = JSON.stringify({
            version: 1,
            paired: true,
            iv: iv.toString('hex'),
            tag: cipher.getAuthTag().toString('hex'),
            data: data.toString('hex'),
            updatedAt: new Date().toISOString(),
        });
        const temporaryFile = path.join(this.dir, `.identity.json.${process.pid}.${Date.now()}.tmp`);
        await fs.writeFile(temporaryFile, payload, { mode: 0o600 });
        await fs.chmod(temporaryFile, 0o600);
        const handle = await fs.open(temporaryFile, 'r');
        await handle.sync();
        await handle.close();
        await fs.rename(temporaryFile, this.file);
        await fs.chmod(this.file, 0o600);
        try {
            const dirFd = fsSync.openSync(this.dir, 'r');
            fsSync.fsyncSync(dirFd);
            fsSync.closeSync(dirFd);
        }
        catch {
            // Best effort directory fsync for platforms/filesystems that support it.
        }
    }
    async clearIntegration() {
        try {
            await fs.unlink(this.file);
        }
        catch {
            // Already clear.
        }
    }
    async updateSecret(secret) {
        const current = await this.readIntegration();
        if (!current) {
            return;
        }
        current.shared_secret = secret;
        current.secret_version += 1;
        await this.saveIntegration(current);
    }
    async getIntegrationUuid() {
        return (await this.readIntegration())?.integration_uuid || null;
    }
}
