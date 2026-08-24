# ExaEarn Pricing, Fees, Rewards, VIP and Commission Audit

## Summary

The existing ExaEarn codebase had pricing and commercial logic spread across product-specific services and configuration files. The new centralized Pricing and Rewards Policy Engine is introduced as the canonical commercial policy layer for fee/reward decisions, while preserving the ledger and settlement systems as the financial source of truth.

## Existing Sources Found

| Area | Current source | Status |
| --- | --- | --- |
| Spot/Futures trading fees | `FeeCalculator`, `config/fees.php`, market/listing records | Bridged to central pricing engine with legacy fallback |
| Institutional/VIP fees | `InstitutionalFeeProfile`, `VipTierService`, `config/fees.php` | Central engine supports institution, user contract and VIP precedence |
| Withdrawal fees | `FeeCalculator`, custody/fiat withdrawal services/config | Central engine supports asset/network/country dimensions |
| Fiat fees | `config/fiat.php`, `FeeCalculator`, payment provider services | Central engine supports fiat product and provider-fee snapshots |
| Convert pricing | `SwapPricingService`, `SwapEngineService`, market/reference price services | Pricing engine supports convert fee/spread decisions; swap execution remains ledger-backed |
| P2P fees | P2P domain services/config | Central engine supports P2P product/merchant tier dimensions |
| Staking commission | Staking admin/product configuration | Central engine supports staking operation dimensions |
| Market maker rebates | `MarketMakerRebateService` | Remains ledger-backed; central reward engine supports rebate policy decisions |
| Rewards/referrals | `RewardEngineService`, `RewardActivity`, `RewardSecurityService` | Reward policy engine added with caps and abuse checks |
| Giftcard/NFT/agri margins | Product-specific pricing configs/services | Audited as migration targets |

## Central Policy Model

Pricing rules are versioned and dimensioned by product, operation, asset, network, market, country, VIP tier, merchant tier, user, institution, promotion and priority.

Precedence is deterministic:

1. User contract
2. Institution contract
3. Promotion
4. VIP tier
5. Merchant tier
6. Country
7. Product default

Fee types supported:

- Fixed
- Percentage
- Hybrid
- Spread
- Tiered
- Dynamic
- Waived
- Rebate
- Custom contract

Negative fees are blocked unless the rule explicitly allows a rebate.

## Reward Policy Model

Reward policy rules support fixed, percentage, revenue-share, tiered and milestone rewards. Decisions enforce daily/lifetime user caps, campaign budgets and existing reward-abuse inspection before issuing approved rewards.

## Safety Controls

- Pricing decisions are snapshotted with rule version and expiry.
- Rule changes use maker-checker approval for pricing rules.
- Shadow comparisons record legacy-vs-engine fee differences.
- Existing financial settlement remains ledger-backed.
- Existing product flows keep compatibility fallback where no approved central rule exists.
- Reward math no longer falls back to PHP floats.

## Remaining Migration Work

The central engine is available and tested, but several product-specific services still retain their legacy configuration fallback until ExaEarn operators seed approved pricing rules and disable shadow mode per product.
