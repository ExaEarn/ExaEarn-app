const { Server } = require('socket.io');
const config = require('../config');
const logger = require('../utils/logger');

class LedgerUpdateHub {
  constructor() {
    this._io = null;
  }

  attach(server) {
    if (this._io) {
      return this._io;
    }

    this._io = new Server(server, {
      path: config.ledgerSocketPath,
      cors: { origin: config.corsOrigins, methods: ['GET', 'POST'] },
    });

    this._io.on('connection', (socket) => {
      logger.info('Ledger WebSocket client connected', { id: socket.id });
      socket.on('disconnect', () => {
        logger.info('Ledger WebSocket client disconnected', { id: socket.id });
      });
    });

    logger.info('Ledger WebSocket hub attached');
    return this._io;
  }

  publish(event) {
    if (!this._io) {
      return;
    }
    this._io.emit(event.event, event.data);
  }
}

module.exports = new LedgerUpdateHub();
