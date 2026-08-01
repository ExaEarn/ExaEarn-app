# Staking Reconciliation

Reconciliation tables track provider health, RPC health, validator health, reconciliation reports, differences, slashing events, and audit logs.

Networks should auto-pause when ledger liabilities, custody balances, delegated balances, reward receivables, ExaToken reserves, or provider statements diverge beyond configured tolerance.

Implemented scheduled jobs:

`MonitorRpcHealth`
`ProcessPendingStakeRequests`
`ActivateStakingPositions`
`FetchNativeStakingRewards`
`DistributeNativeStakingRewards`
`ReleaseUnstakedPrincipal`
`ReconcileStakingWallets`
`DetectSlashingEvents`
`EvaluateExaTokenBonusEligibility`

These jobs are unique/idempotent and fail closed when providers are not configured.

`ActivateStakingPositions` only acts on delegation batches already marked `activated`.
`DistributeNativeStakingRewards` only acts on reward batches already marked `approved`.
`ReleaseUnstakedPrincipal` only acts on unstake requests already marked `withdrawable` or `principal_withdrawn`.

No job releases principal or rewards from estimated dates or displayed APY.
