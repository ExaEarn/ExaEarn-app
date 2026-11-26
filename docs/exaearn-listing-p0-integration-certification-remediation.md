# ExaEarn Listing P0 Integration Certification Remediation

Date: 2026-09-01  
Scope: synthetic Listing launch certification only

## Previous unsafe behavior

`ListingLifecycleService::testResults()` could return `PASS` without executing the behavior named by the test. Canonical ledger was hardcoded to pass; matching, partial fill, cancellation, market data, OMS, and matching-engine readiness were inferred from configured records; Developer API, WebSocket, and cache were hardcoded to pass; deposits and withdrawals were marked not applicable without capability context.

## New architecture

`ListingIntegrationCertificationService` executes a rollback-isolated certification against canonical `LedgerService` and `TradeService`. It does not implement a Listing ledger, wallet, matcher, settlement engine, or market-data engine.

Execution is permitted only when both conditions hold:

1. Laravel runs in the `testing` environment.
2. `LISTING_CERTIFICATION_EXECUTION_ENABLED=true` is explicitly configured.

Every other environment fails closed with required results marked `BLOCKED`. This prevents an operator from creating test users, orders, balances, or trades in production.

The harness opens an outer database transaction, temporarily enables the target pre-launch market, creates isolated certification users, funds them through canonical double entry, executes the scenarios, captures evidence, and rolls the entire transaction back. Only the normalized result evidence is then persisted by the existing Listing test-run workflow.

## Executed canonical verification

| Test | Required | Execution and evidence |
|---|---:|---|
| Canonical ledger | Yes | Canonical asset ID; balanced posting; debit/credit sum; imbalance rejection; idempotent reference |
| Matching engine | Yes | Compatible canonical sell/buy orders; observed fill and trade UUID |
| Partial fill | Yes | Sell 4 against buy 10; observed filled 4, remaining 6, partial state |
| Cancellation | Yes | Canonical cancel; reservation release; idempotent repeat; no later fill |
| Settlement | Yes | Canonical settlement ledger reference; completed transaction; fees; per-asset balance |
| Market data | Yes | Executed trade appears in canonical recent trades and order-book read model responds |
| Developer API | No | `NOT_CONFIGURED`; external network conformance harness required |
| WebSocket | No | `NOT_CONFIGURED`; external publication/reception harness required |
| Cache/Redis | No | `NOT_CONNECTED`; not certified by the rollback database run |
| Deposit | Capability-aware | `NOT_APPLICABLE` only when excluded from initial launch; otherwise `BLOCKED` |
| Withdrawal | Capability-aware | `NOT_APPLICABLE` only when excluded from initial launch; otherwise `BLOCKED` |

Persisted liquidity requirements remain a required precondition. A non-ready requirement adds a required `BLOCKED` result. This does not claim that live liquidity has been exercised; that remains a separate production gate.

## Result contract

Each result records:

- `test_name`, `status`, and `required`
- start/completion timestamps and environment
- target asset, network, and market
- evidence or an empty evidence object
- error code and safe error message
- correlation ID
- certification version
- configuration fingerprint

`PASS` cannot be constructed without non-empty evidence. Exceptions become required `ERROR` results and fail the run.

## Configuration invalidation

The SHA-256 fingerprint binds evidence to the Listing asset, canonical asset identity, contract, network, decimals, confirmation policy, Listing market configuration, and canonical Market precision/fee/engine configuration. Final approval recalculates this fingerprint. A historical run cannot pass the gate when the current configuration differs.

## Audit evidence

The existing Listing audit log now records certification started, completed/failed, run ID, correlation ID, and fingerprint. Test users, balances, orders, fills, reservations, and ledger entries are rollback-isolated and do not persist.

## Failure behavior

- Disabled/non-testing execution: required tests `BLOCKED`.
- Missing canonical market: required tests `BLOCKED`.
- Canonical service exception or invariant mismatch: required tests `ERROR`.
- Required liquidity precondition not ready: required test `BLOCKED`.
- Required result not `PASS`: overall run `FAIL`.
- Empty evidence cannot produce `PASS`.

## Remaining limitations

This remediation does not redesign deposit activation, withdrawal activation, malware scanning, or the launch dispatcher. Developer API, WebSocket, and Redis network verification remain `NOT_CONFIGURED`/`NOT_CONNECTED` until dedicated safe harnesses exist. Deposit and withdrawal certification cannot authorize later activation; those capabilities require their own fresh readiness gates.

## Validation

- Focused Listing/KYB/certification regression: PASS, 12 tests and 196 assertions.
- PHP syntax and diff integrity: PASS.
- Listing TypeScript typecheck: PASS.
- Listing production build: PASS (Vite, 1,723 modules transformed).
- Full backend suite: PASS, 606 passed, 0 failed, 1 skipped, 4,990 assertions.
