# ExaEarn Phase 17 Financial Security

Security controls:
- Admin finance endpoints require Sanctum, `admin.security`, `admin.audit`, and finance permissions.
- Financial adjustments require maker-checker approval.
- Financial close approval requires segregation of duties.
- Export/report snapshot routes require finance export permission.
- Finance DLQ and breaks are read through finance reconciliation permissions.

No private keys, wallet secrets, provider secrets, or internal credentials are exposed.
