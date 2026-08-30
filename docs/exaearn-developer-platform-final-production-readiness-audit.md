# ExaEarn Developer Platform Final Production-Readiness Audit

Date: 2026-08-30  
Audit posture: independent, repository-based, fail closed  
Scope: Developer identity, REST, realtime, webhooks, governance, delivery, SDKs, contracts, deployment, and financial routing

## 1. Executive verdict

ExaEarn must remain at **LEVEL C - PUBLIC SANDBOX BETA**. Private Production Beta is **not authorized**.

The canonical architecture is materially sound: Developer users reuse the ExaEarn user identity, signed API requests are project/environment/scope bound, Sandbox balances are isolated, product writes route to existing product controllers/services, and focused/full regression is clean. The original P0 application defects are closed in code.

The production delivery path is not yet coherent, however. Mandatory CI cannot execute as written; the realtime NetworkPolicy blocks its authority calls; webhook records have no scheduled/queued delivery trigger; Production Access decisions can be applied to terminal requests; the migration classifier can report raw SQL migrations as SAFE; and organization reviewer-conflict enforcement still lacks beneficial-owner identity data. These are production-critical software/control defects, not missing prose.

### Current finding counts

| Severity | Open | Software-controlled | External/deployment evidence |
|---|---:|---:|---:|
| P0 | 0 | 0 | 0 |
| P1 | 11 | 6 | 5 |
| P2 | 12 | 8 | 4 |
| P3 | 5 | 5 | 0 |

Counts describe independently open findings in this report, not historical totals. External gates are also itemized separately near the end.

## 2. Recommended launch level

**LEVEL C - PUBLIC SANDBOX BETA** remains appropriate. Keep Production credentials, wallet withdrawal, and Production webhook delivery disabled. LEVEL D can be reconsidered only after all six software-controlled P1 findings are fixed and controlled-deployment evidence exists.

## 3. Architecture and ownership

The audit found one canonical ownership chain: `User -> DeveloperProfile -> Workspace/Organization -> Project -> Environment -> API Credential`. Organization memberships reference canonical users; Production Access reuses canonical KYC/KYB/institution records; Developer REST writes enter existing Spot, Futures, Margin, Convert, Staking, Copy, ExaAI, and ExaPay controllers/services. No separate Developer wallet, ledger, matching engine, or compliance identity was found.

Sandbox balances are a deliberately separate simulation read model and tests prove they do not mutate production wallets or ledger state. This is correct isolation, not a parallel real-money ledger.

## 4. Original P0/P1 reconciliation

| Original finding | Original | Remediation | Current implementation | Independent verification | Status | Current severity | Evidence |
|---|---|---|---|---|---|---|---|
| Webhook SSRF | P0 | P0 | HTTPS-only validator, public-address DNS validation, metadata/private denial, redirect denial, and cURL address pinning | Adversarial focused tests pass; production egress is still disabled | Code closed; deployment proof required | External P1 gate | `WebhookDestinationValidator`, `DeveloperWebhookService`; P0 tests |
| Untrusted client-IP chain | P0 | P0 | Canonical client-IP service trusts only configured proxies | Spoof/trusted-proxy tests pass | Code closed; ingress verification required | External P1 gate | `CanonicalClientIp`; P0 tests; Kubernetes `TRUSTED_PROXIES` placeholder |
| Wrong organization runtime compliance context | P0 | P0 | Runtime passes institution, KYB, jurisdiction, and representative context | KYB suspension test fails closed | Closed | None | `DeveloperProductionAccessService`; P0 tests |
| Missing authenticated external Developer WebSocket | P1 | Wave 1 | Auth handshake, environment paths, scopes, replay, heartbeat, and bounded sends exist | Local 1K handshake passes, but production NetworkPolicy denies gateway-to-authority traffic | Open | P1 | realtime hub plus `network-policy.yaml` |
| Webhook concurrent claiming | P1 | Wave 1 | Transactional claim, lease token/expiry, `SKIP LOCKED` on PostgreSQL | Claim/recovery tests pass | Closed | None | `DeveloperWebhookService::claimDue` |
| Webhook environment isolation | P1 | Wave 1 | Endpoint, event, project, and environment are bound | Cross-environment tests pass | Closed | None | Wave 1 tests |
| Production Access self-review/four-eyes weakness | P1 | Wave 2 | Canonical reviewer identity and distinct high-risk reviewers added | Requester/owner and duplicate-review tests pass; beneficial owners remain unlinked and terminal states can be re-approved | Open | P1 | `ProductionAccessReviewerConflictService`; `DeveloperProductionAccessService::decide` |
| Missing CI | P1 | Wave 3 | Workflow added | It invokes unsupported `composer install --locked`; test setup copies PostgreSQL `.env` without a PostgreSQL service/extension | Open | P1 | `.github/workflows/developer-platform-gates.yml:26,30-31,45` |
| Missing backend production deployment spec | P1 | Wave 3 | Docker/Kubernetes definitions added | Definitions exist, but realtime egress, readiness, placeholders, and webhook runtime are not deployable as claimed | Open | P1 | `infrastructure/developer-platform/kubernetes/*` |
| Failed requests may evade logging | P1 | Wave 2 | Exception-path request logging and sanitization added | Focused test passes | Closed | None | `DeveloperApiRequestContext`; Wave 2 tests |
| Request-log environment attribution wrong | P1 | Wave 2 | Environment is taken from authenticated key/project context | Focused test passes | Closed | None | Wave 2 tests |
| Webhook payload policy decentralized | P1 | Wave 1 | Central event registry and allowlisted serializers added | Redaction/schema test passes | Closed | None | `DeveloperWebhookEventRegistry` |
| No backup/PITR restore proof | P1 | Wave 3 | Strategy and drill script added | No production restore evidence was available; local PostgreSQL drill was not runnable here | Open evidence gate | P1 external | restore script and Wave 3 report |
| Dependency audit unavailable | P1 | Wave 3 | CI scanner jobs added | Composer audit could not reach Packagist; Gitleaks/Docker/Trivy unavailable locally; workflow itself is currently broken | Open | P1 external plus CI P1 | scanner attempts recorded below |
| Production operations UI incomplete | P1 | Wave 2 | Admin queue, reviews, capability decisions, suspension/revocation, and history added | Admin typecheck/lint/build pass | Closed | None | Admin Production Access page/controllers |

