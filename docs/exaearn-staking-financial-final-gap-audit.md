# ExaEarn Staking Financial Final Gap Audit

## Scope

This closure audit traces the existing staking implementation from stake request through delegation, rewards, unstaking, reconciliation, and Phase 17 accounting. It does not redesign the canonical ledger or enable mainnet providers.

## Final gap classification

| Area | Prior state | Finding | Resolution | Final state |
|---|---|---|---|---|
| Principal custody | READY | Principal already moved through canonical staking liability accounts. | Preserved. | READY |
| Delegation uncertainty | READY | Unknown provider outcomes remain unresolved and are reconciled before release. | Preserved and included in staking reconciliation. | READY |
| Decimal arithmetic | PARTIAL | Four staking paths retained float fallbacks. | Replaced with `FinancialDecimal` operations. | READY |
| Reward recognition | READY | Only approved native reward allocations entered the payable ledger. | Added explicit Phase 17 recognition event. | READY |
| Reward claim | MISSING | Verified payable rewards could not be claimed. | Added locked, ledger-backed, single-settlement claim. | READY |
| Delegation accounting | PARTIAL | Principal transitions lacked complete Phase 17 classification. | Added reserved, activated, unstaked, reward-recognized, and reward-claimed events. | READY |
| Reconciliation | PARTIAL | Product reconciliation reported a static staking result. | Added `StakingReconciliationService` and accounting coverage checks. | READY |
| Compounding | NOT_APPLICABLE | No production compounding flow exists. | Remains disabled; no numeric principal inflation is performed. | NOT_APPLICABLE |
| Mainnet provider | EXTERNAL_OPERATIONAL_DEPENDENCY | Real provider credentials and activation are not software facts. | Kept fail-closed. | EXTERNAL_OPERATIONAL_DEPENDENCY |
| Secure signer | EXTERNAL_OPERATIONAL_DEPENDENCY | Production signer provisioning is external. | Kept fail-closed. | EXTERNAL_OPERATIONAL_DEPENDENCY |
| Validator/fee wallet | EXTERNAL_OPERATIONAL_DEPENDENCY | Real chain funding is required. | Kept fail-closed. | EXTERNAL_OPERATIONAL_DEPENDENCY |

## Verified money flow

`funding -> staking_pending -> staking_active -> staking_pending_unstake -> funding`

Verified rewards follow:

`native staking rewards clearing -> staking_reward_payable -> funding`

Each arrow is a balanced canonical ledger transaction. Position and provider tables are operational read models; they do not replace the ledger.

