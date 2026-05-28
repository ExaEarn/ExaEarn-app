# ExaEarn Ledger System

## Overview
- Double-entry accounting engine with PostgreSQL-backed tables.
- Every committed ledger transaction must satisfy: `sum(entries) = 0`.
- Account balances are cached in `accounts.balance`; ledger entries are source-of-truth.

## Tables
- `accounts`
- `ledger_transactions`
- `ledger_entries`

## Core Service
`App\Services\LedgerService`
- `createTransaction(reference, description)`
- `addEntry(accountId, amount, asset, reference, type, userId?, metadata?)`
- `commitTransaction(reference)`
- `rollbackTransaction(reference)`
- `postDoubleEntry(reference, description, entries, type)`

Operation helpers:
- `fiatDeposit`
- `cryptoDeposit`
- `internalTransfer`
- `trade`
- `withdrawal`
- `chargeFee`
- `exapointReward`

## API
Under `/api/ledger`:
- `POST /transactions`
- `POST /entries`
- `POST /commit`
- `POST /rollback`
- `POST /operations`

## Real-time
On commit, service publishes Redis event:
- Channel: `ledger_updates`
- Payload: `{ user_id, account_type, asset, balance }`

Node websocket bridge:
- Redis subscriber: `src/services/ledgerUpdateSubscriber.js`
- Websocket hub: `src/services/ledgerUpdateHub.js`
- Socket event: `ledger:update`
- WS path: `/ws/ledger`

## Reconciliation
- Service: `App\Services\ReconciliationService`
- Command: `php artisan ledger:reconcile`
- Compares user account totals against treasury per asset and logs mismatches.

## Security Controls
- No negative account balances.
- Duplicate ledger reference rejected.
- Commit blocked if double-entry sum is non-zero.
- All updates are atomic DB transactions.

## Testing
- `tests/Feature/LedgerEngineTest.php`
  - double-entry enforcement
  - fiat deposit path
  - internal transfer path
