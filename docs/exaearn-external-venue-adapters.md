# ExaEarn External Venue Adapters

Phase 8 adds:

- `LiquiditySourceInterface`
- `ExternalVenueAdapterInterface`
- `BinanceLiquidityAdapter`
- `LiquiditySourceRegistry`

Venue states:

- `UNCONFIGURED`
- `CREDENTIALS_REQUIRED`
- `SANDBOX`
- `TESTING`
- `READY`
- `LIVE`
- `DEGRADED`
- `PAUSED`
- `DISABLED`

Current status:

- Binance adapter is available for reference market data.
- Binance is not considered live executable liquidity unless explicitly enabled, authenticated, funded, reconciled and tested.
- Unsupported authenticated execution methods fail closed.

Secrets:

- Secret values are not stored in Phase 8 tables.
- Only environment/KMS/vault references are stored.
- Withdrawal permission defaults to disabled.
