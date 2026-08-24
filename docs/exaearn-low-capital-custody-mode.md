# ExaEarn Low-Capital Custody Mode

ExaEarn can run in low-capital mode without treating user deposits as proprietary capital.

Rules:

- User deposits increase controlled backing and user liabilities together.
- Market-making allocations remain limited to funded corporate or approved treasury capital.
- Withdrawal reserves are protected.
- External venue routing depends on actual venue balances.
- A backing shortage triggers reconciliation and operational incident handling.

`CustodyOperationalReadinessService` reports code readiness separately from funding and production infrastructure readiness.
