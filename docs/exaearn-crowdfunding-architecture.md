# ExaEarn Crowdfunding Architecture

Crowdfunding is implemented as a non-trading product on top of existing ExaEarn financial infrastructure.

## Components

- `CrowdfundingService`: campaign lifecycle, pledges, milestone release, updates, refunds and account-closure checks.
- `CrowdfundingReconciliationService`: pledge/escrow ledger checks and incident creation.
- `CrowdfundingController`: authenticated user campaign, pledge, update and milestone submission APIs.
- `Admin\CrowdfundingOperationsController`: admin review, milestone, refund and reconciliation APIs.
- `CrowdfundingOperationsPage`: admin operational center wired to real backend APIs.

## Source Of Truth

- Campaign state: crowdfunding tables.
- Money state: canonical ledger and reservations.
- Balances: existing balance projection.
- Compliance: Phase 16 policy checks.
- Admin controls: existing RBAC middleware.

