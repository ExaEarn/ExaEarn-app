const axios = require('axios');
const config = require('../config');
const logger = require('../utils/logger');
const optionalModule = require('../utils/optionalModule');

class NonEvmChainService {
  constructor() {
    this._xrplClient = null;
    this._tronWeb = null;
    this._solanaConnection = null;
    this._tonClient = null;
  }

  normalizeNetwork(network) {
    const normalized = String(network || '').toLowerCase();
    if (normalized === 'xrp') {
      return 'xrpl';
    }
    return normalized;
  }

  async verifyTransaction(txHash, network) {
    const normalized = this.normalizeNetwork(network);

    switch (normalized) {
      case 'xrpl':
        return this.verifyXrplTransaction(txHash);
      case 'bitcoin':
        return this.verifyBitcoinTransaction(txHash);
      case 'tron':
        return this.verifyTronTransaction(txHash);
      case 'solana':
        return this.verifySolanaTransaction(txHash);
      case 'ton':
        return this.verifyTonTransaction(txHash);
      default:
        throw new Error(`Unsupported non-EVM verification network: ${normalized}`);
    }
  }

  async broadcastWithdrawal({ network, toAddress, amount }) {
    const normalized = this.normalizeNetwork(network);

    switch (normalized) {
      case 'xrpl':
        return this.broadcastXrplWithdrawal(toAddress, amount);
      case 'bitcoin':
        return this.broadcastBitcoinWithdrawal(toAddress, amount);
      case 'tron':
        return this.broadcastTronWithdrawal(toAddress, amount);
      case 'solana':
        return this.broadcastSolanaWithdrawal(toAddress, amount);
      case 'ton':
        return this.broadcastTonWithdrawal(toAddress, amount);
      default:
        throw new Error(`Unsupported non-EVM withdrawal network: ${normalized}`);
    }
  }

  async getHotWalletBalances() {
    const balances = {};

    try {
      balances.xrpl = await this.getXrplHotWalletBalance();
    } catch (error) {
      balances.xrpl = { error: error.message };
    }

    try {
      balances.bitcoin = await this.getBitcoinHotWalletBalance();
    } catch (error) {
      balances.bitcoin = { error: error.message };
    }

    try {
      balances.tron = await this.getTronHotWalletBalance();
    } catch (error) {
      balances.tron = { error: error.message };
    }

    try {
      balances.solana = await this.getSolanaHotWalletBalance();
    } catch (error) {
      balances.solana = { error: error.message };
    }

    try {
      balances.ton = await this.getTonHotWalletBalance();
    } catch (error) {
      balances.ton = { error: error.message };
    }

    return balances;
  }

  async getCurrentXrplLedger() {
    const client = await this.getXrplClient();
    const response = await client.request({ command: 'ledger_current' });
    return Number(response.result?.ledger_current_index || 0);
  }

  async fetchXrplIncomingPayments(minLedger = -1) {
    const client = await this.getXrplClient();
    const response = await client.request({
      command: 'account_tx',
      account: config.xrpl.hotWallet.address,
      ledger_index_min: minLedger,
      ledger_index_max: -1,
      binary: false,
      forward: true,
      limit: 200,
    });

    return Array.isArray(response.result?.transactions) ? response.result.transactions : [];
  }

  async getXrplClient() {
    if (!this._xrplClient) {
      const xrpl = optionalModule('xrpl', 'pnpm add xrpl');
      this._xrplClient = new xrpl.Client(config.xrpl.wssUrl || config.xrpl.rpcUrl);
      await this._xrplClient.connect();
      logger.info('XRPL client connected');
    }

    return this._xrplClient;
  }

  async verifyXrplTransaction(txHash) {
    const client = await this.getXrplClient();
    const response = await client.request({
      command: 'tx',
      transaction: txHash,
    });

    const tx = response.result || {};
    const currentLedger = await this.getCurrentXrplLedger();
    const ledgerIndex = Number(tx.ledger_index || 0);
    const confirmations = ledgerIndex > 0 ? Math.max(currentLedger - ledgerIndex + 1, 0) : 0;

    return {
      confirmed: Boolean(tx.validated),
      confirmations,
      block_number: ledgerIndex || null,
      tx_hash: tx.hash || txHash,
    };
  }

