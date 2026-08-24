# ExaEarn Phase 6 Borrow / Repay

Borrow flow:

```text
Validate account
Validate asset config
Check real pool liquidity
Simulate projected health
Consume lending capacity
Settle through canonical ledger
Create loan record
```

Repay flow:

```text
Accrue interest
Apply payment to interest
Apply remainder to principal
Settle through canonical ledger
Restore pool liquidity
Update loan status
```

Duplicate borrow and repay submissions use idempotency keys and ledger references.
