# ExaEarn Affiliate Production Gap Audit

## Current State

Status: LEVEL 3 production software ready after this pass.

Existing foundations reused:
- Referral binding and loop prevention.
- ReferralService qualified activity queue.
- RewardPolicyEngine and PricingPolicyEngine policy infrastructure.
- ExaPoints reward-domain balance.
- RewardSecurityService and ReferralAbuseService.
- Admin RBAC/audit middleware.
- Canonical ledger boundary for real financial payouts.

## Gap Classification

| Area | Status | Notes |
| --- | --- | --- |
| Referral binding | READY | Existing immutable referred-user unique constraint remains authoritative. |
| Self-referral prevention | READY | Binding rejects referrer matching referred user. |
| Loop prevention | READY | Existing ancestor traversal prevents loops. |
| Qualified activity | READY | Existing queue remains; ExaAI subscription activity now routes to affiliate commission events. |
| Central commission policy | READY | AffiliateCommissionService uses RewardPolicyEngine when configured, then tier policy fallback. |
| Commissionable registry | READY | `config/affiliate.php` centrally gates eligible product events. |
| Cross-product integration | PARTIAL | ExaAI subscription purchase is active. Other products are explicitly disabled until each revenue event is wired. |
| ExaPoint rewards | READY | ExaPoints remain the supported reward instrument. |
| ExaToken distribution | EXTERNAL_REQUIREMENT | Disabled/fail-closed pending legal, treasury, contract and accounting readiness. |
| Payout workflow | READY | ExaPoint payout workflow is idempotent and auditable. Cash/crypto payout rails remain disabled. |
| Reversals/clawbacks | READY | Pre-payout reversals reverse commission; post-payout reversals create clawback obligations. |
| Fraud holds | READY | Reward security flags move commissions to HELD. |
| Admin operations | READY | Admin affiliate overview, commissions, payouts, tiers, clawbacks, incidents and reconciliation endpoints added. |
| Tax policy | EXTERNAL_REQUIREMENT | Software can report earnings; tax law/configuration requires external review. |
| Mobile affiliate | PARTIAL | No broad mobile rebuild was performed. API contracts are compatible for mobile parity. |

## Money Flow

Commissionable activity must be settled before it can create affiliate earnings:

1. Product emits settled economic event.
2. AffiliateCommissionService checks central registry.
3. Referral relationship is resolved.
4. RewardPolicyEngine or affiliate tier policy decides commission.
5. Security checks may hold the commission.
6. Commission remains PENDING during the hold window.
7. Mature PENDING commission becomes AVAILABLE.
8. ExaPoint payout credits ExaPoints idempotently.
9. Reversal either cancels unpaid commission or creates clawback obligation after payout.

No wallet balance columns are mutated by the affiliate service.
