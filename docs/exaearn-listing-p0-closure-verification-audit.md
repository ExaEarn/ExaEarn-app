# ExaEarn Listing P0 Closure Verification Audit

Date: 2026-09-01  
Posture: independent, repository-based, adversarial, fail closed  
Scope: the seven known Listing P0 controls and realistic launch/activation bypasses only

# Executive Summary

The seven known P0 remediations are closed in the inspected implementation. Certification evidence is executable and fail-closed; deposit and withdrawal activation require current readiness plus fingerprint-bound dual approval; required documents are quarantined and exact-version scan-bound; historical launch rows are quarantined; dispatch loss is lease-recoverable; worker execution is token-fenced; and cancellation cannot win after the atomic execution claim.

The P0 software gate nevertheless **fails** because the direct bypass review found one new P0 root cause: market activation is still available outside the Listing launch control plane. An ordinary authenticated user can call `POST /trade/markets`, which directly upserts a market and defaults it to `active`. Trading service methods also call `TradeService::getMarket()`, which `firstOrCreate`s an unknown pair as `active`. Legacy admin pair endpoints can directly upsert an arbitrary active market without Listing certification, approval, scheduling, execution-time revalidation, or postcondition governance.

This permits an unauthorized production trading-market launch even though the canonical Listing workflow itself is hardened. It is one systemic new P0 with multiple reachable surfaces.

The full backend suite matches the claimed baseline. Listing typecheck and production build pass. Listing lint is not configured. Separately, `php artisan route:list` cannot boot because `DeveloperRealtimeAuthorityController::authorize(Request)` is signature-incompatible with the inherited controller method. That defect is outside this focused Listing P0 classification, but demonstrates that the passing suite does not cover a complete Laravel route boot.

# Audit Method

- Inspected launch models, migrations, lifecycle orchestration, dispatcher, queue job, scheduler registration, readiness services, document security, certification, routes, controllers, market services, and focused tests.
- Traced fresh and historical launch states, dispatch claims, worker claims, cancellation, stale leases, readiness revalidation, side-effect transactions, and canonical postconditions.
- Searched for alternate writes to market status, trading status, deposit/withdrawal flags, and development/test bypasses.
- Ran focused Listing/Convert tests, the complete backend suite, Listing TypeScript typecheck, and Listing production build.
- Did not modify application logic, dependencies, tests, or existing worktree changes.

# P0-1 Synthetic Certification

**Verdict: CLOSED**

`ListingIntegrationCertificationService` executes ledger and matching behavior in an isolated transaction and rolls it back. Required evidence covers canonical ledger posting/idempotency/invariants and trading placement, partial fill, cancellation, settlement, and market-data observations. PASS results are constructed only after these operations return evidence. Disabled or non-isolated execution returns BLOCKED.

Developer API, WebSocket, and Redis checks are explicitly nonrequired for this isolated certification and report `NOT_CONFIGURED`/`NOT_CONNECTED`; they are not misrepresented as PASS. Deposit and withdrawal are either required according to launch scope or explicitly `NOT_APPLICABLE` for a later separately gated activation. Required non-PASS results prevent certification.

No hardcoded required PASS, row-existence inference, catch-and-pass, or market-existence-only certification path was found.

# P0-2 Deposit Activation

**Verdict: CLOSED**

Canonical Listing deposit activation checks custody, RPC, asset/network mapping, address derivation, memo/tag policy, monitoring, confirmations, ledger posting and duplicate-credit controls, reconciliation, risk/security controls, and runbook readiness. Approval is tied to a configuration fingerprint and requires a distinct requester and approver.

Activation and emergency resume both recompute readiness and require a current fingerprint-matching approved request. Approval is consumed on activation. Listing approval, contract validation, trading activation, or launch success alone do not enable deposits through the canonical workflow.

# P0-3 Withdrawal Activation

**Verdict: CLOSED**

Canonical Listing withdrawal activation checks custody, production signer allowlisting, hot-wallet/liquidity readiness, RPC, asset/network mapping, destination and memo/tag policy, fees and limits, account/security/withdrawal risk controls, ledger reservation, signing/broadcast, confirmation tracking, reconciliation, and runbook readiness.

The development signer cannot satisfy production readiness. Activation and emergency resume require fresh, fingerprint-bound dual approval and current PASS readiness. Deposit enablement, Listing approval, trading activation, asset configuration, or launch success alone do not enable withdrawals through the canonical workflow.

