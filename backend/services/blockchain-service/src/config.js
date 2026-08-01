require('dotenv').config();

const evmNetworks = ['base', 'ethereum', 'bsc', 'polygon'];

function numberEnv(name, fallback) {
  return Number(process.env[name] || fallback);
}

function networkConfig(name, defaults) {
  const prefix = name.toUpperCase();
  return {
    rpcUrl: process.env[`${prefix}_RPC_URL`] || defaults.rpcUrl,
    wssUrl: process.env[`${prefix}_RPC_WSS`] || process.env[`${prefix}_WSS`] || '',
    chainId: numberEnv(`${prefix}_CHAIN_ID`, defaults.chainId),
    minConfirmations: numberEnv(`${prefix}_MIN_CONFIRMATIONS`, defaults.minConfirmations),
    hotWallet: {
      privateKey: process.env[`${prefix}_HOT_WALLET_PRIVATE_KEY`] || process.env.PRIVATE_KEY || '',
      address: process.env[`${prefix}_HOT_WALLET_ADDRESS`] || '',
    },
  };
}

const networks = {
  base: networkConfig('base', {
    rpcUrl: 'https://mainnet.base.org',
    chainId: 8453,
    minConfirmations: 12,
  }),
  ethereum: networkConfig('ethereum', {
    rpcUrl: 'https://ethereum-rpc.publicnode.com',
    chainId: 1,
    minConfirmations: 12,
  }),
  bsc: networkConfig('bsc', {
    rpcUrl: 'https://bsc-dataseed.binance.org',
    chainId: 56,
    minConfirmations: 15,
  }),
  polygon: networkConfig('polygon', {
    rpcUrl: 'https://polygon-rpc.com',
    chainId: 137,
    minConfirmations: 20,
  }),
};

const tokenContracts = {
  EXA: { address: process.env.EXA_TOKEN_ADDRESS || '', network: process.env.EXA_NETWORK || 'base', decimals: 18 },
  USDT: { address: process.env.USDT_TOKEN_ADDRESS || '', network: process.env.USDT_NETWORK || 'base', decimals: 6 },
  USDC: { address: process.env.USDC_TOKEN_ADDRESS || '', network: process.env.USDC_NETWORK || 'base', decimals: 6 },
  XRP: { address: process.env.XRP_TOKEN_ADDRESS || '', network: process.env.XRP_NETWORK || 'base', decimals: 18 },
};

const contracts = {
  exaToken: process.env.EXA_TOKEN_ADDRESS || '',
  lottery: process.env.LOTTERY_CONTRACT_ADDRESS || process.env.GAMEFI_LOTTERY_CONTRACT_ADDRESS || process.env.CONTRACT_ADDRESS || '',
  gameFiLottery: process.env.GAMEFI_LOTTERY_CONTRACT_ADDRESS || process.env.LOTTERY_CONTRACT_ADDRESS || '',
  nft: process.env.FINANCIAL_NFT_CONTRACT_ADDRESS || process.env.NFT_CONTRACT_ADDRESS || '',
  financialNft: process.env.FINANCIAL_NFT_CONTRACT_ADDRESS || process.env.NFT_CONTRACT_ADDRESS || '',
  nftMarketplace: process.env.NFT_MARKETPLACE_CONTRACT_ADDRESS || '',
  staking: process.env.STAKING_CONTRACT_ADDRESS || '',
  agriInvestment: process.env.AGRI_INVESTMENT_CONTRACT_ADDRESS || '',
  rewardDistribution: process.env.REWARD_DISTRIBUTION_CONTRACT_ADDRESS || '',
  wrappedXrp: process.env.WRAPPED_XRP_ADDRESS || '',
};

const chainFamilies = {
  ethereum: 'evm',
  base: 'evm',
  bsc: 'evm',
  polygon: 'evm',
  bitcoin: 'utxo',
  xrpl: 'tagged',
  tron: 'account',
  solana: 'account',
  ton: 'account',
};

const coinTypes = {
  ethereum: 60,
  base: 60,
  bsc: 60,
  polygon: 60,
  bitcoin: 0,
  xrpl: 144,
  tron: 195,
  solana: 501,
  ton: 607,
};

