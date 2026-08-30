# ExaEarn Staking Financial Integrity

## Authority

The canonical ledger is authoritative for user principal and payable rewards. Staking positions, delegation batches, provider transactions, reward allocations, and unstake requests describe lifecycle state and provenance.

## Principal lifecycle

- A stake request creates one idempotent principal reservation in `staking_pending`.
- Verified delegation moves principal to `staking_active`.
- Unstake initiation moves only eligible active principal to `staking_pending_unstake`.
- Principal becomes available only after a withdrawable provider state is verified.
- Rejected operations reverse through ledger entries; uncertain provider outcomes remain unresolved.

## Reward lifecycle

Estimated APY and estimated rewards are display information and create no liability. An approved native reward allocation creates a balanced `staking_reward_distribution` into `staking_reward_payable`. Claim locks the position, calculates verified unclaimed rewards with `FinancialDecimal`, verifies the payable ledger balance, and posts one `staking_reward_claim` into funding.

The invariant is:

`claimed_native_rewards <= total_native_net_rewards <= verified provider allocation`

Repeated or concurrent claims cannot pay more than the locked verified remainder. Accounting retries use the ledger transaction as their stable source identity.

## Reconciliation

`StakingReconciliationService` reports, without repairing:

- negative or misplaced principal liability balances;
- claimed rewards exceeding verified rewards;
- claimable rewards exceeding the payable ledger;
- open provider-unknown delegation batches;
- unresolved staking reconciliation reports;
- staking ledger transactions missing Phase 17 events;
- unbalanced staking journals.

Production provider activation, signer provisioning, and validator/fee-wallet funding remain operational dependencies.

