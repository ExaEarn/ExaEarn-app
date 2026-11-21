const { Web3, Web3Validator } = require('web3');

describe('Web3 validator Zod 4 compatibility', () => {
  const validator = new Web3Validator();

  test('preserves bounded-array validation and error mapping', () => {
    const schema = { type: 'array', items: { type: 'string' }, maxItems: 2 };

    expect(validator.validateJSONSchema(schema, ['one', 'two'])).toBeUndefined();

    const errors = validator.validateJSONSchema(schema, ['one', 'two', 'three'], {
      silent: true,
    });

    expect(errors).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          keyword: 'maxItems',
          message: 'must NOT have more than 2 items',
        }),
      ])
    );
  });

  test('preserves EVM address ABI round-trips', () => {
    const web3 = new Web3();
    const address = '0x0000000000000000000000000000000000000001';
    const encoded = web3.eth.abi.encodeParameter('address', address);

    expect(web3.eth.abi.decodeParameter('address', encoded).toLowerCase()).toBe(address);
  });
});