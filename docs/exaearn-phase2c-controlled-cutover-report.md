# ExaEarn Phase 2C Controlled Spot Cutover Report

## A. Executive Summary

Phase 2C implemented controlled per-market Spot engine authority and a repeatable cutover workflow. No production market was migrated. Local/dev validation shows the new Spot engine is ready for normal authority under controlled market-by-market cutover.

## B. Existing Infrastructure Reused

- Phase 1 financial core
- Phase 2 OMS, sequencer, matching engine and journal
- Phase 2B lease, replay, outbox, realtime sequence and shadow comparison

## C. Per-Market Authority

Implemented through `markets.engine_mode` and `SpotEngineModeResolver`.

## D. Cutover State Machine

Implemented states:

- `LEGACY`
- `SHADOW`
- `CUTOVER_PENDING`
- `HALTED_FOR_CUTOVER`
- `INITIALIZING_NEW_ENGINE`
- `VALIDATING`
- `NEW`
- `ROLLBACK_PENDING`
- `HALTED_FOR_ROLLBACK`
- `ROLLBACK_ONLY`

Invalid transitions are rejected.

## E. Readiness Precheck

`SpotCutoverReadinessService` checks market status, instrument configuration, migrations, BCMath, ledger reconciliation, settlement outbox, shadow discrepancies, replay, snapshots, realtime sequence and lease acquisition.

## F. Market Ownership/Fencing

Inherited from Phase 2B and exercised during cutover initialization/promotion.

## G. Legacy Open Order Handling

Initial policy is cancel-and-release. Legacy open orders are not imported into the new engine.

## H. Reservation Reconciliation

Partial-fill cutover cancellation test verifies only remaining unused reservation is released.

## I. Canary Execution

`spot:cutover-canary` and `SpotCutoverService::runCanary` execute a controlled maker/taker trade through the NEW engine.

## J. Settlement Validation

Canary settlement produced one canonical ledger transaction and settled outbox status.

## K. Ledger Validation

Focused financial tests passed. Cutover precheck reports zero blocking ledger reconciliation findings on the local test market.

## L. Realtime Validation

Sequenced realtime events are recorded for trades and book updates. Gap detection was already covered in Phase 2B.

## M. Private Stream Security

Order cancellation ownership is enforced. Full websocket private-channel authorization remains a deployment/security hardening item.

## N. Restart/Replay Validation

`spot:replay CUT2C/USDT` rebuilt the book through sequence 20 with no gaps.

## O. Load Results

Local PostgreSQL command:

```text
php artisan spot:load-harness --orders=20 --market=CUT2C/USDT
```

Result:

- orders accepted: `20/20`
- trades created: `10`
- duration: `2170.801ms`
- p50: `118.098ms`
- p95: `230.022ms`
- p99: `230.022ms`
- errors: `0`

## P. Multi-Market Results

Focused tests verify `BTC/USDT` and `ETH/USDT` use independent authority, sequence and lease state.

## Q. Rollback Results

Focused tests verify `NEW -> ROLLBACK_PENDING -> HALTED_FOR_ROLLBACK -> ROLLBACK_ONLY` without duplicate settlement.

## R. Legacy Matcher Status

Legacy matcher remains `ACTIVE` for markets still in legacy mode and `ROLLBACK_ONLY` where explicitly transitioned. It is marked as deprecated for normal Spot authority by policy documentation.

## S. Full Backend Test Results

```text
215 passed
1 skipped
8 failed
```

## T. Remaining Failures

All remaining full-suite failures are in `ExaEarnStakingRemovalTest` and return 404 for staking routes. These block whole-platform release but do not block Spot cutover.

## U. Security Findings

No new critical Spot financial regressions were found. Logout/session-auth handling was fixed during cleanup.

## V. Markets Actually Tested

- `BTC/USDT`
- `ETH/USDT`
- `CUT2C/USDT`

## W. Markets Actually Migrated

None in production.

## X. Production vs Local/Staging Validation

This was local/development validation against the local Laravel/PostgreSQL environment. It is not a claim that a real production market was switched.

## Y. Remaining Risks

- Staking API failures block whole-platform release.
- Admin cutover UI is not yet implemented.
- Production websocket fanout must consume sequenced realtime events.
- Staged load testing should be run before high-volume market migration.
- Actual production cutover requires operator approval and monitoring.

## Z. Final Authority Decision

NEW SPOT ENGINE SAFE FOR NORMAL AUTHORITATIVE OPERATION: YES, for controlled market-by-market cutover.

PRODUCTION CUTOVER PROCEDURE READY: YES.

ACTUAL PRODUCTION MARKET CUTOVER PERFORMED: NO.

LEGACY MATCHER STATUS: ACTIVE.

WHOLE EXAEARN PLATFORM PRODUCTION RELEASE READY: NO.

