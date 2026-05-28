# ExaEarn XRP Staking System (Hybrid XRPL -> Base)

This module implements a hybrid XRP staking architecture where XRP is locked on XRPL and represented as `wXRP` on Base for staking operations.

## Implemented Components

### 1) Wrapped token contract
- File: `contracts/WrappedXRP.sol`
- Token: `Wrapped XRP (wXRP)`
- Decimals: `6` (aligned with XRP drops model)
- Security: `AccessControl` with `BRIDGE_ROLE`
- Core bridge functions:
  - `mint(address,uint256)` (bridge-only)
  - `burn(address,uint256)` (bridge-only)

### 2) XRP staking contract
- File: `contracts/XRPStakingContract.sol`
- Security: `AccessControl` + `ReentrancyGuard`
- Stake model:
  - `StakeInfo { amount, startTime, lockDuration, rewardRate, lastClaimTime, withdrawn }`
- Lock/APY defaults:
  - `7 days -> 5%`
  - `30 days -> 12%`
  - `90 days -> 20%`
- Core functions:
  - `stake(uint256 amount,uint256 lockDuration)`
  - `claimRewards(uint256 stakeIndex)`
  - `unstake(uint256 stakeIndex)`
- Strict unlock rule:
  - Reverts with: `Stake still locked`
- Reward formula:
  - `reward = (amount * rate * elapsed) / (365 days * 100)`
  - Uses per-second accrual and normalizes 6-decimal wXRP amount to EXA 18-decimal accounting.
- Events:
  - `Staked(user, amount, duration)`
  - `RewardClaimed(user, amount)`
  - `Unstaked(user, amount)`

### 3) Bridge runtime service (Node)
- File: `src/services/xrpBridgeService.js`
- Provides:
  - `monitorDeposits()`
  - `validateTransaction(txHash)`
  - `lockXRP(amount,userId)`
  - `mintWrappedXRP(userAddress,amount)`
  - `burnWrappedXRP(userAddress,amount)`
  - `releaseXRP(userId,amount)`
  - `processUnstakeRelease({ userId, userAddress, amount })`
- Safety controls:
  - duplicate tx protection (`processedDeposits` set)
  - 1:1 invariant enforcement:
    - net locked XRP (drops) == net circulating wXRP (units)
  - audit trail via in-memory audit log + structured logger events

### 4) TypeScript alignment layer
- File: `src/services/xrpBridgeService.ts`
- Purpose:
  - strongly typed bridge reference implementation
  - invariant-focused treasury accounting model

### 5) API integration points (Node controller/routes)
- Files:
  - `src/controllers/blockchainController.js`
  - `src/routes/index.js`
- Endpoints added:
  - `POST /xrp-bridge/deposits/monitor`
  - `POST /xrp-bridge/lock`
  - `POST /xrp-bridge/mint`
  - `POST /xrp-bridge/burn`
  - `POST /xrp-bridge/release`
  - `POST /xrp-bridge/unstake-release`
  - `GET /xrp-bridge/status`

### 6) Runtime startup wiring
- File: `src/index.js`
- Added auto-monitor start:
  - `xrpBridgeService.startMonitoring()`

### 7) Deployment assets
- Script: `scripts/deploy-xrp-hybrid.js`
- Existing deployment script updated: `scripts/deploy.js`
- Package scripts added in `package.json`:
  - `contracts:deploy:xrp-hybrid:base-sepolia`
  - `contracts:deploy:xrp-hybrid:base`

### 8) Configuration
- File: `src/config.js`
- Added:
  - token config for `WXRP`
  - contract config: `wrappedXrp`, `xrpStaking`
  - bridge config block: `xrpBridge`
- File: `.env.example`
- Added env vars:
  - `WRAPPED_XRP_ADDRESS`
  - `XRP_STAKING_CONTRACT_ADDRESS`
  - `BRIDGE_OPERATOR_ADDRESS`
  - `XRPL_TREASURY_ADDRESS`
  - `XRP_BRIDGE_MONITOR_INTERVAL_MS`

## Unstake Hybrid Flow

1. User calls `unstake()` on `XRPStakingContract` after lock expiry.
2. User receives `wXRP` back from staking contract.
3. Backend executes `processUnstakeRelease`:
   - burns `wXRP`
   - releases locked XRP accounting for same amount
4. Treasury invariant is re-validated after each stage.

## Tests Added

- File: `test/XRPStakingHybrid.test.js`
- Coverage includes:
  - lock enforcement (`Stake still locked`)
  - reward accrual + claim behavior
  - bridge mint/burn permissioned path

## Notes

- Contract compilation in this environment is currently blocked by local toolchain/disk constraints:
  - installed Hardhat path expects ESM-only workflow
  - fallback compile attempt hit `ENOSPC` during temporary package install
- Source implementation is completed and structured for immediate compile/deploy in a healthy Node/Hardhat environment.

