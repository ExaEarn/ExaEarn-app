const { ethers } = require('ethers');
const config = require('../config');
const contractInteractionService = require('./contractInteractionService');
const blockchain = require('./blockchain');

const GAMEFI_ABI = [
  'function nextRoundId() view returns (uint256)',
  'function nextPoolId() view returns (uint256)',
  'function lotteryRounds(uint256 roundId) view returns (uint256 id,uint256 entryFee,uint256 maxPlayers,uint256 playerCount,uint256 jackpot,uint256 drawAt,bool drawn,address winner)',
  'function bettingPools(uint256 poolId) view returns (uint256 id,string eventName,uint256 lockAt,bool resolved,string winningOption,uint256 totalPool)',
  'function createLotteryRound(uint256 entryFee,uint256 maxPlayers,uint256 drawAt) returns (uint256)',
  'function createBettingPool(string calldata eventName,string[] calldata options,uint256 lockAt) returns (uint256)',
  'function resolveBettingPool(uint256 poolId,string calldata winningOption)',
  'event LotteryEntered(uint256 indexed roundId,address indexed player,uint256 amount,uint256 jackpot)',
  'event LotteryWinnerSelected(uint256 indexed roundId,address indexed winner,uint256 jackpot)',
  'event BetPlaced(uint256 indexed poolId,address indexed player,string option,uint256 amount)',
];

function requireGameContract() {
  const address = config.contracts.lottery;
  if (!address) {
    throw new Error('GameFi contract not configured');
  }

  return address;
}

function toWei(value) {
  return ethers.parseEther(String(value)).toString();
}

class GameFiContractService {
  async createLotteryRound(payload) {
    const address = requireGameContract();
    const nextRoundId = await contractInteractionService.read({
      address,
      abi: GAMEFI_ABI,
      method: 'nextRoundId',
      args: [],
    });
    const result = await contractInteractionService.write({
      address,
      abi: GAMEFI_ABI,
      method: 'createLotteryRound',
      args: [
        toWei(payload.entry_fee_eth),
        Number(payload.max_players || 0),
        Number(payload.draw_at || 0),
      ],
    });

    return {
      ...result,
      round_id: Number(nextRoundId),
      contract_address: address,
    };
  }

  async verifyLotteryEntry(payload) {
    const address = requireGameContract();
    const receipt = await blockchain.getTransactionReceipt(payload.tx_hash);
    if (!receipt) {
      return { confirmed: false, reason: 'receipt_missing' };
    }

    const iface = new ethers.Interface(GAMEFI_ABI);
    const expectedRoundId = Number(payload.round_id || 0);
    const expectedWallet = String(payload.wallet_address || '').toLowerCase();
    const expectedFee = toWei(payload.entry_fee_eth);

    for (const log of receipt.logs) {
      if (log.address.toLowerCase() !== address.toLowerCase()) continue;
      try {
        const parsed = iface.parseLog(log);
        if (parsed.name !== 'LotteryEntered') continue;
        const roundId = Number(parsed.args.roundId);
        const player = String(parsed.args.player).toLowerCase();
        const amount = String(parsed.args.amount);

        if (roundId === expectedRoundId && player === expectedWallet && amount === expectedFee) {
          return {
            confirmed: true,
            round_id: roundId,
            player,
            amount_wei: amount,
            contract_address: address,
          };
        }
      } catch (error) {
      }
    }

    return { confirmed: false, reason: 'entry_event_not_found' };
  }

  async fetchLotteryResult(payload) {
    const address = requireGameContract();
    const roundId = Number(payload.round_id);
    const round = await contractInteractionService.read({
      address,
      abi: GAMEFI_ABI,
      method: 'lotteryRounds',
      args: [roundId],
    });

    return {
      drawn: Boolean(round[6]),
      winner_wallet: round[7],
      jackpot_amount_eth: ethers.formatEther(String(round[4] || 0)),
    };
  }

  async createBettingPool(payload) {
    const address = requireGameContract();
    const nextPoolId = await contractInteractionService.read({
      address,
      abi: GAMEFI_ABI,
      method: 'nextPoolId',
      args: [],
    });
    const result = await contractInteractionService.write({
      address,
      abi: GAMEFI_ABI,
      method: 'createBettingPool',
      args: [
        String(payload.event_name),
        Array.isArray(payload.bet_options) ? payload.bet_options.map(String) : [],
        Number(payload.locking_at || 0),
      ],
    });

    return {
      ...result,
      pool_id: Number(nextPoolId),
      contract_address: address,
    };
  }

  async verifyBettingEntry(payload) {
    const address = requireGameContract();
    const receipt = await blockchain.getTransactionReceipt(payload.tx_hash);
    if (!receipt) {
      return { confirmed: false, reason: 'receipt_missing' };
    }

    const iface = new ethers.Interface(GAMEFI_ABI);
    const expectedPoolId = Number(payload.pool_id || 0);
    const expectedWallet = String(payload.wallet_address || '').toLowerCase();
    const expectedAmount = toWei(payload.bet_amount_eth);
    const expectedOption = String(payload.bet_option);

    for (const log of receipt.logs) {
      if (log.address.toLowerCase() !== address.toLowerCase()) continue;
      try {
        const parsed = iface.parseLog(log);
        if (parsed.name !== 'BetPlaced') continue;
        const poolId = Number(parsed.args.poolId);
        const player = String(parsed.args.player).toLowerCase();
        const option = String(parsed.args.option);
        const amount = String(parsed.args.amount);

        if (poolId === expectedPoolId && player === expectedWallet && option === expectedOption && amount === expectedAmount) {
          return {
            confirmed: true,
            pool_id: poolId,
            player,
            option,
            amount_wei: amount,
          };
        }
      } catch (error) {
      }
    }

    return { confirmed: false, reason: 'bet_event_not_found' };
  }

  async resolveBettingPool(payload) {
    const address = requireGameContract();
    return contractInteractionService.write({
      address,
      abi: GAMEFI_ABI,
      method: 'resolveBettingPool',
      args: [Number(payload.pool_id), String(payload.winning_option)],
    });
  }
}

module.exports = new GameFiContractService();
