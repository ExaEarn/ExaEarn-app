/**
 * Webhook Notifier
 *
 * Sends webhook notifications to the Laravel backend when blockchain
 * events are detected (deposits confirmed, withdrawals broadcast, etc.).
 *
 * Workflow: Blockchain â†’ Node.js event listener â†’ Webhook to Laravel â†’ Laravel updates wallet balance
 */

const axios = require('axios');
const config = require('../config');
const logger = require('../utils/logger');

class WebhookNotifier {
  constructor() {
    this._client = axios.create({
      baseURL: config.laravelApiUrl,
      timeout: 15000,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Webhook-Secret': config.serviceSecret,
      },
    });
  }

  /**
   * Notify Laravel that a deposit has been confirmed on-chain.
   *
   * @param {Object} params
   * @param {number} params.user_id
   * @param {string} params.currency
   * @param {string} params.amount
   * @param {string} params.tx_hash
   * @param {string} params.network
   * @param {number} params.confirmations
   * @param {number|null} params.block_number
   * @param {string|null} params.reference
   * @param {Object|null} params.metadata
   */
  async notifyDeposit(params) {
    try {
      const response = await this._client.post('/webhooks/deposits', {
        user_id: params.user_id,
        currency: params.currency.toUpperCase(),
        amount: params.amount.toString(),
        tx_hash: params.tx_hash,
        network: params.network,
        confirmations: params.confirmations,
        block_number: params.block_number || null,
        reference: params.reference || null,
        metadata: params.metadata || {},
      });

      logger.info('Deposit webhook sent successfully', {
        tx_hash: params.tx_hash,
        user_id: params.user_id,
        amount: params.amount,
        currency: params.currency,
        laravelStatus: response.data?.status,
      });

      return { success: true, data: response.data };
    } catch (error) {
      const status = error.response?.status;
      const body = error.response?.data;

      // If Laravel says it's a duplicate, that's okay â€” don't retry
      if (status === 200 && body?.status === 'duplicate') {
        logger.info('Deposit already processed (duplicate)', {
          tx_hash: params.tx_hash,
        });
        return { success: true, duplicate: true, data: body };
      }

      // If pending (202), insufficient confirmations â€” retry later
      if (status === 202) {
        logger.info('Deposit pending more confirmations', {
          tx_hash: params.tx_hash,
          confirmations: body?.confirmations,
          required: body?.required,
        });
        return { success: false, pending: true, data: body };
      }

      logger.error('Deposit webhook failed', {
        tx_hash: params.tx_hash,
        status,
        error: error.message,
        response: body,
      });

      return { success: false, error: error.message };
    }
  }

  /**
   * Notify Laravel that a withdrawal has been broadcast and confirmed/failed.
   *
   * @param {Object} params
   * @param {string} params.transaction_id - Laravel transaction UUID
   * @param {string|null} params.tx_hash - On-chain transaction hash
   * @param {string} params.status - 'completed' or 'failed'
   * @param {string|null} params.failure_reason
   * @param {Object|null} params.metadata
   */
  async notifyWithdrawalConfirmation(params) {
    try {
      const response = await this._client.post('/webhooks/withdrawals/confirm', {
        transaction_id: params.transaction_id,
        tx_hash: params.tx_hash || null,
        status: params.status,
        failure_reason: params.failure_reason || null,
        metadata: params.metadata || {},
      });

      logger.info('Withdrawal confirmation webhook sent', {
        transaction_id: params.transaction_id,
        status: params.status,
        tx_hash: params.tx_hash,
      });

      return { success: true, data: response.data };
    } catch (error) {
      logger.error('Withdrawal confirmation webhook failed', {
        transaction_id: params.transaction_id,
        status: error.response?.status,
        error: error.message,
      });

      return { success: false, error: error.message };
    }
  }

  async notifyNftEvent(params) {
    try {
      const response = await this._client.post('/webhooks/nft/events', {
        event: params.event,
        token_id: params.token_id,
        tx_hash: params.tx_hash,
        contract_address: params.contract_address || null,
        buyer_wallet: params.buyer_wallet || null,
        seller_wallet: params.seller_wallet || null,
        owner_wallet: params.owner_wallet || null,
        tier: params.tier || null,
        level: params.level || null,
        sale_price_exa: params.sale_price_exa || null,
        payload: params.payload || {},
      });

      logger.info('NFT event webhook sent', { event: params.event, token_id: params.token_id, tx_hash: params.tx_hash });
      return { success: true, data: response.data };
    } catch (error) {
      logger.error('NFT event webhook failed', { event: params.event, token_id: params.token_id, error: error.message, status: error.response?.status });
      return { success: false, error: error.message };
    }
  }

  async fetchDepositAddresses() {
    try {
      const response = await this._client.get('/webhooks/deposits-addresses');
      return Array.isArray(response.data?.data) ? response.data.data : [];
    } catch (error) {
      logger.error('Failed to fetch deposit addresses from Laravel', {
        status: error.response?.status,
        error: error.message,
      });

      return [];
    }
  }

  /**
   * Health check for the Laravel API.
   */
  async checkLaravelHealth() {
    try {
      const response = await this._client.get('/health');
      return response.data;
    } catch (error) {
      logger.warn('Laravel health check failed', { error: error.message });
      return { status: 'unreachable', error: error.message };
    }
  }
}

module.exports = new WebhookNotifier();
