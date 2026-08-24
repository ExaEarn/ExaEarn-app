# ExaEarn P2P Operational Readiness

`P2POperationalReadinessService` returns:

- `READY`
- `DEGRADED`
- `NOT_READY`

Checks:

- Ledger tables.
- Reservation tables.
- P2P escrow records.
- Enabled payment methods.
- Risk events.
- Disputes.
- Reputation snapshots.
- Order events.
- Reconciliation.

External readiness is reported separately:

- Production payment verification.
- Merchant operations staffing.
- Dispute operations staffing.
- Compliance approval.

Software readiness does not mean production launch approval.
