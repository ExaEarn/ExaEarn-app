# ExaEarn Phase 3 Reference Price Policy

## Purpose

Reference market data keeps the user experience useful when an ExaEarn market has not yet produced internal trades or book liquidity.

## Allowed Uses

- Bootstrap display price
- Reference charts for sparse markets
- Reference order-book display when internal book is empty
- Fair-price checks
- Future index/mark-price construction
- Future smart-order-routing validation

## Disallowed Uses

- Labeling external trades as ExaEarn executions
- Counting external volume as ExaEarn internal volume
- Mixing provider depth into ExaEarn internal order book
- Settling ExaEarn trades from external browser-side data
- Using frontend provider data as authoritative

## Provider

Phase 3 implements a Binance adapter behind `ExternalReferenceMarketDataService`. The frontend does not call Binance directly for Spot market fallback.

## Public Semantics

Fallback rows include:

```text
source = EXTERNAL_REFERENCE
source_type = reference
is_internal = false
reference_provider = BINANCE
```

