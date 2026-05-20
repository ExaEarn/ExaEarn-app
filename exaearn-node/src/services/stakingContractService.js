const { ethers } = require('ethers');
const blockchain = require('./blockchain');
const config = require('../config');

const STAKING_ABI = [
  'function createPool(uint256 lockPeriod,uint256 rewardRate,uint256 multiplierBps,uint256 poolSize) returns (uint256)',
  'function stakeFor(uint256 userId,uint256 poolId,uint256 amount,bool autoCompound)',
  'function claimRewardFor(uint256 userId,uint256 poolId) returns (uint256)',
  'function compoundRewardFor(uint256 userId,uint256 poolId) returns (uint256)',
  'function unstakeFor(uint256 userId,uint256 poolId,uint256 amount) returns (uint256)',
  'function pendingReward(uint256 userId,uint256 poolId) view returns (uint256)',
  'function pools(uint256 poolId) view returns (uint256 lockPeriod,uint256 rewardRate,uint256 multiplierBps,uint256 poolSize,uint256 totalStaked,bool active)',
];

class StakingContractService {
  contract() {
    if (!config.contracts.staking) {
      throw new Error('Staking contract address is not configured');
    }

    return new ethers.Contract(
      config.contracts.staking,
      STAKING_ABI,
      blockchain.getSigner()
    );
  }

  async stake({ userId, poolId, amount, autoCompound }) {
    const tx = await this.contract().stakeFor(userId, poolId, amount, autoCompound);
    const receipt = await tx.wait(1);
    return { tx_hash: tx.hash, status: receipt?.status === 1 ? 'completed' : 'failed', block_number: receipt?.blockNumber ?? null };
  }

  async claim({ userId, poolId }) {
    const tx = await this.contract().claimRewardFor(userId, poolId);
    const receipt = await tx.wait(1);
    return { tx_hash: tx.hash, status: receipt?.status === 1 ? 'completed' : 'failed', block_number: receipt?.blockNumber ?? null };
  }

  async compound({ userId, poolId }) {
    const tx = await this.contract().compoundRewardFor(userId, poolId);
    const receipt = await tx.wait(1);
    return { tx_hash: tx.hash, status: receipt?.status === 1 ? 'completed' : 'failed', block_number: receipt?.blockNumber ?? null };
  }

  async unstake({ userId, poolId, amount }) {
    const tx = await this.contract().unstakeFor(userId, poolId, amount);
    const receipt = await tx.wait(1);
    return { tx_hash: tx.hash, status: receipt?.status === 1 ? 'completed' : 'failed', block_number: receipt?.blockNumber ?? null };
  }

  async getPool(poolId) {
    const pool = await this.contract().pools(poolId);
    return {
      lock_period: pool.lockPeriod.toString(),
      reward_rate: pool.rewardRate.toString(),
      multiplier_bps: pool.multiplierBps.toString(),
      pool_size: pool.poolSize.toString(),
      total_staked: pool.totalStaked.toString(),
      active: pool.active,
    };
  }

  async pendingReward(userId, poolId) {
    const reward = await this.contract().pendingReward(userId, poolId);
    return reward.toString();
  }
}

module.exports = new StakingContractService();
