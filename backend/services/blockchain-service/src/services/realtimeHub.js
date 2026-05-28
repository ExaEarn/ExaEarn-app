const { Server } = require('socket.io');
const config = require('../config');
const logger = require('../utils/logger');

class RealtimeHub {
  constructor() {
    this._io = null;
  }

  attach(server) {
    if (this._io) {
      return this._io;
    }

    this._io = new Server(server, {
      path: config.socketPath,
      cors: {
        origin: config.corsOrigins,
        methods: ['GET', 'POST'],
        credentials: true,
      },
    });

    this._io.on('connection', (socket) => {
      logger.info('Realtime client connected', { id: socket.id });

      socket.on('subscribe:user', (payload) => {
        const userId = Number(payload?.user_id ?? payload?.userId ?? payload?.user);

        if (!Number.isFinite(userId) || userId <= 0) {
          socket.emit('error', { message: 'Invalid user_id for subscription' });
          return;
        }

        const room = `user:${userId}`;
        socket.join(room);
        socket.emit('subscribed', { channel: 'portfolio', room });
      });

      socket.on('disconnect', () => {
        logger.info('Realtime client disconnected', { id: socket.id });
      });
    });

    logger.info('Realtime Socket.IO hub attached', {
      path: config.socketPath,
    });

    return this._io;
  }

  publishPriceUpdate(payload) {
    if (!this._io) {
      return;
    }

    this._io.emit('price:update', payload);
  }

  publishPortfolioUpdate(userId, payload) {
    if (!this._io) {
      return;
    }

    this._io.to(`user:${userId}`).emit('portfolio:update', payload);
  }
}

module.exports = new RealtimeHub();
