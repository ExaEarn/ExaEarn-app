/**
 * Deposit Address Generator
 *
 * Generates deterministic deposit addresses for supported chains using the
 * dedicated HD wallet service. This service only returns public deposit
 * metadata to Laravel; no private keys are exposed across service boundaries.
 */

const config = require('../config');
const hdWalletService = require('./hdWalletService');
const logger = require('../utils/logger');

class AddressGenerator {
  /**
   * Generate a deposit address for a user + currency + network.
   *
   * @param {number} userId
   * @param {string} currency
   * @param {string} network
   * @returns {{ address: string, network: string, metadata?: object }}
   */
  generate(userId, currency, network) {
    const upperCurrency = String(currency).toUpperCase();
    const normalizedNetwork = String(network || config.getNetworkForCurrency(upperCurrency) || 'base').toLowerCase();

    logger.info('Generating deposit address', {
      userId,
      currency: upperCurrency,
      network: normalizedNetwork,
    });

    return hdWalletService.deriveDepositAddress({
      userId,
      currency: upperCurrency,
      network: normalizedNetwork,
    });
  }

  /**
   * Sweep-key recovery is intentionally disabled here until key material is
   * backed by HSM/KMS/MPC rather than application memory.
   */
  getDepositPrivateKey() {
    throw new Error('Deposit private-key recovery is disabled. Use the secure signer pipeline for sweep operations.');
  }

  isKnownDepositAddress() {
    return false;
  }
}

module.exports = new AddressGenerator();
