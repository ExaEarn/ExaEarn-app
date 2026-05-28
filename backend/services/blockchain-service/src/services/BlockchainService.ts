import { ethers } from 'ethers';

export type ContractCallInput = {
  contract: ethers.Contract;
  method: string;
  params?: unknown[];
};

export async function sendTransaction({ contract, method, params = [] }: ContractCallInput) {
  const tx = await contract[method](...params);

  return {
    txHash: tx.hash,
    status: 'pending',
  };
}

export async function callContract({ contract, method, params = [] }: ContractCallInput) {
  return contract[method](...params);
}

export async function getTransactionStatus(provider: ethers.Provider, txHash: string) {
  const receipt = await provider.getTransactionReceipt(txHash);

  if (!receipt) {
    return {
      txHash,
      status: 'pending',
      confirmed: false,
    };
  }

  return {
    txHash,
    status: receipt.status === 1 ? 'confirmed' : 'failed',
    confirmed: receipt.status === 1,
    blockNumber: receipt.blockNumber,
  };
}
