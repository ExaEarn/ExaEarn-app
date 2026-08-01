const { ethers } = require('ethers');
const config = require('../config');
const logger = require('../utils/logger');
const contractInteractionService = require('./contractInteractionService');
const nonEvmChainService = require('./nonEvmChainService');

const WRAPPED_XRP_ABI = [
  'function mint(address to, uint256 amount) external',
  'function burn(address from, uint256 amount) external',
  'function totalSupply() view returns (uint256)',
];

class XRPBridgeService {
  constructor() {
    this.processedDeposits = new Set();
    this.ledgerCursor = 0;
    this.auditLog = [];
    this.treasury = {
      totalXrpLockedDrops: 0n,
      totalWxrpmintedUnits: 0n,
      totalWxrpburnedUnits: 0n,
      totalXrpReleasedDrops: 0n,
      lockedByUser: new Map(),
    };
    this.monitorTimer = null;
  }

  startMonitoring() {
    if (this.monitorTimer) {
      return;
    }

    const run = async () => {
      try {
        await this.monitorDeposits();
      } catch (error) {
        logger.error('XRP bridge monitor tick failed', { error: error.message });
      }
    };

    this.monitorTimer = setInterval(run, config.xrpBridge.monitorIntervalMs);
    run();
    this._recordAudit('bridge.monitor_started', { interval_ms: config.xrpBridge.monitorIntervalMs });
  }

  stopMonitoring() {
    if (!this.monitorTimer) {
      return;
    }

    clearInterval(this.monitorTimer);
    this.monitorTimer = null;
    this._recordAudit('bridge.monitor_stopped');
  }

  _recordAudit(action, data = {}) {
    const entry = {
      id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
      action,
      at: new Date().toISOString(),
      data,
    };

    this.auditLog.push(entry);
    if (this.auditLog.length > 1000) {
      this.auditLog.shift();
    }

    logger.info('XRP bridge audit', entry);
    return entry;
  }

  _toDrops(amountXrp) {
    return BigInt(ethers.parseUnits(String(amountXrp), 6).toString());
  }

  _toWxrUnits(amountXrp) {
    return BigInt(ethers.parseUnits(String(amountXrp), 6).toString());
  }

  _assertOneToOneInvariant() {
    const netLocked = this.treasury.totalXrpLockedDrops - this.treasury.totalXrpReleasedDrops;
    const netMinted = this.treasury.totalWxrpmintedUnits - this.treasury.totalWxrpburnedUnits;

    if (netLocked !== netMinted) {
      this._recordAudit('bridge.invariant_breach', {
        net_locked_drops: netLocked.toString(),
        net_minted_units: netMinted.toString(),
      });
      throw new Error('Bridge invariant breach: locked XRP and circulating wXRP mismatch');
    }
  }

  async validateTransaction(txHash) {
    if (!txHash || typeof txHash !== 'string') {
      throw new Error('txHash is required');
    }

    const verified = await nonEvmChainService.verifyTransaction(txHash, 'xrpl');
    if (!verified.confirmed) {
      throw new Error('XRPL transaction is not confirmed');
    }

    this._recordAudit('bridge.tx_validated', {
      tx_hash: txHash,
      confirmations: verified.confirmations,
      block_number: verified.block_number,
    });

    return verified;
  }

  async lockXRP(amount, userId) {
    if (!userId) {
      throw new Error('userId is required');
    }

    const drops = this._toDrops(amount);
    if (drops <= 0n) {
      throw new Error('Invalid XRP lock amount');
    }

    this.treasury.totalXrpLockedDrops += drops;
    const current = this.treasury.lockedByUser.get(String(userId)) || 0n;
    this.treasury.lockedByUser.set(String(userId), current + drops);

    this._recordAudit('bridge.xrp_locked', {
      user_id: String(userId),
      amount_xrp: String(amount),
      amount_drops: drops.toString(),
      treasury_wallet: config.xrpBridge.treasuryAddress,
    });

    return { user_id: String(userId), amount_drops: drops.toString() };
  }

  async mintWrappedXRP(userAddress, amount) {
    if (!userAddress || !ethers.isAddress(userAddress)) {
      throw new Error('Invalid EVM wallet address');
    }

    if (!config.contracts.wrappedXrp) {
      throw new Error('Wrapped XRP contract is not configured');
    }

    const units = this._toWxrUnits(amount);
    if (units <= 0n) {
      throw new Error('Invalid mint amount');
    }

    const result = await contractInteractionService.write({
      address: config.contracts.wrappedXrp,
      abi: WRAPPED_XRP_ABI,
      method: 'mint',
      args: [userAddress, units],
    });

    this.treasury.totalWxrpmintedUnits += units;
    this._assertOneToOneInvariant();

    this._recordAudit('bridge.wxrp_minted', {
      user_address: userAddress,
      amount_units: units.toString(),
      tx_hash: result.tx_hash,
    });

    return result;
  }

