# ExaEarn AgriTech Escrow and Settlement

Logical accounts separate investor escrow, project settlement, platform fees, farmer funding and investor funding. Customer liabilities are not treated as ExaEarn treasury capital or revenue.

Reservations prevent concurrent spending before settlement. Settlement references are stable and idempotent. The immutable ledger remains authoritative; project tables retain ledger references for product queries and reconciliation. No direct wallet balance mutation is permitted in migrated AgriTech paths.

Milestone releases are not automatic merely because a date passes. Approval, evidence and maker-checker controls are required. Failed settlement does not fabricate a payout or mutate a cached balance.
