const { Server } = require('socket.io');
const config = require('../config');
const logger = require('../utils/logger');

class WalletUpdateHub {
  constructor() {
    this._io = null;
  }

  attach(server) {
    if (this._io) {
      return this._io;
    }

    this._io = new Server(server, {
      path: config.walletSocketPath,
      cors: { origin: config.corsOrigins, methods: ['GET', 'POST'] },
    });

    this._io.on('connection', (socket) => {
      logger.info('Wallet/Futures WebSocket client connected', { id: socket.id });
      socket.on('subscribe:user', (payload = {}) => {
        const userId = Number(payload.user_id);
        if (!Number.isFinite(userId) || userId <= 0) return;
        socket.join(`user:${userId}`);
      });
      socket.on('disconnect', () => {
        logger.info('Wallet/Futures WebSocket client disconnected', { id: socket.id });
      });
    });

    return this._io;
  }

  publish(event) {
    if (!this._io) return;
    this._io.emit(event.event, event.data);
  }

  publishToUser(userId, event) {
    if (!this._io) return;
    this._io.to(`user:${Number(userId)}`).emit(event.event, event.data);
  }
}

module.exports = new WalletUpdateHub();
