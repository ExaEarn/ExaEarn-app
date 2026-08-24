# ExaEarn Phase 2C Market Migration Policy

## Initial Policy

For initial production cutovers, legacy open orders are not imported into the new engine.

The safe policy is:

1. Stop accepting new legacy orders.
2. Let already-created authoritative settlements finish.
3. Halt the market.
4. Cancel remaining open legacy orders.
5. Release only each order's remaining reservation.
6. Run ledger and reservation reconciliation.
7. Initialize the new engine.
8. Replay and verify the journal.
9. Run canary execution.
10. Promote the market to `new`.

## Why Not Import Legacy Open Orders Yet

Open-order import is deferred until ExaEarn can prove:

- priority can be reconstructed deterministically
- remaining quantity is correct
- price and ownership are correct
- reservation consumption cannot be duplicated
- imported journal entries are deterministic and auditable

Until then, cancel-and-release is safer.

## Markets Tested Locally

- `CUT2C/USDT`
- `BTC/USDT`
- `ETH/USDT`

## Markets Migrated In Production

None. No actual production market cutover was performed in this local development run.

