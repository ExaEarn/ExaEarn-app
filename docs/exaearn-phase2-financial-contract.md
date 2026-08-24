# ExaEarn Phase 2 Financial Contract

Date: 2026-08-17

## Purpose

Phase 2 may introduce a production matching engine only through the Phase 1 canonical financial core. The matching engine must not write wallet, balance or ledger rows directly.

## Matching Engine Responsibilities

The matching engine may:

- validate market state
- accept order intent from authenticated trading APIs
- request reservation of required funds
- match compatible orders
- emit execution reports
- request settlement of confirmed executions
- request reservation release on cancellation or expiry

The matching engine must not:

- debit or credit balances directly
- write `wallets`, `wallet_balances`, `balances` or `internal_accounts` as authoritative money state
- calculate final ledger entries outside `SettlementService`
- bypass `ReservationService`
- settle self-trades
- use floats for money, price, quantity, fees or notional values

## Order Placement Contract

Before an order becomes live:

```text
Order intent
  -> market/risk validation
  -> canonical account lookup
  -> ReservationService::reserve(...)
  -> order persisted with reservation_id metadata
  -> order enters book
```

Buy orders must reserve quote asset.

Sell orders must reserve base asset.

For market buy orders, the engine must reserve using the configured notional/slippage policy and release unused reservation after fill.

## Execution Settlement Contract

After a match:

```text
Execution report
  -> SettlementService::spotTrade(...)
  -> LedgerService::postDoubleEntry(...)
  -> reservation consumption/release
```

The execution reference must be unique and idempotent, for example:

```text
spot-fill:{trade_uuid}
```

Repeated settlement requests with the same reference must not duplicate ledger entries or consume reservations twice.

## Required Execution Payload

Spot settlement payload must include:

- buyer user id
- seller user id
- base asset
- quote asset
- base amount
- quote amount
- buyer fee and fee asset
- seller fee and fee asset
- reservation ids and amounts to consume
- market id / symbol
- execution id / trade uuid
- source service metadata

## Cancellation Contract

On cancellation:

```text
Order cancellation
  -> load reservation_id
  -> ReservationService::release(...)
  -> order status updated
```

Cancellation must be idempotent. Repeated cancellation must not fail financial state or release more than the remaining reserved amount.

## Projection Contract

Trading APIs and frontend displays should use `BalanceProjectionService` for:

- total
- available
- reserved
- locked
- transferable

Legacy balance fields may remain as response aliases only during compatibility migration.

## Reconciliation Contract

Phase 2 must preserve these invariants:

- every ledger transaction balances by asset
- no user account becomes negative unless explicitly allowed by product policy
- active reserved amount is never greater than account total
- settlement never consumes more than the remaining reservation
- every execution settlement has a stable idempotency reference
- every reversal links to an existing original ledger transaction

## Phase 2 Entry Gate

Before Phase 2 development starts, run:

```bash
php artisan financial:phase1-gate
php artisan test tests/Feature/Phase1FinancialCoreTest.php tests/Feature/SpotFinancialMigrationTest.php
```

Phase 2 may begin only if the Phase 1 gate says:

```text
SAFE TO BEGIN PHASE 2: YES
```

