# ExaEarn Phase 6 Margin API

Authenticated routes:

- `GET /api/margin/overview`
- `GET /api/margin/accounts`
- `POST /api/margin/accounts`
- `GET /api/margin/assets`
- `GET /api/margin/pools`
- `GET /api/margin/health`
- `POST /api/margin/transfer`
- `POST /api/margin/borrow`
- `GET /api/margin/loans`
- `POST /api/margin/loans/{loanUuid}/repay`
- `POST /api/margin/loans/{loanUuid}/accrue`
- `GET /api/margin/interest`
- `POST /api/margin/liquidation-check`
- `GET /api/margin/liquidations`
- `GET /api/margin/reconciliation`

Sensitive routes are throttled and protected by Sanctum plus existing 2FA/admin middleware where configured.
