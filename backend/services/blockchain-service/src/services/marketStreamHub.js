const { WebSocketServer, WebSocket } = require('ws');
const logger = require('../utils/logger');

class MarketStreamHub {
  constructor() {
    this._wss = null;
    this._clients = new Set();
    this._heartbeat = null;
    this._maxSubscriptions = Number(process.env.MARKET_WS_MAX_SUBSCRIPTIONS || 50);
  }

  attach(server) {
    if (this._wss) {
      return this._wss;
    }

    this._wss = new WebSocketServer({ server, path: '/ws/markets' });

    this._wss.on('connection', (socket) => {
      socket.subscriptions = new Set();
      socket.isAlive = true;
      this._clients.add(socket);

      socket.send(JSON.stringify({
        op: 'connected',
        topics: ['market.{symbol}.ticker', 'market.{symbol}.book', 'market.{symbol}.trade', 'market.{symbol}.kline.{interval}'],
        heartbeat: 'ping/pong',
      }));

      socket.on('pong', () => {
        socket.isAlive = true;
      });

      socket.on('message', (message) => {
        this._handleMessage(socket, message);
      });

      socket.on('close', () => {
        this._clients.delete(socket);
      });
    });

    this._heartbeat = setInterval(() => {
      for (const socket of this._clients) {
        if (socket.isAlive === false) {
          socket.terminate();
          this._clients.delete(socket);
          continue;
        }

        socket.isAlive = false;
        socket.ping();
      }
    }, Number(process.env.MARKET_WS_HEARTBEAT_MS || 30000));

    logger.info('Market WebSocket hub attached');
    return this._wss;
  }

  publish(event) {
    const normalized = this._normalizePublishedEvent(event);
    const serialized = JSON.stringify(normalized);

    for (const socket of this._clients) {
      if (socket.readyState !== WebSocket.OPEN) continue;

      if (this._isSubscribed(socket, normalized)) {
        socket.send(serialized);
      }
    }
  }

  stats() {
    return {
      clients: this._clients.size,
      subscriptions: Array.from(this._clients).reduce((count, socket) => count + socket.subscriptions.size, 0),
      max_subscriptions_per_client: this._maxSubscriptions,
    };
  }

  _handleMessage(socket, rawMessage) {
    try {
      const message = JSON.parse(String(rawMessage));

      if (message.op === 'ping' || message.type === 'ping') {
        socket.send(JSON.stringify({ op: 'pong', timestamp: new Date().toISOString() }));
        return;
      }

      if (message.op === 'subscribe' && Array.isArray(message.topics)) {
        const accepted = [];
        for (const topic of message.topics) {
          const normalized = this._normalizeTopic(topic);
          if (!normalized) {
            socket.send(JSON.stringify({ op: 'error', topic, message: 'Invalid market topic' }));
            continue;
          }

          if (socket.subscriptions.size >= this._maxSubscriptions) {
            socket.send(JSON.stringify({ op: 'error', message: 'Market subscription limit exceeded' }));
            break;
          }

          socket.subscriptions.add(normalized);
          accepted.push(normalized);
        }
        socket.send(JSON.stringify({ op: 'subscribed', topics: accepted }));
        return;
      }

      if (message.op === 'unsubscribe' && Array.isArray(message.topics)) {
        const removed = [];
        for (const topic of message.topics) {
          const normalized = this._normalizeTopic(topic);
          if (!normalized) continue;
          socket.subscriptions.delete(normalized);
          removed.push(normalized);
        }
        socket.send(JSON.stringify({ op: 'unsubscribed', topics: removed }));
        return;
      }

      if (message.type === 'subscribe' && message.channel && message.pair) {
        const topic = this._legacyTopic(message.channel, message.pair, message.timeframe);
        if (!topic) {
          socket.send(JSON.stringify({ type: 'error', message: 'Invalid market subscription' }));
          return;
        }
        socket.subscriptions.add(topic);
        socket.send(JSON.stringify({ type: 'subscribed', channel: message.channel, pair: String(message.pair).toUpperCase(), topic }));
        return;
      }

      if (message.type === 'unsubscribe' && message.channel && message.pair) {
        const topic = this._legacyTopic(message.channel, message.pair, message.timeframe);
        if (topic) socket.subscriptions.delete(topic);
        socket.send(JSON.stringify({ type: 'unsubscribed', channel: message.channel, pair: String(message.pair).toUpperCase(), topic }));
      }
    } catch (error) {
      logger.debug('Invalid market websocket payload', { error: error.message });
      socket.send(JSON.stringify({ op: 'error', message: 'Invalid websocket payload' }));
    }
  }

  _isSubscribed(socket, event) {
    if (!event?.topic) {
      return false;
    }

    return socket.subscriptions.has(event.topic);
  }

  _normalizePublishedEvent(event) {
    if (event?.topic) {
      return { ...event, op: event.op || 'event' };
    }

    const topic = this._legacyTopic(event?.type, event?.pair, event?.timeframe);
    return {
      op: 'event',
      topic,
      type: event?.type,
      pair: event?.pair,
      symbol: this._compactSymbol(event?.pair),
      sequence: event?.sequence ?? event?.data?.sequence ?? null,
      data: event?.data || {},
      timestamp: event?.timestamp || new Date().toISOString(),
    };
  }

  _legacyTopic(channel, pair, timeframe) {
    const symbol = this._compactSymbol(pair);
    const mapped = {
      ticker: 'ticker',
      price: 'ticker',
      trades: 'trade',
      trade: 'trade',
      order_book: 'book',
      book: 'book',
      depth: 'book',
      candle: 'kline',
      kline: 'kline',
    }[String(channel || '').toLowerCase()];

    if (!symbol || !mapped) {
      return null;
    }

    return mapped === 'kline'
      ? `market.${symbol}.kline.${String(timeframe || '1m').toLowerCase()}`
      : `market.${symbol}.${mapped}`;
  }

  _normalizeTopic(topic) {
    const value = String(topic || '').trim();
    const match = /^market\.([A-Za-z0-9]+)\.(ticker|book|trade|kline)(?:\.([A-Za-z0-9]+))?$/.exec(value);
    if (!match) {
      return null;
    }

    const type = match[2].toLowerCase();
    if (type === 'kline' && !match[3]) {
      return null;
    }

    return `market.${match[1].toUpperCase()}.${type}${match[3] ? `.${match[3].toLowerCase()}` : ''}`;
  }

  _compactSymbol(pair) {
    return String(pair || '').replace(/[/-]/g, '').toUpperCase();
  }
}

module.exports = new MarketStreamHub();
