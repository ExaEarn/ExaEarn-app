const config = require('../config');
const logger = require('../utils/logger');
const blockchain = require('../services/blockchain');
const depositMonitor = require('../services/depositMonitor');
const addressGenerator = require('../services/addressGenerator');
const withdrawalBroadcaster = require('../services/withdrawalBroadcaster');
const contractInteractionService = require('../services/contractInteractionService');
const marketStreamHub = require('../services/marketStreamHub');
const marketStreamSubscriber = require('../services/marketStreamSubscriber');
const p2pChatHub = require('../services/p2pChatHub');
const rewardDistributorService = require('../services/rewardDistributorService');
const agriContractService = require('../services/agriContractService');
const fraudDetectionService = require('../services/fraudDetectionService');
const gameFiContractService = require('../services/gameFiContractService');
const financialNftContractService = require('../services/financialNftContractService');
const nonEvmChainService = require('../services/nonEvmChainService');
const xrpBridgeService = require('../services/xrpBridgeService');
const futuresMatchingEngine = require('../services/futuresMatchingEngine');

function parseRequiredString(value, field) {
  if (!value || typeof value !== 'string') {
    throw new Error(`${field} is required`);
  }
  return value;
}

class BlockchainController {
  async health(req, res) {
    const blockchainHealth = await blockchain.healthCheck();
    return res.json({
      status: 'ok',
      service: 'exaearn-blockchain-service',
      environment: config.nodeEnv,
      blockchain: blockchainHealth,
      deposit_monitor: depositMonitor.getStatus(),
      market_stream: {
        transport: marketStreamSubscriber.stats(),
        websocket: marketStreamHub.stats(),
      },
    });
  }