## 5. New and residual findings

### P1 - production blockers

1. **Mandatory CI is non-executable.** Composer 2.8 exposes no `install --locked` option, yet both PHP install steps use it. The backend job also copies `.env.example` (`DB_CONNECTION=pgsql`, Redis queue), creates an unused SQLite file, and provides neither PostgreSQL nor Redis. The required gate will fail before proving migrations/tests.
2. **Developer WebSocket production authorization is blocked by NetworkPolicy.** `realtime-gateway` may egress only to data-services and DNS; it must call Laravel `/internal/developer/realtime/authorize` and `/replay`. No same-namespace API egress rule exists.
3. **Webhook delivery has no runtime trigger.** `deliverDue()` is called only by tests. No command, job dispatch, or scheduler entry invokes it. The declared `developer-webhooks` queue worker therefore has no delivery jobs to consume.
4. **Production Access terminal states are not enforced.** `decide()` has no allowed-source-state check. A normal approval can revisit a rejected, suspended, or revoked request and reactivate capabilities/environment. Suspend/revoke also omits `pending_second_review` from its capability transition set. Emergency revocation needs an explicit, audited reapplication/resume workflow.
5. **Migration classification provides false assurance.** The classifier recognizes only a narrow set of schema helpers. It reports all 146 migrations SAFE while missing `DB::statement()` DDL/data changes (for example the Giftcard currency type migration), index/constraint operations, table-lock risk, and data backfills. SQLite-only migration execution cannot validate PostgreSQL behavior.
6. **Organization reviewer conflicts are incomplete.** Canonical submitter, owner, and institution-master conflicts are checked, but no canonical beneficial-owner/controller relationship exists. Organization Production Access must remain disabled or individually reviewed under an explicit external control until this is implemented and populated.

### P2 - required for GA or formal risk acceptance

1. Production KYC freshness defaults to disabled (`kyc_max_age_days=0`).
2. CORS always includes localhost origins/patterns, wildcard methods, and wildcard headers while credentials are supported.
3. Webhook endpoint creation does not require recent authentication, although rotation does.
4. API and realtime readiness probes check process HTTP endpoints, not database, Redis, authority, or queue health; workers have no hung-worker probe.
5. Redis publish failures in realtime are swallowed without a durable operational alert/metric.
6. Realtime fanout is O(connections), has no repository-enforced global connection cap, and reauthorizes all clients on the same heartbeat cadence.
7. The 1K harness uses an in-memory authority and proves only connect/authenticate, not real authority, Redis delivery, subscription fanout, replay, revocation, heartbeat, or slow-consumer behavior.
8. Realtime sequence allocation uses aggregate `MAX(sequence)` locking; first/concurrent inserts require database-level sequence proof under the production database.
9. `GenericRequest` remains in OpenAPI components with `additionalProperties: true`, even though it is currently unreferenced.
10. Redocly is fetched as mutable `@latest`; GitHub Actions are tag-pinned rather than commit-SHA pinned.
11. Webhook error retention/redaction lifecycle and production payload size enforcement need operational validation.
12. Public status uptime/SLO, CAPTCHA fail-closed behavior, and security-notification provider delivery remain unproven in a deployed environment.

