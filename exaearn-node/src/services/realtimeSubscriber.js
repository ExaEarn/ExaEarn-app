const { createClient } = require('redis');
const config = require('../config');
const logger = require('../utils/logger');
const realtimeHub = require('./realtimeHub');

class RealtimeSubscriber {
  constructor() {
    this._client = null;
    this._subscribed = false;
  }

  async start() {
    if (!config.redisUrl) {
      logger.warn('Realtime Redis subscriber skipped: REDIS_URL missing');
      return;
    }

    if (this._subscribed) {
      return;
    }

    this._client = createClient({ url: config.redisUrl });

    this._client.on('error', (error) => {
      logger.error('Realtime Redis subscriber error', { error: error.message });
    });

    await this._client.connect();

    await this._client.subscribe(config.priceChannel, (message) => {
      this._handlePriceMessage(message);
    });

    await this._client.subscribe(config.portfolioChannel, (message) => {
      this._handlePortfolioMessage(message);
    });

    this._subscribed = true;
    logger.info('Realtime Redis subscriber connected', {
      priceChannel: config.priceChannel,
      portfolioChannel: config.portfolioChannel,
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

  _handlePriceMessage(message) {
    try {
      const payload = JSON.parse(message);
      realtimeHub.publishPriceUpdate(payload.data ?? payload);
    } catch (error) {
      logger.warn('Invalid price update message received', {
        error: error.message,
        raw: message,
      });
    }
  }

  _handlePortfolioMessage(message) {
    try {
      const payload = JSON.parse(message);
      const userId = Number(payload.user_id ?? payload.userId);

      if (!Number.isFinite(userId) || userId <= 0) {
        logger.warn('Portfolio update message missing valid user_id', {
          raw: message,
        });
        return;
      }

      realtimeHub.publishPortfolioUpdate(userId, payload.data ?? payload);
    } catch (error) {
      logger.warn('Invalid portfolio update message received', {
        error: error.message,
        raw: message,
      });
    }
  }
}

module.exports = new RealtimeSubscriber();
