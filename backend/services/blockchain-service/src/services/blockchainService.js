const { ethers } = require('ethers');
const blockchain = require('./blockchain');
const config = require('../config');
const lotteryContract = require('../contracts/LotteryContract.json');
const nftContract = require('../contracts/NFTContract.json');

const registry = {
  lottery: {
    address: () => config.contracts.lottery || process.env.LOTTERY_CONTRACT_ADDRESS || '',
    abi: lotteryContract.abi,
  },
  gameFiLottery: {
    address: () => config.contracts.gameFiLottery || config.contracts.lottery || '',
    abi: lotteryContract.abi,
  },
  nft: {
    address: () => config.contracts.nft || process.env.FINANCIAL_NFT_CONTRACT_ADDRESS || '',
    abi: nftContract.abi,
  },
  financialNft: {
    address: () => config.contracts.financialNft || config.contracts.nft || '',
    abi: nftContract.abi,
  },
};

function serialize(value) {
  return JSON.parse(JSON.stringify(value, (_key, item) => (
    typeof item === 'bigint' ? item.toString() : item
  )));
}

function normalizeArgs(params) {
  return Array.isArray(params) ? params : [];
}

class BlockchainService {
  resolveContract(contract, abi = null, address = null) {
    if (Array.isArray(abi) && address) {
      return { address, abi };
    }

    const key = String(contract || '').trim();
    const known = registry[key];
    if (!known) {
      throw new Error(`Unknown contract: ${key}`);
    }

    const knownAddress = known.address();
    if (!knownAddress || knownAddress === ethers.ZeroAddress) {
      throw new Error(`Contract address is not configured for ${key}`);
    }

    return {
      address: knownAddress,
      abi: known.abi,
    };
  }

  getContract({ contract, network = 'base', abi = null, address = null, signer = false }) {
    const resolved = this.resolveContract(contract, abi, address);
    const runner = signer ? blockchain.getSigner(network) : blockchain.getEthersProvider(network);

    return new ethers.Contract(resolved.address, resolved.abi, runner);
  }

  async sendTransaction({ contract, method, params = [], network = 'base', value = null, abi = null, address = null }) {
    const instance = this.getContract({ contract, network, abi, address, signer: true });
    const overrides = {};
    if (value !== null && value !== undefined && value !== '') {
      overrides.value = ethers.parseEther(String(value));
    }

    const tx = await instance[method](...normalizeArgs(params), overrides);

    return {
      txHash: tx.hash,
      tx_hash: tx.hash,
      status: 'pending',
      network,
      contract,
    };
  }

  async callContract({ contract, method, params = [], network = 'base', abi = null, address = null }) {
    const instance = this.getContract({ contract, network, abi, address, signer: false });
    const result = await instance[method](...normalizeArgs(params));

    return {
      data: serialize(result),
      network,
      contract,
    };
  }

  async getTransactionStatus(txHash, network = 'base') {
    const receipt = await blockchain.getTransactionReceipt(txHash, network);
    if (!receipt) {
      return {
        txHash,
        tx_hash: txHash,
        status: 'pending',
        confirmed: false,
        confirmations: 0,
        network,
      };
    }

    const confirmations = await blockchain.getConfirmations(txHash, network);

    return {
      txHash,
      tx_hash: txHash,
      status: receipt.status === 1 ? 'confirmed' : 'failed',
      confirmed: receipt.status === 1,
      confirmations,
      block_number: receipt.blockNumber,
      network,
    };
  }
}

module.exports = new BlockchainService();