  async broadcastXrplWithdrawal(toAddress, amount) {
    const xrpl = optionalModule('xrpl', 'pnpm add xrpl');
    if (!config.xrpl.hotWallet.secret) {
      throw new Error('XRPL_HOT_WALLET_SECRET not configured');
    }

    const client = await this.getXrplClient();
    const wallet = xrpl.Wallet.fromSeed(config.xrpl.hotWallet.secret);
    const payment = {
      TransactionType: 'Payment',
      Account: wallet.address,
      Destination: toAddress,
      Amount: xrpl.xrpToDrops(amount),
    };

    const prepared = await client.autofill(payment);
    const signed = wallet.sign(prepared);
    const submitted = await client.submitAndWait(signed.tx_blob);
    const txHash = submitted.result?.hash || signed.hash;

    if (submitted.result?.validated !== true) {
      throw new Error(`XRPL withdrawal was not validated: ${txHash}`);
    }

    return txHash;
  }

  async getXrplHotWalletBalance() {
    const client = await this.getXrplClient();
    const response = await client.request({
      command: 'account_info',
      account: config.xrpl.hotWallet.address,
      ledger_index: 'validated',
    });

    const balanceDrops = response.result?.account_data?.Balance || '0';
    return {
      address: config.xrpl.hotWallet.address,
      native: (Number(balanceDrops) / 1_000_000).toFixed(6),
    };
  }

  async callBitcoinRpc(method, params = []) {
    if (!config.bitcoin.rpcUrl) {
      throw new Error('BITCOIN_RPC_URL not configured');
    }

    const url = new URL(config.bitcoin.rpcUrl);
    const auth = url.username || url.password
      ? {
          username: decodeURIComponent(url.username),
          password: decodeURIComponent(url.password),
        }
      : undefined;

    const rpcUrl = `${url.protocol}//${url.host}${url.pathname}`;
    const response = await axios.post(
      rpcUrl,
      {
        jsonrpc: '1.0',
        id: `${method}-${Date.now()}`,
        method,
        params,
      },
      {
        auth,
        timeout: 15000,
      }
    );

    if (response.data?.error) {
      throw new Error(response.data.error.message || `Bitcoin RPC ${method} failed`);
    }

    return response.data?.result;
  }

  async verifyBitcoinTransaction(txHash) {
    const tx = await this.callBitcoinRpc('getrawtransaction', [txHash, true]);
    return {
      confirmed: Number(tx.confirmations || 0) >= config.bitcoin.minConfirmations,
      confirmations: Number(tx.confirmations || 0),
      block_number: tx.blockheight || null,
      tx_hash: tx.txid || txHash,
    };
  }

  async broadcastBitcoinWithdrawal(toAddress, amount) {
    return this.callBitcoinRpc('sendtoaddress', [toAddress, Number(amount)]);
  }

  async getBitcoinHotWalletBalance() {
    const balance = await this.callBitcoinRpc('getbalance', []);
    return {
      address: config.bitcoin.hotWallet.address || 'bitcoin-core-wallet',
      native: String(balance),
    };
  }

  getTronWeb() {
    if (!this._tronWeb) {
      const TronWebModule = optionalModule('tronweb', 'pnpm add tronweb');
      const TronWeb = TronWebModule.TronWeb || TronWebModule.default || TronWebModule;
      this._tronWeb = new TronWeb({
        fullHost: config.tron.fullHost,
        privateKey: config.tron.hotWallet.privateKey,
      });
      logger.info('TronWeb initialized');
    }

    return this._tronWeb;
  }

  async verifyTronTransaction(txHash) {
    const tronWeb = this.getTronWeb();
    const info = await tronWeb.trx.getTransactionInfo(txHash);
    const currentBlock = await tronWeb.trx.getCurrentBlock();
    const blockNumber = Number(info.blockNumber || 0);
    const currentBlockNumber = Number(currentBlock?.block_header?.raw_data?.number || 0);
    const confirmations = blockNumber > 0 ? Math.max(currentBlockNumber - blockNumber + 1, 0) : 0;

    return {
      confirmed: String(info.receipt?.result || '').toUpperCase() === 'SUCCESS'
        && confirmations >= config.tron.minConfirmations,
      confirmations,
      block_number: blockNumber || null,
      tx_hash: txHash,
    };
  }

  async broadcastTronWithdrawal(toAddress, amount) {
    const tronWeb = this.getTronWeb();
    const amountSun = Math.round(Number(amount) * 1_000_000);
    const result = await tronWeb.trx.sendTransaction(toAddress, amountSun);

    if (!result?.result && !result?.txid) {
      throw new Error('Tron withdrawal broadcast failed');
    }

    return result.txid;
  }

  async getTronHotWalletBalance() {
    const tronWeb = this.getTronWeb();
    const address = config.tron.hotWallet.address || tronWeb.defaultAddress?.base58;
    const balanceSun = await tronWeb.trx.getBalance(address);

    return {
      address,
      native: (Number(balanceSun) / 1_000_000).toFixed(6),
    };
  }

