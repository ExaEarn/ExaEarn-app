# ExaEarn Phase 15D Counterparty Risk

Counterparty exposure is tracked in `otc_counterparty_exposures` by provider and asset.

Limits include:

- credit limit
- settlement limit
- receivable
- payable
- unsettled notional
- rating
- status

Providers exceeding configured exposure limits are blocked from quote submission.
