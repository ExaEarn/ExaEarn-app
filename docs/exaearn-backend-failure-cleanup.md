# ExaEarn Backend Failure Cleanup

## Final Phase 2C Full Suite Result

```text
215 passed
1 skipped
8 failed
```

## Fixed During Phase 2C

- `FlightGameTest` failures caused by PostgreSQL advisory locks running against SQLite tests.
- `UserPreferenceTest` language default mismatch.
- `AuditLogTest` logout failure caused by deleting Sanctum transient tokens and calling logout on a request guard.
- `AuthFlowTest` registration initialization expectation updated for the current five-account migration bridge.

## Remaining Failures

All remaining failures are in `Tests\Feature\ExaEarnStakingRemovalTest`.

Classification: `BLOCKS WHOLE PLATFORM RELEASE`

Spot cutover classification: `DOES NOT BLOCK PHASE 2C SPOT CUTOVER`

## Failure List

- `xrp is not listed as native pos staking asset`
- `legacy staking routes are removed`
- `position creation fails closed without ready provider`
- `admin mainnet activation requires second approval`
- `reward claims fail closed without verified allocations`
- `user staking history endpoints are table backed`
- `admin observation endpoints return staking tables`
- `unstaking reserves active principal and release requires withdrawable status`

## Root Category

The staking tests expect `/api/v1/staking/*`, `/api/admin/v1/staking/*`, and legacy `/api/staking/stake` routes to exist with native staking fail-closed behavior. The current route surface returns `404` or has missing legacy-removal controller methods.

## Recommendation

Run a separate scoped staking route/API restoration task. Do not mix that product repair with Spot matching cutover, because it touches Native PoS staking business rules, admin approvals, rewards, unstaking and provider health.