  getSolanaConnection() {
    if (!this._solanaConnection) {
      const solana = optionalModule('@solana/web3.js', 'pnpm add @solana/web3.js');
      this._solanaConnection = new solana.Connection(config.solana.rpcUrl, 'confirmed');
      logger.info('Solana connection initialized');
    }

    return this._solanaConnection;
  }

  getSolanaKeypair() {
    const solana = optionalModule('@solana/web3.js', 'pnpm add @solana/web3.js');
    if (!config.solana.hotWallet.secretKey) {
      throw new Error('SOLANA_HOT_WALLET_SECRET_KEY not configured');
    }

    const secret = JSON.parse(config.solana.hotWallet.secretKey);
    return solana.Keypair.fromSecretKey(Uint8Array.from(secret));
  }

  async verifySolanaTransaction(txHash) {
    const connection = this.getSolanaConnection();
    const status = await connection.getSignatureStatus(txHash, { searchTransactionHistory: true });
    const value = status.value;

    return {
      confirmed: Boolean(value?.confirmationStatus === 'confirmed' || value?.confirmationStatus === 'finalized'),
      confirmations: value?.confirmations === null ? config.solana.minConfirmations : Number(value?.confirmations || 0),
      block_number: value?.slot || null,
      tx_hash: txHash,
    };
  }

  async broadcastSolanaWithdrawal(toAddress, amount) {
    const solana = optionalModule('@solana/web3.js', 'pnpm add @solana/web3.js');
    const connection = this.getSolanaConnection();
    const keypair = this.getSolanaKeypair();
    const lamports = Math.round(Number(amount) * solana.LAMPORTS_PER_SOL);
    const transaction = new solana.Transaction().add(
      solana.SystemProgram.transfer({
        fromPubkey: keypair.publicKey,
        toPubkey: new solana.PublicKey(toAddress),
        lamports,
      })
    );

    const signature = await solana.sendAndConfirmTransaction(connection, transaction, [keypair]);
    return signature;
  }

  async getSolanaHotWalletBalance() {
    const solana = optionalModule('@solana/web3.js', 'pnpm add @solana/web3.js');
    const connection = this.getSolanaConnection();
    const address = config.solana.hotWallet.address || this.getSolanaKeypair().publicKey.toBase58();
    const balance = await connection.getBalance(new solana.PublicKey(address));

    return {
      address,
      native: String(balance / solana.LAMPORTS_PER_SOL),
    };
  }

  getTonClient() {
    if (!this._tonClient) {
      const ton = optionalModule('@ton/ton', 'pnpm add @ton/ton @ton/crypto');
      this._tonClient = new ton.TonClient({
        endpoint: config.ton.rpcUrl,
      });
      logger.info('TON client initialized');
    }

    return this._tonClient;
  }

  async verifyTonTransaction(txHash) {
    return {
      confirmed: false,
      confirmations: 0,
      block_number: null,
      tx_hash: txHash,
    };
  }

  async broadcastTonWithdrawal(toAddress, amount) {
    const ton = optionalModule('@ton/ton', 'pnpm add @ton/ton @ton/crypto');
    const tonCrypto = optionalModule('@ton/crypto', 'pnpm add @ton/ton @ton/crypto');

    if (!config.ton.hotWallet.mnemonic) {
      throw new Error('TON_HOT_WALLET_MNEMONIC not configured');
    }

    const client = this.getTonClient();
    const mnemonicWords = config.ton.hotWallet.mnemonic.trim().split(/\s+/);
    const keyPair = await tonCrypto.mnemonicToPrivateKey(mnemonicWords);
    const wallet = ton.WalletContractV4.create({
      workchain: 0,
      publicKey: keyPair.publicKey,
    });
    const openedWallet = client.open(wallet);
    const seqno = await openedWallet.getSeqno();

    await openedWallet.sendTransfer({
      seqno,
      secretKey: keyPair.secretKey,
      sendMode: ton.SendMode.PAY_GAS_SEPARATELY,
      messages: [
        ton.internal({
          to: toAddress,
          value: ton.toNano(amount),
        }),
      ],
    });

    return `ton-seqno-${seqno}`;
  }

  async getTonHotWalletBalance() {
    const ton = optionalModule('@ton/ton', 'pnpm add @ton/ton @ton/crypto');
    const client = this.getTonClient();
    const address = config.ton.hotWallet.address;
    if (!address) {
      throw new Error('TON_HOT_WALLET_ADDRESS not configured');
    }

    const balance = await client.getBalance(ton.Address.parse(address));
    return {
      address,
      native: ton.fromNano(balance),
    };
  }
}

module.exports = new NonEvmChainService();
