# ExaEarn Phase 7 Production Trading Operations

Phase 7 adds an operational control plane over the existing ExaEarn trading stack. It does not replace the ledger, OMS, matching engine, Futures, Margin or Convert services.

## Implemented Control Plane

- `TradingRiskEngine` evaluates Spot, Futures and Margin new-risk admission.
- `PriceProtectionService` validates price quality using local/internal anchors and explicit Phase 7 price-health records.
- `CircuitBreakerService` manages global, product and market breaker states.
- `ExchangeOperationalReadinessService` returns component readiness for database, Redis, ledger, Spot, Futures, Margin, price protection, circuit breakers, lending, liquidation, treasury, wallets, realtime, queues, reconciliation and risk engine.
- `FinancialReconciliationService` combines ledger, Margin, Futures, Convert, lending and insurance checks into one run.
- `TradingOperationsController` exposes protected admin APIs for readiness, reconciliation, treasury exposure, market pause/resume, kill switch, collateral configuration, insurance fund, load probes and incidents.

## Admin Routes

- `GET /api/admin/v1/operations/readiness`
- `POST /api/admin/v1/operations/reconciliation`
- `GET /api/admin/v1/operations/treasury-exposure`
- `POST /api/admin/v1/operations/circuit-breakers`
- `POST /api/admin/v1/operations/markets/{symbol}/pause`
- `POST /api/admin/v1/operations/markets/{symbol}/resume`
- `POST /api/admin/v1/operations/kill-switch`
- `PUT /api/admin/v1/operations/collateral/{asset}`
- `POST /api/admin/v1/operations/insurance-fund/credit`
- `POST /api/admin/v1/operations/insurance-fund/use`
- `POST /api/admin/v1/operations/load-probe`
- `GET /api/admin/v1/operations/incidents`

All routes use existing Sanctum admin security and audit middleware.

