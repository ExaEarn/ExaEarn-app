# ExaEarn Listing Portal Production-Readiness Re-Audit

Date: 2026-09-01  
Posture: independent, repository-based, fail closed  
Scope: applicant portal, identity, organization/KYB, evidence, review, certification, launch, custody, liquidity, operations, infrastructure, and delisting

## Executive Summary

**Decision: NO_GO.**

The four original P0 defects are closed at the software-control layer. Certification no longer obtains PASS from synthetic records; deposit and withdrawal enablement require independent, fingerprint-bound readiness plus maker-checker approval; and required evidence must have a CLEAN scan for its exact version. Market-data provenance regression tests also pass.

The new durable launch implementation is not production-safe. Three independently verified P0 defects remain:

1. The launch migration initializes every historical event as `SCHEDULED` without backfilling terminal legacy states. A previously executed event can therefore become dispatchable again.
2. The dispatcher writes `DISPATCHED` after queue handoff but never queries that state. A queue message lost after handoff is permanently orphaned even after its lease expires.
3. Cancellation accepts an `EXECUTING` event. The worker performs capability side effects outside the cancellation transaction and can continue after the event was marked cancelled.

The backend regression is clean at 616 passed, 0 failed, 1 skipped, and 5,050 assertions. Listing typecheck and production build pass. Listing lint is **NOT_CONFIGURED**; the package has no lint script, so a successful no-op filter invocation is not counted as PASS.

Production remains blocked independently by disconnected KYB/screening/malware services, custody/signing/RPC and market-maker operations, unverified scheduler/workers, missing post-listing monitoring and delisting workflows, coarse internal RBAC, incomplete Admin operations UX, and absent production SSO/backup/restore evidence.

## Previous vs Current Readiness

| Area | Previous audit | Current evidence | Current verdict |
|---|---|---|---|
| Original P0 | 4 open | All four fail-closed controls materially implemented | 4 closed |
| Original P1 | 12 open | 3 partial, 9 open | Not launch ready |
| Launch scheduling | No durable dispatcher | DB events, scheduler command, queue job, leases, retries and postconditions exist | Unsafe due to 3 new P0 defects |
| Market provenance | Regression present | Source type and freshness checks; focused tests pass | Resolved |
| Backend regression | Earlier baseline claimed | 616/0/1, 5,050 assertions verified | Pass |
| Listing frontend | Typecheck/build claimed | Typecheck/build verified; lint absent | Partial gate coverage |
| External dependencies | 11 not operational | 11 remain not operational or unverified | No improvement proven |

## Architecture Reviewed

The canonical path is:

`React Listing portal -> credentialed Sanctum API -> canonical User -> ListingOrganization/members -> Listing KYB -> ListingApplication -> private versioned evidence -> internal Listing review -> asset/network/market configuration -> executable certification -> maker-checker schedule -> listing_launch_events -> scheduler dispatcher -> listing-launch queue -> capability-specific execution -> canonical market/custody/ledger services`.

No duplicate Listing identity, wallet, ledger, matching engine, custody engine, or market-data authority was found. Applicant requests use cookie credentials and do not store bearer tokens in browser storage. Organization roles are enforced server-side. Internal staff operations use a canonical Admin identity, but the authorization boundary is the broad `listing.manage` permission.

Launch state is database-authoritative. Redis is a queue transport, not the launch system of record. That architecture is appropriate, but the current state recovery queries and transitions are incomplete.

## Original P0 Revalidation

### P0 #1 - Integration Certification

**Verdict: CLOSED at the software layer.**

`ListingIntegrationCertificationService` executes rollback-isolated canonical ledger and trading behavior, including double entry, idempotency, imbalance rejection, matching, full and partial fills, cancellation, reservation release, settlement, balances, and market-data read-model checks. Required checks need non-empty evidence. API, WebSocket, Redis, deposits, and withdrawals are represented as non-required when outside launch scope and cannot masquerade as required PASS.

Certification execution is allowed only in the testing environment with `LISTING_CERTIFICATION_EXECUTION_ENABLED`; otherwise it reports BLOCKED. This prevents false production PASS, but an approved production-equivalent certification environment still has to be supplied operationally. Results and launch events bind to a configuration fingerprint.

