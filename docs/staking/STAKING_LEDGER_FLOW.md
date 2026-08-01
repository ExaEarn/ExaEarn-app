# Staking Ledger Flow

Stake request reservation:

Debit user `funding` asset account.
Credit user `staking_pending` asset account.

Activation, reward distribution, commission, unstaking, and release journals must use balanced ledger entries only. The current implementation does not activate stakes or credit rewards without provider verification.

Implemented local lifecycle journals:

Pending to active activation:

Debit user `staking_pending` asset liability.
Credit user `staking_active` asset liability.

Failed delegation reversal:

Debit user `staking_pending` asset liability.
Credit user `funding` asset account.

Unstake reservation:

Debit user `staking_active` asset liability.
Credit user `staking_pending_unstake` asset liability.

Verified principal release:

Debit user `staking_pending_unstake` asset liability.
Credit user `funding` asset account.

Approved native reward distribution:

Debit platform `native_staking_rewards_clearing`.
Credit user `staking_reward_payable`.
Credit platform `staking_commission_revenue` when a platform fee exists.

These flows are implemented in `App\Domain\Staking\Services\StakingLedgerService` and exercised by the staking feature tests. They do not create chain rewards; they only move value after verified activation, permanent delegation failure, approved reward batches, or confirmed withdrawable principal.
