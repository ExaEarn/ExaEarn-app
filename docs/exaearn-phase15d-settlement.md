# ExaEarn Phase 15D Settlement

Internal OTC settlement is ledger-backed.

Example client buys BTC with USDT:

```text
Client USDT subaccount debit
MM USDT subaccount credit
MM BTC subaccount debit
Client BTC subaccount credit
Optional OTC fee debit
OTC fee revenue credit
```

Client funds are reserved before settlement with `ReservationService`.

External provider settlement is modeled separately and remains `PARTIAL` until live provider adapters, contracts and settlement accounts exist.
