const { createClient } = require('redis');
const config = require('../config');
const logger = require('../utils/logger');
const ledgerUpdateHub = require('./ledgerUpdateHub');

class LedgerUpdateSubscriber {
  constructor() {
    this._client = null;
    this._subscribed = false;
  }

  async start() {
    if (!config.redisUrl) {
      logger.warn('Ledger update Redis subscriber skipped: REDIS_URL missing');
      return;
    }

    if (this._subscribed) {
      return;
    }

    this._client = createClient({ url: config.redisUrl });
    this._client.on('error', (error) => {
      logger.error('Ledger update Redis subscriber error', { error: error.message });
    });

    await this._client.connect();
    await this._client.subscribe('ledger_updates', (message) => {
      this._handleMessage(message);
    });

    this._subscribed = true;
    logger.info('Ledger update Redis subscriber connected');
  }

  async stop() {
    if (!this._client) {
      return;
    }

    await this._client.unsubscribe('ledger_updates');
    await this._client.disconnect();
    this._subscribed = false;
    logger.info('Ledger update Redis subscriber disconnected');
  }

  _handleMessage(message) {
    try {
      const data = JSON.parse(message);
      ledgerUpdateHub.publish({ event: 'ledger:update', data });
    } catch (error) {
      logger.error('Failed to handle ledger update message', { error: error.message, message });
    }
  }
}

module.exports = new LedgerUpdateSubscriber();
