# ExaEarn Phase 6 Liquidation

Phase 6 adds liquidation state records and bad-debt detection.

When health falls below the liquidation threshold:

```text
MarginAccount -> LIQUIDATION_PENDING
MarginLiquidation -> PENDING
MarginBadDebt -> created when equity is negative
```

The current implementation records deterministic liquidation state and bad debt but does not yet execute production Spot liquidation orders. That remains a launch blocker before live customer enablement.
