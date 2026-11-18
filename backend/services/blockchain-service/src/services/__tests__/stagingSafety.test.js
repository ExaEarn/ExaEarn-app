const safety = require('../../stagingSafety');

const safeEnvironment = {
  NODE_ENV: 'staging',
  APP_ENV: 'staging',
  APP_DEPLOYMENT_ENV: 'staging',
  EXAEARN_STAGING_SAFETY_LOCK: 'locked',
  BASE_RPC_URL: 'https://sepolia.base.org',
  ETHEREUM_RPC_URL: 'https://ethereum-sepolia-rpc.publicnode.com',
  BSC_RPC_URL: 'https://data-seed-prebsc-1-s1.bnbchain.org:8545',
  POLYGON_RPC_URL: 'https://rpc-amoy.polygon.technology',
  XRPL_RPC_URL: 'https://s.altnet.rippletest.net:51234',
  XRPL_WSS: 'wss://s.altnet.rippletest.net:51233',
  XRP_RPC_URL: 'https://s.altnet.rippletest.net:51234',
  SOLANA_RPC_URL: 'https://api.devnet.solana.com',
  TRON_RPC_URL: 'https://api.shasta.trongrid.io',
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
  BITCOIN_NETWORK: 'testnet',
  CUSTODY_PRODUCTION_ENABLED: 'false',
  FINANCIAL_SERVICES_ENABLED: 'false',
  STAGING_WORKERS_ENABLED: 'false',
};

test('staging requires explicit testnet RPC configuration', () => {
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, BASE_RPC_URL: '' })).toThrow('BASE_RPC_URL');
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, BASE_RPC_URL: 'https://mainnet.base.org' })).toThrow('BASE_RPC_URL');
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, POLYGON_RPC_URL: '' })).toThrow('POLYGON_RPC_URL');
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, XRPL_RPC_URL: 'https://s1.ripple.com:51234' })).toThrow('XRPL_RPC_URL');
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, XRPL_WSS: '' })).toThrow('XRPL_WSS');
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, SOLANA_RPC_URL: 'https://api.mainnet-beta.solana.com' })).toThrow('SOLANA_RPC_URL');
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, TRON_RPC_URL: 'https://api.trongrid.io' })).toThrow('TRON_RPC_URL');
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, BASE_CHAIN_ID: '8453' })).toThrow('BASE_CHAIN_ID');
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, TON_RPC_URL: 'https://mainnet.toncenter.com' })).toThrow('TON_RPC_URL');
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, BASE_RPC_WSS: 'wss://base-mainnet.invalid' })).toThrow('BASE_RPC_WSS');
});

test('staging rejects signing material and financial operations', () => {
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, PRIVATE_KEY: 'not-a-real-key' })).toThrow('PRIVATE_KEY');
  expect(() => safety.assertFinancialOperationBlocked('withdrawal broadcasting', safeEnvironment)).toThrow('withdrawal broadcasting');
});

test('actual Node startup skips every background worker when staging workers are disabled', async () => {
  const names = Object.keys(safeEnvironment);
  const prior = Object.fromEntries(names.map((name) => [name, process.env[name]]));
  Object.assign(process.env, safeEnvironment, { STAGING_WORKERS_ENABLED: 'FALSE' });

  const index = require('../../index');
  const targets = [
    require('../realtimeSubscriber'),
    require('../futuresUpdateSubscriber'),
    require('../ledgerUpdateSubscriber'),
    index.developerRealtimeSubscriber,
    require('../blockchainEventListener'),
  ];
  const spies = targets.map((target) => jest.spyOn(target, 'start').mockResolvedValue(undefined));

  try {
    expect(await index.startBackgroundServices()).toBe(false);
    for (const spy of spies) expect(spy).not.toHaveBeenCalled();
  } finally {
    spies.forEach((spy) => spy.mockRestore());
    for (const name of names) {
      if (prior[name] === undefined) delete process.env[name];
      else process.env[name] = prior[name];
    }
  }
});

test('actual contract transaction route rejects staging financial execution', async () => {
  const http = require('node:http');
  const names = Object.keys(safeEnvironment);
  const prior = Object.fromEntries(names.map((name) => [name, process.env[name]]));
  Object.assign(process.env, safeEnvironment);

  const { server, developerRealtimeHub } = require('../../index');
  try {
    const response = await new Promise((resolve, reject) => {
      server.once('error', reject);
      server.listen(0, '127.0.0.1', () => {
        const request = http.request({
          host: '127.0.0.1',
          port: server.address().port,
          path: '/contracts/execute',
          method: 'POST',
        }, (result) => {
          let body = '';
          result.setEncoding('utf8');
          result.on('data', (chunk) => { body += chunk; });
          result.on('end', () => resolve({ status: result.statusCode, body: JSON.parse(body) }));
        });
        request.on('error', reject);
        request.end();
      });
    });

    expect(response.status).toBe(422);
    expect(response.body.error).toContain('disabled by the centralized staging safety policy');
  } finally {
    await new Promise((resolve) => server.close(resolve));
    await developerRealtimeHub.close();
    for (const name of names) {
      if (prior[name] === undefined) delete process.env[name];
      else process.env[name] = prior[name];
    }
  }
});

