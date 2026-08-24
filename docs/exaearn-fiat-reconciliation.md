# ExaEarn Fiat Reconciliation

Reconciliation compares:

- user fiat liabilities from canonical accounts
- controlled backing from provider, treasury and settlement-bank accounts
- coverage ratio

The service records `fiat_reconciliation_runs` and `fiat_reconciliation_differences`.

It reports discrepancies and does not auto-correct money.

Corrections must use explicit reversal or adjustment flows.
