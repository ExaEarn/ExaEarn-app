# ExaEarn Phase 5B Partial Liquidation

Phase 5B replaces single-step full liquidation with a deterministic liquidation ladder.

## Ladder

```text
refresh PnL using mark price
if healthy: stop
reduce configured partial ratio
record liquidation event
recalculate
repeat until healthy or max stage
full liquidation only when required
```

Configured keys:

- `futures.liquidation.partial_liquidation_ratio`
- `futures.liquidation.max_stages`
- `futures.liquidation.fee_rate`

Each stage records:

- liquidation id
- stage
- requested quantity
- executed quantity
- mark price
- bankruptcy price
- fee
- durable status
