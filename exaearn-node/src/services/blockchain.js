/**
 * Blockchain Provider Manager
 *
 * Manages connections to supported EVM networks and exposes network-aware
 * helpers for providers, signers, token contracts, and transaction queries.
 */

const { ethers } = require('ethers');
const { Web3 } = require('web3');
const config = require('../config');
const hdWalletService = require('./hdWalletService');
const logger = require('../utils/logger');

const ERC20_ABI = [
  'event Transfer(address indexed from, address indexed to, uint256 value)',
  'function balanceOf(address owner) view returns (uint256)',
  'function transfer(address to, uint256 amount) returns (bool)',
  'function decimals() view returns (uint8)',
  'function symbol() view returns (string)',
  'function allowance(address owner, address spender) view returns (uint256)',
];

class BlockchainProvider {
  constructor() {
    this._ethersProviders = new Map();
    this._ethersWsProviders = new Map();
    this._web3Instances = new Map();
    this._signers = new Map();
    this._tokenContracts = new Map();
  }

  normalizeNetwork(network) {
    return String(network || 'base').toLowerCase();
  }

  ensureEvmNetwork(network) {
    const normalized = this.normalizeNetwork(network);
    if (!config.evmNetworks.includes(normalized)) {
      throw new Error(`Unsupported EVM network: ${normalized}`);
    }
    return normalized;
  }

  getEthersProvider(network = 'base') {
    const normalized = this.ensureEvmNetwork(network);

    if (!this._ethersProviders.has(normalized)) {
      const networkConfig = config.getNetworkConfig(normalized);
      this._ethersProviders.set(
        normalized,
        new ethers.JsonRpcProvider(networkConfig.rpcUrl, {
          name: normalized,
          chainId: networkConfig.chainId,
        })
      );

      logger.info('Ethers.js JSON-RPC provider initialized', {
        network: normalized,
        rpcUrl: networkConfig.rpcUrl,
        chainId: networkConfig.chainId,
      });
    }

    return this._ethersProviders.get(normalized);
  }

  getEthersWsProvider(network = 'base') {
    const normalized = this.ensureEvmNetwork(network);
    const networkConfig = config.getNetworkConfig(normalized);

    if (!networkConfig.wssUrl) {
      return null;
    }

    if (!this._ethersWsProviders.has(normalized)) {
      const wsProvider = new ethers.WebSocketProvider(networkConfig.wssUrl, {
        name: normalized,
        chainId: networkConfig.chainId,
      });

      wsProvider.websocket.on('close', () => {
        logger.warn('WebSocket provider disconnected', { network: normalized });
        this._ethersWsProviders.delete(normalized);
      });

      this._ethersWsProviders.set(normalized, wsProvider);
      logger.info('Ethers.js WebSocket provider initialized', { network: normalized });
    }

    return this._ethersWsProviders.get(normalized);
  }

  getWeb3(network = 'base') {
    const normalized = this.ensureEvmNetwork(network);

    if (!this._web3Instances.has(normalized)) {
      const networkConfig = config.getNetworkConfig(normalized);
      this._web3Instances.set(normalized, new Web3(networkConfig.rpcUrl));

      logger.info('Web3.js instance initialized', {
        network: normalized,
        rpcUrl: networkConfig.rpcUrl,
      });
    }

    return this._web3Instances.get(normalized);
  }

  getSigner(network = 'base') {
    const normalized = this.ensureEvmNetwork(network);

    if (!this._signers.has(normalized)) {
      const networkConfig = config.getNetworkConfig(normalized);
      const privateKey = networkConfig.hotWallet?.privateKey;

      if (!privateKey) {
        throw new Error(`${normalized.toUpperCase()} hot wallet private key not configured`);
      }

      const signer = new ethers.Wallet(privateKey, this.getEthersProvider(normalized));
      this._signers.set(normalized, signer);

      logger.info('Hot wallet signer initialized', {
        network: normalized,
        address: signer.address,
      });
    }

    return this._signers.get(normalized);
  }

  getTokenContract(currency, network) {
    const upper = String(currency).toUpperCase();
    const tokenConfig = config.getTokenConfig(upper);

    if (!tokenConfig || !tokenConfig.address) {
      throw new Error(`Token contract address not configured for ${upper}`);
    }

    const targetNetwork = this.ensureEvmNetwork(network || tokenConfig.network);
    if (this.normalizeNetwork(tokenConfig.network) !== targetNetwork) {
      throw new Error(`${upper} is configured for ${tokenConfig.network}, not ${targetNetwork}`);
    }

    const cacheKey = `${targetNetwork}:${upper}`;
    if (!this._tokenContracts.has(cacheKey)) {
      this._tokenContracts.set(
        cacheKey,
        new ethers.Contract(tokenConfig.address, ERC20_ABI, this.getEthersProvider(targetNetwork))
      );

      logger.info('ERC-20 contract loaded', {
        currency: upper,
        network: targetNetwork,
        address: tokenConfig.address,
      });
    }

    return this._tokenContracts.get(cacheKey);
  }

  getSignedTokenContract(currency, network) {
    return this.getTokenContract(currency, network).connect(this.getSigner(network));
  }

  async getBlockNumber(network = 'base') {
    return this.getEthersProvider(network).getBlockNumber();
  }

  async getTransactionReceipt(txHash, network = 'base') {
    return this.getEthersProvider(network).getTransactionReceipt(txHash);
  }

  async getConfirmations(txHash, network = 'base') {
    const provider = this.getEthersProvider(network);
    const receipt = await provider.getTransactionReceipt(txHash);
    if (!receipt) {
      return 0;
    }

    const currentBlock = await provider.getBlockNumber();
    return currentBlock - receipt.blockNumber;
  }

  async healthCheck() {
    const results = {};

    for (const network of config.evmNetworks) {
      try {
        const provider = this.getEthersProvider(network);
        const blockNumber = await provider.getBlockNumber();
        const chain = await provider.getNetwork();

        results[network] = {
          connected: true,
          blockNumber,
          chainId: Number(chain.chainId),
          network: chain.name,
        };
      } catch (error) {
        results[network] = {
          connected: false,
          error: error.message,
        };
      }
    }

    return results;
  }

  createRandomWallet() {
    const wallet = ethers.Wallet.createRandom();
    return {
      address: wallet.address,
      privateKey: wallet.privateKey,
      mnemonic: wallet.mnemonic?.phrase || null,
    };
  }

  deriveWallet(index, network = 'base') {
    const normalizedNetwork = this.ensureEvmNetwork(network);
    const derived = hdWalletService.deriveEvmAddress({
      userId: Number(index),
      currency: normalizedNetwork.toUpperCase(),
      network: normalizedNetwork,
    });

    return {
      address: derived.address,
      derivationPath: derived.metadata.derivation_path,
      addressIndex: derived.metadata.address_index,
    };
  }
}

module.exports = new BlockchainProvider();
