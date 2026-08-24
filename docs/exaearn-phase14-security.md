# ExaEarn Phase 14 Developer API Security

## Authentication

Private developer APIs require:

- `EXA-API-KEY`
- `EXA-API-TIMESTAMP`
- `EXA-API-NONCE`
- `EXA-API-SIGNATURE`
- optional `EXA-API-PASSPHRASE`

The signature is HMAC-SHA256 over method, path, query string, timestamp, nonce and SHA-256 request-body hash.

## Controls

- API keys are hashed for lookup.
- API secrets are encrypted at rest and shown only on creation or rotation.
- Nonces are persisted per API key and cannot be reused.
- Stale timestamps fail closed.
- Revoked or expired keys fail closed.
- IP allowlists are enforced before product access.
- Withdrawal-capable keys require IP allowlisting at creation.
- Product scopes are granular; there is no universal `full_access` scope.

## Product Safety

Developer APIs do not bypass ExaEarn product controls:

- Spot orders use Spot OMS/risk.
- Futures orders use Futures OMS/risk/margin paths.
- Margin borrow/repay/order actions use margin services and lending controls.
- Staking uses native user staking service, not admin controls.
- Copy Trading uses eligibility, public-mode, capacity and follower risk controls.
- ExaAI uses strategy governance, operations mode and kill-switch controls.

## Tested

Phase 14 tests cover invalid signatures, expired timestamps, missing scopes, IP mismatch, nonce replay, product scope enforcement, sandbox isolation, signed webhook delivery/replay and durable realtime replay.
