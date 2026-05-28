const contractInteractionService = require('./contractInteractionService');
const config = require('../config');

const rewardDistributorAbi = [
  'function distribute(address to, uint256 amount, bytes32 rewardType, bytes32 campaignId) external',
];

class RewardDistributorService {
  async distribute({ walletAddress, amount, token, activityType, rewardId }) {
    if (!/^0x[a-fA-F0-9]{40}$/.test(walletAddress)) {
      throw new Error('Invalid wallet address');
    }

    if (!config.contracts.rewardDistribution) {
      throw new Error('Reward distributor contract is not configured');
    }

    const tokenConfig = config.getTokenConfig(token);
    if (!tokenConfig) {
      throw new Error(`Unsupported reward token: ${token}`);
    }

    const decimals = Number(tokenConfig.decimals || 18);
    const amountInUnits = BigInt(require('ethers').ethers.parseUnits(String(amount), decimals).toString());
    const rewardType = require('ethers').ethers.encodeBytes32String(String(activityType).slice(0, 31));
    const campaignId = require('ethers').ethers.encodeBytes32String(`reward-${rewardId}`.slice(0, 31));

    return await contractInteractionService.write({
      address: config.contracts.rewardDistribution,
      abi: rewardDistributorAbi,
      method: 'distribute',
      args: [walletAddress, amountInUnits, rewardType, campaignId],
    });
  }
}

module.exports = new RewardDistributorService();
