const { createClient } = require('redis');
const config = require('../config');
const logger = require('../utils/logger');
const walletUpdateHub = require('./walletUpdateHub');

class ExaPointUpdateSubscriber {
  constructor() {
    this._client = null;
    this._subscribed = false;
  }

  async start() {
    if (!config.redisUrl) {
      logger.warn('ExaPoint Redis subscriber skipped: REDIS_URL missing');
      return;
    }

    if (this._subscribed) {
      return;
    }

    this._client = createClient({ url: config.redisUrl });
    this._client.on('error', (error) => {
      logger.error('ExaPoint Redis subscriber error', { error: error.message });
    });

    await this._client.connect();
    await this._client.subscribe(config.exapointStream.channel, (message) => {
      this._handleMessage(message);
    });

    this._subscribed = true;
    logger.info('ExaPoint Redis subscriber connected', { channel: config.exapointStream.channel });
  }

  async stop() {
    if (!this._client) {
      return;
    }

    await this._client.unsubscribe(config.exapointStream.channel);
    await this._client.disconnect();
    this._subscribed = false;
    logger.info('ExaPoint Redis subscriber disconnected');
  }

  _handleMessage(message) {
    try {
      const data = JSON.parse(message);
      walletUpdateHub.publishToUser(Number(data.user_id), {
        event: 'exapoint:update',
        data: {
          total_points: data.total_points,
          available_points: data.available_points,
          locked_points: data.locked_points,
        },
      });
    } catch (error) {
      logger.error('Failed to handle ExaPoint update message', { error: error.message, message });
    }
  }
}

module.exports = new ExaPointUpdateSubscriber();

