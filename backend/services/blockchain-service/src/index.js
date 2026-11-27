// Load the service's local environment before deciding whether this is staging.
// The guard must see the same values that config.js and service modules use.
require('dotenv').config();

const stagingSafety = require('./stagingSafety');
stagingSafety.assertSafeStagingConfiguration();
const http = require('http');
const config = require('./config');
const app = require('./app');
const logger = require('./utils/logger');
const realtimeHub = require('./services/realtimeHub');
const realtimeSubscriber = require('./services/realtimeSubscriber');
const walletUpdateHub = require('./services/walletUpdateHub');
const ledgerUpdateHub = require('./services/ledgerUpdateHub');
const futuresUpdateSubscriber = require('./services/futuresUpdateSubscriber');
const ledgerUpdateSubscriber = require('./services/ledgerUpdateSubscriber');
const blockchainEventListener = require('./services/blockchainEventListener');
const DeveloperRealtimeHub = require('./services/developerRealtimeHub');
const DeveloperRealtimeSubscriber = require('./services/developerRealtimeSubscriber');

const server = http.createServer(app);
realtimeHub.attach(server);
walletUpdateHub.attach(server);
ledgerUpdateHub.attach(server);
const developerRealtimeHub = new DeveloperRealtimeHub();
developerRealtimeHub.attach(server);
const developerRealtimeSubscriber = new DeveloperRealtimeSubscriber(developerRealtimeHub);

async function startBackgroundServices() {
  if (stagingSafety.stagingWorkersDisabled()) {
    logger.info('Staging workers are disabled by safety policy');
    return false;
  }

  try {
    await realtimeSubscriber.start();
    await futuresUpdateSubscriber.start();
    await ledgerUpdateSubscriber.start();
    await developerRealtimeSubscriber.start();
    blockchainEventListener.start();
    return true;
  } catch (error) {
    logger.error('Failed to start background services', { error: error.message });
    return false;
  }
}

if (require.main === module) {
  server.listen(config.port, async () => {
    logger.info('Realtime node server started', { port: config.port });
    await startBackgroundServices();
  });
}

module.exports = { app, server, developerRealtimeHub, developerRealtimeSubscriber, startBackgroundServices };
