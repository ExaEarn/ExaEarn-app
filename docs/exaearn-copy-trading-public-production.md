# ExaEarn Copy Trading Public Production

ExaEarn Copy Trading public production is controlled by server-side mode, product flags, jurisdiction rules, market eligibility, terms acceptance, follower risk controls, surveillance, complaints, and admin activation workflows.

The public activation layer does not replace the Phase 12 Copy Trading engine. Public copied trades still flow through genuine lead executions, copy fanout, follower sizing, risk checks, the existing Spot/Futures OMS, matching, settlement, ledger accounting, strategy attribution, realtime, and profit-share services.

## Public Modes

`COPY_TRADING_MODE` supports:

- `DISABLED`
- `SHADOW`
- `INTERNAL`
- `LIMITED_PUBLIC`
- `PUBLIC`
- `PAUSED`
- `EMERGENCY`

Public activation flags are independently controlled:

- `SPOT_COPY_PUBLIC`
- `FUTURES_COPY_PUBLIC`
- `LEAD_TRADER_APPLICATIONS_PUBLIC`
- `PROFIT_SHARE_PUBLIC`

Each flag supports `DISABLED`, `LIMITED`, `ENABLED`, and `PAUSED`.

## External Approval Separation

The backend can report software and operational readiness while regulatory/legal status remains `PENDING`. No code path marks regulatory approval as approved unless configured through the explicit external approval state.