test('staging requires the lock, disabled services and disabled workers', () => {
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, EXAEARN_STAGING_SAFETY_LOCK: '' })).toThrow('safety lock');
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, FINANCIAL_SERVICES_ENABLED: 'true' })).toThrow('Financial services');
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, CUSTODY_PRODUCTION_ENABLED: undefined })).toThrow('Production custody');
  expect(() => safety.assertSafeStagingConfiguration({ ...safeEnvironment, STAGING_WORKERS_ENABLED: 'true' })).toThrow('Workers');
});

test('staging detection rejects conflicting or missing environment configuration', () => {
  expect(() => safety.assertSafeStagingConfiguration({ APP_DEPLOYMENT_ENV: 'staging' })).toThrow('safety lock');
  expect(() => safety.isStaging({ NODE_ENV: 'staging', APP_ENV: 'production' })).toThrow('conflicts');
  expect(() => safety.isStaging({ APP_ENV: 'stagng' })).toThrow('marker is invalid');
});

test('actual Node entrypoint fails before startup on an unsafe staging environment', () => {
  const { spawnSync } = require('node:child_process');
  const path = require('node:path');
  for (const unsafe of [
    { NODE_ENV: 'staging', EXAEARN_STAGING_SAFETY_LOCK: '' },
    { ...safeEnvironment, XRPL_WSS: '' },
    { ...safeEnvironment, BASE_RPC_WSS: 'wss://base-mainnet.invalid' },
  ]) {
    const result = spawnSync(process.execPath, [path.resolve(__dirname, '../../index.js')], {
      env: { ...process.env, ...unsafe },
      encoding: 'utf8',
      timeout: 5000,
    });
    expect(result.status).not.toBe(0);
    expect(`${result.stderr}${result.stdout}`).toMatch(/safety lock|XRPL_WSS|BASE_RPC_WSS/);
  }
});

test('actual Node entrypoint loads a staging .env before applying the startup guard', () => {
  const { spawnSync } = require('node:child_process');
  const fs = require('node:fs');
  const os = require('node:os');
  const path = require('node:path');
  const cwd = fs.mkdtempSync(path.join(os.tmpdir(), 'exaearn-staging-dotenv-'));
  const entrypoint = path.resolve(__dirname, '../../index.js');
  const env = { ...process.env };
  for (const name of ['NODE_ENV', 'APP_ENV', 'APP_DEPLOYMENT_ENV', 'EXAEARN_STAGING_SAFETY_LOCK']) {
    delete env[name];
  }

  try {
    fs.writeFileSync(path.join(cwd, '.env'), 'APP_ENV=staging\n');
    const result = spawnSync(process.execPath, [entrypoint], {
      cwd,
      env,
      encoding: 'utf8',
      timeout: 5000,
    });
    expect(result.status).not.toBe(0);
    expect(`${result.stderr}${result.stdout}`).toContain('Staging safety lock is not enabled');
  } finally {
    fs.rmSync(cwd, { recursive: true, force: true });
  }
});

test('staging entrypoint does not mount TON routes or load TON/Zod RPC code at startup', async () => {
  const Module = require('node:module');
  const names = Object.keys(safeEnvironment);
  const prior = Object.fromEntries(names.map((name) => [name, process.env[name]]));
  Object.assign(process.env, safeEnvironment);
  jest.resetModules();
  const loadSpy = jest.spyOn(Module, '_load');
  let index;

  try {
    index = require('../../index');
    const routes = index.app._router.stack
      .filter((layer) => layer.route)
      .map((layer) => layer.route.path);
    const rpcModulesLoaded = loadSpy.mock.calls
      .map(([request]) => request)
      .filter((request) => request === '@ton/ton' || request === 'zod');

    expect(routes.some((route) => /ton/i.test(String(route)))).toBe(false);
    expect(rpcModulesLoaded).toEqual([]);
  } finally {
    await index.developerRealtimeHub.close();
    loadSpy.mockRestore();
    jest.resetModules();
    for (const name of names) {
      if (prior[name] === undefined) delete process.env[name];
      else process.env[name] = prior[name];
    }
  }
});

