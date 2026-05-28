/**
 * Deposit Monitor
 *
 * Watches registered deposit addresses across supported EVM networks and
 * forwards confirmed native/token deposits to Laravel. It also restores
 * watched addresses from Laravel on startup so monitoring survives restarts.
 */

const { ethers } = require('ethers');
const config = require('../config');
const logger = require('../utils/logger');
const blockchain = require('./blockchain');
const webhookNotifier = require('./webhookNotifier');
const nonEvmChainService = require('./nonEvmChainService');

const TRANSFER_EVENT_TOPIC = ethers.id('Transfer(address,address,uint256)');

const _processedEventKeys = new Set();
const _lastScannedBlocks = new Map();
const _watchedAddresses = new Map();
let _lastXrplLedger = 0;

class DepositMonitor {
  constructor() {
    this._polling = false;
    this._pollTimer = null;
  }

  watchAddress(address, userId, currency, network = 'base', metadata = {}) {
    const key = this._watchKey(network, address, metadata.destination_tag);
    _watchedAddresses.set(key, {
      user_id: userId,
      currency: String(currency).toUpperCase(),
      network: String(network).toLowerCase(),
      address: String(address),
      metadata,
    });

    logger.info('Now watching deposit address', {
      key,
      address,
      userId,
      currency,
      network,
      destinationTag: metadata.destination_tag || null,
    });
  }

  unwatchAddress(address, network = 'base', destinationTag = null) {
    _watchedAddresses.delete(this._watchKey(network, address, destinationTag));
  }

  getWatchedAddresses() {
    return Array.from(_watchedAddresses.values());
  }

  async start() {
    if (this._polling) {
      logger.warn('Deposit monitor already running');
      return;
    }

    try {
      await this._restoreWatchedAddresses();

      for (const network of config.evmNetworks) {
        try {
          const currentBlock = await blockchain.getBlockNumber(network);
          _lastScannedBlocks.set(
            network,
            Math.max(currentBlock - this._requiredConfirmations(network), 0)
          );
        } catch (error) {
          logger.warn('Failed to initialize scan cursor', {
            network,
            error: error.message,
          });
        }
      }

      try {
        _lastXrplLedger = await nonEvmChainService.getCurrentXrplLedger();
      } catch (error) {
        logger.warn('Failed to initialize XRPL ledger cursor', { error: error.message });
      }

      this._polling = true;
      logger.info('Deposit monitor starting', {
        watchedAddresses: _watchedAddresses.size,
        pollInterval: config.blockPollInterval,
        lastScannedBlocks: Object.fromEntries(_lastScannedBlocks),
      });
      this._poll();
    } catch (error) {
      logger.error('Failed to start deposit monitor', { error: error.message });
    }
  }

  stop() {
    this._polling = false;
    if (this._pollTimer) {
      clearTimeout(this._pollTimer);
      this._pollTimer = null;
    }
    logger.info('Deposit monitor stopped');
  }

  async _restoreWatchedAddresses() {
    const records = await webhookNotifier.fetchDepositAddresses();

    for (const record of records) {
      this.watchAddress(
        record.address,
        record.user_id,
        record.currency,
        record.network,
        record.metadata || {}
      );
    }

    logger.info('Restored watched deposit addresses', { count: records.length });
  }

  async _poll() {
    if (!this._polling) {
      return;
    }

    try {
      for (const network of config.evmNetworks) {
        await this._pollNetwork(network);
      }
      await this._pollXrpl();
    } catch (error) {
      logger.error('Deposit monitor poll error', { error: error.message });
    }

    this._pollTimer = setTimeout(() => this._poll(), config.blockPollInterval);
  }