### P0 #2 - Deposit Activation

**Verdict: CLOSED at the software layer.**

Deposit activation evaluates production custody, RPC, asset/network mapping, address generation, memo policy, deposit monitoring, confirmation policy, ledger posting, reconciliation, risk/security controls, runbook status, and a fingerprint-bound dual approval. `openDeposits()` reevaluates readiness and consumes the approval atomically with enablement. Listing approval, trading status, contract validation, or scheduling alone is insufficient.

The control currently fails closed because production custody/RPC and operational evidence are not connected. Pause/resume is not a complete governed Listing operations workflow and remains P1.

### P0 #3 - Withdrawal Activation

**Verdict: CLOSED at the software layer.**

Withdrawal activation evaluates custody, approved production signer, hot-wallet and liquidity readiness, RPC, mapping, destination/memo controls, fees, limits, security/account/withdrawal risk, ledger reservation, broadcast, confirmation tracking, reconciliation, and runbook evidence. A development signer is rejected for production. Approval is fingerprint-bound, dual controlled, execution-time reevaluated, and consumed with enablement.

The control fails closed with the current development/local or absent production infrastructure. Production signer, custody, RPC, liquidity, and reconciliation operations remain unproven.

### P0 #4 - Malware and Document Safety

**Verdict: CLOSED at the software layer.**

New evidence is quarantined, privately stored, restricted by declared/content type and size, checksummed, versioned, and scanned asynchronously. Scan attempts bind to the exact document checksum/version. Downloads and KYB/Listing approval gates require current CLEAN evidence. Infected, suspicious, failed, pending, not-connected, and legacy-unverified evidence cannot satisfy the required gate. CLEAN version 1 cannot authorize replacement version 2.

The selected production adapter is `NotConnectedMalwareScanner`; therefore real evidence processing remains unavailable rather than falsely ready.

## Market Data Revalidation

**MARKET DATA REGRESSION: RESOLVED.**

Canonical market data distinguishes `EXTERNAL_REFERENCE` from `EXAEARN_INTERNAL`, validates timestamps and freshness, and rejects stale, future, unknown, or unsupported provenance. Convert verifies source type and age. Market-row existence alone does not create internal provenance. MarketData/Convert focused regression passed 17 tests and 114 assertions.

## Durable Launch Revalidation

### Trace

Scheduling seeds capability-specific DB events with due time, fingerprint, correlation ID and scope. The registered command runs every minute with overlap and single-server guards. The dispatcher locks due rows, claims them, publishes `ExecuteListingLaunchEvent`, and records dispatch state. The worker validates token/lease, increments attempts, verifies fingerprint and protected gates, performs one capability action, verifies postconditions, and records a terminal result.

### Duplicate dispatcher safety

**SAFE for simultaneous claims.** Row locking and claim tokens prevent two scheduler instances from normally claiming the same row at once.

### Duplicate job safety

**CONDITIONALLY SAFE for the same current event, but not safe across migrated legacy state.** Claim-token and terminal checks suppress ordinary duplicate queue delivery. Canonical trading launch also rejects a second launch after the application is LIVE, and deposit/withdrawal approvals are consumed. However, the migration can reclassify historical terminal events as scheduled, bypassing the intended terminal-operation guard.

### Crash and outage recovery

| Scenario | Result |
|---|---|
| Worker dies before execution while event is `DISPATCHED` | **P0: not recovered**; dispatcher excludes `DISPATCHED` |
| Worker dies during execution before side effect | Lease recovery exists for `EXECUTING` |
| Worker dies after trading side effect but before terminal operation update | **P1: does not converge**; retry calls `launch()` against an already LIVE application and eventually records failure |
| Scheduler temporarily offline | Due `SCHEDULED` work is recovered within the lateness window; older work is visibly BLOCKED |
| Redis restart after accepted enqueue | **P0: message can be lost and DB event orphaned in `DISPATCHED`** |
| Queue dispatch throws before accepted enqueue | Becomes `RETRY_PENDING` and is rediscovered |
| Database deadlock | Framework retry may redeliver; operation convergence after partial side effects is incomplete |

### Execution-time revalidation

