const express = require('express');
const config = require('./config');
const { verifyServiceSecret } = require('./middleware/auth');
const realtimeHub = require('./services/realtimeHub');
const blockchainService = require('./services/blockchainService');
const logger = require('./utils/logger');

const app = express();
app.use(express.json({ limit: '1mb' }));

function requireTransactionExecutionEnabled(_req, res, next) {
  if (!config.transactionExecutionEnabled) {
    return res.status(403).json({ message: 'Transaction execution is disabled.' });
  }
  return next();
}

app.get('/health', (_req, res) => {
  res.json({ status: 'ok' });
});

app.post('/contracts/execute', verifyServiceSecret, requireTransactionExecutionEnabled, async (req, res) => {
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
    logger.error('Contract execution failed', { error: error.message });
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
    logger.error('Contract call failed', { error: error.message });
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
  const data = body.data ?? body;
  const userId = Number(body.user_id ?? body.userId);

  switch (topic) {
    case 'price':
    case 'market':
      realtimeHub.publishPriceUpdate(data);
      return res.status(204).send();
    case 'portfolio':
      if (!Number.isFinite(userId) || userId <= 0) {
        return res.status(400).json({ message: 'user_id is required for portfolio publishing' });
      }
      realtimeHub.publishPortfolioUpdate(userId, data);
      return res.status(204).send();
    case 'game':
      realtimeHub.publishGameFlight(body);
      return res.status(204).send();
    default:
      return res.status(404).json({ message: 'Unknown publish topic' });
  }
});

module.exports = app;
