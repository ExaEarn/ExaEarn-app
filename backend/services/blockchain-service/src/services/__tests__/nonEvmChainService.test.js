jest.mock('../../config', () => ({
  ton: { rpcUrl: 'https://ton.example.test/jsonRPC' },
}));
jest.mock('../../utils/logger', () => ({ info: jest.fn(), warn: jest.fn(), error: jest.fn() }));
jest.mock('../../utils/optionalModule', () => jest.fn());

const axios = require('axios');
const optionalModule = require('../../utils/optionalModule');
const service = require('../nonEvmChainService');

describe('NonEvmChainService TON RPC response bounds', () => {
  let clientOptions;

  beforeEach(() => {
    service._tonClient = null;
    clientOptions = null;
    optionalModule.mockImplementation((name) => {
      if (name !== '@ton/ton') throw new Error(`Unexpected optional module: ${name}`);
      return {
        TonClient: class TonClientMock {
          constructor(options) {
            clientOptions = options;
          }
        },
      };
    });
  });

  afterEach(() => {
    service._tonClient = null;
    jest.restoreAllMocks();
    optionalModule.mockReset();
  });

  test('caps RPC response bytes before TON Zod schemas process returned collections', async () => {
    const transport = jest.fn().mockResolvedValue({ data: { ok: true } });
    jest.spyOn(axios, 'getAdapter').mockReturnValue(transport);

    service.getTonClient();
    expect(clientOptions.endpoint).toBe('https://ton.example.test/jsonRPC');
    expect(clientOptions.httpAdapter).toEqual(expect.any(Function));

    await clientOptions.httpAdapter({
      url: 'https://ton.example.test/jsonRPC',
      method: 'post',
    });

    expect(axios.getAdapter).toHaveBeenCalledWith('http');
    expect(transport).toHaveBeenCalledWith(expect.objectContaining({
      maxContentLength: 256 * 1024,
      adapter: transport,
    }));
  });
});
