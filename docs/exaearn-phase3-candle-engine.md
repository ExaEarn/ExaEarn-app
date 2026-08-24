# ExaEarn Phase 3 Candle Engine

## Source

Candles are built from ExaEarn `trades` for markets with internal executions.

Each candle contains:

- `open_time`
- `close_time`
- `open`
- `high`
- `low`
- `close`
- `base_volume`
- `quote_volume`
- `trade_count`
- `source`

## Intervals

Supported intervals:

```text
1m, 3m, 5m, 15m, 30m, 1h, 4h, 1d
```

## Empty Candle Policy

If a market has no ExaEarn trades in the requested range, Phase 3 returns external reference candles with `source=EXTERNAL_REFERENCE` when available. It does not fabricate internal ExaEarn candles or volume.

If a market has internal trades, returned candles are built from those trades.

## Recovery

The candle read model is reconstructable from `trades`. Future materialized candle tables must remain rebuildable from trade history and must deduplicate by trade/execution ID.

