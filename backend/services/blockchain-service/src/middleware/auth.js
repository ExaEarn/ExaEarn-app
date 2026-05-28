/**
 * Authentication middleware for incoming requests from Laravel backend.
 *
 * Validates the X-Service-Secret header against the shared secret.
 * This ensures only the authorized Laravel backend can call this service.
 */

const config = require('../config');
const logger = require('../utils/logger');

function verifyServiceSecret(req, res, next) {
  const provided = req.headers['x-service-secret'];

  if (!config.serviceSecret) {
    logger.warn('SERVICE_SECRET not configured — rejecting all requests');
    return res.status(500).json({ error: 'Service secret not configured.' });
  }

  if (!provided || !timingSafeEqual(config.serviceSecret, provided)) {
    logger.warn('Unauthorized request attempt', {
      ip: req.ip,
      path: req.path,
      userAgent: req.headers['user-agent'],
    });
    return res.status(401).json({ error: 'Unauthorized.' });
  }

  next();
}

/**
 * Timing-safe string comparison to prevent timing attacks.
 */
function timingSafeEqual(a, b) {
  if (typeof a !== 'string' || typeof b !== 'string') return false;
  if (a.length !== b.length) return false;

  const crypto = require('crypto');
  try {
    return crypto.timingSafeEqual(Buffer.from(a), Buffer.from(b));
  } catch {
    return false;
  }
}

module.exports = { verifyServiceSecret };
