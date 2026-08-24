# ExaEarn Phase 15B Institutional Architecture

Phase 15B adds institutional master accounts, VIP commercial controls, subaccounts, team RBAC, scoped developer API keys and consolidated reporting around existing ExaEarn infrastructure.

It does not introduce a second wallet, ledger, user account or settlement system. Subaccounts are operational containers. Their authoritative financial state is represented by canonical ledger `accounts` rows where `owner_type = institutional_subaccount`, `owner_id = institutional_subaccounts.id`, and `account_type = subaccount:{TYPE}`.

Core flow:

```text
Application
→ Admin KYB/risk state machine
→ Maker-checker activation
→ Institutional master account
→ Subaccounts + team roles
→ Canonical ledger accounts
→ BalanceProjectionService
→ Reports / API / admin
```

Application states are `DRAFT`, `SUBMITTED`, `KYB_REVIEW`, `RISK_REVIEW`, `APPROVED`, `ACTIVE`, `REJECTED`, `SUSPENDED`, and `CLOSED`. Activation requires a second admin after approval recommendation.

## Reused Systems

- Laravel Sanctum authentication
- Admin RBAC, `admin.security`, and `admin.audit`
- Canonical `LedgerService`, `SettlementService`, and `BalanceProjectionService`
- Phase 14 developer projects and API key security
- Existing fee calculation framework
- Existing admin dashboard module registry

