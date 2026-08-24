# ExaEarn Phase 15B VIP Fees

VIP tier definitions are stored in `vip_tier_definitions`. Tier history is immutable through `vip_tier_history`.

`VipTierService` calculates eligible tiers from volume, assets, maker ratio and market maker status. Compliance and lifecycle state cap the effective tier to `STANDARD` until the institution is active and compliance-approved.

`FeeCalculator::institutionalMarket` applies fee precedence:

1. Institution-specific fee profile
2. VIP tier definition
3. Standard market fee

The returned fee includes a `fee_policy_snapshot` so later financial records can prove which commercial rule produced the fee.

