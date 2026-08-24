# ExaEarn Pricing Product Migration Matrix

| Product | Current Source | Central Engine Available | Shadow Comparison | Difference Status | Migration Ready | Final Action |
| --- | --- | --- | --- | --- | --- | --- |
| Spot | `FeeCalculator`, `config/fees.php`, listing market fee fields | YES | YES | PASS after seeding `SPOT/MAKER_FEE` and `SPOT/TAKER_FEE` | YES | Central enforcement enabled through `FeeCalculator` |
| Futures | `FeeCalculator`, `config/fees.php`, futures config | YES | YES | PASS after seeding `FUTURES/MAKER_FEE` and `FUTURES/TAKER_FEE` | YES | Central enforcement enabled through `FeeCalculator` |
| Crypto Withdrawals | `FeeCalculator`, custody withdrawal fee config | YES | YES | PASS for asset fee, platform fee and network fee rules | YES | Central enforcement enabled through `FeeCalculator` and custody `WithdrawalFeeService` |
| Convert | `SwapPricingService`, `config/swap.php` | YES | YES | PASS after converting `swap.fee_percent` to bps | YES | Central fee decision included in immutable quote metadata |
| Fiat | `FeeCalculator`, fiat/payment provider config | YES | YES | PASS for fiat deposit fee rules; provider costs remain provider reconciliation inputs | YES | Central enforcement enabled for ExaEarn fiat fee component |
| P2P | `P2PFeeService`, `config/p2p.php` | YES | YES | PASS after converting maker/taker/merchant rates to bps | YES | Central enforcement enabled through P2P fee quote |
| Staking/Earn | Staking product/admin configuration | YES | YES | PASS for platform commission default; yield/reward source remains staking provider/accounting | YES | Central pricing rule seeded for ExaEarn commission |
| ExaAI | Entitlement/subscription services plus underlying Spot/Futures fees | YES | YES | PASS for subscription/usage pricing; generated trading fees use Spot/Futures rules | YES | Central pricing rule seeded for ExaAI commercial fees |
| Institutional | `InstitutionalFeeProfile`, VIP tier config | YES | YES | PASS via user/institution/VIP dimensions and deterministic precedence | YES | Central enforcement enabled through institutional market fee path |
| OTC | `OtcRfqService`, provider fee/spread fields | YES | YES | PASS for RFQ spread/provider fee rule seed; execution remains RFQ/settlement-backed | YES | Central pricing rules available for explicit OTC contracts |
| Market Maker Rebates | `MarketMakerRebateService` | YES | YES | PASS with explicit rebate rule requiring `allow_negative` | YES | Rebate policy seeded; ledger settlement remains canonical |
| Affiliate | Referral/reward services | YES | YES | PASS through RewardPolicyEngine policy decision path | YES | Central reward policy available; no parallel reward ledger |
| Referral | `ReferralService`, `RewardEngineService` | YES | YES | PASS through RewardPolicyEngine with caps and abuse controls | YES | Central reward policy path active with legacy activity fallback only when no policy exists |

## Enforcement Notes

`PRICING_ENFORCED_PRODUCTS` controls fail-safe behavior. When a product is listed there, missing or unavailable central pricing does not silently fall back to legacy config; the action fails closed.

Current default enforced products:

```text
SPOT,FUTURES,WITHDRAWAL,CONVERT,FIAT,P2P,STAKING,EXAAI,INSTITUTIONAL,OTC,MARKET_MAKER,AFFILIATE,REFERRAL
```

Legacy config files remain in the repository as audit evidence and as rule seeding sources. They are no longer the intended final authority for migrated products.