Approval, KYB, current clean documents, certification-derived checks, market/liquidity checks, capability readiness, and configuration fingerprint are rechecked. Stale configuration cannot launch. The protected gate does not explicitly require the expected current integration state or a canonical global maintenance/emergency enablement decision, so launch-versus-pause/emergency races are not comprehensively closed.

### Capability isolation and postconditions

Trading, deposit, and withdrawal events are separate. Trading does not enable deposits or withdrawals. Deposit and withdrawal each require their own readiness approval. `SUCCEEDED` is written only after capability-specific canonical-state postconditions pass. A postcondition mismatch becomes `PARTIALLY_FAILED`, not success.

### Terminal failure operations

Operations can inspect history/metrics and cancel some events. There is no governed retry, resume, repair, or reschedule workflow for `FAILED`, `BLOCKED`, or `PARTIALLY_FAILED`; `PARTIALLY_FAILED` is neither treated as terminal by the worker nor selected for recovery. This is P1 because operators cannot safely converge a failed real launch without developer intervention.

## Original P1 Status Matrix

| # | Original finding | Current implementation/evidence | Status | Current severity | Exact remaining work |
|---:|---|---|---|---|---|
| 1 | Durable dispatcher | Command, scheduler, DB claims, queue job, retries, leases, metrics and tests exist; P0 recovery defects remain | PARTIALLY_CLOSED | P0 | Backfill legacy states; reclaim accepted-but-lost dispatches; fence cancellation/execution; prove crash convergence |
| 2 | Dedicated Listing Operations UI | Admin still renders generic `ModulePage`; only overview/application calls are integrated | OPEN | P1 | Build evidence, KYB, review, readiness, launch, failure and history views over existing APIs |
| 3 | Specialist staff RBAC/separation | All Listing functions require broad `listing.manage` | OPEN | P1 | Add reviewer, compliance, security, custody, risk and launch permissions plus conflict tests |
| 4 | Locks/version checks on decisions/transitions | Launch claims use locks; most KYB/review/approval/schedule/emergency paths lack consistent version checks | PARTIALLY_CLOSED | P1 | Lock/version every critical transition and add PostgreSQL race tests |
| 5 | Semantic nested validation | Submission still treats non-empty top-level JSON as complete in important paths | OPEN | P1 | Add schema-versioned semantic cross-field validation |
| 6 | Draft optimistic concurrency | No ETag/version/updated-at precondition or conflict UX | OPEN | P1 | Add optimistic locking and multi-tab/contributor tests |
| 7 | Registry/screening/malware providers | Fail-closed flags/interfaces exist; production providers are disconnected | PARTIALLY_CLOSED | P1/external | Connect normalized providers and prove signed results, timeout/retry and outage behavior |
| 8 | KYB reverification/expiry/rescreening | States include expiry/reverification; no complete scheduled producer and policy execution | OPEN | P1 | Implement periodic/material-change expiry and rescreen jobs with launch invalidation |
| 9 | Information-request workflow | Admin request creation/message primitives exist; response requirements, attachments, deadlines, closure and notification state are incomplete | OPEN | P1 | Complete explicit two-party workflow and approval blocking |
| 10 | Post-listing monitoring | Generic market/security systems exist; Listing-specific health producers, ownership and fail-safe automation do not | OPEN | P1 | Implement asset/custody/RPC/liquidity/compliance monitoring and alerts |
| 11 | Delisting lifecycle | Status constants/transitions exist, but no full governed withdrawal-window/balance/notification/archive flow | OPEN | P1 | Implement maker-checker delisting state machine and financial/user safeguards |
| 12 | Production SSO/CORS/cookies | Configurable cookies/Sanctum exist; production evidence absent; CORS still merges dev origins and wildcards | OPEN | P1 | Restrict production CORS and verify TLS/cookie/CSRF/trusted-proxy behavior end to end |

Totals: **0 closed, 3 partial, 9 open, 0 superseded**.

## New Findings

### New P0