  async _pollXrpl() {
    const watchedForNetwork = this._getWatchedForNetwork('xrpl');
    if (watchedForNetwork.length === 0) {
      return;
    }

    const currentLedger = await nonEvmChainService.getCurrentXrplLedger();
    const minLedger = Math.max(_lastXrplLedger + 1, currentLedger - 200);
    const transactions = await nonEvmChainService.fetchXrplIncomingPayments(minLedger);

    for (const entry of transactions) {
      const tx = entry.tx || {};
      if (!entry.validated || tx.TransactionType !== 'Payment') {
        continue;
      }

      const destination = String(tx.Destination || '').toLowerCase();
      const destinationTag = tx.DestinationTag ?? null;
      const watchEntry = watchedForNetwork.find((candidate) => (
        String(candidate.address).toLowerCase() === destination
        && Number(candidate.metadata?.destination_tag) === Number(destinationTag)
      ));

      if (!watchEntry) {
        continue;
      }

      const eventKey = `xrpl:${tx.hash}:${destinationTag}`;
      if (_processedEventKeys.has(eventKey)) {
        continue;
      }

      if (typeof tx.Amount !== 'string') {
        continue;
      }

      _processedEventKeys.add(eventKey);
      await webhookNotifier.notifyDeposit({
        user_id: watchEntry.user_id,
        currency: 'XRP',
        amount: (Number(tx.Amount) / 1_000_000).toFixed(6),
        tx_hash: tx.hash,
        network: 'xrpl',
        confirmations: config.xrpl.minConfirmations,
        block_number: entry.ledger_index || null,
        reference: null,
        metadata: {
          from_address: tx.Account,
          to_address: tx.Destination,
          destination_tag: destinationTag,
          asset_type: 'native',
        },
      });
    }

    _lastXrplLedger = currentLedger;
  }

  async _pollNetwork(network) {
    const watchedForNetwork = this._getWatchedForNetwork(network);
    if (watchedForNetwork.length === 0) {
      return;
    }

    const currentBlock = await blockchain.getBlockNumber(network);
    const lastScanned = _lastScannedBlocks.get(network) ?? currentBlock;
    const matureTip = currentBlock - this._requiredConfirmations(network);

    if (matureTip <= lastScanned) {
      return;
    }

    const fromBlock = lastScanned + 1;
    const toBlock = Math.min(matureTip, fromBlock + 25);

    await this._scanNativeTransfers(network, fromBlock, toBlock, watchedForNetwork);
    await this._scanTokenTransfers(network, fromBlock, toBlock, watchedForNetwork);
    _lastScannedBlocks.set(network, toBlock);
  }

  async _scanNativeTransfers(network, fromBlock, toBlock, watchedEntries) {
    const provider = blockchain.getEthersProvider(network);
    const watchedByAddress = new Map(
      watchedEntries.map((entry) => [String(entry.address).toLowerCase(), entry])
    );

    for (let blockNumber = fromBlock; blockNumber <= toBlock; blockNumber += 1) {
      const block = await provider.getBlock(blockNumber, true);
      const transactions = block?.prefetchedTransactions || block?.transactions || [];
      if (!block || !Array.isArray(transactions)) {
        continue;
      }

      for (const tx of transactions) {
        if (!tx || typeof tx === 'string' || !tx.to) {
          continue;
        }

        const recipient = String(tx.to).toLowerCase();
        const watchEntry = watchedByAddress.get(recipient);
        if (!watchEntry) {
          continue;
        }

        const requiredCurrency = String(watchEntry.currency).toUpperCase();
        const nativeCurrency = this._nativeCurrencyForNetwork(network);
        if (requiredCurrency !== nativeCurrency) {
          continue;
        }

        const value = tx.value ?? 0n;
        if (value <= 0n) {
          continue;
        }

        const eventKey = `${network}:native:${tx.hash}:${recipient}`;
        if (_processedEventKeys.has(eventKey)) {
          continue;
        }

        const amount = ethers.formatEther(value);

        _processedEventKeys.add(eventKey);
        await webhookNotifier.notifyDeposit({
          user_id: watchEntry.user_id,
          currency: nativeCurrency,
          amount,
          tx_hash: tx.hash,
          network,
          confirmations: this._requiredConfirmations(network),
          block_number: blockNumber,
          reference: null,
          metadata: {
            from_address: tx.from,
            to_address: tx.to,
            asset_type: 'native',
          },
        });
      }
    }
  }

