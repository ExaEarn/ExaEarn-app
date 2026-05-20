const express = require('express');
const http = require('http');
const config = require('./config');
const logger = require('./utils/logger');
const realtimeHub = require('./services/realtimeHub');
const realtimeSubscriber = require('./services/realtimeSubscriber');
const walletUpdateHub = require('./services/walletUpdateHub');
const ledgerUpdateHub = require('./services/ledgerUpdateHub');
const futuresUpdateSubscriber = require('./services/futuresUpdateSubscriber');
const ledgerUpdateSubscriber = require('./services/ledgerUpdateSubscriber');
const blockchainService = require('./services/blockchainService');
const blockchainEventListener = require('./services/blockchainEventListener');

const app = express();
app.use(express.json());

function verifyServiceSecret(req, res, next) {
  const secret = config.serviceSecret;

  if (secret === '') {
    return next();
  }

  const incoming = req.header('x-service-secret');
  if (incoming !== secret) {
    return res.status(403).json({ message: 'Forbidden' });
  }

  return next();
}

app.get('/health', (_req, res) => {
  res.json({ status: 'ok' });
});

app.post('/contracts/execute', verifyServiceSecret, async (req, res) => {
  try {
    const result = await blockchainService.sendTransaction({
      contract: req.body.contract,
      method: req.body.method,
      params: req.body.params || req.body.args || [],
      network: req.body.network || 'base',
      value: req.body.value,
      abi: Array.isArray(req.body.abi) ? req.body.abi : null,
      address: req.body.address || null,
    });

    return res.status(201).json(result);
  } catch (error) {
    logger.error('Contract execution failed', { error: error.message, body: req.body });
    return res.status(422).json({ error: error.message });
  }
});

app.post('/contracts/call', verifyServiceSecret, async (req, res) => {
  try {
    const result = await blockchainService.callContract({
      contract: req.body.contract,
      method: req.body.method,
      params: req.body.params || req.body.args || [],
      network: req.body.network || 'base',
      abi: Array.isArray(req.body.abi) ? req.body.abi : null,
      address: req.body.address || null,
    });

    return res.json(result);
  } catch (error) {
    logger.error('Contract call failed', { error: error.message, body: req.body });
    return res.status(422).json({ error: error.message });
  }
});

app.get('/transactions/:txHash/status', verifyServiceSecret, async (req, res) => {
  try {
    const result = await blockchainService.getTransactionStatus(
      req.params.txHash,
      req.query.network || 'base'
    );

    return res.json(result);
  } catch (error) {
    logger.error('Transaction status lookup failed', { error: error.message, txHash: req.params.txHash });
    return res.status(422).json({ error: error.message });
  }
});

app.post('/streams/:topic/publish', verifyServiceSecret, (req, res) => {
  const topic = req.params.topic;
  const body = req.body || {};
  const event = body.event || null;
  const data = body.data ?? body;
  const userId = Number(body.user_id ?? body.userId);

  switch (topic) {
    case 'price':
      realtimeHub.publishPriceUpdate(data);
      return res.status(204).send();
    case 'portfolio':
      if (!Number.isFinite(userId) || userId <= 0) {
        return res.status(400).json({ message: 'user_id is required for portfolio publishing' });
      }
      realtimeHub.publishPortfolioUpdate(userId, data);
      return res.status(204).send();
    case 'market':
      realtimeHub.publishPriceUpdate(data);
      return res.status(204).send();
    default:
      return res.status(404).json({ message: 'Unknown publish topic' });
  }
});

const server = http.createServer(app);
realtimeHub.attach(server);
walletUpdateHub.attach(server);
ledgerUpdateHub.attach(server);

server.listen(config.port, async () => {
  logger.info('Realtime node server started', { port: config.port });

  try {
    await realtimeSubscriber.start();
    await futuresUpdateSubscriber.start();
    await ledgerUpdateSubscriber.start();
    blockchainEventListener.start();
  } catch (error) {
    logger.error('Failed to start background services', { error: error.message });
  }
});
