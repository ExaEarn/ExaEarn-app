# ExaEarn Affiliate Reconciliation

`AffiliateCommissionService::reconcile()` checks:
- duplicate commissions
- paid records with broken referral linkage

Material discrepancies create `affiliate_reconciliation_incidents`.

The service does not silently repair financial differences.

Future finance expansion should add:
- ledger/payable reconciliation for enabled fiat/crypto payouts
- Phase 17 statement reconciliation
- tax-year payout report verification
- cross-product revenue-event completeness checks
