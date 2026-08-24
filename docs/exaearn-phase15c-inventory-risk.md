# ExaEarn Phase 15C Inventory Risk

Inventory snapshots are read models derived from canonical institutional subaccount ledger balances.

## Snapshot Fields

- current base inventory
- current quote inventory
- target quote size
- maximum exposure
- inventory utilization
- status

## Safety Modes

- `NORMAL`
- `NEW_RISK_DISABLED`
- `REDUCE_ONLY`
- `PAUSED`
- `EMERGENCY`

Safety mode changes are admin controlled and audited through the institutional audit log.
