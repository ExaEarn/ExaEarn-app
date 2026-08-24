# ExaEarn Financial Write Policy

Date: 2026-08-17

## Principle

The canonical double-entry ledger is the authoritative source of truth for migrated financial flows.

No migrated service may directly mutate user money in legacy tables as an authoritative operation. Legacy tables may temporarily remain as compatibility projections, read bridges or migration sources, but not as the final source of financial truth.

## Required Money Flow

All financial operations must follow this order:

```text
Operation request
  -> idempotency validation
  -> account ownership/policy validation
  -> reservation or hold when funds must be protected
  -> settlement service
  -> canonical double-entry ledger
  -> immutable ledger transaction and entries
  -> balance projection
  -> compatibility projection where still required
```

## Allowed Writers

Only these services are allowed to write canonical financial state for migrated Phase 1 flows:

- `LedgerService`
- `ReservationService`
- `SettlementService`
- `LedgerReversalService`
- narrowly scoped compatibility projection updates inside migration bridges

Product services such as spot, convert, internal transfer and futures order management must request reservations or settlements through the canonical services instead of changing balances directly.

## Disallowed Patterns In Migrated Flows

Do not use these as authoritative balance operations:

```php
$wallet->available_balance -= $amount;
$wallet->locked_balance += $amount;
$balance->balance = $newBalance;
WalletBalance::query()->increment(...);
WalletBalance::query()->decrement(...);
InternalAccount::query()->increment(...);
InternalAccount::query()->decrement(...);
```

Do not compensate failed operations by manually adding value back to the same mutable balance row. Use reservation release or ledger reversal.

Do not use PHP `float`, `double`, `round()` or implicit numeric conversion for authoritative financial calculations.

## Legacy Tables

The following tables remain during migration:

- `wallets`
- `wallet_balances`
- `balances`
- `internal_accounts`
- `internal_wallet_transactions`

Their permitted uses are:

- historical compatibility
- custody metadata
- migration seed source
- frontend/mobile compatibility projection
- dual-read reconciliation

Their forbidden use after Phase 1 migration is:

- independently authorizing a trade, conversion, internal transfer or withdrawal
- directly locking/releasing funds for migrated product flows
- overwriting canonical ledger state

## Idempotency

Each external or internal financial operation needs a stable unique reference or idempotency key.

Required for:

- deposits
- withdrawals
- internal transfers
- spot settlements
- convert executions
- fees
- reservations
- reservation consumption
- reversals
- provider callbacks

Retries must return the original result or fail safely. They must never create duplicate money movement.

## Reversals

Posted ledger entries are immutable.

Corrections must be new reversal transactions with opposite balanced entries and an auditable link to the original transaction.

The old delete-based rollback model is not allowed for posted financial history.

## Projection Rule

Balance projections may cache or mirror ledger state for performance and client compatibility, but they must always be reconstructable from:

- `accounts`
- `ledger_transactions`
- `ledger_entries`
- `reservations`

If projection and ledger disagree, the ledger wins and reconciliation must report the mismatch.

