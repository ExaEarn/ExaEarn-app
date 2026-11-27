describe('blockchain HTTP application', () => {
  let app;
  let server;
  let baseUrl;
  const originalSecret = process.env.NODE_SERVICE_SECRET;
  const originalExecutionFlag = process.env.BLOCKCHAIN_TRANSACTION_EXECUTION_ENABLED;

  beforeAll(async () => {
    process.env.NODE_SERVICE_SECRET = 'test-service-secret-that-is-long-enough';
    process.env.BLOCKCHAIN_TRANSACTION_EXECUTION_ENABLED = 'false';
    jest.resetModules();
    app = require('../src/app');
    server = app.listen(0);
    await new Promise((resolve) => server.once('listening', resolve));
    baseUrl = `http://127.0.0.1:${server.address().port}`;
  });

  afterAll(async () => {
    await new Promise((resolve, reject) => server.close((error) => (error ? reject(error) : resolve())));
    if (originalSecret === undefined) delete process.env.NODE_SERVICE_SECRET;
    else process.env.NODE_SERVICE_SECRET = originalSecret;
    if (originalExecutionFlag === undefined) delete process.env.BLOCKCHAIN_TRANSACTION_EXECUTION_ENABLED;
    else process.env.BLOCKCHAIN_TRANSACTION_EXECUTION_ENABLED = originalExecutionFlag;
  });

  test('rejects protected routes when the service secret is absent', async () => {
    const response = await fetch(`${baseUrl}/contracts/call`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ contract: '0x0', method: 'balanceOf' }),
    });

    expect(response.status).toBe(401);
  });

  test('keeps transaction execution disabled even with valid service authentication', async () => {
    const response = await fetch(`${baseUrl}/contracts/execute`, {
      method: 'POST',
      headers: {
        'content-type': 'application/json',
        'x-service-secret': 'test-service-secret-that-is-long-enough',
      },
      body: JSON.stringify({ contract: '0x0', method: 'transfer' }),
    });

    expect(response.status).toBe(403);
    await expect(response.json()).resolves.toEqual({ message: 'Transaction execution is disabled.' });
  });
});
