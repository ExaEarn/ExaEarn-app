# ExaEarn Phase 15E Load Testing

Focused local validation covers quote-cycle idempotency, live order creation, worker lease exclusion and a 10-bot quote-decision storm probe persisted to `market_maker_bot_load_runs`.

The local probe is a software correctness gate, not an exchange-scale throughput claim. Quote-storm tests at 100/1,000 bots require a dedicated runtime environment with queue/worker, Redis and OMS throughput monitoring. They are not faked in local tests.