1. **Historical launch replay on migration.** `operation_status` defaults every existing row to `SCHEDULED` and no data backfill maps legacy `EXECUTED`, `BLOCKED`, or `CANCELLED` status. Acceptance: migration test with terminal legacy rows proves none are claimable afterward.
2. **Lost accepted queue message.** `DISPATCHED` is omitted from both overdue blocking and due-event discovery. Acceptance: remove a queued message after handoff, expire the lease, and prove the same DB event is safely redispatched once.
3. **Cancellation versus execution race.** Cancellation allows `EXECUTING`, while capability side effects continue outside that lock. Acceptance: race cancellation against each event type and prove either cancellation wins before side effects or execution wins with one truthful terminal state.

### New P1

1. **Post-side-effect crash does not converge.** Detect and verify already-achieved postconditions before re-executing; use durable per-step state/outbox semantics.
2. **No governed terminal recovery.** Add authorized, audited revalidate/retry/reschedule operations with maker-checker rules and immutable attempt history.
3. **Pause/emergency revalidation incomplete.** Bind every launch to current application/integration state and canonical maintenance/emergency policy under appropriate locking/fencing.
4. **Rescheduling rewrites existing event identity/state.** `updateOrCreate` resets terminal rows using the same idempotency key. Preserve immutable attempts and create a new schedule generation.

### Current P2

Ten material hardening items remain: renewable execution heartbeat, actionable launch/provider alerts, database-enforced state constraints, safe large-table migration rehearsal, tamper-evident/restricted audit retention, applicant/member management UX, document retention/export/deletion and private object-storage lifecycle, pagination/index capacity proof, frontend lint/accessibility/browser automation, and dependency-aware worker/scheduler health probes.

### P3

Nice-to-have work includes richer reviewer workload analytics, additional applicant progress assistance, and expanded long-term operational reporting. These do not supersede P0/P1 controls.

## External Dependency Matrix

| Dependency | Purpose/capability | Adapter | Configured/connected/tested | Production credentials | Fail closed | Blocks |
|---|---|---|---|---|---|---|
| Company registry | KYB entity verification | No normalized production adapter proven | No/No/No | No | Yes via `NOT_CONNECTED` | Automated KYB/launch |
| Sanctions/PEP/adverse media | Organization/person screening | No normalized production adapter proven | No/No/No | No | Yes | KYB/launch |
| Malware scanner | Evidence safety | Interface + not-connected adapter | Selected not-connected/No/No | No | Yes | Evidence-dependent approval/launch |
| Blockchain analytics | Contract/address risk | Canonical validation hooks, no operational provider proof | Unverified | Unverified | Partial/manual | Production asset approval |
| Custody/signing | Deposits/withdrawals | Canonical custody/provider interfaces | Production not connected/tested | No evidence | Yes | Deposit/withdrawal launch |
| RPC/nodes | Chain validation and monitoring | Provider manager/failover code | Production endpoints unverified | No evidence | Yes | Contract/deposit/withdrawal |
| Market data | Launch and ongoing prices | Canonical market-data services | New-asset production source unverified | Unverified | Yes when stale/unknown | Trading launch/operations |
| Market makers | Executable liquidity | Requirement records only for Listing | Real connectivity/capital unverified | N/A | Launch requires READY but evidence can remain manual | Trading launch |
| Email | Verification/invitations/operations | Laravel notification infrastructure | Production delivery/bounce handling unverified | No evidence | Operationally incomplete | Applicant/reviewer operations |
| Private object storage | Confidential documents | Laravel storage abstraction | Production encrypted bucket/backup unverified | No evidence | Deployment-dependent | Evidence operations |
| Monitoring/on-call | Incident response | Generic Phase 19 services | Listing dashboards/alerts/on-call unverified | N/A | No | Safe production operations |

**External dependencies not operational: 11.**

## Operational Readiness

- Scheduler code is **READY** and registered every minute. Production scheduler/cron is **UNVERIFIED**.
- Queue job/configuration exists with a dedicated `listing-launch` queue. Production supervisors, restart policy, failed-job ownership, backlog alerting and running workers are **UNVERIFIED**.
- Redis loss does not erase DB launch records, but current `DISPATCHED` recovery makes queue loss a P0 operational defect.
- Reconciliation readiness is checked before capability activation, but production schedules, mismatch alerts, investigation queues and ownership are not proven.
- Emergency pause primitives exist, but granular governed UI, race safety, specialist permissions, drills and propagation evidence are incomplete.
- Routine applicant operations are available; routine internal lifecycle and terminal launch recovery still require API/CLI/developer intervention.

