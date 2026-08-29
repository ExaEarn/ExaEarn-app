# ExaPay Reconciliation

`ExaPayMerchantService::reconcile` records merchant reconciliation runs.

Current checks:

- captured payment without ledger reference
- duplicate ledger references

Material failures create reconciliation difference rows and can be used by operations to hold merchant settlement.

The reconciliation service does not silently repair ledger or provider differences.
