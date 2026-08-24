# ExaEarn Phase 13 ExaAI Architecture

## Scope

Phase 13 turns ExaAI into a controlled automated-trading orchestration layer over ExaEarn's existing trading stack. It does not introduce a separate exchange, wallet, matching engine, ledger, or custody system.

## Authoritative Flow

```text
Strategy signal
    -> durable ExaAI decision
    -> production risk gate
    -> existing Spot/Futures order services
    -> existing OMS / matching / risk / ledger settlement
    -> ExaAI attribution, realtime, analytics and reconciliation
```

AI or model output is only a signal input. Server-side strategy rules, market eligibility, capital limits, terms acceptance, stale-market checks and global kill switches decide whether an order may be submitted.

## New Production Tables

- `exaai_portfolios`
- `exaai_market_eligibilities`
- `exaai_decisions`
- `exaai_position_attributions`
- `exaai_realtime_events`
- `exaai_reconciliation_runs`
- `exaai_reconciliation_differences`
- `exaai_surveillance_cases`
- `exaai_load_runs`
- `exaai_public_settings`
- `exaai_term_acceptances`

The existing `exaai_plans`, `exaai_subscriptions`, `exaai_capital_allocations`, `exaai_sessions`, `exaai_strategy_definitions`, `exaai_strategy_versions`, `exaai_orders` and `exaai_audit_logs` remain in use.

## Services

- `ExaAiProductionService`: accepts terms, creates portfolios, records decisions, submits approved decisions.
- `ExaAiProductionRiskService`: fail-closed risk gate for automated decisions.
- `ExaAiRealtimeService`: durable private event sequence and replay.
- `ExaAiOperationalReadinessService`: separates software readiness from public launch readiness.
- `ExaAiReconciliationService`: detects portfolio accounting differences without auto-correcting money.
- `ExaAiLoadTestService`: persists load-run results.
- `ExaAiSurveillanceService`: creates reviewable operational risk cases.

## Risk Gates

Current Phase 13 software gates include:

- active portfolio
- active ExaAI session
- active subscription
- explicit automated-trading terms acceptance
- admin global kill switch
- per-market ExaAI eligibility
- market-data freshness
- minimum confidence
- available allocated capital
- maximum market exposure
- deterministic BCMath decimal calculations

## Realtime

Private user stream: `exaai.private`

Events are persisted with:

- `user_id`
- `stream`
- `sequence`
- `event_type`
- `payload`

Clients reconnect using:

```text
GET /api/exaai/realtime/replay?after_sequence=N
```

Realtime replay is informational only. Replay cannot create financial effects.

## Execution

Approved decisions submit through `ExaAiExecutionService::executeSignal()`, which uses the existing `TradeService` for Spot and `FuturesOrderService` for Futures. ExaAI metadata is attached to order requests and ExaAI order records retain the source order references.

## Launch Distinction

Phase 13 backend software readiness does not mean public automated trading is legally or operationally ready. Public launch still requires compliance approval, operational staffing, exchange liquidity/risk policies, provider credentials where applicable, monitoring, and jurisdiction-specific eligibility controls.