## Security Review

Positive controls include canonical authentication, CSRF-aware cookie requests, server-side tenant checks, private evidence, clean-version gates, rate limits, configuration fingerprints, audit records, maker-checker activation, and fail-closed external adapters.

Material gaps are coarse internal privileges, incomplete comprehensive BOLA/race coverage, credentialed wildcard CORS policy with development origins, no production CSP/SSO evidence in this audit, no tamper-resistance proof for Listing audit logs, disconnected scanner/screening providers, and the launch cancellation/replay defects. No hardcoded wallet key or production provider credential was printed or relied upon. Production secret injection and scanning evidence remain deployment/CI concerns.

## Auth/SSO Review

Listing reuses canonical ExaEarn users, passwords, registration, verification, reset, MFA/session behavior and account restrictions. The API uses `credentials: include`; no Listing localStorage bearer token path was found. Sensitive routes are authenticated, tenant checked and rate limited, with recent authentication on ownership transfer.

Cross-subdomain production SSO is **UNVERIFIED**. `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, `SANCTUM_STATEFUL_DOMAINS`, TLS and trusted proxies are environment-owned. CORS always merges development origins/patterns, allows wildcard methods/headers, and supports credentials. This must be corrected and tested in the real domains.

## KYB Review

The ten-stage workflow, explicit states, directors/UBOs/representatives, declarations, documents, invitations, membership roles, transfer of ownership and admin review are implemented. Organization access is server-side. Manual verification evidence does not equal connected registry/screening results.

Remaining controls include semantic UBO/director validation and duplicate/control-person policy, reviewer-conflict governance, automatic expiry, document expiry, material-change invalidation, registry changes and periodic sanctions/PEP/adverse-media rescreening. Production jurisdiction and legal-document version populations were not verified.

## Custody Review

Listing uses the canonical custody/network/readiness services and fails closed. The default signer is development-local, production allowed-provider checks exist, and development signing cannot authorize production withdrawal activation. Production custody provider, HSM/MPC/multisig arrangement, credentials, key ceremonies, address derivation, monitors, hot/cold-wallet policy, signing, broadcast and reconciliation have not been connected and tested for a listed asset.

## Liquidity Review

Liquidity records capture arrangement, base/quote requirements, spread and depth expectations. Final approval checks a persisted READY state, but the repository does not prove live capital, market-maker accounts, connectivity, executable quoting, sustained depth/spread, evidence expiry, or launch-time MM health. Real liquidity/MM readiness is not verified.

## Post-Listing Review

Canonical market surveillance and generic security/reliability services exist elsewhere, but no complete Listing-owned producer continuously combines market health, liquidity, custody, deposit/withdrawal monitor health, RPC, contract changes, KYB expiry and compliance revocation with alerts and fail-safe actions. Post-listing operations are **NOT_READY**.

## Delisting Review

`DELISTING` and `DELISTED` states exist, but there is no complete maker-checker decision, announcement, trading/deposit disablement, withdrawal-only window, remaining-balance resolution, final withdrawal policy, user notification, archival and retained audit workflow. Delisting is **NOT_READY** and blocks a full public Listing lifecycle.

## Infrastructure Review

Code-level scheduler and queue registration pass. Production process supervision, Redis persistence/HA, scheduler heartbeat, backlog monitoring, deployment topology, environment secrets, provider credentials, Listing health dashboards, alert routing, database/object-storage backups and tested restores are unverified. A backup strategy or runbook is not restore evidence. No production-equivalent PostgreSQL migration/replay/race exercise was presented for the new launch migration.

## Test Evidence

| Gate | Result |
|---|---|
| Complete backend | PASS - 616 passed, 0 failed, 1 skipped, 5,050 assertions |
| Listing-focused remediation | PASS - 16 tests, 234 assertions |
| MarketData/Convert focused | PASS - 17 tests, 114 assertions |
| Listing TypeScript typecheck | PASS |
| Listing production build | PASS |
| Listing lint | NOT_CONFIGURED - no `lint` script in `@exaearn/listing` |
| Scheduler registration | PASS - `listing:launches:dispatch` registered |
| Production scheduler/workers | NOT RUN / UNVERIFIED |
| Production browser/SSO | NOT RUN |
| Production provider integration | NOT RUN / NOT_CONNECTED |
| Production backup/restore | NOT RUN / UNVERIFIED |

The single backend skip remains environment-dependent. Test volume does not close the migration replay, accepted-message loss, cancellation race, or post-side-effect recovery defects because no current test exercises those contracts.

## Capability Readiness Matrix

| Capability | Verdict | Reason |
|---|---|---|
| Applicant portal | CONDITIONALLY_READY | Functional, but lint/browser/accessibility and production deployment evidence incomplete |
| Authentication | CONDITIONALLY_READY | Canonical auth; production SSO/CORS unverified |
| Organization management | CONDITIONALLY_READY | Server RBAC works; lifecycle/policy edge cases remain |
| KYB software | CONDITIONALLY_READY | Core state machine exists; reverification/providers missing |
| Listing application | CONDITIONALLY_READY | Structured workflow; semantic validation/concurrency incomplete |
| Document safety | CONDITIONALLY_READY | Fail-closed software; real scanner/object storage absent |
| Listing review | CONDITIONALLY_READY | APIs exist; specialist RBAC/Admin UX incomplete |
| Certification | CONDITIONALLY_READY | Executable and fail closed; production-equivalent execution not operational |
| Trading launch | NOT_READY | Three new launch P0 defects and unverified operations/liquidity |
| Deposit launch | NOT_READY | Safe software gate but external custody/RPC/monitoring absent; dispatcher unsafe |
| Withdrawal launch | NOT_READY | Safe software gate but signer/custody/RPC/liquidity absent; dispatcher unsafe |
| Post-listing operations | NOT_READY | Monitoring/alerting/ownership incomplete |
| Delisting | NOT_READY | Lifecycle absent |
| Admin operations | NOT_READY | Generic shell, coarse RBAC, no governed recovery UX |

## Launch Mode Analysis

- **APPLICATION_INTAKE_ONLY: CONDITIONALLY_READY** after production SSO/CORS, private storage, scanner handling policy, jurisdiction/legal content and operational staffing are validated. It must not imply approval or launch readiness.
- **REVIEW_ONLY: NOT_READY** for final production decisions until real registry/screening/scanner providers or formally governed manual evidence paths, specialist RBAC and dedicated Admin workflows are operational.
- **TRADING_ONLY: NOT_READY** because the durable dispatcher has P0 defects and real liquidity/market operations are unverified.
- **FULL_LISTING_WITH_DEPOSITS_WITHDRAWALS: NOT_READY** because custody, signer, RPC, liquidity, reconciliation and production operations are not connected/tested.

## Prioritized Remediation Plan

### P0 - Must fix before any automated launch

1. **Backfill launch migration safely.** Map every legacy status to a terminal/nonterminal operation status before enabling the dispatcher; add upgrade and rollback tests with historical data. Dependency: production database rehearsal. Acceptance: no historical terminal event can be claimed.
2. **Recover lost `DISPATCHED` work.** Add lease-based rediscovery and token fencing for accepted-but-missing queue jobs. Dependency: queue failure harness. Acceptance: Redis restart/message purge results in one effective recovered launch.
3. **Fence cancellation against execution.** Disallow unsafe cancellation after execution claim or introduce generation/fencing checked inside every side-effect transaction. Acceptance: cancellation-versus-launch races produce exactly one truthful outcome and no capability activates after cancellation wins.

### P1 - Must fix before full production launch

1. Make execution converge after crashes at every side-effect boundary and preserve immutable attempts/schedule generations.
2. Add governed retry/revalidate/reschedule/cancel operations and a dedicated Listing Operations UI.
3. Implement specialist internal RBAC, conflict checks and maker-checker controls across review, readiness, launch, override and recovery.
4. Add locking/version checks to KYB, reviews, approval, configuration, scheduling, pause and emergency transitions.
5. Add nested semantic application/KYB validation and optimistic draft concurrency.
6. Connect registry, screening and malware providers or formalize tightly controlled manual paths where law/policy permits.
7. Implement KYB expiry/material-change/rescreening and complete information-request workflow.
8. Implement Listing-specific post-launch monitoring, alert ownership, pause propagation and reconciliation operations.
9. Implement the complete governed delisting lifecycle.
10. Restrict production CORS and verify SSO/cookies/CSRF/TLS/trusted proxies.

## Final GO/NO_GO Decision

**NO_GO.**

The original safety remediation is meaningful and the current backend is regression-clean, but launch-event migration, queue-loss recovery, and cancellation concurrency can violate durable launch guarantees. Even after those defects are fixed, full production remains blocked by the nine open original P1s, disconnected providers and custody infrastructure, and unverified operations/deployment evidence.

## Final Scorecard

```text
EXAEARN LISTING PORTAL RE-AUDIT

