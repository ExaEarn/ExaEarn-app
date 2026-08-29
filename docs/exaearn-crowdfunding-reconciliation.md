# ExaEarn Crowdfunding Reconciliation

`CrowdfundingReconciliationService` checks:

- active campaign raised amount versus active escrow/released/refund-pending pledges
- held pledge reservation references
- held pledge ledger references
- minimum double-entry ledger entries for pledge escrow

Findings create `CrowdfundingReconciliationIncident` records. The service does not silently repair financial discrepancies.

