# ExaEarn Phase 19 Market Data Failover

`MarketDataFailoverService` selects a fallback reference source only when the primary source is stale and fallback deviation is within configured bounds.

## Rules

- Internal ExaEarn market data remains source-labelled.
- External fallback may be used as reference data.
- External depth or volume must not masquerade as internal ExaEarn activity.
- If all sources are stale or divergent, action is `DISABLE_NEW_RISK`.

