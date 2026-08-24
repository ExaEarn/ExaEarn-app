# ExaEarn Phase 5B ADL Integration

ADL is triggered only after:

```text
bankruptcy deficit exists
AND
insurance fund cannot cover it
```

Ranking:

```text
rank_score = abs(unrealized_pnl / margin) * leverage
```

ADL reduction is idempotent at the ADL event level. Partial deleveraging reduces only the required event quantity and records:

- executed price
- executed quantity
- realized PnL
- event id
