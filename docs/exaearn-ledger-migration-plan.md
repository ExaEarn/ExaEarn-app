# ExaEarn Ledger Migration Plan

Phase: 1
Date: 2026-08-14

## Goal

Move ExaEarn from a hybrid mutable-balance model to a single canonical double-entry ledger while preserving existing user balances, transactions, wallet records, and API compatibility.

## Current State

The repository contains these financial stores:

- `accounts`
- `ledger_transactions`
- `ledger_entries`
- `wallets`
- `wallet_balances`
- `balances`
- `internal_accounts`
- `internal_wallet_transactions`
- product-specific financial tables such as swaps, fiat withdrawals, P2P trades, giftcard records, staking records, treasury balances, futures positions, and game entries.

Phase 1 established canonical services and migrated internal wallet transfer money movement to ledger-first settlement. Legacy balance rows are still synchronized for compatibility.

## Canonical Model

Authoritative financial state should be reconstructed from:

1. `accounts`
2. `ledger_transactions`
3. `ledger_entries`
4. `reservations`
5. `ledger_reversal_links`
6. `financial_operation_idempotencies`

Legacy wallet tables remain as projections or custody/product metadata until fully migrated.

## Migration Stages

### Stage 1 - Dual Read and Reconciliation

- Keep current APIs stable.
- Introduce canonical account dimensions: `owner_type`, `owner_id`, `asset`, `account_type`, `status`.
- Post new migrated financial operations through `SettlementService` and `LedgerService`.
- Keep legacy tables updated only as compatibility projections.
- Run `LedgerReconciliationService` to find divergence.

Status: Started in Phase 1.

### Stage 2 - Ledger-Authoritative Transfers and Deposits

- Move all internal transfer flows to `SettlementService::internalTransfer`.
- Move confirmed deposits to `SettlementService::depositCredit` with idempotency references.
- Stop direct balance mutation in deposit and transfer controllers.
- Backfill canonical accounts from legacy wallet balances with auditable `legacy_balance_migration` ledger transactions.

Status: Internal transfer bridge implemented for `TransferService::internalTransfer`; other paths remain.

### Stage 3 - Canonical Reservations

- Replace `UnifiedTradingReservationService` and wallet lock/release calls with `ReservationService`.
- Use active reservations to calculate available balances.
- Reserve funds for spot orders, futures margin, convert, withdrawals, P2P escrow, giftcard purchases, game entries, ExaAI allocations, and other product holds.

Status: Canonical `ReservationService` exists and is tested; product wiring remains.

### Stage 4 - Canonical Settlement

- Route confirmed business events into `SettlementService`:
  - spot executions
  - convert executions
  - withdrawal success/failure
  - fees
  - P2P releases
  - giftcard purchase/refund
  - staking operations
  - ExaSkills commerce
  - ExaAI allocations/executions
- Matching/order services must no longer mutate user wallets directly.

Status: Settlement service supports internal transfer, deposit credit, withdrawal debit, convert, and spot trade primitives; active product services are only partially migrated.

### Stage 5 - Projection and Compatibility API Migration

- Replace wallet/account API reads with `BalanceProjectionService` where safe.
- Return backward-compatible aliases while documenting deprecated fields.
- Treat `wallet_balances`, `balances`, and `internal_accounts` as projections only.
- Add projection rebuild tooling.

Status: `BalanceProjectionService` exists and is used by migrated internals; broad API migration remains.

### Stage 6 - Stop Legacy Writes

- Deprecate direct mutators in `WalletService`, `TransactionService`, `UnifiedTradingReservationService`, and product services.
- Add tests/static scans for forbidden production balance writes.
- Require all new financial operations to use idempotency + reservation + settlement.

Status: Not complete.

### Stage 7 - Full Historical Reconciliation

- Reconcile every legacy balance against ledger projections.
- Investigate mismatches manually.
- Post explicit adjustment/reversal transactions where approved.
- Preserve old rows for audit/reporting.

Status: Initial reconciliation service can detect mismatches; no auto-correction.

### Stage 8 - Legacy Deprecation

- Remove legacy reads from user-facing financial APIs only after web, mobile, admin, and workers have migrated.
- Keep historical tables archived or read-only where required.
- Never drop financial tables without a separate approved migration and rollback plan.

Status: Future.

## Product Migration Priority

1. Internal transfer and deposit confirmation.
2. Withdrawal reservation/settlement.
3. Spot order reservation and settlement.
4. Convert reservation and settlement.
5. Futures margin reservation, then futures PnL/funding/liquidation in its dedicated phase.
6. P2P escrow and release.
7. Giftcard, staking, ExaSkills, Agri, Game, ExaAI, NFT and other commerce products.
8. Admin balance adjustments and treasury reporting.

## Safety Rules

- Never reset balances.
- Never delete posted ledger history.
- Never compensate by editing historical ledger entries.
- Use reversal/adjustment transactions.
- Use BCMath-only deterministic decimal calculations.
- Use idempotency keys and database transactions.
- Use row locks for reservations and settlement.
- Keep treasury/custody balances separate from customer liability balances.

## Phase 2 Gate

Phase 2 production matching engine migration must not begin until spot order reservation and settlement are fully ledger-backed and direct spot wallet mutations are removed or hard-disabled for migrated markets.