# P0-4 Document Safety

**Verdict: CLOSED**

New Listing documents start `QUARANTINED`; the scanner job carries the expected checksum, and scan attempts record document version and checksum. A CLEAN document is usable only when a matching CLEAN scan-attempt record exists and the stored content still hashes to the approved checksum. `NOT_CONNECTED`, timeout, scan error, suspicious, infected, and legacy-unverified states fail closed.

Current required evidence is selected by latest version per document type. A replacement therefore supersedes an older CLEAN version and must be independently scanned. Normal review download requires clean integrity. Listing approval, KYB approval, certification/launch readiness, and protected launch gates invoke current-document safety assertions.

# P0-5 Historical Replay

**Verdict: CLOSED**

The original hardening migration maps recognized historical terminal outcomes to non-dispatchable terminal states and defaults uncertain history to `LEGACY_UNVERIFIED`. The corrective migration preserves recognized success, failure, and cancellation; quarantines incomplete or ambiguous nonterminal rows; and preserves a legitimate future schedule only when durable fields are complete.

The correction is idempotent in the focused test. Dispatcher selection is restricted to explicit dispatchable states and cannot select SUCCEEDED, FAILED, BLOCKED, CANCELLED, PARTIALLY_FAILED, or LEGACY_UNVERIFIED.

# P0-6 Lost Queue Message

**Verdict: CLOSED**

The database is authoritative. A due row is locked, assigned a new claim token, moved to `DISPATCH_PENDING`, and leased before publication. A synchronous publish exception moves it to `RETRY_PENDING`; an ambiguous timeout or lost accepted message leaves a lease that can expire and be reclaimed. Redis or worker downtime therefore does not erase the operation.

Redispatch uses a new fencing token. The worker can atomically claim execution only for the current token and eligible dispatched state. Delivery of both the original and redispatched messages produces one effective execution claim. Dispatch attempt accounting is separate from execution attempts.

# P0-7 Cancellation Race

**Verdict: CLOSED**

Cancellation is allowed only from pre-execution states (`SCHEDULED`, `RETRY_PENDING`, `DISPATCH_PENDING`, `DISPATCHED`) under row lock. Worker execution claims the same row and atomically transitions it to `EXECUTING`. If cancellation wins, the token/state checks make old and duplicate jobs no-ops. If execution wins, cancellation fails explicitly with `EXECUTION_IN_PROGRESS`.

Normal cancellation cannot produce a valid `CANCELLED + SUCCEEDED` outcome. Cancellation does not hold a database transaction over long external operations.

# Launch State Machine

Observed states:

| State | Legal automatic transitions |
|---|---|
| `SCHEDULED` | `DISPATCH_PENDING`, `BLOCKED`, `CANCELLED` |
| `RETRY_PENDING` | `DISPATCH_PENDING`, `BLOCKED`, `CANCELLED` |
| `DISPATCH_PENDING` | `DISPATCHED`, `RETRY_PENDING`, `CANCELLED` |
| `DISPATCHED` | `DISPATCH_PENDING` after lease expiry, `EXECUTING`, `CANCELLED` |
| `EXECUTING` | `SUCCEEDED`, `BLOCKED`, `FAILED`, `PARTIALLY_FAILED` |
| `SUCCEEDED` | terminal |
| `FAILED` | terminal |
| `PARTIALLY_FAILED` | terminal/manual reconciliation |
| `BLOCKED` | terminal |
| `CANCELLED` | terminal |
| `LEGACY_UNVERIFIED` | terminal/manual review |

The implementation uses guarded query conditions and row locks rather than one centralized transition object. Within the inspected launch paths, terminal states are excluded from automatic execution.

# Migration Safety

Fresh migration and legacy correction logic were reviewed. Focused tests cover historical success, failure, cancellation, ambiguous rows, a valid future schedule, and repeated corrective execution. Historical and ambiguous states remain non-dispatchable.

Operational deployment still requires workers/schedulers to be paused while the corrective migration runs; that is a rollout control, not evidence of automatic historical replay in the resulting schema.

# Queue / Dispatcher Concurrency

Dispatcher selection runs inside a database transaction with `lockForUpdate`. Two dispatchers cannot both retain a valid claim for the same row. Even if queue publication duplicates due to ambiguous broker acknowledgement, token/state fencing prevents duplicate effective launch.

