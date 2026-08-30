# Non-Trading Ledger and Accounting Reconciliation

## Contract

The canonical ledger answers who owns or owes an asset. Phase 17 answers the economic classification of that movement. Reconciliation compares the two; it never uses accounting to rewrite the ledger.

## Checks

The product reconciliation service checks material non-trading ledger transaction types for a linked finance event. Product reconcilers additionally inspect lifecycle state, reservations, payables, escrows, settlements, reversals, and provider-unknown records. Finance journals are checked per asset with `FinancialDecimal` so debit and credit totals equal zero.

Detected discrepancies include:

- ledger transaction without accounting event;
- accounting event without valid source provenance;
- duplicate source identity;
- amount, asset, or product mismatch;
- unbalanced journal;
- missing payable, escrow, revenue, refund, or reversal classification;
- unresolved provider outcome.

## Incident policy

Discrepancies are surfaced through existing reconciliation findings/incidents. Historical ledger or accounting records are not deleted or silently patched. Corrections use explicit adjustment or reversal events and respect closed accounting periods.