OVERALL DECISION:
NO_GO

SOFTWARE READINESS:
66/100

SECURITY READINESS:
58/100

AUTH READINESS:
72/100

KYB/COMPLIANCE SOFTWARE READINESS:
62/100

LAUNCH ORCHESTRATION READINESS:
38/100

OPERATIONAL READINESS:
22/100

PRODUCTION INFRASTRUCTURE READINESS:
18/100

EXTERNAL INTEGRATION READINESS:
10/100

OVERALL PRODUCTION READINESS:
39/100

ORIGINAL P0:
CLOSED: 4
PARTIAL: 0
OPEN: 0

ORIGINAL P1:
CLOSED: 0
PARTIAL: 3
OPEN: 9
SUPERSEDED: 0

NEW P0:
3

NEW P1:
4

P2:
10

EXTERNAL DEPENDENCIES NOT OPERATIONAL:
11

BACKEND TESTS:
616 passed / 0 failed / 1 skipped

LISTING TYPECHECK:
PASS

LISTING BUILD:
PASS

LISTING LINT:
NOT_CONFIGURED
```

## Required Direct Answers

```text
CAN SYNTHETIC CERTIFICATION STILL PRODUCE A FALSE PASS?
NO

CAN LISTING APPROVAL ALONE ENABLE DEPOSITS?
NO

