# Staking Deployment Checklist

For each network: configure RPC failover, configure secure signer, fund fee wallet, configure custody wallet, add validator allowlist, pass delegation, pass activation, pass verified reward detection, pass reward allocation, pass unstaking, pass principal release, pass ledger reconciliation, test emergency pause, record dual approval, then enable mainnet.

Current local verification:

`php artisan migrate --force` completed for staking migrations.
`php artisan staking:remove-legacy-xrp --drop-resolved` completed after a zero-liability XRP audit.
`php artisan test --filter=ExaEarnStakingRemovalTest` passed with 18 tests and 68 assertions.
`php artisan test` passed with 134 tests and 454 assertions.
`php artisan schedule:list` lists the staking scheduler loop, including pending-position processing, delegation batching, delegation confirmation, activation monitoring, ledger activation, reward distribution, principal release, reconciliation, RPC health, validator/slashing checks, and ExaToken campaign reserve monitoring.
Legacy Node paper-staking controller methods and `stakingContractService.js` were removed.
`node --check backend/services/blockchain-service/src/controllers/blockchainController.js` passed.
`corepack pnpm install --filter exaearn-blockchain-service --force --fetch-retries=5 --fetch-timeout=300000` completed after registry retry.
`corepack pnpm --filter exaearn-blockchain-service lint` passed after adding the ESLint 9 flat config and fixing source lint issues.
`corepack pnpm --filter exaearn-blockchain-service test` passed with 1 Jest suite and 5 tests.
`corepack pnpm --filter exaearn-blockchain-service contracts:compile` compiled 36 Solidity files successfully after Hardhat compiler cache access was allowed.

Production blockers:

No supported chain is mainnet-ready yet. Every asset remains disabled/fail-closed until RPC endpoints, custody wallets, secure signer integration, validator allowlists, fee funding, and real testnet delegation/reward/unstake/reconciliation evidence are recorded and dual-approved.
