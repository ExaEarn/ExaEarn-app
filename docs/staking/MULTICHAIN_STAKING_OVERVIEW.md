# Multichain Staking Overview

Supported Native PoS assets: SOL, ETH, ADA, BNB, AVAX, SUI, DOT, ATOM, NEAR, XTZ, POL.

Every asset is seeded as `development`, `emergency_paused=true`, and `new_positions_enabled=false`. Mainnet activation must be enabled per asset after testnet delegation, activation, verified reward detection, reward distribution, unstaking, principal release, and ledger reconciliation pass.

Implemented Laravel foundation:

- Staking schema and seeded native PoS assets.
- Fail-closed provider registry for SOL, ETH, ADA, BNB, AVAX, SUI, DOT, ATOM, NEAR, XTZ, and POL.
- Ledger-based pending stake reservation.
- Delegation batch creation from batched positions when provider health, validator allowlist, staking wallet secure-key reference, fee balance, secure signer, and transaction broadcast checks pass.
- Delegation confirmation monitoring from provider-confirmed transaction state.
- Stake activation monitoring from provider-verified active delegation state.
- Ledger-backed activation from `staking_pending` to `staking_active` after a delegation batch is marked `activated`.
- Failed delegation confirmation reverses pending staking principal back to the user funding account through a balanced ledger journal.
- Ledger-backed unstake reservation from `staking_active` to `staking_pending_unstake`.
- Principal release back to `funding` only after an unstake request is marked `withdrawable` or `principal_withdrawn`.
- Approved native reward distribution from `native_staking_rewards_clearing` to user `staking_reward_payable`, with platform commission posted separately.
- HTTP secure signer adapter bound to `SecureSignerInterface`; it fails closed without signer URL, key reference, and secret.
- Admin asset/product controls.
- Mainnet activation and validator changes through dual approval.
- Scheduled health, batching, delegation confirmation, activation, reward-fetch, reward-distribution, principal-release, reconciliation, slashing, and ExaToken reserve guard jobs.

Local PostgreSQL migration status on July 24, 2026: `2026_07_24_000001_create_exaearn_staking_tables` and `2026_07_24_000002_create_staking_admin_approvals_table` ran successfully.

Current verification:

- `php artisan test` passes with 134 tests and 454 assertions.
- `php artisan test --filter=ExaEarnStakingRemovalTest` passes with 18 tests and 68 assertions.
- `php artisan schedule:list` lists the staking scheduler loop.
- `node --check backend/services/blockchain-service/src/controllers/blockchainController.js` passes.
- `corepack pnpm --filter exaearn-blockchain-service lint` passes.
- `corepack pnpm --filter exaearn-blockchain-service test` passes with 1 suite and 5 tests.
- `corepack pnpm --filter exaearn-blockchain-service contracts:compile` compiles 36 Solidity files.

Current blocker:

No supported chain is mainnet-ready. Live testnet staking flows for delegation, activation, verified rewards, unstaking, unbonding, principal release, and ledger reconciliation still require configured RPC endpoints, custody wallets, funded fee wallets, validator allowlists, secure signer access, and chain-specific testnet funds.