CAN LISTING APPROVAL ALONE ENABLE WITHDRAWALS?
NO

CAN AN UNSCANNED REQUIRED DOCUMENT SUPPORT APPROVAL?
NO

CAN MARKET EXISTENCE ALONE CREATE INTERNAL PRICE PROVENANCE?
NO

CAN A SCHEDULED LAUNCH BE LOST DURING TEMPORARY SCHEDULER DOWNTIME?
NO (it is recovered within the execution window or visibly blocked after expiry)

CAN DUPLICATE JOB DELIVERY CAUSE TWO EFFECTIVE LAUNCHES?
NO for ordinary same-event redelivery; historical migration replay remains a separate P0

DOES LAUNCH REVALIDATE CURRENT READINESS?
NO (many gates are rechecked, but maintenance/emergency and current-state fencing are incomplete)

CAN STALE APPROVAL LAUNCH CHANGED CONFIGURATION?
NO

CAN TRADING-ONLY LAUNCH ENABLE DEPOSITS?
NO

CAN TRADING-ONLY LAUNCH ENABLE WITHDRAWALS?
NO

IS PRODUCTION CROSS-SUBDOMAIN SSO ACTUALLY VERIFIED?
UNVERIFIED

IS A REAL MALWARE SCANNER CONNECTED AND TESTED?
NO

IS PRODUCTION CUSTODY CONNECTED AND TESTED?
NO

IS A PRODUCTION WITHDRAWAL SIGNER CONNECTED AND TESTED?
NO

ARE PRODUCTION RPC PROVIDERS CONNECTED AND TESTED?
NO

IS REAL LIQUIDITY/MM READINESS VERIFIED?
NO

ARE PRODUCTION SCHEDULER AND QUEUE WORKERS VERIFIED RUNNING?
NO

IS POST-LISTING MONITORING PRODUCTION READY?
NO

IS DELISTING PRODUCTION READY?
NO

CAN ROUTINE LISTING OPERATIONS BE PERFORMED WITHOUT DATABASE MANIPULATION?
PARTIAL

