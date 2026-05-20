const axios = require('axios');
const { ethers } = require('ethers');
const blockchain = require('./blockchain');
const config = require('../config');
const logger = require('../utils/logger');
const lotteryContract = require('../contracts/LotteryContract.json');
const nftContract = require('../contracts/NFTContract.json');

const listeners = [];

function contractAddress(name) {
  return config.contracts[name] && config.contracts[name] !== ethers.ZeroAddress
    ? config.contracts[name]
    : '';
}

function eventPayload(eventName, log, args, network) {
  const base = {
    event: eventName,
    network,
    txHash: log.transactionHash,
    tx_hash: log.transactionHash,
    block_number: log.blockNumber,
    contract_address: log.address,
  };

  if (eventName === 'LotteryEntered') {
    return {
      ...base,
      round_id: Number(args.roundId),
      wallet_address: String(args.player).toLowerCase(),
      amount_wei: args.amount.toString(),
      jackpot_wei: args.jackpot.toString(),
    };
  }

  if (eventName === 'WinnerSelected') {
    return {
      ...base,
      round_id: Number(args.roundId),
      wallet_address: String(args.winner).toLowerCase(),
      jackpot_wei: args.jackpot.toString(),
    };
  }

  if (eventName === 'NFTMinted') {
    return {
      ...base,
      token_id: args.tokenId.toString(),
      wallet_address: String(args.owner).toLowerCase(),
      utility_type: args.utilityType,
      tier: args.tier,
      metadata_uri: args.metadataURI,
      mint_fee_wei: args.mintFee.toString(),
    };
  }

  return base;
}

async function notifyLaravel(payload) {
  const url = `${config.laravelApiUrl.replace(/\/$/, '')}/blockchain/event`;
  await axios.post(url, payload, {
    timeout: 10000,
    headers: {
      'Accept': 'application/json',
      'X-Webhook-Secret': config.webhookSecret,
    },
  });
}

function attachContractListener({ name, address, abi, events, network }) {
  const provider = blockchain.getEthersWsProvider(network) || blockchain.getEthersProvider(network);
  const contract = new ethers.Contract(address, abi, provider);

  for (const item of events) {
    const handler = async (...values) => {
      const log = values[values.length - 1]?.log || values[values.length - 1];
      const args = {};

      item.argNames.forEach((argName, index) => {
        args[argName] = values[index];
      });

      const payload = eventPayload(item.webhookName, log, args, network);

      try {
        await notifyLaravel(payload);
        logger.info('Blockchain event delivered to Laravel', {
          event: payload.event,
          txHash: payload.txHash,
          network,
        });
      } catch (error) {
        logger.error('Failed to deliver blockchain event webhook', {
          event: payload.event,
          txHash: payload.txHash,
          network,
          error: error.message,
        });
      }
    };

    contract.on(item.contractName, handler);
    listeners.push({ contract, event: item.contractName, handler, name, network });
  }
}

class BlockchainEventListener {
  start() {
    if (!config.eventListenerEnabled) {
      logger.info('Blockchain event listener disabled');
      return;
    }

    const network = process.env.BLOCKCHAIN_EVENT_NETWORK || 'base';
    const lotteryAddress = contractAddress('lottery') || contractAddress('gameFiLottery');
    const nftAddress = contractAddress('nft') || contractAddress('financialNft');

    if (lotteryAddress) {
      attachContractListener({
        name: 'lottery',
        address: lotteryAddress,
        abi: lotteryContract.abi,
        network,
        events: [
          { contractName: 'LotteryEntered', webhookName: 'LotteryEntered', argNames: ['roundId', 'player', 'amount', 'jackpot'] },
          { contractName: 'LotteryWinnerSelected', webhookName: 'WinnerSelected', argNames: ['roundId', 'winner', 'jackpot'] },
        ],
      });
    }

    if (nftAddress) {
      attachContractListener({
        name: 'nft',
        address: nftAddress,
        abi: nftContract.abi,
        network,
        events: [
          { contractName: 'NFTMinted', webhookName: 'NFTMinted', argNames: ['tokenId', 'owner', 'utilityType', 'tier', 'metadataURI', 'mintFee'] },
        ],
      });
    }

    logger.info('Blockchain event listener started', {
      network,
      listeners: listeners.length,
    });
  }
}

module.exports = new BlockchainEventListener();
