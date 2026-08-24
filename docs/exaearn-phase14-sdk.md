# ExaEarn Phase 14 SDK

## Package

```text
@exaearn/sdk
```

## Implemented

- Public market REST helpers
- Signed private balance reads
- Signed Spot order submission
- Spot order lookup
- HMAC-SHA256 signing
- Timestamp and nonce generation
- Typed request errors with request IDs

## Signing Contract

The SDK signs:

```text
METHOD
PATH
QUERY_STRING
TIMESTAMP
NONCE
BODY_SHA256
```

The nonce is sent as `EXA-API-NONCE`.

## Validation

```text
pnpm --filter @exaearn/sdk typecheck
PASS
```

Future SDK modules for futures, margin, copy trading, ExaAI, full WebSocket transport and webhook management should only be marked ready once their corresponding public developer APIs are fully exposed.
# Final SDK Completion

`@exaearn/sdk` now includes helpers for:

- public market REST
- signed wallet balances
- Spot orders
- Futures order and position endpoints
- Margin account, loan, borrow and repay endpoints
- Staking product and position endpoints
- Copy Trading leader, relationship and follow endpoints
- ExaAI strategy, portfolio, allocation and session controls

Financial amounts are represented as strings. Signed requests include timestamp, nonce and HMAC signature headers.
