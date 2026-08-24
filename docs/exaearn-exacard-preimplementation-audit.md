# ExaCard Preimplementation Audit

Date: 2026-08-24

## Scope

ExaCard was audited as a card-issuance orchestration layer inside the existing ExaEarn monorepo. It must reuse ExaEarn financial, compliance, security, finance, notification, admin and reliability infrastructure rather than creating a disconnected card wallet.

## Capability Map

| Capability | Status | Notes |
| --- | --- | --- |
| Canonical ledger | EXISTS | `LedgerService`, `Account`, `LedgerTransaction`, `LedgerEntry` are the authoritative source for migrated financial balances. |
| Reservations | EXISTS | `ReservationService` supports concurrency-safe active/consumed/released reservations. |
| Settlement | EXISTS / NEEDS_INTEGRATION | Product-specific services settle into `LedgerService`; ExaCard now uses `CardSettlementService` over canonical ledger. |
| Balance projection | EXISTS | `BalanceProjectionService` provides total/available/reserved by account type and asset. |
| Decimal precision | EXISTS | `FinancialDecimal` requires BCMath and rejects float fallback. |
| Central pricing | EXISTS | `PricingPolicyEngine` is the product-wide pricing authority. ExaCard uses it for funding fee previews where policy is configured/enforced. |
| Rewards | EXISTS | `RewardPolicyEngine` exists but ExaCard rewards are not enabled by default. |
| Treasury | EXISTS / NEEDS_INTEGRATION | Phase 17 treasury/accounting exists. ExaCard now has provider-balance snapshots and reconciliation entry points. |
| Custody | EXISTS | Card funding does not touch custody directly; custody remains separate from internal accounting. |
| KYC | EXISTS | `users.kyc_level`, verified/residence country and KYC admin flows exist. |
| Compliance | EXISTS | `CompliancePolicyService` is used for ExaCard eligibility decisions. |
| Security/fraud | EXISTS | `SecurityRiskEngine` is used for issue/fund/unload/detail decisions. |
| Finance accounting | EXISTS | `FinanceAccountingService` records card funding/unload ledger events into Phase 17 journals. |
| Notifications | EXISTS | `NotificationService` is used for issuance/funding notifications. |
| Admin RBAC/audit | EXISTS | Admin routes use existing `auth:sanctum`, `admin.security`, `admin.audit` and permissions. ExaCard also records card audit logs. |
| Existing card backend | MISSING | No production ExaCard backend existed. Giftcard and payment-card references were separate products. |
| Provider abstraction | MISSING | Added `CardProviderInterface`, registry and sandbox provider. |
| Real card provider | EXTERNAL_PROVIDER_REQUIRED | No SudoCard/Maplerad/Flutterwave/UfitPay live credentials or adapter were configured. Live issuance remains disabled. |
| Web UI | PARTIAL | Added a connected web ExaCard page from More. Full card-detail UX, disputes and transaction drill-down still need product UI expansion. |
| Mobile UI | MISSING | Mobile source exists but ExaCard mobile screens were not added in this pass. |
| PCI review | EXTERNAL_PROVIDER_REQUIRED | ExaCard does not persist PAN/CVV and uses provider-tokenized details, but production card launch still requires external PCI/provider review. |

## Existing Infrastructure Reused

- `LedgerService`
- `ReservationService`
- `BalanceProjectionService`
- `FinancialDecimal`
- `PricingPolicyEngine`
- `CompliancePolicyService`
- `SecurityRiskEngine`
- `FinanceAccountingService`
- `NotificationService`
- Admin RBAC, security and audit route middleware

## Key Non-Goals

- No plaintext PAN/CVV/PIN storage.
- No direct card-provider debit from arbitrary user balances.
- No duplicate wallet table for card funds.
- No live provider claims without a configured live card issuer.