### P3

Passkeys are absent; recovery-code UX remains incomplete; executable SDK examples are not yet a dedicated conformance suite; production status copy needs continuous contract verification; and PHPUnit doc-comment metadata warnings should be migrated before PHPUnit 12.

## 6. Authentication, workspace, and credentials

Developer browser authentication reuses canonical Sanctum/user identity. Signup, verification, reset, TOTP-aware reauthentication, session revocation, logout-all, safe reset behavior, and recent-auth protection for API credential creation/rotation/policy changes have focused coverage. Workspace RBAC tests cover role boundaries, recipient-bound invitations, final-owner protection, ownership transfer, archive, and IDOR.

API credentials are environment/project/scope/IP bound. Secrets are encrypted for HMAC verification and returned once; nonce uniqueness and timestamp checks are enforced. Disable/revoke invalidates realtime sessions. No secret leakage was found in normal serialization/audit tests. Production remains gated by capability approval and runtime compliance.

Residual browser/deployment risks are the CORS production policy, live CAPTCHA/provider proof, and recent-auth omission for webhook creation.

## 7. Production Access and compliance

Individual KYC and organization KYB/institution status are reused. Capability-level partial approval and distinct canonical reviewers for high/restricted capabilities are present. Runtime checks fail closed after KYB suspension. This foundation is credible, but Production Access is **BLOCKED** by the terminal-state transition defect and beneficial-owner conflict gap. KYC freshness must also be given a nonzero production policy.

## 8. REST and financial safety

Public REST uses the authoritative market-data layer. Signed private routes consistently apply Developer auth and product-specific scopes. Representative writes route into existing controllers/services: Spot through `TradeService`, Futures through `FuturesController`, Margin through `MarginController`, Convert through `SwapController`, staking through `StakingController`, Copy through `CopyTradingController`, ExaAI through `ExaAiController`, and ExaPay through its merchant controller. Tests cover signing, nonce replay, scope/IP/environment denial, idempotency, and Sandbox isolation. No Developer-specific real-money ledger or direct balance mutation was found.

The Developer API exposes Wallet read only. No Wallet transfer or withdrawal route is registered. This is safer than an incomplete write surface: Wallet Transfer and Wallet Withdraw are **BLOCKED**, not implicitly available.

## 9. WebSocket readiness

Application software includes separate Sandbox/Production paths, challenge/session authorization, topic scopes, capability revalidation on heartbeat, bounded buffering, disconnect policy, durable sequence/replay, and gap recovery contracts. A local test authenticated 1,000 sockets with zero failures in 2.174 seconds at 84 MB RSS.

This is not production-equivalent capacity evidence. The harness substitutes an in-memory authority and sends no financial event workload. More importantly, the supplied production NetworkPolicy prevents authority traffic. WebSockets are therefore **BLOCKED** for LEVEL D until the deployment path is fixed and end-to-end load/revocation/replay/backpressure tests pass.

## 10. Webhook readiness

Destination validation and delivery mechanics are substantially hardened: HTTPS, DNS/IP checks, address pinning, redirect denial, atomic claim, lease recovery, retry, dead letter, stable event identity, signing, environment binding, and payload allowlisting. Production delivery correctly defaults off.

Webhooks remain **BLOCKED** because no production execution path calls `deliverDue()`, and the referenced egress proxy is not defined or verified in this repository. Keep production delivery disabled.

## 11. SDK, OpenAPI, and documentation

The generated contract contains 93 paths, 103 operations, 32 schemas, 958 internal references, and zero broken internal references. One unused `GenericRequest` schema remains. TypeScript SDK and Developer Portal typechecks pass; Python SDK reports 6/6. Canonical signing fixtures pass. Developer and Admin production builds pass outside the Windows process-spawn sandbox.

OpenAPI drift cannot be considered a mandatory gate until CI is fixed. Redocly execution and live contract conformance were not independently run because network/tooling was unavailable.

