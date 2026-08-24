# ExaEarn AgriTech Audit

## Maturity

Current level: Level 2 functional domain prototype, not production financial ready.

## What Exists

- Web pages for agriculture, share acquisition, and subscription.
- API client in `apps/web/src/services/agriApi.js`.
- Backend service/controller for projects, investments, farmer applications/review, leases, produce updates, harvest settlement queueing, and dashboard data.
- Tables for farming projects, farm shares, farmers, farm investments, leases, produce tracking, and agri rewards.
- Optional blockchain integration hooks for tokenized farm projects, investments, leases, and reward distribution.
- Feature tests for project creation, share purchase, farmer approval, progress update, and harvest settlement queueing.

## Production Blockers

- Investment purchase reduces `farm_shares.shares_available` and records an investment, but no canonical ledger reservation/debit was observed.
- Harvest returns create `agri_rewards` and optional blockchain calls, but not a complete ledger-backed payout and reconciliation workflow.
- Arithmetic helper methods still fall back to PHP float behavior if BCMath is unavailable.
- Farmer identity, land title, insurance, crop reporting, oracle verification, and investor disclosure/compliance are not production-complete.

## Required Next Work

1. Make investments ledger-backed with escrow/reservation and disbursement rules.
2. Add land/project verification, document storage, and admin review workflows.
3. Replace float fallbacks with deterministic financial decimal utilities.
4. Add harvest revenue reconciliation and investor payout settlement.

