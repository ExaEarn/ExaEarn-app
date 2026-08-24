# ExaEarn P2P Reconciliation

`P2PReconciliationService` verifies unresolved P2P orders against active reservations.

Checks:

- Open escrowed trades have a reservation ID.
- Reservation exists.
- Reservation is active or partially consumed.
- Reservation remaining amount is not below the trade crypto amount.

Runs are stored in `p2p_reconciliation_runs`.

Invariant:

`active P2P escrow = sum(active reservation remaining amounts for unresolved P2P orders)`
