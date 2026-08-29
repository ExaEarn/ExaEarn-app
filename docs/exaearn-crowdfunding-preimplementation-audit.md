# ExaEarn Crowdfunding Preimplementation Audit

## Scope Inspected

- Web crowdfunding pages and `useCrowdfunding`.
- Admin module registry and generic module placeholder.
- Existing campaign-generation and notification infrastructure.
- Canonical financial services: `LedgerService`, `ReservationService`, `SettlementService`, `BalanceProjectionService`.
- Phase 16 compliance, account closure safety, notifications and admin RBAC patterns.

## Findings

- The previous user-facing crowdfunding flow relied on static campaign fallback data whenever the API was absent, empty or failing.
- Admin crowdfunding used a generic placeholder module instead of real campaign, escrow, payout, refund and reconciliation operations.
- No complete persisted pledge escrow, milestone payout, refund batch or crowdfunding reconciliation service existed.
- There was no explicit investment/equity crowdfunding gate.

## Money Flow Implemented

Backer funding account -> canonical reservation -> crowdfunding escrow ledger account -> milestone creator payable -> creator funding account, or escrow -> backer funding account for refunds.

No separate crowdfunding wallet was introduced.

