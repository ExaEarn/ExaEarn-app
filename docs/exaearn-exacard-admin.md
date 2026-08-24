# ExaCard Admin Operations

Admin ExaCard is exposed through:

```text
Admin -> ExaCard
```

Backend routes under `/api/admin/v1/exacard` provide:

- Overview
- Customers
- Cards
- Transactions
- Funding and unload requests
- Disputes
- Treasury
- Provider status
- Revenue and provider cost totals
- Audit logs
- Provider balance updates
- Reconciliation runs

Routes use existing admin auth, admin security middleware, admin audit middleware, and permission middleware.

