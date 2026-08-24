# ExaEarn Phase 15B Preimplementation Audit

## Existing Systems Reused

- User identity, authentication, MFA, and sessions remain on the existing `users` and Sanctum infrastructure.
- Admin authentication, RBAC, and audit middleware remain on `admins`, `roles`, `permissions`, `admin.security`, and `admin.audit`.
- Canonical accounting remains on `accounts`, `ledger_transactions`, `ledger_entries`, `reservations`, `LedgerService`, `ReservationService`, `SettlementService`, and `BalanceProjectionService`.
- Product execution systems remain existing Spot, Futures, Margin, Convert, Copy Trading, ExaAI, Fiat, Custody, P2P, Staking, and Liquidity infrastructure.
- Developer/API access extends Phase 14 `developer_projects`, `developer_api_keys`, API permissions, HMAC signing, nonce replay protection, IP allowlists, and request logs.
- Fee calculation extends the existing `FeeCalculator` rather than creating a second fee engine.
- Phase 15A Listing remains separate and is not duplicated.

## Existing Gaps

- There is no institutional application/onboarding state machine.
- There is no institutional master account object.
- There is no subaccount model that maps to canonical ledger accounts.
- There is no institution-scoped team RBAC or subaccount permission table.
- Phase 14 API keys are not institution/subaccount scoped.
- There is no internal institutional transfer workflow with maker-checker approval.
- VIP tiers and institutional fee profiles are not modeled as auditable policy.
- There is no consolidated institutional reporting surface.

## Phase 15B Accounting Decision

Institutional subaccounts use canonical `accounts` rows with:

- `owner_type = institutional_subaccount`
- `owner_id = institutional_subaccounts.id`
- `account_type = subaccount:{type}`
- `asset = ASSET`

The `institutional_subaccounts` table stores metadata and lifecycle only. It does not store authoritative money.

## Non-Goals

- No new wallet or ledger.
- No full Phase 15C market maker system.
- No full Phase 15D OTC/RFQ.
- No cross-subaccount portfolio margin.
- No VIP/commercial override of compliance, sanctions, jurisdiction, risk, or account restrictions.
