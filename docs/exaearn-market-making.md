# ExaEarn Market Making

`MarketMakingEngineService` is a controlled quote framework, not a fake-volume engine.

It supports:

- Two-sided quotes.
- Configurable spread.
- Quote TTL.
- Reference-price protection.
- Withdrawal reserve checks.
- Dedicated `market_maker_accounts`.
- Persisted `market_maker_quotes`.

It does not:

- Create fake trades.
- Count fake volume.
- Bypass Phase 7 risk controls.
- Use customer funds as proprietary market-making capital.

Production quoting requires real treasury allocation and policy approval.
