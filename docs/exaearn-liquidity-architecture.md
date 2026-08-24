# ExaEarn Liquidity Architecture

Phase 8 introduces a liquidity control plane above the completed Phase 1-7 financial, trading and risk systems.

```text
Internal ExaEarn Book
Treasury Buckets
Approved Market Makers
External Venue Adapters
Reference Feeds
        ↓
NormalizedMarketDataService
        ↓
ConsolidatedLiquidityBookService
        ↓
SmartOrderRouter
        ↓
Liquidity Route Plans / Executions
        ↓
Reservation + Settlement + Reconciliation
```

Rules:

- ExaEarn internal order book remains separate from external venue/reference books.
- Public venue data can inform routing and display, but it is not executable unless the venue is `LIVE`.
- Customer balances remain separate from ExaEarn treasury and market-making capital.
- Withdrawal reserve protection takes priority over market-making, convert and rebalancing allocation.
- Phase 7 `TradingRiskEngine`, price protection and circuit breakers remain authoritative pre-trade controls.
