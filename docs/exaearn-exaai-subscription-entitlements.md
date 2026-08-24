# ExaEarn ExaAI Subscription Entitlements

## Separation

Subscriptions answer: what ExaAI capabilities can this user access?

Strategies answer: how should ExaAI trade allocated capital?

The system intentionally permits combinations such as Elite subscription plus Conservative strategy. A plan may unlock strategies but does not force the user into a risk profile.

## Effective Permission

`ExaAiEntitlementService` calculates the effective permission using the most restrictive result from:

- Active subscription and expiry
- Plan entitlements
- Account status
- Phase 16 compliance policy
- Phase 18 security risk engine
- Market eligibility
- Current usage against plan limits

## Entitlement Fields

- `exaai_access`
- `maximum_ai_capital`
- `allowed_strategies`
- `allowed_markets`
- `spot_enabled`
- `futures_enabled`
- `maximum_leverage`
- `maximum_positions`
- `market_scanning_coverage`
- `signal_frequency`
- `portfolio_rebalancing`
- `advanced_tp_sl`
- `analytics_level`
- `strategy_customization`
- `api_bot_access`
- `priority_features`

## Upgrade / Downgrade Policy

Plan upgrades unlock future capacity but do not automatically increase leverage, allocation or existing positions.

Plan downgrades do not force unsafe liquidation. If current usage exceeds the new plan, effective permission returns a protected state such as `USER_ACTION_REQUIRED` or no-new-risk until exposure is brought within limits.

## Admin Controls

Admins can update ExaAI plan entitlements through:

`PATCH /api/admin/exaai/plans/{id}/entitlements`

Every change requires a reason and writes an ExaAI audit log.