## 12. CI/CD, deployment, and migration readiness

Repository workflows contain backend, contract, SDK, portal/admin, CodeQL, dependency, Gitleaks, SBOM, and Trivy intentions with no `continue-on-error`. Nevertheless, the unsupported Composer option and missing test services make the workflow nonfunctional. No repository evidence proves branch protection or required checks.

Docker/Kubernetes manifests include replicas, resource limits, disruption budgets, ingress TLS declarations, default-deny networking, workers, scheduler, and fail-closed production webhook default. They still contain placeholder images/hosts/secrets/proxies, use `artisan serve`, lack dependency-aware readiness, omit operational metrics wiring, block realtime authority traffic, and declare an idle webhook worker. They are templates, not production-ready deployment evidence.

Migration classification is not trustworthy for PostgreSQL production rollout. A PostgreSQL migration rehearsal and backward-compatible deploy/rollback proof are required.

## 13. Backup/PITR and observability

Backup architecture, RPO/RTO targets, and a restore-drill script exist. Proven restorability does not. There is no production backup/PITR restore artifact, measured RPO/RTO, or retained restore evidence available to this audit.

Phase 19 supplies generic SRE models/services, but the Developer deployment offers no concrete scrape configuration, dashboards, alert rules, pager ownership, queue-depth exporter, or dependency-aware readiness. Monitoring/on-call remains a deployment and operational gate; missing runtime instrumentation/probes is also a repository P2.

## 14. Environment isolation

Credential, project, environment, Sandbox balance, WebSocket session, and webhook environment boundaries have focused tests and fail-closed middleware. Sandbox does not credit canonical wallets. Provider credentials and actual Redis/database separation are deployment concerns and require production verification.

## 15. Withdrawal readiness

**Wallet Withdraw: BLOCKED.** The scope is restricted and requires an IP allowlist/dual approval at Production Access, but there is no Developer withdrawal endpoint. Do not enable the scope or infer readiness from generic Production Access. A future endpoint must reuse canonical withdrawal compliance, address controls, risk, step-up authorization, idempotency, ledger, and revocation paths and receive separate security review.

## 16. Product readiness matrix

| Product | Verdict | Basis |
|---|---|---|
| Public Market Data | PRIVATE BETA READY | Authoritative public source and REST contracts; live SLO/capacity remains external |
| Spot Read | PRIVATE BETA READY | Scoped canonical read path |
| Spot Trade | SANDBOX ONLY | Canonical OMS path passes tests; Production Access/deployment blocked |
| Futures Read | PRIVATE BETA READY | Canonical controller/read models |
| Futures Trade | SANDBOX ONLY | Canonical risk/OMS path, but production gate blocked |
| Margin | RESTRICTED | Canonical service; product/risk allowlisting required |
| Wallet Read | PRIVATE BETA READY | Scoped canonical balances; Sandbox isolated |
| Wallet Transfer | BLOCKED | No Developer route |
| Wallet Withdraw | BLOCKED | No Developer route; restricted scope only |
| Earn/Staking | RESTRICTED | Canonical product path; terms/product operations remain gated |
| Convert | SANDBOX ONLY | Canonical quote/execute path; production gate blocked |
| ExaPay | RESTRICTED | Canonical merchant controls; external payment operations required |
| Copy Trading | RESTRICTED | Canonical Phase 12 governance/capacity controls |
| ExaAI | RESTRICTED | Canonical strategy/operations controls and regulatory configuration |
| WebSockets | BLOCKED | Production authority path denied by NetworkPolicy |
| Webhooks | BLOCKED | No runtime delivery trigger; egress unverified |
| Sandbox | PRIVATE BETA READY | Isolation and signed API regression pass |
| Production Access | BLOCKED | State-machine and beneficial-owner gaps |
| TypeScript SDK | PRIVATE BETA READY | Typecheck/signing fixture pass; CI/conformance incomplete |
| Python SDK | PRIVATE BETA READY | 6/6 unit tests; CI/conformance incomplete |

No product is marked GA READY because underlying production operations, deployment, independent security, and capacity evidence are not established.

## 17. Test evidence

