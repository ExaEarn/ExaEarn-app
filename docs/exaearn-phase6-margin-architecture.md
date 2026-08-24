# ExaEarn Phase 6 Margin Architecture

ExaEarn Margin is implemented as an accounting and risk layer on top of the canonical ledger.

```text
Margin Account
  -> Collateral / Health Service
  -> Borrow / Repay Service
  -> Lending Pool
  -> SettlementService
  -> LedgerService
```

## Account Modes

- Cross: `margin_cross`
- Isolated: `margin_isolated_{base}_{quote}`

The account mode controls which ledger account type is valued and which loans are included in health checks.

## Source Of Truth

Ledger accounts remain authoritative for balances. Margin tables track configuration, risk state, loans, pool capacity, liquidations, bad debt, and reconciliation findings.
