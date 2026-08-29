# EXA Flight Reconciliation

## Service

`GameReconciliationService` reconciles EXA Flight bets against ledger entries and game state.

## Checks

It detects:

- Real-money bets missing ledger references
- Ledger references with missing ledger entries
- Active bets on completed/cancelled/failed rounds
- Settled bets missing settlement timestamps
- Cashouts with non-positive payouts

## Incident Recording

Findings are recorded as `FinanceReconciliationBreak` rows with scope `GAMES_EXA_FLIGHT`.

## Repair Policy

The service does not silently repair financial differences. Operators must review and resolve breaks through existing finance/reconciliation workflows.
