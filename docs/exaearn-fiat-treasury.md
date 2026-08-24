# ExaEarn Fiat Treasury

Fiat treasury state is represented by:

- `fiat_treasury_balances`
- `fiat_withdrawal_reserves`
- `provider_settlements`
- `fiat_daily_snapshots`

The reserve service calculates:

- pending withdrawals
- 24h volume
- provider balance
- minimum reserve
- target reserve
- stress reserve

Phase 10 supports low-capital mode: software can operate in sandbox/testing while production funding status remains explicit.
