# XRP Staking Removal Report

Run:

```bash
php artisan staking:remove-legacy-xrp
```

The command exports encrypted legacy XRP staking records to `storage/app/staking/xrp-removal/*_legacy_xrp_staking_backup.enc` and writes a JSON report beside it.

It freezes legacy XRP staking pools and refuses table drops when outstanding principal or reward liabilities remain. Use `--drop-resolved` only after all liabilities are zero.

Local audit result on July 24, 2026:

Affected users: 0.
Total XRP principal: 0.
Rewards credited: 0.
Outstanding principal: 0.
Outstanding reward liabilities: 0.

Encrypted backup:

`backend/api-gateway/storage/app/private/staking/xrp-removal/20260724_114049_legacy_xrp_staking_backup.enc`

Report:

`backend/api-gateway/storage/app/private/staking/xrp-removal/20260724_114049_xrp_staking_removal_report.json`

Legacy local tables were dropped after the zero-liability audit.

Code removal completed:

`backend/api-gateway/app/Models/StakingPool.php`
`backend/api-gateway/app/Models/UserStake.php`
`backend/api-gateway/app/Models/StakingReward.php`
`backend/api-gateway/app/Jobs/CalculateStakingRewardsJob.php`
`backend/api-gateway/app/Jobs/CompoundRewardsJob.php`
`backend/api-gateway/app/Jobs/DistributeRewardsJob.php`
`backend/services/blockchain-service/src/services/stakingContractService.js`
`backend/services/blockchain-service/contracts/XRPStakingContract.sol`
`backend/services/blockchain-service/scripts/deploy-xrp-hybrid.js`
`backend/services/blockchain-service/test/XRPStakingHybrid.test.js`
`backend/services/blockchain-service/README_XRP_STAKING.md`

Ordinary XRP wallet, bridge, deposit, withdrawal, buy, sell, convert, and trading flows are intentionally out of scope for this removal and must remain available through their non-staking modules.
