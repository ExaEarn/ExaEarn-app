# ExaEarn Phase 15F Emergency Controls

`Phase15EmergencyControlService` supports scoped emergency controls:

- `GLOBAL`
- `MARKET`
- `INSTITUTION`
- `BOT`

Supported controls:

- `GLOBAL_LIQUIDITY_EMERGENCY`
- `PAUSE_NEW_RISK`
- `MARKET_HALT`

## Effects

For market scope, the service:

- Halts the market.
- Disables matching OTC market configuration.
- Mass-cancels affected market-maker bot orders.
- Pauses affected bots.
- Persists before/after state in `phase15_emergency_controls`.

Emergency controls are administrative operations and require a reason.
