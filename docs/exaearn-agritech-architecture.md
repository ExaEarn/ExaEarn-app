# ExaEarn AgriTech Architecture

AgriTech reuses the canonical ExaEarn platform. It does not own a wallet or ledger.

```text
Project classification and evidence
  -> compliance and security decision
  -> share row lock
  -> ReservationService
  -> SettlementService
  -> canonical LedgerService
  -> investor allocation
  -> milestone-controlled disbursement
  -> verified harvest revenue
  -> investor payout or refund
  -> reconciliation
```

The project state machine is explicit. Public funding requires a verified project, approved legal state, enabled product configuration, eligible jurisdiction and user, sufficient canonical available balance, and available share capacity. Blockchain registration is optional metadata and cannot establish legal title.

Read models and product records are reconstructable against canonical ledger transaction references. Realtime or frontend state never authorizes financial effects.
