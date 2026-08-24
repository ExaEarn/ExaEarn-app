# ExaEarn Wallet Rebalancing

Phase 9 adds `DepositSweepService` and network-fee reserve tracking.

Sweep decisions can return:

- `NO_ACTION`
- `SWEEP_TO_HOT`
- `SWEEP_TO_WARM`
- `SWEEP_TO_COLD`
- `CONSOLIDATE_LATER`

Sweeping does not create or remove user liabilities. It only moves controlled backing between custody locations.

Hot/cold rebalancing must honor Phase 8 withdrawal reserve and treasury policies.