| Validation | Result |
|---|---|
| Developer P0/P1/auth/credential/RBAC/Phase 14 focused | PASS - 66 tests, 2009 assertions |
| Full backend | PASS - 590 passed, 0 failed, 1 skipped, 4860 assertions |
| TypeScript SDK typecheck | PASS |
| Python SDK | PASS - 6 tests |
| Developer Portal typecheck/build | PASS / PASS (build required execution outside spawn-restricted sandbox) |
| Admin typecheck/lint/build | PASS / PASS / PASS (build required execution outside spawn-restricted sandbox) |
| OpenAPI generation | PASS - 93 paths |
| OpenAPI internal references | PASS - 958 refs, 0 broken |
| GenericRequest | FAIL - 1 unused permissive schema remains |
| Migration classifier | Script PASS - 146 reported SAFE; audit result FAIL because raw SQL is missed |
| Local 1K WebSocket authenticate | PASS - 1000/1000, 2.174 s, 84 MB RSS |
| End-to-end production-equivalent WebSocket | NOT RUN |
| `pnpm audit` | INCOMPLETE - local pnpm launcher was malformed; no trustworthy audit output |
| Composer audit | BLOCKED - Packagist unreachable |
| Gitleaks | BLOCKED - tool unavailable locally |
| Docker/Trivy | BLOCKED - Docker unavailable locally |
| Production PostgreSQL migration rehearsal | NOT RUN |
| Backup/PITR restore drill | NOT RUN - production PostgreSQL/evidence unavailable |

The one backend skip is retained as a separate environment-dependent test and did not affect Developer focused coverage.

## 18. Exact blockers to LEVEL D

1. Repair mandatory CI and demonstrate required checks on a protected branch.
2. Add a real durable webhook delivery command/job/schedule and verify its worker lifecycle.
3. Fix realtime NetworkPolicy/service authority routing and run end-to-end tests.
4. Enforce an explicit Production Access state machine and safe reapplication/resume after terminal/emergency states.
5. Replace the migration classifier with meaningful PostgreSQL-aware policy and rehearsal.
6. Block organization Production Access until canonical beneficial-owner/controller conflicts are enforceable.
7. Verify trusted proxy, webhook egress, monitoring/alerting, controlled cohort/allowlists, rollback, production capacity, and restore evidence in the target environment.
8. Complete network-enabled dependency/secret/container scanning and independent penetration testing with no unaccepted critical/high findings.

## 19. Exact blockers to LEVEL E

All LEVEL D blockers, plus sustained private-beta operational history; independent penetration and architecture review; production-scale REST/WS/webhook capacity and failure testing; proven backup/PITR RPO/RTO; mature SLOs/on-call/incident drills; provider and jurisdiction approvals; supply-chain artifact provenance/signing; ongoing OpenAPI/SDK conformance; and closure or formal risk acceptance of all P2 findings.

## 20. Remaining software-controlled blockers

1. Broken CI commands/test-service configuration.
2. Missing webhook delivery dispatcher.
3. Realtime-to-authority NetworkPolicy/service path.
4. Production Access terminal-state and emergency-resume transitions.
5. Inadequate migration classifier/PostgreSQL migration gate.
6. Beneficial-owner conflict enforcement for organization Production Access (or repository-enforced individual-only cohort restriction).

## 21. Remaining deployment / external / operational gates

1. Trusted proxy/edge-header chain verification.
2. Production webhook egress proxy, DNS rebinding, metadata denial, and packet/log proof.
3. PostgreSQL backup/PITR production restore evidence with measured RPO/RTO.
4. Network-enabled Composer/npm vulnerability scans.
5. Gitleaks, CodeQL, SBOM, image/Trivy results from trusted CI.
6. Independent penetration test and security review.
7. Production-equivalent REST, WebSocket, webhook, database, and Redis capacity/failure testing.
8. DNS/TLS/WAF/rate-limit and DDoS configuration evidence.
9. Monitoring dashboards, actionable alerts, pager/on-call ownership, and incident drills.
10. Provider contracts/credentials and product operational readiness for exposed products.
11. Legal/jurisdiction approval and canonical beneficial-owner data/process for organization cohorts.

FINAL INDEPENDENT VERDICT:
NOT PRODUCTION READY - SOFTWARE AND EXTERNAL GATES REMAIN

RECOMMENDED LAUNCH LEVEL:
LEVEL C

PRIVATE PRODUCTION BETA:
NOT AUTHORIZED

PRODUCTION GA:
NOT AUTHORIZED

UNRESOLVED P0:
0

UNRESOLVED P1:
11

REMAINING SOFTWARE-CONTROLLED PRODUCTION BLOCKERS:
6

REMAINING EXTERNAL/DEPLOYMENT/OPERATIONAL GATES:
11

NEXT ACTION:
Run one targeted remediation phase for the six software P1 blockers, then repeat this audit against a controlled production-like deployment.
