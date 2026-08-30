const { createClient } = require('redis');
const config = require('../config');
const logger = require('../utils/logger');

class DeveloperRealtimeSubscriber {
  constructor(hub) { this.hub = hub; this.client = null; }
  async start() {
    if (!config.redisUrl) return;
    this.client = createClient({ url: config.redisUrl });
    this.client.on('error', (error) => logger.error('Developer realtime Redis error', { error: error.message }));
    await this.client.connect();
    await this.client.subscribe(config.developerRealtime.redisChannel, (message) => {
      try { this.hub.publish(JSON.parse(message)); } catch { logger.warn('Invalid Developer realtime event'); }
    });
  }
  async stop() { if(this.client) await this.client.quit(); }
}
module.exports = DeveloperRealtimeSubscriber;
