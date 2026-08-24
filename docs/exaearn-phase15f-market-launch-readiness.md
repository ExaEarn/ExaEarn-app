# ExaEarn Phase 15F Market Launch Readiness

`MarketLaunchReadinessService` evaluates whether a listed market can move toward launch.

## Required Conditions

- Listing application is `APPROVED`.
- Asset configuration exists.
- At least one market configuration exists.
- Latest listing test run is `PASS`.
- Market and listing market configuration are both `PRE_LAUNCH`.
- Liquidity requirement exists.
- At least one active market-maker assignment exists.
- At least one assigned market maker has capital readiness `READY`.
- Active market-maker bot readiness is reported, but launch remains operator-controlled.

## Safety Rule

The service returns `no_unsafe_auto_launch: true`. Phase 15F does not automatically open trading. It provides an explicit readiness decision for admin/operations review.
