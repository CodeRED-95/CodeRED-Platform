import { loadConfig } from './config/Config.js';
import { Logger } from './logging/Logger.js';
import { CodeREDClient } from './protocol/CodeREDClient.js';
import { DiscoveryService } from './protocol/DiscoveryService.js';
import { HeartbeatService } from './protocol/HeartbeatService.js';
import { PairingService } from './protocol/PairingService.js';
import { ReconnectionService } from './protocol/ReconnectionService.js';
import { ConnectionManager } from './protocol/ConnectionManager.js';
import { LocalApiServer } from './server/LocalApiServer.js';
import { createRouter } from './server/routes.js';
import { EncryptedFileStorage } from './storage/EncryptedFileStorage.js';
export async function createApp() {
    const config = loadConfig();
    const logger = new Logger(config.logLevel);
    const storage = new EncryptedFileStorage(config.dataPath, config.encryptionKey);
    await storage.ensure();
    const client = new CodeREDClient(config, storage);
    await client.restorePairing();
    const heartbeat = new HeartbeatService(config, client, logger);
    const discovery = new DiscoveryService(config, client, logger);
    const pairing = new PairingService(config, storage, client, discovery, heartbeat, logger);
    const reconnect = new ReconnectionService(storage, client, discovery, heartbeat, logger);
    const connectionManager = new ConnectionManager(config, storage, client, pairing, discovery, heartbeat, reconnect, logger);
    const server = new LocalApiServer(config, createRouter(config, storage, connectionManager, discovery, heartbeat, client));
    process.on('unhandledRejection', (reason) => {
        logger.error('agent.unhandled_rejection', { error: reason instanceof Error ? reason.message : String(reason) });
    });
    process.on('uncaughtException', (error) => {
        logger.error('agent.uncaught_exception', { error: error.message });
    });
    const stop = () => {
        logger.info('agent.stopping');
        connectionManager.stop();
        server.stop();
    };
    process.once('SIGTERM', stop);
    process.once('SIGINT', stop);
    return {
        config,
        logger,
        storage,
        client,
        heartbeat,
        discovery,
        pairing,
        reconnect,
        connectionManager,
        server,
        async start() {
            server.start();
            logger.info('agent.started', { port: config.port, paired: client.isPaired() });
            await connectionManager.start();
            setInterval(() => { void discovery.sync(); }, config.discoverySeconds * 1000);
        },
    };
}
