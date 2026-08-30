const { WebSocketServer, WebSocket } = require('ws');
const axios = require('axios');
const crypto = require('crypto');
const config = require('../config');
const logger = require('../utils/logger');

class DeveloperRealtimeHub {
  constructor(authority = null) {
    this.clients = new Set();
    this.bySession = new Map();
    this.authority = authority || {
      authorize: (body) => this._post('authorize', body),
      replay: (body) => this._post('replay', body),
    };
  }

  attach(server) {
    this.wss = new WebSocketServer({ noServer: true, maxPayload: config.developerRealtime.maxMessageBytes });
    server.on('upgrade', (request, socket, head) => {
      const match = /^\/ws\/developer\/(sandbox|production)$/.exec(new URL(request.url, 'http://localhost').pathname);
      if (!match) return;
      this.wss.handleUpgrade(request, socket, head, (ws) => this.wss.emit('connection', ws, request, match[1]));
    });
    this.wss.on('connection', (socket, request, environment) => this._connect(socket, request, environment));
    this.heartbeat = setInterval(() => this._heartbeat(), config.developerRealtime.heartbeatMs);
    logger.info('Developer WebSocket gateway attached');
    return this.wss;
  }

  _connect(socket, request, environment) {
    socket.connectionId = `devconn_${crypto.randomUUID()}`;
    socket.environment = environment; socket.isAlive = true; socket.authority = null; socket.subscriptions = new Set(); socket.commandTimes = [];
    this.clients.add(socket);
    const timer = setTimeout(() => this._close(socket, 4401, 'AUTH_TIMEOUT'), config.developerRealtime.authTimeoutMs); socket.authTimer = timer;
    socket.on('pong', () => { socket.isAlive = true; });
    socket.on('message', (raw) => this._message(socket, raw, timer));
    socket.on('close', () => this._remove(socket));
    socket.on('error', () => this._remove(socket));
    this._send(socket, { op: 'connected', connection_id: socket.connectionId, environment, authenticate_with: 'devws_session' });
  }

  async _message(socket, raw, authTimer) {
    if (Buffer.byteLength(raw) > config.developerRealtime.maxMessageBytes) return this._close(socket, 4400, 'MESSAGE_TOO_LARGE');
    let message; try { message = JSON.parse(String(raw)); } catch { return this._send(socket, { op: 'error', code: 'INVALID_JSON' }); }
    if (!this._takeCommand(socket)) return this._close(socket, 4429, 'RATE_LIMITED');
    if (!socket.authority) {
      if (message.op !== 'authenticate' || typeof message.session_id !== 'string') return this._close(socket, 4401, 'AUTH_REQUIRED');
      try {
        const authority = await this.authority.authorize({ session_id: message.session_id, environment: socket.environment });
        const count = this.bySession.get(authority.session_uuid)?.size || 0;
        if (count >= config.developerRealtime.maxConnectionsPerSession) return this._close(socket, 4429, 'CONNECTION_LIMIT');
        socket.sessionId = message.session_id; socket.authority = authority;
        socket.subscriptions = new Set(authority.topics || []);
        if (!this.bySession.has(authority.session_uuid)) this.bySession.set(authority.session_uuid, new Set());
        this.bySession.get(authority.session_uuid).add(socket); clearTimeout(authTimer);
        return this._send(socket, { op: 'authenticated', connection_id: socket.connectionId, project: authority.project_uuid, environment: authority.environment, topics: [...socket.subscriptions] });
      } catch { return this._close(socket, 4403, 'AUTH_DENIED'); }
    }
    if (message.op === 'ping') return this._send(socket, { op: 'pong', timestamp: new Date().toISOString() });
    if (message.op === 'subscribe' && Array.isArray(message.topics)) {
      const allowed = new Set(socket.authority.topics || []);
      if (message.topics.length + socket.subscriptions.size > config.developerRealtime.maxSubscriptions) return this._close(socket, 4409, 'SUBSCRIPTION_LIMIT');
      if (message.topics.some((topic) => !allowed.has(topic))) return this._send(socket, { op: 'error', code: 'TOPIC_NOT_AUTHORIZED' });
      message.topics.forEach((topic) => socket.subscriptions.add(topic));
      return this._send(socket, { op: 'subscribed', topics: message.topics });
    }
    if (message.op === 'replay') {
      if (!socket.subscriptions.has(message.stream)) return this._send(socket, { op: 'error', code: 'TOPIC_NOT_AUTHORIZED' });
      try {
        const result = await this.authority.replay({ session_id: socket.sessionId, environment: socket.environment, stream: message.stream, after_sequence: Number(message.after_sequence || 0), limit: Math.min(Number(message.limit || 500), 500) });
        return this._send(socket, { op: 'replay', stream: message.stream, ...result });
      } catch { return this._close(socket, 4403, 'SESSION_REVOKED'); }
    }
    return this._send(socket, { op: 'error', code: 'UNKNOWN_OPERATION' });
  }

  publish(event) {
    for (const socket of this.clients) {
      if (!socket.authority || socket.authority.project_id !== event.project_id || socket.environment !== event.environment || !socket.subscriptions.has(event.stream)) continue;
      this._send(socket, { op: 'event', ...event });
    }
  }

  async _heartbeat() {
    for (const socket of [...this.clients]) {
      if (socket.isAlive === false) { this._close(socket, 4408, 'HEARTBEAT_TIMEOUT'); continue; }
      socket.isAlive = false; socket.ping();
      if (socket.authority) {
        try { socket.authority = await this.authority.authorize({ session_id: socket.sessionId, environment: socket.environment }); }
        catch { this._close(socket, 4403, 'SESSION_REVOKED'); }
      }
    }
  }

  _send(socket, payload) {
    if (socket.readyState !== WebSocket.OPEN) return false;
    if (socket.bufferedAmount > config.developerRealtime.maxBufferedBytes) { this._close(socket, 4408, 'SLOW_CONSUMER'); return false; }
    socket.send(JSON.stringify(payload)); return true;
  }
  _takeCommand(socket) { const now = Date.now(); socket.commandTimes = socket.commandTimes.filter((t) => now - t < 60000); if (socket.commandTimes.length >= config.developerRealtime.commandLimitPerMinute) return false; socket.commandTimes.push(now); return true; }
  _close(socket, code, reason) { try { socket.close(code, reason); } catch { socket.terminate(); } }
  _remove(socket) { clearTimeout(socket.authTimer); this.clients.delete(socket); if (socket.authority) { const set=this.bySession.get(socket.authority.session_uuid); set?.delete(socket); if(set?.size===0)this.bySession.delete(socket.authority.session_uuid); } }
  async _post(path, body) { const response = await axios.post(`${config.developerRealtime.authorityUrl}/${path}`, body, { headers: { 'X-Webhook-Secret': config.webhookSecret }, timeout: 3000 }); return response.data.data; }
  stats() { return { connections: this.clients.size, authenticated: [...this.clients].filter((s)=>s.authority).length, environments: { sandbox:[...this.clients].filter((s)=>s.environment==='sandbox').length, production:[...this.clients].filter((s)=>s.environment==='production').length } }; }
  async close() { clearInterval(this.heartbeat); for(const socket of this.clients)this._close(socket,1012,'SERVICE_RESTART'); await new Promise((resolve)=>this.wss?.close(resolve)); }
}

module.exports = DeveloperRealtimeHub;
