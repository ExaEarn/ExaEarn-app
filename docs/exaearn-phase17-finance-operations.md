# ExaEarn Phase 17 Finance Operations

Admin routes:
- `GET /api/admin/v1/finance/overview`
- `GET /api/admin/v1/finance/backing`
- `GET /api/admin/v1/finance/trial-balance`
- `GET /api/admin/v1/finance/balance-sheet`
- `POST /api/admin/v1/finance/reports/snapshot`
- `POST /api/admin/v1/finance/ledger/{reference}/event`
- `POST /api/admin/v1/finance/adjustments`
- `POST /api/admin/v1/finance/adjustments/{uuid}/approve`
- `POST /api/admin/v1/finance/close/prepare`
- `GET /api/admin/v1/finance/readiness`
- `GET /api/admin/v1/finance/breaks`
- `GET /api/admin/v1/finance/dlq`

Operational setup is still required for live bank/custody/RPC verification.