  async burnWrappedXRP(userAddress, amount) {
    if (!userAddress || !ethers.isAddress(userAddress)) {
      throw new Error('Invalid EVM wallet address');
    }

    if (!config.contracts.wrappedXrp) {
      throw new Error('Wrapped XRP contract is not configured');
    }

    const units = this._toWxrUnits(amount);
    if (units <= 0n) {
      throw new Error('Invalid burn amount');
    }

    const result = await contractInteractionService.write({
      address: config.contracts.wrappedXrp,
      abi: WRAPPED_XRP_ABI,
      method: 'burn',
      args: [userAddress, units],
    });

    this.treasury.totalWxrpburnedUnits += units;
    this._assertOneToOneInvariant();

    this._recordAudit('bridge.wxrp_burned', {
      user_address: userAddress,
      amount_units: units.toString(),
      tx_hash: result.tx_hash,
    });

    return result;
  }

  async releaseXRP(userId, amount) {
    const userKey = String(userId);
    const drops = this._toDrops(amount);
    if (drops <= 0n) {
      throw new Error('Invalid XRP release amount');
    }

    const current = this.treasury.lockedByUser.get(userKey) || 0n;
    if (current < drops) {
      throw new Error('Insufficient locked XRP for user release');
    }

    this.treasury.lockedByUser.set(userKey, current - drops);
    this.treasury.totalXrpReleasedDrops += drops;
    this._assertOneToOneInvariant();

    this._recordAudit('bridge.xrp_released', {
      user_id: userKey,
      amount_drops: drops.toString(),
    });

    return { user_id: userKey, released_drops: drops.toString() };
  }

  async monitorDeposits() {
    const currentLedger = await nonEvmChainService.getCurrentXrplLedger();
    const minLedger = this.ledgerCursor > 0 ? this.ledgerCursor + 1 : Math.max(currentLedger - 200, 0);
    const incoming = await nonEvmChainService.fetchXrplIncomingPayments(minLedger);

    const processed = [];

    for (const entry of incoming) {
      const tx = entry?.tx || {};
      if (!entry?.validated || tx.TransactionType !== 'Payment') {
        continue;
      }

      const txHash = tx.hash;
      if (!txHash || this.processedDeposits.has(txHash)) {
        continue;
      }

      if (String(tx.Destination || '') !== String(config.xrpl.hotWallet.address || '')) {
        continue;
      }

      if (typeof tx.Amount !== 'string') {
        continue;
      }

      const amountXrp = Number(tx.Amount) / 1_000_000;
      this.processedDeposits.add(txHash);
      processed.push({
        tx_hash: txHash,
        amount_xrp: amountXrp,
        from_address: tx.Account,
        destination_tag: tx.DestinationTag ?? null,
        ledger_index: entry.ledger_index || null,
      });

      this._recordAudit('bridge.deposit_detected', {
        tx_hash: txHash,
        amount_xrp: amountXrp,
        from_address: tx.Account,
      });
    }

    this.ledgerCursor = currentLedger;
    return { ledger_cursor: this.ledgerCursor, processed };
  }

  getAuditLog(limit = 100) {
    const size = Math.max(Number(limit) || 100, 1);
    return this.auditLog.slice(-size);
  }

  getTreasuryStatus() {
    const netLocked = this.treasury.totalXrpLockedDrops - this.treasury.totalXrpReleasedDrops;
    const netMinted = this.treasury.totalWxrpmintedUnits - this.treasury.totalWxrpburnedUnits;

    return {
      total_xrp_locked_drops: this.treasury.totalXrpLockedDrops.toString(),
      total_xrp_released_drops: this.treasury.totalXrpReleasedDrops.toString(),
      total_wxrp_minted_units: this.treasury.totalWxrpmintedUnits.toString(),
      total_wxrp_burned_units: this.treasury.totalWxrpburnedUnits.toString(),
      net_locked_drops: netLocked.toString(),
      net_wxrp_circulating_units: netMinted.toString(),
      invariant_ok: netLocked === netMinted,
    };
  }
}

module.exports = new XRPBridgeService();

