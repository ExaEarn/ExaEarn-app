# ExaEarn Merchant Settlement

Merchant settlement records are stored in `merchant_settlements`.

The initial settlement service aggregates captured ExaEarn Pay intents for a merchant and records gross amount, fees, refunds and net amount.

Actual external bank settlement requires a configured live provider, settlement account funding and compliance approval.
