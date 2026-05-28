const { createClient } = require('redis');
const config = require('../config');
const logger = require('../utils/logger');
const marketStreamHub = require('./marketStreamHub');

class MarketStreamSubscriber {
  constructor() {
    this._client = null;
    this._subscribed = false;
  }

  async start() {
    if (config.marketStream.driver !== 'redis') {
      logger.info('Market stream Redis subscriber disabled', {
        driver: config.marketStream.driver,
      });
      return;
    }

    if (!config.marketStream.redisUrl) {
      logger.warn('Market stream Redis subscriber skipped: REDIS_URL missing');
      return;
    }

    if (this._subscribed) {
      return;
    }

    this._client = createClient({ url: config.marketStream.redisUrl });

    this._client.on('error', (error) => {
      logger.error('Market stream Redis subscriber error', { error: error.message });
    });

    await this._client.connect();
    await this._client.subscribe(config.marketStream.channel, (message) => {
      this._handleMessage(message);
    });

    this._subscribed = true;
    logger.info('Market stream Redis subscriber connected', {
      channel: config.marketStream.channel,
    });
  }

  async stop() {
    if (!this._client) {
      return;
    }

    await this._client.quit();
    this._client = null;
    this._subscribed = false;
  }

  stats() {
    return {
      driver: config.marketStream.driver,
      channel: config.marketStream.channel,
      connected: this._subscribed,
    };
  }

  _handleMessage(message) {
    try {
      const event = JSON.parse(message);
      marketStreamHub.publish(event);
    } catch (error) {
      logger.warn('Invalid market stream Redis payload', {
        error: error.message,
      });
    }
  }
}

module.exports = new MarketStreamSubscriber();
