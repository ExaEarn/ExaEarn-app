/**
 * Withdrawal Broadcaster
 *
 * Broadcasts withdrawals across supported EVM networks. Native assets and
 * ERC-20 tokens are handled separately. Non-EVM networks remain disabled
 * until their network SDKs are installed and integrated.
 */

const { ethers } = require('ethers');
const config = require('../config');
const logger = require('../utils/logger');
const blockchain = require('./blockchain');
const webhookNotifier = require('./webhookNotifier');
const nonEvmChainService = require('./nonEvmChainService');

class WithdrawalBroadcaster {
  async broadcast(params) {
    const { transaction_id, currency, network, to_address, amount } = params;
    const upper = String(currency).toUpperCase();
    const net = String(network || config.getNetworkForCurrency(upper) || 'base').toLowerCase();

    logger.info('Processing withdrawal broadcast', {
      transaction_id,
      currency: upper,
      network: net,
      to_address,
      amount,
    });

    try {
      let txHash;

      if (config.evmNetworks.includes(net)) {
        if (!ethers.isAddress(to_address)) {
          throw new Error(`Invalid EVM address: ${to_address}`);
        }

        txHash = await this._broadcastEvmAsset({
          currency: upper,
          network: net,
          toAddress: to_address,
          amount,
        });
      } else {
        txHash = await nonEvmChainService.broadcastWithdrawal({
          currency: upper,
          network: net,
          toAddress: to_address,
          amount,
        });
      }

      await webhookNotifier.notifyWithdrawalConfirmation({
        transaction_id,
        tx_hash: txHash,
        status: 'completed',
        metadata: {
          network: net,
          to_address,
          amount,
          broadcast_at: new Date().toISOString(),
        },
      });

      return { tx_hash: txHash, status: 'completed' };
    } catch (error) {
      logger.error('Withdrawal broadcast failed', {
        transaction_id,
        currency: upper,
        network: net,
        to_address,
        amount,
        error: error.message,
      });

      await webhookNotifier.notifyWithdrawalConfirmation({
        transaction_id,
        tx_hash: null,
        status: 'failed',
        failure_reason: error.message,
        metadata: {
          network: net,
          error_code: error.code || 'BROADCAST_ERROR',
        },
      });

      throw error;
    }
  }

  async _broadcastEvmAsset({ currency, network, toAddress, amount }) {
    const tokenConfig = config.getTokenConfig(currency);
    if (tokenConfig?.address) {
      return this._broadcastEvmToken({ currency, network, toAddress, amount, tokenConfig });
    }

    return this._broadcastEvmNative({ currency, network, toAddress, amount });
  }

  async _broadcastEvmToken({ currency, network, toAddress, amount, tokenConfig }) {
    const contract = blockchain.getSignedTokenContract(currency, network);
    const signer = blockchain.getSigner(network);
    const amountInUnits = ethers.parseUnits(amount, tokenConfig.decimals);
    const readContract = blockchain.getTokenContract(currency, network);
    const balance = await readContract.balanceOf(signer.address);

    if (balance < amountInUnits) {
      throw new Error(
        `Insufficient hot wallet balance for ${currency} on ${network}. ` +
        `Required: ${amount}, Available: ${ethers.formatUnits(balance, tokenConfig.decimals)}`
      );
    }

    const gasEstimate = await contract.transfer.estimateGas(toAddress, amountInUnits);
    const feeData = await blockchain.getEthersProvider(network).getFeeData();

    const tx = await contract.transfer(toAddress, amountInUnits, {
      gasLimit: gasEstimate * 120n / 100n,
      maxFeePerGas: feeData.maxFeePerGas || undefined,
      maxPriorityFeePerGas: feeData.maxPriorityFeePerGas || undefined,
    });

    const receipt = await tx.wait(1);
    if (!receipt || receipt.status === 0) {
      throw new Error(`Transaction reverted: ${tx.hash}`);
    }

    return tx.hash;
  }

  async _broadcastEvmNative({ currency, network, toAddress, amount }) {
    const signer = blockchain.getSigner(network);
    const provider = blockchain.getEthersProvider(network);
    const amountInWei = ethers.parseEther(amount);
    const balance = await provider.getBalance(signer.address);

    if (balance < amountInWei) {
      throw new Error(
        `Insufficient hot wallet balance for ${currency} on ${network}. ` +
        `Required: ${amount}, Available: ${ethers.formatEther(balance)}`
      );
    }

    const feeData = await provider.getFeeData();
    const tx = await signer.sendTransaction({
      to: toAddress,
      value: amountInWei,
      maxFeePerGas: feeData.maxFeePerGas || undefined,
      maxPriorityFeePerGas: feeData.maxPriorityFeePerGas || undefined,
    });

    const receipt = await tx.wait(1);
    if (!receipt || receipt.status === 0) {
      throw new Error(`Transaction reverted: ${tx.hash}`);
    }

    return tx.hash;
  }

  async getHotWalletBalances() {
    const balances = {};

    for (const network of config.evmNetworks) {
      try {
        const signer = blockchain.getSigner(network);
        const provider = blockchain.getEthersProvider(network);

        balances[network] = {
          address: signer.address,
          native: ethers.formatEther(await provider.getBalance(signer.address)),
          tokens: {},
        };

        for (const [currency, tokenConfig] of config.getTokensForNetwork(network)) {
          if (!tokenConfig.address) {
            continue;
          }

          try {
            const contract = blockchain.getTokenContract(currency, network);
            const balance = await contract.balanceOf(signer.address);
            balances[network].tokens[currency] = ethers.formatUnits(balance, tokenConfig.decimals);
          } catch (error) {
            balances[network].tokens[currency] = 'error';
            logger.warn('Failed to get hot wallet token balance', {
              network,
              currency,
              error: error.message,
            });
          }
        }
      } catch (error) {
        balances[network] = {
          error: error.message,
        };
      }
    }

    const nonEvmBalances = await nonEvmChainService.getHotWalletBalances();
    Object.assign(balances, nonEvmBalances);

    return balances;
  }
}

module.exports = new WithdrawalBroadcaster();
