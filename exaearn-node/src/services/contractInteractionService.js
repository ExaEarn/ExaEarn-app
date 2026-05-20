const { ethers } = require('ethers');
const blockchain = require('./blockchain');
const config = require('../config');

class ContractInteractionService {
  getKnownContract(name) {
    const address = config.contracts[name];
    if (!address) {
      throw new Error(`Contract not configured for ${name}`);
    }

    return address;
  }

  async read({ address, abi, method, args = [] }) {
    const contract = new ethers.Contract(address, abi, blockchain.getEthersProvider());
    return await contract[method](...args);
  }

  async write({ address, abi, method, args = [], overrides = {} }) {
    const contract = new ethers.Contract(address, abi, blockchain.getSigner());
    const tx = await contract[method](...args, overrides);
    const receipt = await tx.wait(1);

    return {
      tx_hash: tx.hash,
      status: receipt?.status === 1 ? 'completed' : 'failed',
      block_number: receipt?.blockNumber ?? null,
    };
  }

  async transferToken({ currency, to, amount }) {
    return await require('./withdrawalBroadcaster').broadcast({
      transaction_id: `contract-${Date.now()}`,
      currency,
      network: config.getNetworkForCurrency(currency) || 'base',
      to_address: to,
      amount,
    });
  }
}

module.exports = new ContractInteractionService();
