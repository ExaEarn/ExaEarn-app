# ExaEarn Market Data and Convert Regression Remediation

Date: 2026-09-01

## Root cause

`MarketDataService::ticker()` had its external-reference lookup and merge branch commented out. The method therefore returned an `EXAEARN_INTERNAL` market-contract shell for every existing market, even when ExaEarn had no internal execution and the configured reference provider had returned a valid price.

This confused market identity with observation provenance. It also left `reference_price` null, causing Convert pricing for BTC/USDT to fail with no usable price.

## Correction

- Restored the established internal-first/reference-fallback behavior in `MarketDataService`.
- A genuine ExaEarn execution remains `EXAEARN_INTERNAL` and may carry an external reference alongside it.
- With no internal execution, a valid provider observation remains `EXTERNAL_REFERENCE`.
- Provider failure continues to return no fabricated reference price.
- Convert now accepts only the canonical internal or external source values and rejects missing, unknown, malformed, future-dated, or stale observations.
- Reference freshness uses `swap.reference_price_max_age_seconds`, defaulting to 30 seconds.

No pricing arithmetic, decimal precision, fees, market matching, Listing controls, or ledger behavior changed.

## Evidence and tests

The regression was reproduced in `Phase3MarketDataTest` and `Phase4ConvertEngineTest`: the reference fallback was labeled internal, and Convert could not price BTC/USDT. Focused coverage now proves:

- market-record existence does not establish internal provenance;
- real ExaEarn executions retain internal provenance;
- a fresh external BTC/USDT reference is usable by Convert;
- stale external observations fail closed;
- unknown source values do not default to internal;
- provider failure does not fabricate a price;
- internal order books/trades/candles cannot be overwritten by external fallback data.

The current implementation does not cache or persist provider ticker observations, so no cache schema, cache migration, or production data migration is required.

## Files changed

- `backend/api-gateway/app/Services/MarketDataService.php`
- `backend/api-gateway/app/Services/SwapPricingService.php`
- `backend/api-gateway/config/swap.php`
- `backend/api-gateway/tests/Feature/Phase4ConvertEngineTest.php`

## Remaining operational consideration

The external adapter timestamps observations when ExaEarn successfully receives and validates the provider response. Provider transport availability remains an external dependency and continues to fail closed for Convert when no valid internal price exists.
