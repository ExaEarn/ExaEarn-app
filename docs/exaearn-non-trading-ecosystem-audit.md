# ExaEarn Non-Trading Ecosystem Audit

Date: 2026-08-24

Scope: static repository audit of non-trading modules only. No product build work was performed.

## Maturity Scale

- Level 0: Concept/UI only.
- Level 1: Foundation exists: routes, pages, models, or migrations, but important workflows are incomplete.
- Level 2: Functional software path exists with persistence and some tests, but has production blockers.
- Level 3: Production software ready: ledger/security/admin/reconciliation/test gates are materially present, while real-world provider/compliance operations may still be external.
- Level 4: Real-world operationally ready: software plus external providers, liquidity/treasury, legal/compliance, staffed operations, monitoring, and reconciliation are active.

## Master Readiness Table

| Product | Current Level | Evidence | Main Blockers |
| --- | ---: | --- | --- |
| Giftcards | 2 | Web buy/sell pages, Laravel controllers, inventory/rate/fraud/delivery services, migrations, feature tests. | Direct wallet mutations still exist, floats in service interfaces, provider purchase is simulated in `GiftCardPurchaseService`, delivery/email TODOs, treasury/reconciliation incomplete. |
| Earn/Staking | 3 software / 2 operational | v1 routes, admin routes, provider registry, ledger-backed staking reservation/unstake flows, fail-closed reward claims, many focused tests. | Mainnet provider enablement, secure signer/wallet funding, live reward allocation/reconciliation, operations readiness. |
| NFT Marketplace | 1 | Web marketplace and API client, controller, NFT finance migrations/models. | `App\Services\NftService` is referenced but not present, so routes are not service-backed; no proven ledger settlement or provider/on-chain finality. |
| Crowdfunding | 1 | Web pages and hook, fallback campaign data, backend campaign generation service and campaign tests. | Crowdfunding pages fall back to mock data; no complete persisted pledge/escrow/disbursement/refund product identified. |
| ExaSkills | 2 | ExaSkills routes, service, categories/courses/enrollment, paid course ledger split, challenge escrow, credentials, admin overview, focused tests. | Course creation/upload UI still has placeholders; business portal disabled; full LMS assessments/media/moderation/subscription operations incomplete. |
| AgriTech | 2 | Agri routes/service, farming/farmer/share/lease/reward migrations, blockchain optional hooks, feature tests. | Investment purchase does not reserve/debit through canonical ledger; harvest returns create reward records and optional blockchain calls but no complete fiat/ledger settlement; float fallback remains. |
| Web3 Games / Gaming | 2 | EXA Flight service, realtime, fairness, ledger lock/cashout/loss settlement, tests. | Product is gambling-like and uses betting terminology/tables; needs licensing/jurisdiction controls, responsible gaming, treasury risk, surveillance, and operations before public launch. |
| ExaPay / Payments | 2 to 3 software | Phase 10 fiat/payment tests, `ExaEarnPayService` uses settlement for capture, provider router/webhook/dispute/refund services. | Merchant product UX/admin coverage is incomplete; provider credentials/settlement ops external; withdrawal/payments must remain gated. |
| ExaCard | 3 software / 2 operational | Dedicated ExaCard reports, card routes, provider abstraction, ledger funding/unload, webhooks, admin operations, realtime, notification tests. | Real card issuer, PCI/program approval, prefunding, provider production credentials, compliance operations. |
| Affiliate / Referral / Rewards | 2 | Referral binding, abuse checks, leaderboard jobs, ExaPoint reward engine, reward policy engine, focused tests. | Some reward payouts are ExaPoint-only; revenue attribution and broad anti-abuse operations need more depth; product-wide reward policy migration is partial. |
| Notifications | 2 | Notification model/service/controller, in-app/email/push jobs, stats and retry methods. | Product event coverage is uneven, no unified template/campaign preference center found, delete cleanup is destructive, delivery observability/dedup is limited. |
| Support / Disputes / Help Center | 0 to 1 | Web support/help/live chat pages; product-specific disputes exist in P2P/Card/Fiat. | Support ticket form is local UI success only; no unified ticket model/API/workflow/SLA/assignment/escalation discovered. |
| Personalization / Module Discovery | 2 | User preferences routes/tests, dashboard registry/composer, web and mobile personalization components. | Demo/local fallback still exists, feature eligibility/compliance/product readiness not fully enforced across module discovery. |

