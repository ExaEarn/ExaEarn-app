# ExaEarn Affiliate / Referral / Rewards Audit

## Maturity

Current level: Level 2 functional.

## What Exists

- Referral binding during registration and referral dashboard routes.
- Referral service with loop detection, abuse checks, qualified activity processing, leaderboard jobs, and paginated rewards.
- Reward engine with activity sync, daily caps, fraud/security inspection, policy engine integration, and ExaPoint issuance.
- Rewards pages and referral pages in web.
- Tests for daily check-in, reward caps, suspicious activity, disabled token distribution, pricing/reward policy, referral-related audit logs.

## Gaps

- Rewards are now issued as ExaPoints; ExaToken distribution intentionally fails closed.
- Commission/revenue-share accounting across every product is not fully proven from this audit.
- Affiliate tiering, payout ledger, tax reporting, chargeback/reversal clawbacks, and advanced fraud operations need productization.

## Required Next Work

1. Define affiliate commercial terms and commissionable events centrally.
2. Add payout/reversal/clawback ledger workflows.
3. Expand anti-abuse signals and admin case review.
4. Connect rewards to product pricing policy and finance reporting.

