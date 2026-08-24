# ExaEarn Phase 15C Preimplementation Audit

Phase 15C was implemented on top of the existing Phase 8 liquidity layer and Phase 15B institutional account layer.

## Reused Infrastructure

- Canonical ledger: `LedgerService`, `BalanceProjectionService`, institutional subaccount ledger accounts.
- Institutional accounts: `InstitutionalAccount`, `InstitutionalSubaccount`, memberships, permissions and audit events.
- Developer API keys: project/key creation, scope validation, subaccount scoping and rate profile storage.
- Phase 8 liquidity: `market_maker_accounts`, `market_maker_quotes`, liquidity source registry, SOR, treasury inventory, health and reconciliation services.
- Admin security: existing `auth:sanctum`, `admin.security`, `admin.audit`, throttling and role permission checks.

## Important Existing Constraint

Phase 8 market-maker quotes are protected quote records and do not fabricate exchange fills or internal market volume. Phase 15C preserves that policy. Live order placement remains routed through the normal OMS/risk/ledger systems when product order APIs are used.

## Gaps Closed

- Dedicated market-maker application and approval workflow.
- Maker-checker activation for market-maker profiles.
- Dedicated `MARKET_MAKER` institutional subaccount requirement.
- Market assignments and listing liquidity agreements.
- Capital readiness from canonical institutional subaccount ledger projections.
- Inventory snapshots from ledger balances.
- Market liquidity health snapshots from active Phase 8 quote records.
- Rebate accrual and ledger-backed settlement.
- Related-institution overlap surveillance case generation.
- Market-maker safety modes and mass quote cancellation.

## Remaining External Dependencies

- Signed commercial market-maker agreements.
- Real market-maker capital funding beyond test/admin seeded balances.
- External venue credentials and production liquidity venue funding where required.
- Human operations staffing and compliance approvals.
