jest.mock('../../config', () => ({
  evmNetworks: ['base', 'ethereum'],
  getNetworkConfig: jest.fn((network) => ({
    rpcUrl: `https://${network}.rpc.example.test`,
    chainId: network === 'base' ? 8453 : 1,
    hotWallet: { privateKey: `0x${'01'.repeat(32)}` },
  })),
  getTokenConfig: jest.fn((currency) => (
    String(currency).toUpperCase() === 'EXA'
      ? {
          address: '0x0000000000000000000000000000000000000001',
          network: 'base',
          decimals: 18,
        }
      : null
  )),
}));
jest.mock('../../utils/logger', () => ({ info: jest.fn(), warn: jest.fn(), error: jest.fn() }));
jest.mock('../hdWalletService', () => ({
  deriveEvmAddress: jest.fn(() => ({
    address: '0x0000000000000000000000000000000000000002',
    metadata: { derivation_path: "m/44'/60'/0'/0/5", address_index: 5 },
  })),
}));

const config = require('../../config');
const hdWalletService = require('../hdWalletService');
const blockchain = require('../blockchain');

describe('ethers-backed EVM provider and contract compatibility', () => {
  beforeEach(() => {
    blockchain._ethersProviders.clear();
    blockchain._ethersWsProviders.clear();
    blockchain._signers.clear();
    blockchain._tokenContracts.clear();
    jest.clearAllMocks();
  });

  test('creates and caches a provider for the requested supported chain', () => {
    const first = blockchain.getEthersProvider('base');
    const second = blockchain.getEthersProvider('BASE');

    expect(first).toBe(second);
    expect(config.getNetworkConfig).toHaveBeenCalledWith('base');
    expect(first).toBeInstanceOf(require('ethers').ethers.JsonRpcProvider);
  });

  test('rejects unsupported chains before constructing a provider or signer', () => {
    expect(() => blockchain.getEthersProvider('mainnet')).toThrow('Unsupported EVM network: mainnet');
    expect(() => blockchain.getSigner('mainnet')).toThrow('Unsupported EVM network: mainnet');
  });

  test('creates and caches the configured EVM signer', () => {
    const signer = blockchain.getSigner('base');

    expect(signer).toBeInstanceOf(require('ethers').ethers.Wallet);
    expect(signer.provider).toBe(blockchain.getEthersProvider('base'));
    expect(blockchain.getSigner('base')).toBe(signer);
  });

  test('fails closed when the chain has no signing key', () => {
    config.getNetworkConfig.mockReturnValueOnce({
      rpcUrl: 'https://base.rpc.example.test',
      chainId: 8453,
      hotWallet: {},
    });

    expect(() => blockchain.getSigner('base')).toThrow('BASE hot wallet private key not configured');
  });

  test('retains ERC-20 ABI encoding and connects writes to the configured signer', () => {
    const contract = blockchain.getTokenContract('EXA', 'base');
    const signer = blockchain.getSigner('base');
    const signedContract = blockchain.getSignedTokenContract('EXA', 'base');
    const destination = '0x0000000000000000000000000000000000000003';
    const encoded = contract.interface.encodeFunctionData('transfer', [destination, 25n]);

    expect(contract.runner).toBe(blockchain.getEthersProvider('base'));
    expect(signedContract.runner).toBe(signer);
    expect(contract.interface.getEvent('Transfer')).toBeDefined();
    expect(contract.interface.decodeFunctionData('transfer', encoded)[0]).toBe(destination);
    expect(contract.interface.decodeFunctionData('transfer', encoded)[1]).toBe(25n);
  });

  test('rejects malformed ABI response data and invalid transaction arguments', () => {
    const contract = blockchain.getTokenContract('EXA', 'base');

    expect(() => contract.interface.decodeFunctionResult('balanceOf', '0x')).toThrow();
    expect(() => contract.interface.encodeFunctionData('transfer', ['not-an-address', 1n])).toThrow();
    expect(() => contract.interface.encodeFunctionData('transfer', [
      '0x0000000000000000000000000000000000000003',
      -1n,
    ])).toThrow();
  });

  test('preserves deterministic wallet derivation through the existing HD wallet service', () => {
    expect(blockchain.deriveWallet(5, 'base')).toEqual({
      address: '0x0000000000000000000000000000000000000002',
      derivationPath: "m/44'/60'/0'/0/5",
      addressIndex: 5,
    });
    expect(hdWalletService.deriveEvmAddress).toHaveBeenCalledWith({
      userId: 5,
      currency: 'BASE',
      network: 'base',
    });
  });

  test('rejects an unconfigured token instead of constructing an address-less contract', () => {
    config.getTokenConfig.mockReturnValueOnce(null);

    expect(() => blockchain.getTokenContract('UNKNOWN', 'base')).toThrow(
      'Token contract address not configured for UNKNOWN'
    );
  });
});
