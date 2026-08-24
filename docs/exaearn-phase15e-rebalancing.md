# ExaEarn Phase 15E Rebalancing

Bot rebalancing creates durable `market_maker_bot_rebalances` records. `RECOMMEND_ONLY` records a recommendation. `AUTOMATED_WITH_LIMITS` delegates movement to the existing institutional transfer workflow, preserving maker-checker thresholds and canonical ledger settlement.

Duplicate rebalance idempotency keys do not create duplicate transfers.
