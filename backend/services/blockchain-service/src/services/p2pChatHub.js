const { WebSocketServer } = require('ws');
const logger = require('../utils/logger');

class P2PChatHub {
  constructor() {
    this._wss = null;
    this._clients = new Set();
  }

  attach(server) {
    if (this._wss) {
      return this._wss;
    }

    this._wss = new WebSocketServer({ server, path: '/ws/p2p' });

    this._wss.on('connection', (socket) => {
      socket.rooms = new Set();
      this._clients.add(socket);

      socket.send(JSON.stringify({
        type: 'connected',
        channels: ['trade_room'],
      }));

      socket.on('message', (message) => {
        this._handleMessage(socket, message);
      });

      socket.on('close', () => {
        this._clients.delete(socket);
      });
    });

    logger.info('P2P WebSocket hub attached');
    return this._wss;
  }

  publish(tradeUuid, event, payload = {}) {
    const room = String(tradeUuid).toUpperCase();
    const serialized = JSON.stringify({
      type: event,
      trade_uuid: room,
      payload,
      timestamp: new Date().toISOString(),
    });

    for (const socket of this._clients) {
      if (socket.readyState !== 1) continue;
      if (socket.rooms.has(room)) {
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
      const tradeUuid = String(message.trade_uuid || '').toUpperCase();

      if (message.type === 'subscribe' && tradeUuid) {
        socket.rooms.add(tradeUuid);
        socket.send(JSON.stringify({ type: 'subscribed', trade_uuid: tradeUuid }));
        return;
      }

      if (message.type === 'unsubscribe' && tradeUuid) {
        socket.rooms.delete(tradeUuid);
        socket.send(JSON.stringify({ type: 'unsubscribed', trade_uuid: tradeUuid }));
      }
    } catch (error) {
      logger.debug('Invalid P2P websocket payload', { error: error.message });
      socket.send(JSON.stringify({ type: 'error', message: 'Invalid websocket payload' }));
    }
  }
}

module.exports = new P2PChatHub();
