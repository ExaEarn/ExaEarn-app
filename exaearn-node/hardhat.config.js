require('@nomicfoundation/hardhat-toolbox');
require('dotenv').config();

const accounts = process.env.DEPLOYER_PRIVATE_KEY ? [process.env.DEPLOYER_PRIVATE_KEY] : [];

module.exports = {
  solidity: {
    version: '0.8.26',
    settings: {
      evmVersion: 'cancun',
      optimizer: {
        enabled: true,
        runs: 200,
      },
    },
  },
  networks: {
    hardhat: {},
    sepolia: {
      url: process.env.ETHEREUM_SEPOLIA_RPC_URL || '',
      chainId: 11155111,
      accounts,
    },
    baseSepolia: {
      url: process.env.BASE_SEPOLIA_RPC_URL || '',
      chainId: 84532,
      accounts,
    },
    bscTestnet: {
      url: process.env.BSC_TESTNET_RPC_URL || '',
      chainId: 97,
      accounts,
    },
    ethereum: {
      url: process.env.ETHEREUM_RPC_URL || '',
      chainId: Number(process.env.ETHEREUM_CHAIN_ID || 1),
      accounts,
    },
    base: {
      url: process.env.BASE_RPC_URL || '',
      chainId: Number(process.env.BASE_CHAIN_ID || 8453),
      accounts,
    },
    bsc: {
      url: process.env.BSC_RPC_URL || '',
      chainId: Number(process.env.BSC_CHAIN_ID || 56),
      accounts,
    },
  },
  etherscan: {
    apiKey: {
      sepolia: process.env.ETHERSCAN_API_KEY || '',
      baseSepolia: process.env.BASESCAN_API_KEY || '',
      base: process.env.BASESCAN_API_KEY || '',
      bscTestnet: process.env.BSCSCAN_API_KEY || '',
      bsc: process.env.BSCSCAN_API_KEY || '',
    },
  },
};
