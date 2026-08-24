# ExaCard Reconciliation

The ExaCard reconciliation service compares ledger-projected ExaCard liabilities to provider balance snapshots.

It does not silently repair financial state.

Current behaviors:

- Missing provider balance produces `REVIEW_REQUIRED`.
- Matching provider liquidity produces `PASS`.
- Reconciliation runs are persisted in `card_reconciliation_runs`.
- Findings are stored for operations review.

Provider-unknown funding states retain reservations so operations can reconcile with the issuer before settlement or release.