## Cross-Product Gates

| Gate | Status | Notes |
| --- | --- | --- |
| Canonical ledger usage across non-trading money flows | PARTIAL | Staking, ExaSkills, ExaPay, ExaCard, and Flight are ledger-aware. Giftcard and AgriTech still have direct wallet/share/reward mutations. |
| Provider abstraction | PARTIAL | Staking, fiat/payments, ExaCard, gift card routing, and blockchain service abstractions exist. Some product paths still simulate provider success or lack a service. |
| Admin operations | PARTIAL | Stronger for ExaCard, Staking, Pricing/Rewards, Giftcard, ExaSkills, Flight. Several module dashboard routes return placeholder data. |
| Notifications | PARTIAL | Shared notification service exists but integration coverage varies by product. |
| Reconciliation | PARTIAL | Stronger in finance/card/fiat/staking. Weak or absent in NFT, crowdfunding, support, and parts of giftcards/agri. |
| Compliance / jurisdiction | PARTIAL | Mature compliance phases exist globally, but product-level gating is uneven for gaming, agriculture investments, NFTs, giftcards, and rewards. |
| Mobile parity | WEAK | Mobile includes giftcard, staking, ExaCard, and personalization, but most ecosystem modules are web-only. |
| Production readiness for the whole ecosystem | NO | Several modules are Level 0-2 and some runtime/service gaps remain. |

## Highest-Risk Findings

1. Giftcard financial flows are not yet canonical enough: `GiftCardPurchaseService` deducts `Wallet.available_balance` directly, uses `float` parameters, and simulates external provider success.
2. NFT API routes appear runtime-broken because controllers import `App\Services\NftService`, but no service class was found.
3. Crowdfunding and support include polished pages that can appear complete while relying on mock/local behavior.
4. AgriTech has meaningful domain persistence, but investor money and harvest distributions are not fully ledger-backed.
5. Gaming uses real ledger settlement but needs regulatory, jurisdiction, treasury exposure, and responsible-gaming controls before release.

## Recommended Product Phase Order

1. Fix runtime-broken or deceptive surfaces: NFT service gap, support ticket persistence, crowdfunding mock fallback labeling.
2. Migrate high-risk money paths: giftcard buy/sell settlement and AgriTech investment/harvest settlement to canonical reservations/ledger.
3. Complete admin/reconciliation for Staking, Giftcards, ExaSkills, AgriTech, ExaPay, and Rewards.
4. Add product-level compliance gates for Gaming, AgriTech investments, NFTs, Giftcards, and Rewards.
5. Fill mobile parity only after backend truth and financial safety are complete.

## Audit Reports

- `docs/exaearn-giftcards-audit.md`
- `docs/exaearn-earn-staking-audit.md`
- `docs/exaearn-nft-marketplace-audit.md`
- `docs/exaearn-crowdfunding-audit.md`
- `docs/exaearn-exaskills-audit.md`
- `docs/exaearn-agritech-audit.md`
- `docs/exaearn-games-audit.md`
- `docs/exaearn-exapay-audit.md`
- ExaCard: see existing `docs/exaearn-exacard-final-completion-report.md`
- `docs/exaearn-affiliate-rewards-audit.md`
- `docs/exaearn-non-trading-notifications-audit.md`
- `docs/exaearn-support-operations-audit.md`
- `docs/exaearn-non-trading-financial-integrity-audit.md`
- `docs/exaearn-non-trading-admin-operations-audit.md`
- `docs/exaearn-non-trading-dependency-map.md`
- `docs/exaearn-non-trading-production-roadmap.md`