  async generateAddress(req, res) {
    try {
      const userId = Number(req.body.user_id);
      const currency = parseRequiredString(req.body.currency, 'currency');
      const network = req.body.network || config.getNetworkForCurrency(currency) || 'base';

      if (!Number.isInteger(userId) || userId <= 0) {
        return res.status(422).json({ error: 'user_id must be a positive integer' });
      }

      const result = addressGenerator.generate(userId, currency, network);
      depositMonitor.watchAddress(result.address, userId, currency, result.network, result.metadata || {});

      return res.status(201).json(result);
    } catch (error) {
      logger.error('Address generation failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async broadcastWithdrawal(req, res) {
    try {
      const payload = {
        transaction_id: parseRequiredString(req.body.transaction_id, 'transaction_id'),
        currency: parseRequiredString(req.body.currency, 'currency'),
        network: req.body.network || config.getNetworkForCurrency(req.body.currency) || 'base',
        to_address: parseRequiredString(req.body.to_address, 'to_address'),
        amount: parseRequiredString(String(req.body.amount ?? ''), 'amount'),
      };

      const result = await withdrawalBroadcaster.broadcast(payload);
      return res.status(201).json(result);
    } catch (error) {
      logger.error('Withdrawal broadcast request failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async verifyTransaction(req, res) {
    try {
      const txHash = parseRequiredString(req.body.tx_hash, 'tx_hash');
      const network = (req.body.network || 'base').toLowerCase();
      const currency = req.body.currency ? String(req.body.currency).toUpperCase() : null;

      if (config.evmNetworks.includes(network)) {
        const receipt = await blockchain.getTransactionReceipt(txHash, network);
        if (!receipt) {
          return res.json({
            confirmed: false,
            confirmations: 0,
            tx_hash: txHash,
            network,
          });
        }

        const confirmations = await blockchain.getConfirmations(txHash, network);
        const tokenConfig = currency ? config.getTokenConfig(currency) : null;
        const matchesToken = tokenConfig?.address
          ? receipt.logs.some((log) => log.address.toLowerCase() === tokenConfig.address.toLowerCase())
          : true;
        const minConfirmations = config.getNetworkConfig(network)?.minConfirmations || config.base.minConfirmations;

        return res.json({
          confirmed: confirmations >= minConfirmations && matchesToken,
          confirmations,
          block_number: receipt.blockNumber,
          tx_hash: txHash,
          network,
          token_verified: matchesToken,
        });
      }

      const result = await nonEvmChainService.verifyTransaction(txHash, network);
      return res.json({
        ...result,
        network,
        currency,
      });
    } catch (error) {
      logger.error('Transaction verification failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async getHotWalletBalances(req, res) {
    const balances = await withdrawalBroadcaster.getHotWalletBalances();
    return res.json({ data: balances });
  }

  async publishMarketStream(req, res) {
    try {
      const type = parseRequiredString(req.body.type, 'type');
      const pair = parseRequiredString(req.body.pair, 'pair').toUpperCase();
      const event = {
        type,
        pair,
        timeframe: req.body.timeframe || null,
        data: req.body.data || {},
        timestamp: new Date().toISOString(),
      };

      marketStreamHub.publish(event);

      return res.status(202).json({
        status: 'accepted',
        clients: marketStreamHub.stats().clients,
      });
    } catch (error) {
      logger.error('Market stream publish failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async submitFuturesOrder(req, res) {
    try {
      const payload = {
        order_uuid: parseRequiredString(req.body.order_uuid, 'order_uuid'),
        user_id: Number(req.body.user_id),
        symbol: parseRequiredString(req.body.symbol, 'symbol'),
        type: parseRequiredString(req.body.type, 'type'),
        side: parseRequiredString(req.body.side, 'side'),
        price: req.body.price == null ? null : Number(req.body.price),
        quantity: Number(req.body.quantity),
        created_at: req.body.created_at || new Date().toISOString(),
      };

      const result = futuresMatchingEngine.submitOrder(payload);
      return res.status(201).json(result);
    } catch (error) {
      logger.error('Submit futures order failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async cancelFuturesOrder(req, res) {
    try {
      const symbol = parseRequiredString(req.body.symbol, 'symbol');
      const orderUuid = parseRequiredString(req.body.order_uuid, 'order_uuid');
      const result = futuresMatchingEngine.cancelOrder(symbol, orderUuid);
      return res.status(200).json(result);
    } catch (error) {
      logger.error('Cancel futures order failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async readContract(req, res) {
    try {
      const address = parseRequiredString(req.body.address, 'address');
      const method = parseRequiredString(req.body.method, 'method');
      const abi = Array.isArray(req.body.abi) ? req.body.abi : null;
      if (!abi) {
        return res.status(422).json({ error: 'abi must be provided as an array' });
      }

      const result = await contractInteractionService.read({
        address,
        abi,
        method,
        args: Array.isArray(req.body.args) ? req.body.args : [],
      });

      return res.json({ data: result });
    } catch (error) {
      logger.error('Contract read failed', { error: error.message });
      return res.status(422).json({ error: error.message });
    }
  }

  async writeContract(req, res) {
    try {
      const address = parseRequiredString(req.body.address, 'address');
      const method = parseRequiredString(req.body.method, 'method');
      const abi = Array.isArray(req.body.abi) ? req.body.abi : null;
      if (!abi) {
        return res.status(422).json({ error: 'abi must be provided as an array' });
      }

      const result = await contractInteractionService.write({
        address,
        abi,
        method,
        args: Array.isArray(req.body.args) ? req.body.args : [],
        overrides: req.body.overrides && typeof req.body.overrides === 'object' ? req.body.overrides : {},
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('Contract write failed', { error: error.message });
      return res.status(422).json({ error: error.message });
    }
  }

  async monitorXrpDeposits(req, res) {
    try {
      const result = await xrpBridgeService.monitorDeposits();
      return res.json(result);
    } catch (error) {
      logger.error('XRP deposit monitor execution failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async lockXrp(req, res) {
    try {
      const result = await xrpBridgeService.lockXRP(
        parseRequiredString(String(req.body.amount ?? ''), 'amount'),
        parseRequiredString(String(req.body.user_id ?? ''), 'user_id')
      );
      return res.status(201).json(result);
    } catch (error) {
      logger.error('XRP lock failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async mintWrappedXrp(req, res) {
    try {
      const result = await xrpBridgeService.mintWrappedXRP(
        parseRequiredString(req.body.user_address, 'user_address'),
        parseRequiredString(String(req.body.amount ?? ''), 'amount')
      );
      return res.status(201).json(result);
    } catch (error) {
      logger.error('wXRP mint failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async burnWrappedXrp(req, res) {
    try {
      const result = await xrpBridgeService.burnWrappedXRP(
        parseRequiredString(req.body.user_address, 'user_address'),
        parseRequiredString(String(req.body.amount ?? ''), 'amount')
      );
      return res.status(201).json(result);
    } catch (error) {
      logger.error('wXRP burn failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async releaseXrp(req, res) {
    try {
      const result = await xrpBridgeService.releaseXRP(
        parseRequiredString(String(req.body.user_id ?? ''), 'user_id'),
        parseRequiredString(String(req.body.amount ?? ''), 'amount')
      );
      return res.status(201).json(result);
    } catch (error) {
      logger.error('XRP release failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async bridgeStatus(req, res) {
    return res.json({
      treasury: xrpBridgeService.getTreasuryStatus(),
      audit: xrpBridgeService.getAuditLog(Number(req.query.limit || 100)),
    });
  }

  async distributeReward(req, res) {
    try {
      const result = await rewardDistributorService.distribute({
        walletAddress: parseRequiredString(req.body.wallet_address, 'wallet_address'),
        amount: parseRequiredString(String(req.body.amount ?? ''), 'amount'),
        token: parseRequiredString(req.body.token, 'token'),
        activityType: parseRequiredString(req.body.activity_type, 'activity_type'),
        rewardId: parseRequiredString(String(req.body.reward_id ?? ''), 'reward_id'),
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('Reward distribution failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async tokenizeAgriProject(req, res) {
    try {
      const result = await agriContractService.tokenizeProject({
        project_id: Number(req.body.project_id),
        project_name: parseRequiredString(req.body.project_name, 'project_name'),
        investment_target: parseRequiredString(String(req.body.investment_target ?? ''), 'investment_target'),
        total_shares: Number(req.body.total_shares),
        price_per_share: parseRequiredString(String(req.body.price_per_share ?? ''), 'price_per_share'),
        token_symbol: req.body.token_symbol || 'EXAFARM',
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('Agri project tokenization failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async recordAgriInvestment(req, res) {
    try {
      const result = await agriContractService.recordInvestment({
        user_id: Number(req.body.user_id),
        project_id: Number(req.body.project_id),
        shares_owned: Number(req.body.shares_owned),
        investment_amount: parseRequiredString(String(req.body.investment_amount ?? ''), 'investment_amount'),
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('Agri investment recording failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async registerAgriLease(req, res) {
    try {
      const result = await agriContractService.registerLease({
        project_id: Number(req.body.project_id),
        farmer_id: Number(req.body.farmer_id),
        investment_id: Number(req.body.investment_id || 0),
        profit_share: Number(req.body.profit_share),
        lease_terms: parseRequiredString(req.body.lease_terms, 'lease_terms'),
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('Agri lease registration failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async distributeAgriReward(req, res) {
    try {
      const result = await rewardDistributorService.distribute({
        walletAddress: config.base.hotWallet.address,
        amount: parseRequiredString(String(req.body.amount ?? ''), 'amount'),
        token: 'EXA',
        activityType: 'agriculture_reward',
        rewardId: parseRequiredString(String(req.body.reward_id ?? ''), 'reward_id'),
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('Agri reward distribution failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async analyzeGiftcardFraud(req, res) {
    try {
      const result = fraudDetectionService.analyzeGiftcardTransaction({
        user_id: Number(req.body.user_id),
        amount: Number(req.body.amount),
        transaction_type: req.body.transaction_type,
        account_age_days: Number(req.body.account_age_days),
        total_transactions: Number(req.body.total_transactions),
        failed_transactions: Number(req.body.failed_transactions),
        ip_address: req.body.ip_address,
        device_id: req.body.device_id,
        geo_location: req.body.geo_location,
        is_vpn: Boolean(req.body.is_vpn),
        submission_frequency: Number(req.body.submission_frequency),
        card_hash_match: Boolean(req.body.card_hash_match),
        verified_source: Boolean(req.body.verified_source),
      });

      return res.json(result);
    } catch (error) {
      logger.error('Giftcard fraud analysis failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async createLotteryRound(req, res) {
    try {
      const result = await gameFiContractService.createLotteryRound({
        game_id: Number(req.body.game_id),
        entry_fee_eth: parseRequiredString(String(req.body.entry_fee_eth ?? ''), 'entry_fee_eth'),
        max_players: Number(req.body.max_players || 0),
        draw_at: Number(req.body.draw_at || 0),
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('GameFi lottery round creation failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async verifyLotteryEntry(req, res) {
    try {
      const result = await gameFiContractService.verifyLotteryEntry({
        tx_hash: parseRequiredString(req.body.tx_hash, 'tx_hash'),
        round_id: Number(req.body.round_id || 0),
        wallet_address: parseRequiredString(req.body.wallet_address, 'wallet_address'),
        entry_fee_eth: parseRequiredString(String(req.body.entry_fee_eth ?? ''), 'entry_fee_eth'),
      });

      return res.json(result);
    } catch (error) {
      logger.error('GameFi lottery entry verification failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async fetchLotteryResult(req, res) {
    try {
      const result = await gameFiContractService.fetchLotteryResult({
        round_id: Number(req.body.round_id || 0),
      });

      return res.json(result);
    } catch (error) {
      logger.error('GameFi lottery result fetch failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async createBettingPool(req, res) {
    try {
      const result = await gameFiContractService.createBettingPool({
        pool_id: Number(req.body.pool_id),
        event_name: parseRequiredString(req.body.event_name, 'event_name'),
        bet_options: Array.isArray(req.body.bet_options) ? req.body.bet_options : [],
        locking_at: Number(req.body.locking_at || 0),
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('GameFi betting pool creation failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async verifyBettingEntry(req, res) {
    try {
      const result = await gameFiContractService.verifyBettingEntry({
        tx_hash: parseRequiredString(req.body.tx_hash, 'tx_hash'),
        pool_id: Number(req.body.pool_id || 0),
        wallet_address: parseRequiredString(req.body.wallet_address, 'wallet_address'),
        bet_option: parseRequiredString(req.body.bet_option, 'bet_option'),
        bet_amount_eth: parseRequiredString(String(req.body.bet_amount_eth ?? ''), 'bet_amount_eth'),
      });

      return res.json(result);
    } catch (error) {
      logger.error('GameFi betting entry verification failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async resolveBettingPool(req, res) {
    try {
      const result = await gameFiContractService.resolveBettingPool({
        pool_id: Number(req.body.pool_id || 0),
        winning_option: parseRequiredString(req.body.winning_option, 'winning_option'),
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('GameFi betting pool resolution failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async publishGameEvent(req, res) {
    try {
      const channel = parseRequiredString(req.body.channel, 'channel');
      const event = parseRequiredString(req.body.event, 'event');
      const payload = req.body.payload && typeof req.body.payload === 'object' ? req.body.payload : {};

      p2pChatHub.publish(`GAME:${channel}`, event, payload);

      return res.status(202).json({
        status: 'accepted',
        channel,
        clients: p2pChatHub.stats().clients,
      });
    } catch (error) {
      logger.error('GameFi event publish failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async moderateP2PMessage(req, res) {
    try {
      const tradeUuid = parseRequiredString(req.body.trade_uuid, 'trade_uuid');
      const message = typeof req.body.message === 'string' ? req.body.message : '';
      const attachment = typeof req.body.attachment === 'string' ? req.body.attachment : '';

      const haystack = `${message} ${attachment}`.toLowerCase();
      const flags = config.p2p.chatFlagKeywords.filter((keyword) => haystack.includes(String(keyword).toLowerCase()));

      return res.json({
        trade_uuid: tradeUuid,
        status: flags.length > 0 ? 'flagged' : 'clear',
        flags,
        warning: flags.length > 0 ? 'Possible off-platform payment or scam attempt detected.' : null,
      });
    } catch (error) {
      logger.error('P2P chat moderation failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async publishP2PTradeEvent(req, res) {
    try {
      const tradeUuid = parseRequiredString(req.body.trade_uuid, 'trade_uuid');
      const event = parseRequiredString(req.body.event, 'event');
      const payload = req.body.payload && typeof req.body.payload === 'object' ? req.body.payload : {};

      p2pChatHub.publish(tradeUuid, event, payload);

      return res.status(202).json({
        status: 'accepted',
        trade_uuid: String(tradeUuid).toUpperCase(),
        clients: p2pChatHub.stats().clients,
      });
    } catch (error) {
      logger.error('P2P event publish failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }
  async mintFinancialNft(req, res) {
    try {
      const result = await financialNftContractService.mintUtilityNft({
        wallet_address: parseRequiredString(req.body.wallet_address, 'wallet_address'),
        utility_type: parseRequiredString(req.body.utility_type, 'utility_type'),
        tier: req.body.tier || 'standard',
        metadata_url: parseRequiredString(req.body.metadata_url, 'metadata_url'),
        mint_fee_exa: parseRequiredString(String(req.body.mint_fee_exa ?? '0'), 'mint_fee_exa'),
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('Financial NFT mint failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async verifyFinancialNftOwnership(req, res) {
    try {
      const result = await financialNftContractService.verifyOwnership({
        token_id: Number(req.body.token_id || 0),
        wallet_address: parseRequiredString(req.body.wallet_address || req.body.owner_wallet, 'wallet_address'),
      });

      return res.json(result);
    } catch (error) {
      logger.error('Financial NFT ownership verification failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async createFinancialNftListing(req, res) {
    try {
      const result = await financialNftContractService.createListing({
        token_id: Number(req.body.token_id || 0),
        price_exa: parseRequiredString(String(req.body.price_exa ?? ''), 'price_exa'),
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('Financial NFT listing creation failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async buyFinancialNftListing(req, res) {
    try {
      const result = await financialNftContractService.buyListing({
        listing_id: Number(req.body.listing_id || 0),
        buyer_wallet: parseRequiredString(req.body.buyer_wallet || req.body.wallet_address, 'buyer_wallet'),
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('Financial NFT listing purchase failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async createFinancialNftAuction(req, res) {
    try {
      const result = await financialNftContractService.createAuction({
        token_id: Number(req.body.token_id || 0),
        starting_price_exa: parseRequiredString(String(req.body.starting_price_exa ?? req.body.price_exa ?? ''), 'starting_price_exa'),
        reserve_price_exa: String(req.body.reserve_price_exa ?? '0'),
        ends_at: Number(req.body.ends_at || 0),
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('Financial NFT auction creation failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async placeFinancialNftBid(req, res) {
    try {
      const result = await financialNftContractService.placeBid({
        auction_id: Number(req.body.auction_id || 0),
        bidder_wallet: parseRequiredString(req.body.bidder_wallet || req.body.wallet_address, 'bidder_wallet'),
        bid_amount_exa: parseRequiredString(String(req.body.bid_amount_exa ?? ''), 'bid_amount_exa'),
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('Financial NFT bid failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async finalizeFinancialNftAuction(req, res) {
    try {
      const result = await financialNftContractService.finalizeAuction({
        auction_id: Number(req.body.auction_id || 0),
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('Financial NFT auction finalize failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }

  async upgradeFinancialNft(req, res) {
    try {
      const result = await financialNftContractService.upgradeNft({
        token_id: Number(req.body.token_id || 0),
        target_tier: req.body.target_tier || 'standard',
        target_level: Number(req.body.target_level || 1),
        upgrade_fee_exa: String(req.body.upgrade_fee_exa ?? '0'),
      });

      return res.status(201).json(result);
    } catch (error) {
      logger.error('Financial NFT upgrade failed', { error: error.message, body: req.body });
      return res.status(422).json({ error: error.message });
    }
  }
}

module.exports = new BlockchainController();
