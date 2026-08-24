# ExaEarn Non-Trading Dependency Map

## Shared Dependencies

- Auth: Laravel Sanctum, existing user/admin systems.
- Finance: `LedgerService`, `SettlementService`, `ReservationService`, `BalanceProjectionService`.
- Pricing/Rewards: `PricingPolicyEngine`, `RewardPolicyEngine`, `ExaPointRewardEngineService`.
- Notifications: `NotificationService`, email/push jobs, product-specific services.
- Compliance/Security: KYC/KYB, RBAC, audit logs, jurisdiction controls, security layer.
- Realtime: `RealtimeStreamService` where implemented.

## Product Dependencies

| Product | Internal Dependencies | External Dependencies |
| --- | --- | --- |
| Giftcards | Wallet, ledger, inventory, rate engine, fraud, notifications, admin. | Giftcard provider APIs, delivery/email, treasury funding. |
| Staking | Ledger, secure signer, provider registry, admin approvals, jobs, reconciliation. | Native chain validators, RPCs, secure signing, fee wallets. |
| NFT | Blockchain service, NFT models, webhooks, wallet/ledger needed. | Smart contracts, metadata/IPFS/CDN, royalty/on-chain settlement. |
| Crowdfunding | Campaign pages, campaign generation, notifications, ledger/escrow needed. | Payment rails, legal structure, verification, fund custody. |
| ExaSkills | Ledger, courses, instructor profiles, credentials, notifications, admin. | Media storage/CDN, instructors, business customers, compliance/tax. |
| AgriTech | Blockchain service, rewards, farmer/project records, ledger needed. | Land/title verification, insurance, field verification, crop markets. |
| Games | Ledger, realtime, fairness, treasury, admin. | Gaming license/legal approval, responsible gaming tooling. |
| ExaPay | Settlement, fiat providers, webhooks, treasury, merchant risk. | Payment processors, bank rails, chargeback/dispute networks. |
| ExaCard | Ledger, card services, realtime, notifications, admin ops. | Issuer/processor, PCI, prefunding, provider webhooks. |
| Referral/Rewards | Reward policy, ExaPoints, referral abuse, jobs. | Tax/compliance, anti-fraud data providers if expanded. |
| Support | Auth, notifications, product disputes. | Helpdesk/chat provider if externalized. |

