const { createClient } = require('redis');
const axios = require('axios');
const config = require('../config');
const logger = require('../utils/logger');
const walletUpdateHub = require('./walletUpdateHub');

class FuturesUpdateSubscriber {
  constructor() {
    this._client = null;
    this._subscribed = false;
    this._http = axios.create({
      baseURL: config.laravelApiUrl,
      timeout: 5000,
      headers: {
        Accept: 'application/json',
      },
    });
  }

  async start() {
    if (!config.redisUrl) {
      logger.warn('Futures Redis subscriber skipped: REDIS_URL missing');
      return;
    }

    if (this._subscribed) return;

    this._client = createClient({ url: config.redisUrl });
    this._client.on('error', (error) => logger.error('Futures Redis subscriber error', { error: error.message }));

    await this._client.connect();
    await this._client.subscribe(config.futuresStream.channel, (message) => this._handleMessage(message));
    this._subscribed = true;
  }

  _handleMessage(message) {
    try {
      const payload = JSON.parse(message);
      const evt = payload?.event;
      const data = payload?.data || {};

      if (Number.isFinite(Number(data.user_id))) {
        walletUpdateHub.publishToUser(Number(data.user_id), {
          event: evt || 'futures:update',
          data,
        });
      }

      walletUpdateHub.publish({
        event: evt || 'futures:update',
        data,
      });

      this._processMarketTick(data).catch((error) => {
        logger.error('Failed to process futures market tick', { error: error.message });
      });
    } catch (error) {
      logger.error('Invalid futures update payload', { error: error.message });
    }
  }

  async _processMarketTick(data) {
    const symbol = String(data.symbol || '').toUpperCase();
    const markPrice = data.mark_price ?? data.price ?? data.last_price;

    if (!symbol || markPrice == null) {
      return;
    }

    await this._http.post('/futures/market/tick', {
      symbol,
      mark_price: String(markPrice),
    });
  }
}

module.exports = new FuturesUpdateSubscriber();
