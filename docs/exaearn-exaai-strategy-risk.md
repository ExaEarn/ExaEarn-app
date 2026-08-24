# ExaEarn ExaAI Strategy Risk

## Strategy Profiles

The current strategy definitions are:

- Conservative
- Balanced
- Aggressive

Each strategy stores deterministic constraints such as position percentage, trade frequency and minimum signal confidence. Strategy versions also store supported products, supported markets and risk rules.

## Governance

Strategy versions are governed through lifecycle states:

`DRAFT -> BACKTESTING -> SHADOW -> RISK_REVIEW -> APPROVED -> LIMITED_PRODUCTION -> PRODUCTION`

Operational rollback states include:

`RESTRICTED`, `PAUSED`, `RETIRED`.

Transitions are recorded in `exaai_strategy_transitions`.

## Risk Gate

New ExaAI risk passes through:

Decision normalization -> Entitlement -> Compliance -> Security -> Market eligibility -> Position sizing -> Risk -> OMS.

No strategy output can directly credit wallets, debit wallets, settle trades or bypass canonical ledger infrastructure.