Two workers receiving the same current message compete for the atomic `DISPATCHED/DISPATCH_PENDING -> EXECUTING` claim. Only one proceeds. Tests simulate duplicate delivery sequentially; they do not run true PostgreSQL parallel workers, which remains a test-strength limitation but does not reveal a P0 logic break.

# Cancellation Concurrency

Cancellation and execution claim lock and condition the same database row. The non-cancellable boundary is the committed `EXECUTING` transition, not the first external side effect. An old job after cancellation cannot satisfy the state/token claim.

The focused test deterministically exercises both winner outcomes but is not a true concurrent PostgreSQL race test. The database predicates and row lock provide the safety mechanism.

# Execution-Time Revalidation

Execution reloads current application state, recomputes the certification fingerprint, checks protected launch gates, and re-evaluates scoped deposit/withdrawal readiness before activation. Changed KYB, current required documents, certification, fingerprint, market readiness, custody readiness, or emergency state blocks the relevant operation.

Latest-version document selection prevents CLEAN v1 from authorizing a launch after quarantined v2 replaces it. Configuration A cannot authorize changed configuration B.

# Capability Isolation

The canonical launch scope controls which operations are scheduled. Trading-only launch does not enable deposit or withdrawal flags in Listing or canonical blockchain-asset state. Deposit and withdrawal require their own readiness decisions and consumed dual approvals.

# Postcondition Verification

Before `SUCCEEDED`, the lifecycle verifies actual canonical market and capability state. A returned step result without the intended canonical state transitions the operation to `PARTIALLY_FAILED` with `POSTCONDITION_VERIFICATION_FAILED` rather than success.

If a worker dies after an irreversible side effect but before operation completion, an expired `EXECUTING` lease becomes `PARTIALLY_FAILED` for reconciliation and is never automatically redispatched. Side-effect event writes are transactionally recorded and canonical operations are idempotently keyed where implemented.

# Direct Bypass Review

**NEW P0: ungoverned market activation outside Listing launch controls.**

1. `routes/api.php` registers `POST trade/markets` inside the ordinary `dev.auth` authenticated-user group.
2. `TradeController::createMarket()` accepts user-controlled market fields and directly `updateOrCreate`s the market with status defaulting to `active`.
3. `TradeService::getMarket()` uses `firstOrCreate` with `status = active` when normal trade methods resolve an unknown pair.
4. Legacy `POST admin/trading` and `POST admin/pairs` call `AdminPlatformController::createPair()`, which directly upserts a caller-supplied status without Listing certification or launch readiness.

These paths bypass Listing approval, executable certification, launch scheduling, execution-time revalidation, capability scope, audit evidence expected by the Listing workflow, and canonical launch postconditions. Rate limiting, ordinary authentication, or generic admin authorization does not make direct launch safe.

The finding realistically permits unauthorized production market activation and therefore meets the prompt's NEW P0 threshold. It is counted as one root-cause finding, not one per endpoint.

# Test Quality

Strengths:

- Certification test proves disabled/non-isolated execution fails closed.
- Migration test uses representative historical states and reruns correction.
- Dispatch tests cover publish exception, ambiguous acknowledgement, lost delivery recovery, new fencing token, duplicate message, stale execution, cancellation, changed fingerprint, and old failed-job fencing.
- Portal tests assert deposit/withdrawal isolation and quarantined/infected document denial.
- Convert tests cover source provenance, stale references, unknown source, and BTC/USDT behavior.

Limitations:

- Dispatcher/worker/cancellation tests are deterministic simulations on SQLite, not true parallel PostgreSQL processes.
- Redis restart and scheduler downtime are modeled through leases rather than tested against a real Redis/worker topology.
- No test asserts that ordinary users cannot create markets or that trading an unknown pair cannot auto-create an active market; this is why the new P0 passed the suite.
- No route-boot smoke test catches the current inherited-method signature fatal in `DeveloperRealtimeAuthorityController`.

# Regression Results

Focused Listing/Convert run:

- 31 passed / 0 failed / 0 skipped / 255 assertions

Full backend:

- 625 passed / 0 failed / 1 skipped / 5,086 assertions
- PHPUnit emitted existing doc-comment metadata deprecation warnings.
- `php artisan route:list --path=trade/markets -v` failed before listing routes because `DeveloperRealtimeAuthorityController::authorize(Request)` conflicts with `Controller::authorize($ability, $arguments = [])`. This is a real boot-time regression outside the focused Listing P0 category.

Frontend:

- Listing typecheck: PASS
- Listing production build: PASS
- Listing lint: NOT_CONFIGURED (no lint script exists)
- The build initially hit sandboxed Windows `spawn EPERM`; the same build passed outside the process sandbox with 1,723 modules transformed.

MarketData/Convert regression: **RESOLVED**. External and internal provenance remain distinct, market existence does not alone establish provenance, unknown/stale inputs fail closed, and focused BTC/USDT Convert behavior passes.

# New P0 Findings

**Count: 1**

`LISTING-P0-NEW-01`: Multiple normal runtime surfaces can directly create or upsert an active trading market outside the Listing certification and launch state machine. Severity is P0 because it permits unauthorized production launch.

# Final P0 Gate

P0-1 SYNTHETIC CERTIFICATION:  
CLOSED

P0-2 DEPOSIT ACTIVATION:  
CLOSED

P0-3 WITHDRAWAL ACTIVATION:  
CLOSED

P0-4 DOCUMENT SAFETY:  
CLOSED

P0-5 HISTORICAL REPLAY:  
CLOSED

P0-6 LOST QUEUE MESSAGE:  
CLOSED

P0-7 CANCELLATION RACE:  
CLOSED

CAN CERTIFICATION FALSELY PASS WITHOUT REQUIRED EXECUTION?  
NO

CAN LISTING APPROVAL ALONE ENABLE DEPOSITS?  
NO

CAN LISTING APPROVAL ALONE ENABLE WITHDRAWALS?  
NO

CAN AN UNSCANNED REQUIRED DOCUMENT SUPPORT KYB/LISTING APPROVAL OR LAUNCH?  
NO

CAN HISTORICAL TERMINAL LAUNCH HISTORY BECOME EXECUTABLE?  
NO

CAN AN AMBIGUOUS LEGACY EVENT AUTO-BECOME EXECUTABLE?  
NO

CAN A LOST QUEUE MESSAGE PERMANENTLY STRAND A LAUNCH?  
NO

CAN AN EXPIRED DISPATCH LEASE BE RECOVERED?  
YES

CAN REDISPATCH PLUS ORIGINAL DELIVERY PRODUCE TWO EFFECTIVE LAUNCHES?  
NO

CAN TWO WORKERS PRODUCE TWO EFFECTIVE LAUNCHES?  
NO

CAN TWO DISPATCHERS PRODUCE TWO EFFECTIVE LAUNCHES?  
NO

CAN SUCCESSFUL PRE-EXECUTION CANCELLATION STILL PRODUCE LAUNCH SIDE EFFECTS?  
NO

CAN AN OLD JOB EXECUTE AFTER SUCCESSFUL CANCELLATION?  
NO

CAN CANCELLED AND SUCCEEDED BOTH BE VALID TERMINAL RESULTS?  
NO

CAN STALE EXECUTING AUTOMATICALLY REPLAY AN IRREVERSIBLE LAUNCH?  
NO

DOES EXECUTION REVALIDATE CURRENT READINESS?  
YES

CAN STALE CONFIGURATION/FINGERPRINT LAUNCH?  
NO

CAN TRADING-ONLY LAUNCH ENABLE DEPOSITS?  
NO

CAN TRADING-ONLY LAUNCH ENABLE WITHDRAWALS?  
NO

DOES SUCCEEDED REQUIRE CANONICAL POSTCONDITION VERIFICATION?  
YES

IS DATABASE STATE AUTHORITATIVE OVER QUEUE STATE?  
YES

IS THERE ANY DIRECT NORMAL-PRODUCTION ADMIN BYPASS OF P0 GATES?  
YES

HAS THE MARKETDATA/CONVERT FIX REGRESSED?  
NO

NEW P0 FINDINGS:  
1

BACKEND:  
625 passed / 0 failed / 1 skipped / 5086 assertions

LISTING TYPECHECK:  
PASS

LISTING BUILD:  
PASS

LISTING LINT:  
NOT_CONFIGURED

EXAEARN LISTING P0 CLOSURE AUDIT

P0-1:
CLOSED

P0-2:
CLOSED

P0-3:
CLOSED

P0-4:
CLOSED

P0-5:
CLOSED

P0-6:
CLOSED

P0-7:
CLOSED

NEW P0:
1

ALL KNOWN LISTING P0s CLOSED:
YES

P0 SOFTWARE GATE:
FAIL

RECOMMENDATION:
STOP_AND_FIX_P0
