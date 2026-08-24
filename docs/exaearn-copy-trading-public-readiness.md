# ExaEarn Copy Trading Public Readiness

`PublicCopyTradingReadinessService` returns separate readiness dimensions:

- Software readiness from the Phase 12 copy backend readiness service.
- Product readiness for markets, terms, criteria, capacity, and risk configuration.
- Operations readiness for review, surveillance, complaint, capacity, emergency, and production roles.
- External readiness for compliance/legal approval.

Possible high-level states:

- `SOFTWARE_READY`
- `OPERATIONALLY_READY`
- `EXTERNAL_APPROVAL_PENDING`
- `PUBLIC_READY`

The service intentionally does not collapse external legal status into a software boolean.
