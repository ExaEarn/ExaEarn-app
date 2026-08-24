# ExaEarn Phase 15E Hedging

Hedging is controlled by bot configuration and supports `DISABLED`, `RECOMMEND_ONLY` and `AUTOMATED_WITH_LIMITS`.

Automated hedges are sent through the real Futures order service, record funding and basis-risk metadata, and use idempotent hedge records in `market_maker_bot_hedges`. The hedge layer never fabricates positions or PnL.
