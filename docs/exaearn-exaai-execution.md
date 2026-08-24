# ExaEarn ExaAI Execution

## Modes

- PAPER: records decisions, never touches real balances, never submits real orders.
- SHADOW: uses real market context, records would-be decisions, never submits real orders.
- LIVE: requires explicit user authorization and routes through ExaEarn normal Spot/Futures infrastructure.

## Live Execution Path

For Spot:

`ExaAI Decision -> Risk -> TradeService/Spot OMS -> Matching -> Settlement -> Ledger -> ExaAI Attribution`

For Futures:

`ExaAI Decision -> Futures Risk -> Futures OMS -> Position/Margin -> Settlement -> Ledger -> ExaAI Attribution`

## Safety

Worker evaluation skips PAPER and SHADOW sessions. Replayed realtime events cannot create financial effects. Duplicate decisions are protected by user/idempotency key constraints.
