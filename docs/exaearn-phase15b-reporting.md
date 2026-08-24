# ExaEarn Phase 15B Reporting

`InstitutionalService::report` creates consolidated institutional reports from canonical balance projections.

Reports include:

- institution ID
- report type
- period
- balance totals by subaccount and asset
- metadata

Internal transfers are deliberately excluded from revenue. They are treasury movements between institutional subaccounts, not platform revenue.

The report model is reconstructable from ledger projections and should be treated as a read model rather than the accounting source of truth.

