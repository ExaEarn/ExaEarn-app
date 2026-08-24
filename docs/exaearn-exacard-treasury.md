# ExaCard Treasury

ExaCard treasury separates:

- User funding balances
- User ExaCard account balances
- ExaEarn card fee revenue
- Provider payable/cost accounts
- Provider balance snapshots

Card funding reserves user funding money first, then settles through canonical double-entry ledger entries after provider completion.

Provider balance snapshots expose `REBALANCE_REQUIRED` when available provider liquidity drops below the configured required minimum.

