const sensitiveVariables = [
  'PRIVATE_KEY', 'DEPLOYER_PRIVATE_KEY', 'WITHDRAWAL_SIGNING_KEY',
  'HD_WALLET_MNEMONIC', 'HD_WALLET_PASSPHRASE',
  'ETHEREUM_HOT_WALLET_PRIVATE_KEY', 'BASE_HOT_WALLET_PRIVATE_KEY',
  'BSC_HOT_WALLET_PRIVATE_KEY', 'POLYGON_HOT_WALLET_PRIVATE_KEY', 'BITCOIN_HOT_WALLET_WIF',
  'SOLANA_HOT_WALLET_SECRET_KEY', 'TON_HOT_WALLET_MNEMONIC',
  'XRPL_HOT_WALLET_SECRET', 'TRON_HOT_WALLET_PRIVATE_KEY',
];

const requiredTestnetRpcs = {
  BASE_RPC_URL: 'https://sepolia.base.org',
  ETHEREUM_RPC_URL: 'https://ethereum-sepolia-rpc.publicnode.com',
  BSC_RPC_URL: 'https://data-seed-prebsc-1-s1.bnbchain.org:8545',
  POLYGON_RPC_URL: 'https://rpc-amoy.polygon.technology',
  XRPL_RPC_URL: 'https://s.altnet.rippletest.net:51234',
  XRPL_WSS: 'wss://s.altnet.rippletest.net:51233',
  XRP_RPC_URL: 'https://s.altnet.rippletest.net:51234',
  SOLANA_RPC_URL: 'https://api.devnet.solana.com',
  TRON_RPC_URL: 'https://api.shasta.trongrid.io',
};

const requiredStagingValues = {
  BITCOIN_NETWORK: 'testnet',
  BASE_CHAIN_ID: '84532',
  ETHEREUM_CHAIN_ID: '11155111',
  BSC_CHAIN_ID: '97',
  POLYGON_CHAIN_ID: '80002',
  BITCOIN_RPC_URL: '',
  TON_RPC_URL: '',
  TRON_FULL_HOST: '',
  BASE_RPC_WSS: '',
  BASE_WSS: '',
  ETHEREUM_RPC_WSS: '',
  ETHEREUM_WSS: '',
  BSC_RPC_WSS: '',
  BSC_WSS: '',
  POLYGON_RPC_WSS: '',
  POLYGON_WSS: '',
};

function isStaging(env = process.env) {
  const nodeEnv = String(env.NODE_ENV || '').toLowerCase();
  const appEnv = String(env.APP_ENV || '').toLowerCase();
  const deploymentEnv = String(env.APP_DEPLOYMENT_ENV || '').toLowerCase();
  const lockSignal = String(env.EXAEARN_STAGING_SAFETY_LOCK || '').trim() !== '';
  if ([nodeEnv, appEnv, deploymentEnv].some((value) => value && value !== 'staging' && /stag|stg/.test(value))) {
    throw new Error('Staging environment marker is invalid. Refusing to start.');
  }
  const stagingDetected = nodeEnv === 'staging' || appEnv === 'staging' || deploymentEnv === 'staging' || lockSignal;
  const declared = [nodeEnv, appEnv, deploymentEnv].filter(Boolean);
  if (stagingDetected && declared.some((value) => !['staging', 'production'].includes(value))) {
    throw new Error('Staging environment detection is inconsistent. Refusing to start.');
  }
  if (stagingDetected && declared.includes('production')) {
    throw new Error('Staging environment conflicts with production configuration. Refusing to start.');
  }
  if (lockSignal && declared.some((value) => value !== 'staging')) {
    throw new Error('Staging environment conflicts with non-staging configuration. Refusing to start.');
  }
  return stagingDetected;
}

function assertSafeStagingConfiguration(env = process.env) {
  if (!isStaging(env)) return;
  if (env.EXAEARN_STAGING_SAFETY_LOCK !== 'locked') {
    throw new Error('Staging safety lock is not enabled. Refusing to start.');
  }
  if (String(env.FINANCIAL_SERVICES_ENABLED || '').toLowerCase() !== 'false') {
    throw new Error('Financial services must be explicitly disabled in staging. Refusing to start.');
  }
  if (String(env.STAGING_WORKERS_ENABLED || '').toLowerCase() !== 'false') {
    throw new Error('Workers must be explicitly disabled in staging. Refusing to start.');
  }
  if (String(env.CUSTODY_PRODUCTION_ENABLED || '').toLowerCase() !== 'false') {
    throw new Error('Production custody must be disabled in staging. Refusing to start.');
  }
  for (const [name, expected] of Object.entries(requiredTestnetRpcs)) {
    const actual = String(env[name] || '');
    if (actual !== expected || actual.toLowerCase().includes('mainnet')) {
      throw new Error(`Unsafe staging RPC configuration for ${name}. Refusing to start.`);
    }
  }
  for (const [name, expected] of Object.entries(requiredStagingValues)) {
    if (String(env[name] || '').trim().toLowerCase() !== expected) {
      throw new Error(`Unsafe staging value for ${name}. Refusing to start.`);
    }
  }
  for (const name of sensitiveVariables) {
    if (String(env[name] || '').trim() !== '') {
      throw new Error(`${name} must be empty in staging. Refusing to start.`);
    }
  }
}

function stagingWorkersDisabled(env = process.env) {
  return isStaging(env) && String(env.STAGING_WORKERS_ENABLED || '').trim().toLowerCase() === 'false';
}

function assertFinancialOperationBlocked(operation, env = process.env) {
  if (isStaging(env)) {
    throw new Error(`${operation} is disabled by the centralized staging safety policy.`);
  }
}

module.exports = { isStaging, assertSafeStagingConfiguration, stagingWorkersDisabled, assertFinancialOperationBlocked };
