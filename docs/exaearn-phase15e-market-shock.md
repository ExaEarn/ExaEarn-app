# ExaEarn Phase 15E Market Shock

Market shock protection compares current trusted fair value against the previous quote-cycle fair value. When movement exceeds configured thresholds or market data is stale, the bot escalates to `LIMIT_NEW_RISK` or `PAUSED` and calls mass cancel through the OMS path.

The shock engine records incidents with evidence and does not enter a panic requote loop.
