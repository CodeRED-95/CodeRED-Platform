import { loadConfig } from './config/Config.js';
import { Logger } from './logging/Logger.js';
import { CodeREDClient } from './protocol/CodeREDClient.js';
import { DiscoveryService } from './protocol/DiscoveryService.js';
import { HeartbeatService } from './protocol/HeartbeatService.js';
import { PairingService } from './protocol/PairingService.js';
import { ReconnectionService } from './protocol/ReconnectionService.js';
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
    const server = new LocalApiServer(config, createRouter(config, storage, pairing, discovery, heartbeat, reconnect, client));
    async function runHeartbeatSafely() {
        try {
            await heartbeat.send();
        }
        catch (error) {
            logger.error('heartbeat.failed', { error: error instanceof Error ? error.message : 'Unknown heartbeat error' });
        }
    }
    async function runDiscoverySafely() {
        try {
            await discovery.sync();
        }
        catch (error) {
            logger.error('discovery.failed', { error: error instanceof Error ? error.message : 'Unknown discovery error' });
        }
    }
    process.on('unhandledRejection', (reason) => {
        logger.error('agent.unhandled_rejection', { error: reason instanceof Error ? reason.message : String(reason) });
    });
    process.on('uncaughtException', (error) => {
        logger.error('agent.uncaught_exception', { error: error.message });
    });
    const stop = () => {
        logger.info('agent.stopping');
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
        server,
        async start() {
            server.start();
            logger.info('agent.started', { port: config.port, paired: client.isPaired() });
            await reconnect.start();
            setInterval(() => { void runHeartbeatSafely(); }, config.heartbeatSeconds * 1000);
            setInterval(() => { void runDiscoverySafely(); }, config.discoverySeconds * 1000);
        },
    };
}
