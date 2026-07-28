import crypto from 'node:crypto';
import fsSync from 'node:fs';
import fs from 'node:fs/promises';
import path from 'node:path';
import type { AgentStorage } from './AgentStorage.js';
import type { AgentIdentity, StoredIntegration } from './types.js';

function keyBytes(key: string): Buffer {
  if (/^[0-9a-f]{64}$/i.test(key)) {
    return Buffer.from(key, 'hex');
  }

  const fromBase64 = Buffer.from(key, 'base64');

  return fromBase64.length >= 32 ? fromBase64.subarray(0, 32) : crypto.createHash('sha256').update(key).digest();
}

function isUuid(value: unknown): value is string {
  return typeof value === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value);
}

function isAgentIdentity(value: unknown): value is AgentIdentity {
  const record = value as Partial<AgentIdentity> | null;

  return !!record
    && isUuid(record.instance_uuid)
    && typeof record.created_at === 'string'
    && typeof record.agent_name === 'string';
}

function isStoredIntegration(value: unknown): value is StoredIntegration {
  const record = value as Partial<StoredIntegration> | null;

  return !!record
    && isUuid(record.instance_uuid)
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

function isLegacyIntegrationWithoutInstanceUuid(value: unknown): value is Omit<StoredIntegration, 'instance_uuid'> {
  const record = value as Partial<StoredIntegration> | null;

  return !!record
    && typeof record.instance_uuid !== 'string'
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

export class EncryptedFileStorage implements AgentStorage {
  private identityFile: string;
  private integrationFile: string;
  private legacyIntegrationFile: string;

  public constructor(private dir: string, private key: string) {
    this.identityFile = path.join(dir, 'agent-identity.json');
    this.integrationFile = path.join(dir, 'integration.json');
    this.legacyIntegrationFile = path.join(dir, 'integration.enc');
  }

  public async ensure(): Promise<void> {
    await fs.mkdir(this.dir, { recursive: true, mode: 0o700 });

    try {
      await fs.chmod(this.dir, 0o700);
    } catch {
      // Best effort on filesystems that do not support chmod.
    }
  }

  public async hasIntegration(): Promise<boolean> {
    for (const file of [this.integrationFile, path.join(this.dir, 'identity.json'), this.legacyIntegrationFile]) {
      try {
        await fs.access(file);
        return true;
      } catch {
        // Try the next known file name.
      }
    }

    return false;
  }

  public async readIdentity(): Promise<AgentIdentity | null> {
    try {
      const parsed = await this.readEncryptedFile(this.identityFile);

      return isAgentIdentity(parsed) ? parsed : null;
    } catch {
      return null;
    }
  }

  public async ensureIdentity(agentName: string): Promise<AgentIdentity> {
    const existing = await this.readIdentity();

    if (existing) {
      return existing;
    }

    const integration = await this.readIntegration();
    const identity: AgentIdentity = {
      instance_uuid: integration?.instance_uuid || crypto.randomUUID(),
      created_at: integration?.paired_at || new Date().toISOString(),
      agent_name: integration?.agent_name || agentName,
    };

    await this.writeEncryptedFile(this.identityFile, identity);

    return identity;
  }

  public async readIntegration(): Promise<StoredIntegration | null> {
    for (const file of [this.integrationFile, path.join(this.dir, 'identity.json'), this.legacyIntegrationFile]) {
      try {
        const parsed = await this.readEncryptedFile(file);

        if (isStoredIntegration(parsed)) {
          if (file !== this.integrationFile) {
            await this.saveIntegration(parsed);
          }

          return parsed;
        }

        if (isLegacyIntegrationWithoutInstanceUuid(parsed)) {
          let identity = await this.readIdentity();

          if (!identity) {
            identity = {
              instance_uuid: crypto.randomUUID(),
              created_at: parsed.paired_at || new Date().toISOString(),
              agent_name: parsed.agent_name,
            };
            await this.writeEncryptedFile(this.identityFile, identity);
          }

          const migrated: StoredIntegration = { ...parsed, instance_uuid: identity.instance_uuid };
          await this.saveIntegration(migrated);
          return migrated;
        }
      } catch {
        // Try the next known file name before reporting unpaired.
      }
    }

    return null;
  }

  private async readEncryptedFile(file: string): Promise<unknown> {
    const raw = JSON.parse(await fs.readFile(file, 'utf8')) as { iv: string; tag: string; data: string };
    const decipher = crypto.createDecipheriv('aes-256-gcm', keyBytes(this.key), Buffer.from(raw.iv, 'hex'));
    decipher.setAuthTag(Buffer.from(raw.tag, 'hex'));
    const decrypted = Buffer.concat([
      decipher.update(Buffer.from(raw.data, 'hex')),
      decipher.final(),
    ]).toString('utf8');

    return JSON.parse(decrypted) as unknown;
  }

  private async writeEncryptedFile(file: string, value: unknown): Promise<void> {
    await this.ensure();

    const iv = crypto.randomBytes(12);
    const cipher = crypto.createCipheriv('aes-256-gcm', keyBytes(this.key), iv);
    const data = Buffer.concat([cipher.update(JSON.stringify(value), 'utf8'), cipher.final()]);
    const payload = JSON.stringify({
      version: 1,
      iv: iv.toString('hex'),
      tag: cipher.getAuthTag().toString('hex'),
      data: data.toString('hex'),
      updatedAt: new Date().toISOString(),
    });
    const temporaryFile = path.join(this.dir, `.${path.basename(file)}.${process.pid}.${Date.now()}.tmp`);

    await fs.writeFile(temporaryFile, payload, { mode: 0o600 });
    await fs.chmod(temporaryFile, 0o600);

    const handle = await fs.open(temporaryFile, 'r');
    await handle.sync();
    await handle.close();

    await fs.rename(temporaryFile, file);
    await fs.chmod(file, 0o600);

    try {
      const dirFd = fsSync.openSync(this.dir, 'r');
      fsSync.fsyncSync(dirFd);
      fsSync.closeSync(dirFd);
    } catch {
      // Best effort directory fsync for platforms/filesystems that support it.
    }
  }

  public async saveIntegration(value: StoredIntegration): Promise<void> {
    await this.writeEncryptedFile(this.integrationFile, value);
    await this.writeEncryptedFile(this.identityFile, {
      instance_uuid: value.instance_uuid,
      created_at: value.paired_at,
      agent_name: value.agent_name,
    });
  }

  public async clearIntegration(): Promise<void> {
    try {
      await fs.unlink(this.integrationFile);
    } catch {
      // Already clear.
    }

    try {
      await fs.unlink(path.join(this.dir, 'identity.json'));
    } catch {
      // Legacy integration file may not exist.
    }
  }

  public async updateSecret(secret: string): Promise<void> {
    const current = await this.readIntegration();

    if (!current) {
      return;
    }

    current.shared_secret = secret;
    current.secret_version += 1;
    await this.saveIntegration(current);
  }

  public async getIntegrationUuid(): Promise<string | null> {
    return (await this.readIntegration())?.integration_uuid || null;
  }
}
