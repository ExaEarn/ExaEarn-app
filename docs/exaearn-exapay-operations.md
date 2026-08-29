# ExaPay Operations

Admin ExaPay APIs are available under:

```text
/api/admin/exapay/*
```

Sections supported by backend data:

- overview
- merchants
- applications/KYB
- payments
- payment links
- refunds
- disputes
- settlements
- provider health
- reconciliation
- risk
- reports
- audit via existing admin audit middleware

High-impact production actions remain gated by RBAC, reason capture, audit and external provider readiness.
