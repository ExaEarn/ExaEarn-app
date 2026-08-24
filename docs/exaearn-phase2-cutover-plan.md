# ExaEarn Phase 2 Spot Engine Cutover Plan

Date: 2026-08-17

## Stage 1 - Unit And Integration Tests

Keep `TRADING_ENGINE_MODE=legacy`.

Run:

```bash
php artisan test tests/Feature/Phase1FinancialCoreTest.php tests/Feature/SpotFinancialMigrationTest.php tests/Feature/Phase2SpotEngineTest.php
```

## Stage 2 - Development New Engine

Enable:

```text
TRADING_ENGINE_MODE=new
```

Use non-production markets and test users only.

## Stage 3 - Shadow Comparison

Feed sanitized order commands to the new engine in a non-settling shadow mode after adding a dedicated shadow runner.

Do not dual-settle.

Compare:

- execution price
- maker/taker identity
- partial fills
- final book state
- snapshots/checksums

## Stage 4 - Sandbox Authority

Make the Phase 2 engine authoritative for sandbox markets.

Require:

- settlement outbox retry worker
- replay runner
- health checks
- market halt controls

## Stage 5 - Limited Internal Market

Enable one low-risk internal market with limited size.

Monitor:

- sequence lag
- settlement failures
- ledger reconciliation
- order-book checksum drift
- pending settlement outbox

## Stage 6 - Production Market-by-Market Cutover

Cut over one market at a time.

Do not switch all markets in one deployment.

## Stage 7 - Legacy Matcher Disabled

After stable production operation:

- mark DB matcher as non-authoritative
- block new legacy order routing
- keep rollback branch for a controlled period

## Stage 8 - Legacy Cleanup

Remove legacy matching logic in a later cleanup phase only after:

- no open legacy orders remain
- historical reads are preserved
- all clients are compatible with sequenced engine events