ARE BACKUP AND RESTORE PROCEDURES TESTED?
UNVERIFIED
```

## What Is Actually Left Before Production

### A. Software Remaining

| Item | Priority | Status | Production blocker |
|---|---|---|---|
| Safe legacy launch-event migration/backfill | P0 | OPEN | YES |
| Reclaim lost `DISPATCHED` events | P0 | OPEN | YES |
| Fence cancellation versus execution | P0 | OPEN | YES |
| Crash-convergent launch steps and immutable schedule generations | P1 | OPEN | YES |
| Semantic application/KYB validation and draft concurrency | P1 | OPEN | YES |
| Information-request response lifecycle | P1 | OPEN | YES |
| Post-listing monitoring and delisting | P1 | OPEN | YES |

### B. Security Remaining

| Item | Priority | Status | Production blocker |
|---|---|---|---|
| Specialist RBAC, conflicts and maker-checker coverage | P1 | OPEN | YES |
| Production CORS/SSO/browser security validation | P1 | UNVERIFIED | YES |
| Audit-log integrity/access/retention proof | P2 | PARTIAL | NO for intake; YES for full operations |
| Independent adversarial/race/file security assessment | P1 | UNVERIFIED | YES |

### C. External Providers

| Item | Priority | Status | Production blocker |
|---|---|---|---|
| Registry, sanctions/PEP/adverse-media, malware | P1 | NOT_CONNECTED | YES |
| Blockchain analytics | P1 | NOT_CONNECTED/UNVERIFIED | YES for relevant assets |
| Custody, production signer and RPC | P1 | NOT_CONNECTED | YES |
| Market data and market-maker connectivity | P1 | UNVERIFIED | YES |
| Email and private object storage | P1 | UNVERIFIED | YES |

### D. Infrastructure

| Item | Priority | Status | Production blocker |
|---|---|---|---|
| Scheduler/worker supervision and queue/Redis recovery proof | P1 | UNVERIFIED | YES |
| Production PostgreSQL migration/race rehearsal | P0 | UNVERIFIED | YES |
| Listing health, metrics, alerts and dependency probes | P1 | PARTIAL | YES |
| Database and document backup/PITR restore drill | P1 | UNVERIFIED | YES |

### E. Operations

| Item | Priority | Status | Production blocker |
|---|---|---|---|
| Named reviewer/custody/risk/launch/on-call ownership | P1 | UNVERIFIED | YES |
| Launch, rollback, provider outage and reconciliation drills | P1 | UNVERIFIED | YES |
| Governed terminal failure recovery | P1 | OPEN | YES |
| Incident and delisting runbooks rehearsed | P1 | UNVERIFIED | YES |

### F. Compliance/Policy

| Item | Priority | Status | Production blocker |
|---|---|---|---|
| Approved jurisdictions and legal-document versions | P1 | UNVERIFIED | YES |
| KYB expiry, material-change and periodic rescreen policy | P1 | OPEN | YES |
| UBO/control-person and reviewer-conflict policy | P1 | PARTIAL | YES |
| Evidence retention, export and deletion policy | P2 | OPEN | NO for pilot; YES for GA |

### G. UI/Admin Operations

| Item | Priority | Status | Production blocker |
|---|---|---|---|
| Dedicated Listing/KYB/evidence/review operations center | P1 | OPEN | YES |
| Launch status, correlation, attempts, failures and recovery controls | P1 | PARTIAL | YES |
| Member/invitation/ownership management UX | P2 | PARTIAL | NO |
| Listing lint, accessibility and responsive browser automation | P2 | NOT_CONFIGURED/PARTIAL | NO |

### H. Final Validation

| Item | Priority | Status | Production blocker |
|---|---|---|---|
| New launch migration, loss, cancellation and crash tests | P0 | OPEN | YES |
| Production-equivalent custody/RPC/scanner/MM integration tests | P1 | NOT_CONNECTED | YES |
| End-to-end SSO, scheduler, queue, alert and restore evidence | P1 | UNVERIFIED | YES |
| Full regression, build, lint/security and controlled launch rehearsal | P1 | PARTIAL | YES |

