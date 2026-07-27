import { loadConfig } from './config/Config.js';
import { Logger } from './logging/Logger.js';
import { EncryptedFileStorage } from './storage/EncryptedFileStorage.js';
import { CodeREDClient } from './protocol/CodeREDClient.js';
import { DiscoveryService } from './protocol/DiscoveryService.js';
import { HeartbeatService } from './protocol/HeartbeatService.js';
import { PairingService } from './protocol/PairingService.js';
import { ReconnectionService } from './protocol/ReconnectionService.js';
import { createRouter } from './server/routes.js';
import { LocalApiServer } from './server/LocalApiServer.js';
export async function createApp() { const config = loadConfig(); const logger = new Logger(config.logLevel); const storage = new EncryptedFileStorage(config.dataPath, config.encryptionKey); const client = new CodeREDClient(config, storage); const heartbeat = new HeartbeatService(config, storage, client); const discovery = new DiscoveryService(config, client); const pairing = new PairingService(config, storage, client, discovery, heartbeat); const reconnect = new ReconnectionService(storage, discovery, heartbeat); const server = new LocalApiServer(config, createRouter(config, storage, pairing, discovery, heartbeat, reconnect)); return { config, logger, storage, client, heartbeat, discovery, pairing, reconnect, server, async start() { server.start(); logger.info('agent.started', { port: config.port }); await reconnect.start(); setInterval(() => void heartbeat.send(), config.heartbeatSeconds * 1000); setInterval(() => void discovery.sync(), config.discoverySeconds * 1000); } }; }