  async _scanTokenTransfers(network, fromBlock, toBlock, watchedEntries) {
    const tokenEntries = config.getTokensForNetwork(network)
      .filter(([, tokenConfig]) => Boolean(tokenConfig.address));

    if (tokenEntries.length === 0) {
      return;
    }

    const provider = blockchain.getEthersProvider(network);
    const watchedByAddress = new Map(
      watchedEntries.map((entry) => [String(entry.address).toLowerCase(), entry])
    );

    const logs = await provider.getLogs({
      fromBlock,
      toBlock,
      address: tokenEntries.map(([, tokenConfig]) => tokenConfig.address),
      topics: [TRANSFER_EVENT_TOPIC],
    });

    const iface = new ethers.Interface([
      'event Transfer(address indexed from, address indexed to, uint256 value)',
    ]);

    for (const log of logs) {
      const parsed = iface.parseLog({ topics: log.topics, data: log.data });
      if (!parsed) {
        continue;
      }

      const toAddress = String(parsed.args.to).toLowerCase();
      const watchEntry = watchedByAddress.get(toAddress);
      if (!watchEntry) {
        continue;
      }

      const tokenEntry = tokenEntries.find(([, tokenConfig]) => (
        String(tokenConfig.address).toLowerCase() === String(log.address).toLowerCase()
      ));

      if (!tokenEntry) {
        continue;
      }

      const [currency, tokenConfig] = tokenEntry;
      if (String(watchEntry.currency).toUpperCase() !== currency.toUpperCase()) {
        continue;
      }

      const eventKey = `${network}:token:${log.transactionHash}:${log.index}`;
      if (_processedEventKeys.has(eventKey)) {
        continue;
      }

      _processedEventKeys.add(eventKey);
      await webhookNotifier.notifyDeposit({
        user_id: watchEntry.user_id,
        currency,
        amount: ethers.formatUnits(parsed.args.value, tokenConfig.decimals),
        tx_hash: log.transactionHash,
        network,
        confirmations: this._requiredConfirmations(network),
        block_number: log.blockNumber,
        reference: null,
        metadata: {
          from_address: parsed.args.from,
          to_address: parsed.args.to,
          token_contract: log.address,
          asset_type: 'token',
        },
      });
    }
  }

  _getWatchedForNetwork(network) {
    return Array.from(_watchedAddresses.values())
      .filter((entry) => String(entry.network).toLowerCase() === String(network).toLowerCase());
  }

  _watchKey(network, address, destinationTag = null) {
    return [
      String(network).toLowerCase(),
      String(address).toLowerCase(),
      destinationTag === null || destinationTag === undefined ? '' : String(destinationTag),
    ].join(':');
  }

  _nativeCurrencyForNetwork(network) {
    switch (String(network).toLowerCase()) {
      case 'ethereum':
      case 'base':
        return 'ETH';
      case 'bsc':
        return 'BNB';
      case 'polygon':
        return 'MATIC';
      default:
        throw new Error(`Unknown native currency for network: ${network}`);
    }
  }

  _requiredConfirmations(network) {
    const networkConfig = config.getNetworkConfig(network);
    return networkConfig?.minConfirmations || 1;
  }

  getStatus() {
    return {
      running: this._polling,
      lastScannedBlocks: Object.fromEntries(_lastScannedBlocks),
      watchedAddresses: _watchedAddresses.size,
      processedEvents: _processedEventKeys.size,
      lastXrplLedger: _lastXrplLedger,
      pollInterval: config.blockPollInterval,
    };
  }
}

module.exports = new DepositMonitor();
