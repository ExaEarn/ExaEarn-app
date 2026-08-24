# ExaEarn Phase 13 ExaAI API

## User APIs

- `POST /api/exaai/terms/accept`
- `GET /api/exaai/portfolio`
- `POST /api/exaai/decisions`
- `POST /api/exaai/decisions/{id}/execute`
- `GET /api/exaai/realtime/replay`
- `GET /api/exaai/readiness`

## Admin APIs

- `GET /api/admin/exaai/readiness`
- `POST /api/admin/exaai/market-eligibility`
- `POST /api/admin/exaai/controls`
- `GET /api/admin/exaai/surveillance-cases`

## Decision Contract

Required fields include:

- `idempotency_key`
- `product`
- `symbol`
- `side` or `action`
- `requested_notional`
- `confidence`
- `market_snapshot.updated_at`

Malformed structured output fails closed.
