# ExaEarn Phase 15B Subaccounts

Institutional subaccounts are not standalone wallets. They are permissioned desks or treasury buckets attached to a master institutional account.

Supported types:

- `TREASURY`
- `GENERAL`
- `SPOT`
- `FUTURES`
- `MARGIN`
- `API`
- `OTC`
- `MARKET_MAKER`

Each subaccount can receive its own canonical ledger account per asset through `InstitutionalService::canonicalSubaccountLedgerAccount`.

Internal treasury movement uses:

```text
source institutional_subaccount ledger account
→ debit
destination institutional_subaccount ledger account
→ credit
```

Large transfers move to `PENDING_APPROVAL` and require maker-checker approval. Small transfers can settle immediately after permission and balance checks.

