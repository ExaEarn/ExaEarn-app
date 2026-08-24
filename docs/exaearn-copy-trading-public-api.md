# ExaEarn Copy Trading Public API

User API:

- `GET /api/v1/copy-trading/eligibility`
- `GET /api/v1/copy-trading/leaders`
- `GET /api/v1/copy-trading/leaders/{id}`
- `POST /api/v1/copy-trading/follow`
- `PATCH /api/v1/copy-trading/follow/{id}`
- `DELETE /api/v1/copy-trading/follow/{id}`
- `GET /api/v1/copy-trading/relationships`
- `GET /api/v1/copy-trading/orders`
- `GET /api/v1/copy-trading/positions`
- `GET /api/v1/copy-trading/pnl`
- `GET /api/v1/copy-trading/realtime/replay`
- `POST /api/v1/copy-trading/terms/accept`
- `POST /api/v1/copy-trading/complaints`
- `POST /api/v1/copy-trading/lead/apply`
- `GET /api/v1/copy-trading/lead/profile`
- `GET /api/v1/copy-trading/lead/performance`
- `GET /api/v1/copy-trading/lead/earnings`

Admin API:

- `GET /api/admin/v1/copy-trading/public/readiness`
- `POST /api/admin/v1/copy-trading/public/request-enable`
- `POST /api/admin/v1/copy-trading/public/approve-enable`
- `POST /api/admin/v1/copy-trading/public/pause`
- `POST /api/admin/v1/copy-trading/public/resume`
- `POST /api/admin/v1/copy-trading/public/settings`
- `GET|POST /api/admin/v1/copy-trading/public/markets`
- `GET|POST /api/admin/v1/copy-trading/public/jurisdictions`
- `GET|POST /api/admin/v1/copy-trading/public/terms`
- `GET|PATCH /api/admin/v1/copy-trading/public/complaints`

Protected user routes use Sanctum auth, `2fa` middleware where configured, throttling, and route-level rate limiting for state-changing operations.
