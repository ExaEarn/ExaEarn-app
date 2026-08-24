# ExaEarn Price Protection

`PriceProtectionService` protects pre-trade order entry from invalid or extreme prices.

## Implemented Behavior

- rejects non-positive trusted reference prices
- rejects stale trusted prices
- rejects excessive order-price deviation from a trusted anchor
- supports explicit index price median calculation
- supports mark price calculation from index and last price
- persists price snapshots and source-health records

## Bootstrap Policy

New markets may not have a trusted trade/index/reference anchor. For those markets, Phase 7 allows limit-order bootstrap without pretending `last_price` is authoritative. Once a market has a real execution or explicit source-health/snapshot anchor, deviation checks apply.

This prevents false rejections for internal market bootstrapping while preserving protection for anchored markets.

