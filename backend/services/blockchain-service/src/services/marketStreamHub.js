const { WebSocketServer } = require('ws');
const logger = require('../utils/logger');

class MarketStreamHub {
  constructor() {
    this._wss = null;
    this._clients = new Set();
  }

  attach(server) {
    if (this._wss) {
      return this._wss;
    }

    this._wss = new WebSocketServer({ server, path: '/ws/markets' });

    this._wss.on('connection', (socket) => {
      socket.subscriptions = new Set();
      this._clients.add(socket);

      socket.send(JSON.stringify({
        type: 'connected',
        channels: ['trades', 'order_book', 'candle'],
      }));

      socket.on('message', (message) => {
        this._handleMessage(socket, message);
      });

      socket.on('close', () => {
        this._clients.delete(socket);
      });
    });

    logger.info('Market WebSocket hub attached');
    return this._wss;
  }

  publish(event) {
    const serialized = JSON.stringify(event);

    for (const socket of this._clients) {
      if (socket.readyState !== 1) continue;

      if (this._isSubscribed(socket, event)) {
        socket.send(serialized);
      }
    }
  }

  stats() {
    return {
      clients: this._clients.size,
    };
  }

  _handleMessage(socket, rawMessage) {
    try {
      const message = JSON.parse(String(rawMessage));

      if (message.type === 'subscribe' && message.channel && message.pair) {
        socket.subscriptions.add(`${message.channel}:${String(message.pair).toUpperCase()}`);
        socket.send(JSON.stringify({ type: 'subscribed', channel: message.channel, pair: String(message.pair).toUpperCase() }));
        return;
      }

      if (message.type === 'unsubscribe' && message.channel && message.pair) {
        socket.subscriptions.delete(`${message.channel}:${String(message.pair).toUpperCase()}`);
        socket.send(JSON.stringify({ type: 'unsubscribed', channel: message.channel, pair: String(message.pair).toUpperCase() }));
      }
    } catch (error) {
      logger.debug('Invalid market websocket payload', { error: error.message });
      socket.send(JSON.stringify({ type: 'error', message: 'Invalid websocket payload' }));
    }
  }

  _isSubscribed(socket, event) {
    if (!event?.type || !event?.pair) {
      return false;
    }

    return socket.subscriptions.has(`${event.type}:${String(event.pair).toUpperCase()}`);
  }
}

module.exports = new MarketStreamHub();
