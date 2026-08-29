# EXA Flight Financial Flow

## Demo / Free-Play

Demo entries use non-withdrawable demo credits.

- They do not debit user funding accounts.
- They do not create user asset liabilities.
- They do not create canonical reservations.
- They do not write ledger entries.
- They are marked with `mode=demo`.
- Metadata records `sandbox_no_withdrawal_value=true`.

## Real Money

Public real-money mode is disabled by default. When legally enabled and configured, the flow is:

```text
Eligibility
-> Compliance and security checks
-> Responsible-gaming checks
-> Treasury-risk checks
-> ReservationService reserves user funding
-> Entry stores reservation_id
-> Ledger lock consumes reservation and moves funding -> game_locked
-> Cashout, loss, cancel or refund settlement
-> Canonical ledger
-> Phase 17 accounting event
-> Reconciliation
```

## Canonical Accounts

- User source: `funding`
- User locked game balance: `game_locked`
- Platform game treasury: `game_treasury`

No independent game wallet is introduced.

## Accounting Events

`FinanceAccountingService::recordLedgerEvent` records:

- `GAME_ENTRY_LOCKED`
- `GAME_CASHOUT_SETTLED`
- `GAME_LOSS_SETTLED`
- `GAME_CANCELLED_REFUND`

Locked customer funds are not recognized as revenue.
