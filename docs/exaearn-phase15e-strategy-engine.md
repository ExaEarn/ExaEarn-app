# ExaEarn Phase 15E Strategy Engine

Strategies are versioned in `market_maker_bot_strategies` and `market_maker_bot_strategy_versions`.

Initial supported strategy shape:

- `TWO_SIDED_MARKET_MAKING`
- `INVENTORY_SKEW_MARKET_MAKING`
- `DEPTH_TARGET_MARKET_MAKING`
- `SPREAD_TARGET_MARKET_MAKING`

Live modifications must create a new strategy version. Quote cycles store the version used to produce each plan.
