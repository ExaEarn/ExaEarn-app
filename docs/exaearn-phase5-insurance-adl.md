# ExaEarn Phase 5 Insurance Fund and ADL

## Insurance Fund

Insurance balances are canonical ledger accounts:

```text
futures_insurance_fund:{asset}
```

Liquidation fee credits are routed to this account through double-entry ledger settlement.

## ADL

ADL is an emergency mechanism, not ordinary liquidation.

Phase 5 introduces deterministic ADL ranking based on:

```text
rank_score = abs(unrealized_pnl / margin) * leverage
```

The highest ranked opposing profitable positions are first in queue. Selection is deterministic and auditable.
