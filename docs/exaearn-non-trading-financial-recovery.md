# ExaEarn Non-Trading Financial Recovery

## Restart Recovery

Product services must be able to resume from persisted product state, reservations, ledger transactions and reconciliation findings. Redis/realtime delivery is never a financial source of truth.

## Failure Points

Products must be safe across:

- after reservation but before provider call;
- after provider submission but before provider result;
- provider unknown/timeout;
- after provider success but before ledger settlement;
- after ledger settlement but before product final-state update;
- notification failure;
- worker restart;
- duplicate retry.

## Unknown Provider State

When outcome is ambiguous, do not release funds and do not retry blindly. Mark the product record as provider/chain unknown and reconcile before settlement or release.

## Compensating Actions

Refunds, reversals and corrections must create new double-entry transactions. Existing ledger rows remain immutable.

