# ExaEarn Financial Core

Phase: 1
Date: 2026-08-14

## Canonical Source of Truth

ExaEarn financial state is moving to this canonical model:

```text
Financial operation
    -> idempotency validation
    -> reservation / hold service
    -> settlement service
    -> canonical double-entry ledger
    -> immutable ledger transactions
    -> balance projection
    -> API/user/admin display
```

The ledger is authoritative for migrated paths. Legacy `wallets`, `wallet_balances`, `balances`, and `internal_accounts` remain for compatibility and migration only.

## Account Model

Canonical accounts are stored in `accounts` and support:

- `owner_type`: `user`, `system`, `treasury`, etc.
- `owner_id`: user/admin/system owner identifier where applicable.
- `user_id`: compatibility user link for existing code.
- `asset`: asset/currency symbol.
- `account_type`: funding, spot, futures, margin, treasury, fee_revenue, clearing, settlement, etc.
- `status`: active, suspended, closed.
- `metadata`: product/source information.

Examples:

- USER FUNDING USDT
- USER SPOT BTC
- USER FUTURES USDT
- EXAEARN FEE REVENUE USDT
- EXAEARN TREASURY BTC
- EXAEARN CLEARING USDT
- EXAEARN SETTLEMENT NGN

Custody wallets and blockchain addresses are not the same thing as internal ledger accounts.

## Reservation Model

The `reservations` table records holds against canonical accounts.

Supported states:

- `active`
- `partially_consumed`
- `consumed`
- `released`
- `expired`
- `cancelled`

Each reservation stores:

- `reservation_id`
- `account_id`
- `user_id`
- `asset`
- `amount`
- `remaining_amount`
- `purpose`
- `reference_type`
- `reference_id`
- `idempotency_key`
- `status`
- `expires_at`
- `metadata`

Available balance is calculated as:

```text
ledger account balance - active reservation remaining amounts
```

Reservations use database transactions, row locks, and idempotency keys.

## Settlement Model

`SettlementService` is the central settlement layer. It does not match orders. It receives confirmed business events and posts balanced ledger transactions.

Initial primitives:

- internal transfer
- deposit credit
- withdrawal debit from reservation
- convert settlement
- spot trade settlement
- fee routing
- reservation consumption/release

All settlement entries must balance per asset.

## Reversal Model

Posted ledger history is immutable. Financial correction uses reversal transactions:

```text
Original transaction remains
    -> reversal transaction posts opposite entries
    -> ledger_reversal_links records the relationship
```

`LedgerService::rollbackTransaction()` no longer deletes ledger entries. It creates a reversal reference and links it when possible. New code should use `LedgerReversalService` directly.

## Precision Policy

Phase 1 introduced `FinancialDecimal`, a BCMath-only helper for financial arithmetic. It fails clearly if BCMath is unavailable.

Migrated financial helpers now use deterministic decimal operations instead of PHP floats:

- `LedgerService`
- `ReservationService`
- `SettlementService`
- `BalanceProjectionService`
- `LedgerReversalService`
- `LedgerReconciliationService`
- `TransferService` bridge
- targeted helpers in `Wallet`, `WalletService`, `TransactionService`, and `FeeCalculator`

Remaining non-migrated product services still contain float fallback debt and are listed in the Phase 1 report.

## Idempotency

Phase 1 added `financial_operation_idempotencies` and idempotent reservation keys. Migrated settlement paths use deterministic references and idempotency metadata.

Required future idempotency coverage:

- deposits
- withdrawals
- transfers
- spot settlements
- convert executions
- fees
- futures settlements
- P2P escrow
- game/entry settlement
- commerce/refunds
- admin adjustments

## Balance Projection

`BalanceProjectionService` exposes:

- total ledger balance
- reserved balance
- available balance
- locked/reserved aliases
- user balances by account and asset

Projection values are reconstructable from ledger and reservation records. Cached/materialized projections may be added later, but they must not become the source of truth.

## Reconciliation

`LedgerReconciliationService` currently detects:

- unbalanced ledger transactions by asset
- negative user accounts
- legacy wallet balance divergence
- duplicate transaction references

It reports findings only. It does not automatically correct discrepancies.

## Remaining Legacy Paths

Known remaining direct or indirect legacy balance writers include:

- `TradeService`
- `SpotTradingService`
- `SwapEngineService`
- `UnifiedTradingAccountService`
- `UnifiedTradingReservationService`
- `TransactionService` production wallet effects
- `WalletService` legacy wallet mutation helpers
- `WithdrawalEngineService`
- `WithdrawalCenterController`
- `FuturesOrderService` through legacy reservation service
- `FuturesLiquidationService`
- `P2PService`
- giftcard purchase/sell services
- staking jobs/services
- ExaSkills, Agri, Game, ExaAI, NFT and other product commerce paths
- admin platform balance freeze/adjustment controls

These must migrate before ExaEarn can claim full canonical financial safety.

## Migration Progress

Completed in this phase:

- canonical account dimensions migration
- canonical reservations table/model/service
- central settlement service primitives
- immutable reversal service/linking
- balance projection service
- reconciliation service
- internal transfer bridge through ledger-first settlement
- active spot order reservation/fill/cancel settlement migration
- convert reservation/settlement migration with legacy wallet bridge
- futures initial-margin reservation migration
- focused invariant tests

Not complete:

- full deposit/withdrawal lifecycle migration
- full futures PnL/funding/liquidation/insurance settlement
- broad API read migration to projections
- all legacy direct write shutdowns
- full no-float cleanup across every product module
- live database reconciliation and static direct-write guards