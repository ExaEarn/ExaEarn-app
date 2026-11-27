const { Address } = require('@ton/core');
const { TonClient } = require('@ton/ton');

const address = Address.parse(`0:${'0'.repeat(64)}`);

function createClient(result) {
  const adapter = jest.fn(async (request) => ({
    data: { ok: true, result },
    status: 200,
    statusText: 'OK',
    headers: {},
    config: request,
  }));

  return {
    adapter,
    client: new TonClient({
      endpoint: 'https://ton.example.test/jsonRPC',
      httpAdapter: adapter,
    }),
  };
}

describe('TON SDK with the scoped patched Zod runtime', () => {
  test('parses balance RPC responses and preserves wallet balance behavior', async () => {
    const { client, adapter } = createClient({
      balance: '1234567890',
      extra_currencies: [],
      state: 'active',
      data: '',
      code: '',
      last_transaction_id: {
        '@type': 'internal.transactionId',
        lt: '0',
        hash: '',
      },
      block_id: {
        '@type': 'ton.blockIdExt',
        workchain: 0,
        shard: '8000000000000000',
        seqno: 1,
        root_hash: '',
        file_hash: '',
      },
      sync_utime: 1,
    });

    await expect(client.getBalance(address)).resolves.toBe(1234567890n);
    expect(adapter).toHaveBeenCalledTimes(1);
  });

  test('parses valid transaction arrays', async () => {
    const { client } = createClient([]);

    await expect(client.getTransactions(address, { limit: 20 })).resolves.toEqual([]);
  });

  test('rejects malformed RPC objects with a bounded SDK error', async () => {
    const { client } = createClient({
      balance: [],
      extra_currencies: [],
      state: 'active',
      data: '',
      code: '',
      last_transaction_id: {
        '@type': 'internal.transactionId',
        lt: '0',
        hash: '',
      },
      block_id: {
        '@type': 'ton.blockIdExt',
        workchain: 0,
        shard: '8000000000000000',
        seqno: 1,
        root_hash: '',
        file_hash: '',
      },
      sync_utime: 1,
    });

    await expect(client.getBalance(address)).rejects.toThrow('Malformed response:');
  });

  test('rejects malformed transaction entries before TON decodes their BOC data', async () => {
    const { client } = createClient([{}]);

    await expect(client.getTransactions(address, { limit: 1 })).rejects.toThrow('Malformed response:');
  });

  test('rejects malformed TON addresses before any RPC request', () => {
    const { client, adapter } = createClient({});

    expect(() => Address.parse('not-a-ton-address')).toThrow();
    expect(adapter).not.toHaveBeenCalled();
    expect(client).toBeDefined();
  });
});
