# ExaEarn Matching Engine Runtime Decision

Date: 2026-08-17

## Decision

Phase 2 implements the first production-oriented Spot matching core inside the existing Laravel API gateway.

This is an intentional transitional runtime decision.

## Rationale

Laravel/PHP is not the final low-latency runtime most exchanges would choose for a high-throughput central limit order book. However, ExaEarn already has:

- Laravel API routes and authentication
- `Order`, `Trade`, `Market`, `OrderBook` models
- Phase 1 `ReservationService`
- Phase 1 `SettlementService`
- Phase 1 canonical ledger
- existing frontend/mobile compatibility through `/api/trade/*`
- existing PHPUnit coverage

For Phase 2, correctness, determinism, ledger safety and migration control matter more than raw throughput.

Keeping the first engine inside Laravel allows ExaEarn to:

- preserve app API compatibility
- prove the OMS/sequencer/journal/snapshot model
- settle through the existing canonical ledger without a distributed boundary
- test the cutover behind `TRADING_ENGINE_MODE`
- avoid prematurely introducing Go/Rust/Node operational complexity

## Future Runtime

A later phase may move the matching core into a dedicated service in Go, Rust or TypeScript/Node.

That move should happen only after:

- the Phase 2 Laravel engine contract is stable
- command and execution events are durable
- replay/snapshot semantics are proven
- market-data Phase 3 consumes sequenced book events
- operational ownership and deployment plans are clear

## Current Mode

The implementation supports:

```text
TRADING_ENGINE_MODE=legacy
TRADING_ENGINE_MODE=new
```

Default:

```text
legacy
```

The new engine is ready for development/sandbox/shadow validation. It should not be enabled for all production markets at once.