module.exports = {
  nodeEnv: process.env.NODE_ENV || 'development',
  logLevel: process.env.LOG_LEVEL || 'info',
  logFile: process.env.LOG_FILE || 'logs/blockchain-service.log',
  port: Number(process.env.PORT || 4000),
  redisUrl: process.env.REDIS_URL || 'redis://127.0.0.1:6379',
  serviceSecret: process.env.NODE_SERVICE_SECRET || process.env.SERVICE_SECRET || '',
  laravelApiUrl: process.env.LARAVEL_API_URL || 'http://localhost:8000/api',
  priceChannel: process.env.PRICE_STREAM_CHANNEL || 'price_updates',
  portfolioChannel: process.env.PORTFOLIO_STREAM_CHANNEL || 'portfolio_updates',
  marketChannel: process.env.MARKET_STREAM_CHANNEL || 'exaearn.market.stream',
  futuresStream: {
    channel: process.env.FUTURES_REDIS_CHANNEL || 'futures_updates',
  },
  walletSocketPath: process.env.WALLET_SOCKET_PATH || '/ws/futures',
  ledgerSocketPath: process.env.LEDGER_SOCKET_PATH || '/ws/ledger',
  socketPath: process.env.SOCKET_PATH || '/ws/wallet',
  corsOrigins: process.env.NODE_CORS_ORIGINS
    ? process.env.NODE_CORS_ORIGINS.split(',').map((origin) => origin.trim())
    : ['*'],
  eventListenerEnabled: process.env.BLOCKCHAIN_EVENT_LISTENER_ENABLED !== 'false',
  eventPollIntervalMs: Number(process.env.BLOCKCHAIN_EVENT_POLL_INTERVAL_MS || 15000),
  eventStartBlock: process.env.BLOCKCHAIN_EVENT_START_BLOCK || 'latest',
  webhookSecret: process.env.NODE_WEBHOOK_SECRET || process.env.NODE_SERVICE_SECRET || process.env.SERVICE_SECRET || '',
  evmNetworks,
  networks,
  base: networks.base,
  ethereum: networks.ethereum,
  bsc: networks.bsc,
  polygon: networks.polygon,
  bitcoin: {
    rpcUrl: process.env.BITCOIN_RPC_URL || '',
    network: process.env.BITCOIN_NETWORK || 'mainnet',
    hotWallet: {
      wif: process.env.BITCOIN_HOT_WALLET_WIF || '',
      address: process.env.BITCOIN_HOT_WALLET_ADDRESS || '',
    },
    minConfirmations: numberEnv('BITCOIN_MIN_CONFIRMATIONS', 3),
  },
  xrpl: {
    rpcUrl: process.env.XRPL_RPC_URL || 'https://s1.ripple.com:51234',
    wssUrl: process.env.XRPL_WSS || 'wss://s1.ripple.com',
    hotWallet: {
      secret: process.env.XRPL_HOT_WALLET_SECRET || '',
      address: process.env.XRPL_HOT_WALLET_ADDRESS || '',
    },
    minConfirmations: numberEnv('XRPL_MIN_CONFIRMATIONS', 1),
  },
  tron: {
    rpcUrl: process.env.TRON_RPC_URL || '',
    fullHost: process.env.TRON_FULL_HOST || process.env.TRON_RPC_URL || '',
    hotWallet: {
      privateKey: process.env.TRON_HOT_WALLET_PRIVATE_KEY || '',
      address: process.env.TRON_HOT_WALLET_ADDRESS || '',
    },
    minConfirmations: numberEnv('TRON_MIN_CONFIRMATIONS', 20),
  },
  solana: {
    rpcUrl: process.env.SOLANA_RPC_URL || '',
    hotWallet: {
      secretKey: process.env.SOLANA_HOT_WALLET_SECRET_KEY || '',
      address: process.env.SOLANA_HOT_WALLET_ADDRESS || '',
    },
    minConfirmations: numberEnv('SOLANA_MIN_CONFIRMATIONS', 20),
  },
  ton: {
    rpcUrl: process.env.TON_RPC_URL || '',
    hotWallet: {
      mnemonic: process.env.TON_HOT_WALLET_MNEMONIC || '',
      address: process.env.TON_HOT_WALLET_ADDRESS || '',
    },
    minConfirmations: numberEnv('TON_MIN_CONFIRMATIONS', 20),
  },
  hdWallet: {
    mnemonic: process.env.HD_WALLET_MNEMONIC || '',
    passphrase: process.env.HD_WALLET_PASSPHRASE || '',
    account: numberEnv('HD_WALLET_ACCOUNT', 0),
    change: numberEnv('HD_WALLET_CHANGE', 0),
    startIndex: numberEnv('HD_WALLET_START_INDEX', 0),
    addressType: process.env.HD_WALLET_ADDRESS_TYPE || 'deposit',
  },
  chainFamilies,
  coinTypes,
  contracts,
  tokens: tokenContracts,
  withdrawalSigningKey: process.env.WITHDRAWAL_SIGNING_KEY || '',
  blockPollInterval: Number(process.env.BLOCK_POLL_INTERVAL || 5000),
  marketStream: {
    driver: process.env.MARKET_STREAM_DRIVER || 'redis',
    channel: process.env.MARKET_STREAM_CHANNEL || 'exaearn.market.stream',
    redisUrl: process.env.REDIS_URL || '',
  },
  exapointStream: {
    channel: process.env.EXAPOINT_REDIS_CHANNEL || 'exapoint_updates',
  },
  nft: {
    eventPollIntervalMs: Number(process.env.NFT_EVENT_POLL_INTERVAL_MS || 15000),
  },
  p2p: {
    chatFlagKeywords: (process.env.P2P_CHAT_FLAG_KEYWORDS || 'whatsapp,telegram,off-platform,external deal,send outside,cashapp,crypto first')
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean),
  },
  xrpBridge: {
    monitorIntervalMs: Number(process.env.XRP_BRIDGE_MONITOR_INTERVAL_MS || 15000),
    treasuryAddress: process.env.XRPL_TREASURY_ADDRESS || '',
  },
  getNetworkConfig(network = 'base') {
    const normalized = String(network || 'base').toLowerCase();
    const found = networks[normalized];
    if (!found) {
      throw new Error(`Network not configured: ${normalized}`);
    }
    return found;
  },
  getTokenConfig(currency) {
    return tokenContracts[String(currency || '').toUpperCase()] || null;
  },
  getNetworkForCurrency(currency) {
    const token = tokenContracts[String(currency || '').toUpperCase()];
    return token?.network || null;
  },
  getTokensForNetwork(network) {
    const normalized = String(network || '').toLowerCase();
    return Object.entries(tokenContracts)
      .filter(([, token]) => String(token.network || '').toLowerCase() === normalized);
  },
  getChainFamily(network) {
    return chainFamilies[String(network || '').toLowerCase()] || null;
  },
  getCoinType(network) {
    return coinTypes[String(network || '').toLowerCase()] ?? null;
  },
};
