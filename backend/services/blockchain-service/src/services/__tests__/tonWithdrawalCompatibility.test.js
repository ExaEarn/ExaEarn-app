jest.mock('../../config', () => ({
  ton: {
    rpcUrl: 'https://ton.example.test/jsonRPC',
    hotWallet: { mnemonic: 'test-only mnemonic words' },
  },
}));
jest.mock('../../utils/logger', () => ({ info: jest.fn(), warn: jest.fn(), error: jest.fn() }));
jest.mock('../../utils/optionalModule', () => jest.fn());

const optionalModule = require('../../utils/optionalModule');
const tonSdk = require('@ton/ton');
const service = require('../nonEvmChainService');

describe('NonEvmChainService TON withdrawal compatibility', () => {
  let sendTransfer;

  beforeEach(() => {
    service._tonClient = null;
    sendTransfer = jest.fn().mockResolvedValue(undefined);
    const openedWallet = {
      getSeqno: jest.fn().mockResolvedValue(7),
      sendTransfer,
    };
    const client = { open: jest.fn(() => openedWallet) };

    optionalModule.mockImplementation((name) => {
      if (name === '@ton/ton') {
        return {
          ...tonSdk,
          TonClient: jest.fn(function TonClientMock() {
            return client;
          }),
        };
      }
      if (name === '@ton/crypto') {
        return {
          mnemonicToPrivateKey: jest.fn().mockResolvedValue({
            publicKey: Buffer.alloc(32, 1),
            secretKey: Buffer.alloc(64, 2),
          }),
        };
      }
      throw new Error(`Unexpected optional module: ${name}`);
    });
  });

  afterEach(() => {
    service._tonClient = null;
    optionalModule.mockReset();
  });

  test('builds a TON transfer through the existing wallet SDK flow without network writes', async () => {
    const destination = `0:${'1'.repeat(64)}`;

    await expect(service.broadcastTonWithdrawal(destination, '1.25')).resolves.toBe('ton-seqno-7');
    expect(sendTransfer).toHaveBeenCalledTimes(1);
    const transfer = sendTransfer.mock.calls[0][0];
    expect(transfer.seqno).toBe(7);
    expect(transfer.secretKey).toEqual(Buffer.alloc(64, 2));
    expect(transfer.messages).toHaveLength(1);
    expect(transfer.messages[0].info.dest.toString()).toBe(tonSdk.Address.parse(destination).toString());
  });

  test('rejects malformed withdrawal destinations before calling the wallet send method', async () => {
    await expect(service.broadcastTonWithdrawal('invalid-destination', '1')).rejects.toThrow();
    expect(sendTransfer).not.toHaveBeenCalled();
  });
});
