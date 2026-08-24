# ExaEarn Smart Order Router

`App\Services\Liquidity\SmartOrderRouter` creates durable route plans.

It evaluates:

- Internal ExaEarn book liquidity.
- External venue/reference books after normalization.
- Source executable state.
- Split limits.
- Expected average price and cost.
- Phase 7 risk acceptance.

The router only selects levels marked executable. Reference-only depth, including public Binance depth when credentials and live state are absent, is rejected for execution.

Durability:

- `liquidity_route_plans`
- `liquidity_route_executions`
- `best_execution_audits`

Idempotency:

- Route plans are unique by `parent_reference + idempotency_key`.

The Phase 8 implementation is route-planning ready. Authenticated external execution remains gated by venue state, credentials, funding and reconciliation.
