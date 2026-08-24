# ExaEarn Fiat Architecture

The Phase 10 fiat stack is:

```text
Provider / Bank Rail
  -> Provider adapter
  -> Webhook security and normalization
  -> Fiat deposit / withdrawal processor
  -> ReservationService
  -> SettlementService
  -> Canonical Ledger
  -> Reconciliation and admin operations
```

The canonical source of truth remains the Phase 1 ledger. Fiat tables store operational state, provider references, webhook evidence, virtual accounts, beneficiaries, transfer attempts, treasury buckets and reconciliation results.

New user APIs are exposed under `fiat/*`. Admin operations are exposed under `admin/v1/fiat/*`.

Production provider credentials and legal/compliance approval are intentionally separate from backend software readiness.
