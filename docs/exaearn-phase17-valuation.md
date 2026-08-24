# ExaEarn Phase 17 Valuation

Valuations are stored as immutable snapshots in `finance_valuation_snapshots`.

Fields:
- asset
- reporting currency
- rate
- source
- quality
- valued_at

Historical reports must use historical valuation snapshots. Missing valuations are surfaced as data-quality issues rather than silently using current prices.
