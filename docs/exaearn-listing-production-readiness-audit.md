# ExaEarn Listing Portal Final Production-Readiness Audit

Date: 2026-09-01  
Audit posture: repository-based, fail closed, no production logic changed

## Executive Summary

**Overall readiness classification: `NO_GO`.**

The applicant-facing foundation is materially implemented. Listing signup creates canonical ExaEarn users; organizations and recipient-bound membership exist; tenant checks are enforced in services; organization KYB is structurally separated from user identity and asset due diligence; applicant documents are private, versioned, and checksum-bound; and application approval does not immediately enable deposits, withdrawals, or trading.

The production activation chain is not trustworthy enough to authorize a real listing. The strongest blocker is that `ListingLifecycleService::testResults()` labels important controls as passing without executing them: canonical ledger is hardcoded `PASS`; matching, partial-fill, cancel, market-data, and OMS checks infer success from market-record existence; and deposit/withdrawal tests are `NOT_APPLICABLE`. Scheduled deposit activation checks contract-validation rows only, while withdrawal activation checks only that trading is live. Neither proves custody, signing, RPC, reconciliation, fee, security, address-control, or withdrawal-risk readiness. These are software-controlled launch-integrity defects.

Launch automation is also incomplete: due events can be processed through an admin HTTP endpoint, but no Listing command, scheduled task, or queue worker invokes `processDueLaunchEvents()`. The generic Admin Listing Center reads real overview/application data, but its advertised actions post to a nonexistent generic action endpoint and therefore fail. Post-listing monitoring and delisting are primarily tables/status constants, not complete operational workflows.

The three KYB provider classes reported by the application are correctly `NOT_CONNECTED`, but there are no normalized provider adapters with production retry/timeout/webhook/error semantics. Production SSO, domains, cookies, object storage, email, queues, backup, monitoring, browser QA, capacity, custody credentials, RPC, liquidity counterparties, and staffing remain unverified deployment or operational dependencies.

## Architecture Map

| Layer | Canonical implementation | Audit result |
|---|---|---|
| Identity | `User`, Sanctum/session auth, canonical register/reset/verification | `PARTIAL` |
| Applicant UI | `apps/listing` React/Vite application | `PARTIAL` |
| Organization | `ListingOrganization`, `ListingTeamMember` | `PRODUCTION_READY` for software scope |
| Organization KYB | `ListingKybService` and KYB domain tables | `PARTIAL` |
| Application | `ListingPortalController`, `ListingLifecycleService` | `PARTIAL` |
| Internal review | `ListingCenterController`, Listing review tables | `PARTIAL` |
| Asset identity | `ListingAssetConfiguration` linked to `BlockchainAsset` | `PARTIAL` |
| Networks/custody | `BlockchainNetwork`, `BlockchainAsset`, custody services | `EXTERNAL_DEPENDENCY` for production |
| Markets | `ListingMarketConfiguration` linked to canonical `Market` | `PARTIAL` |
| Launch | schedule/event tables and lifecycle service | `BROKEN` for unattended production |
| Monitoring/delisting | alert table and status constants | `MISSING` as complete workflows |

No `listing_users`, Listing-specific password store, shadow ledger, or second matching engine was found.

## End-to-End Lifecycle Trace

| Transition | Owner and control | Evidence | Result |
|---|---|---|---|
| Visit -> login/signup | Listing React app -> canonical `/api/login`, `/api/register` | Auth controller and Phase 15A tests | `PARTIAL`: production SSO unverified |
| Identity -> organization | `ListingPortalController::createOrganization` | canonical `owner_user_id`, OWNER membership, audit | `PRODUCTION_READY` |
| Join organization | `ListingKybService::invite/acceptInvitation` | hashed token, recipient binding, expiry, row lock | `PRODUCTION_READY` |
| Organization -> KYB | ten-stage React flow and `ListingKybService` | focused KYB tests | `PARTIAL`: providers/reverification missing |
| KYB -> decision | Admin `kybDecision` | state map, audit, manual evidence fallback | `PARTIAL`: coarse reviewer permission/conflict controls |
| KYB approved -> listing application | `saveDraft`, `submit` | OWNER/ADMIN submit and approved-KYB gate | `PARTIAL`: completion only checks non-empty sections |
| Review -> approval | reviews, recommendation, distinct approver | four review types required and maker/checker | `PARTIAL`: specialist RBAC and concurrent decision locking absent |
| Approval -> asset configuration | `createAssetConfiguration` | canonical `BlockchainAsset`, capabilities disabled | `PARTIAL`: verification source is incomplete |
| Asset -> network/market | network and market configuration methods | canonical records begin disabled/pre-launch | `PARTIAL` |
| Integration -> tests | `runListingTests` | persisted test run | `SECURITY_BLOCKER`: synthetic PASS values |
| Tests -> schedule | final recommendation plus distinct approver | schedule transaction and events | `PARTIAL` |
| Schedule -> activation | `processDueLaunchEvents`, `launch`, open methods | idempotency keys and row locks | `SECURITY_BLOCKER`: no runtime trigger; insufficient readiness checks |
| Live -> monitor/suspend/delist | emergency method and state constants | pause controls only | `MISSING`: no complete monitoring/delisting lifecycle |

## Production Blockers

### P0 - Must Fix Before Any Production Listing

1. **Synthetic launch certification (`SECURITY_BLOCKER`).** `testResults()` does not execute canonical ledger, OMS, matching, partial fill, cancel, market-data, developer API, WebSocket, or cache tests. Several results are hardcoded or inferred from row existence.
2. **Unsafe deposit activation gate (`SECURITY_BLOCKER`).** `openDeposits()` requires only absence of blocking contract-validation rows. It does not require custody/RPC health, address derivation, confirmation policy verification, reconciliation, scanner/analytics clearance, or production credential readiness.
3. **Unsafe withdrawal activation gate (`SECURITY_BLOCKER`).** `openWithdrawals()` requires only `integration_status === LIVE`; it does not prove signer/custody health, fees, limits, liquidity, withdrawal risk, address controls, reconciliation, or maker-checker approval.
4. **Unscanned documents can participate in approval (`SECURITY_BLOCKER`).** Malware scanning defaults to `NOT_CONNECTED`; submission requires document types but not a clean scan. Manual evidence covers registry/screening, not malware safety.

### P1 - Must Fix Before General Production

1. Add a durable scheduled/queued launch-event dispatcher with retries, leases, dead-letter handling, metrics, and restart tests.
2. Build a dedicated Listing Operations UI; current generic actions call an unsupported endpoint and cannot operate the real workflow.
3. Replace coarse `listing.manage` authorization with specialist review/operations permissions and separation of duties.
4. Add row locking/version checks for KYB decisions, reviews, approval, scheduling, and emergency transitions.
5. Validate required application fields semantically; `{ "field": "" }` and incomplete nested objects can satisfy section completion.
6. Add optimistic concurrency/versioning for draft autosave to prevent lost updates.
7. Connect normalized registry, screening, and malware interfaces or enforce explicit controlled manual paths for each.
8. Add KYB reverification scheduling for expiry, ownership/director/regulatory changes, periodic review, and rescreening.
9. Implement real information-request response/attachment status workflow and applicant/internal notifications.
10. Implement post-listing monitoring signal ingestion, alert ownership, escalation, and fail-safe actions.
11. Implement a safe delisting sequence including withdrawal-only windows, deadlines, remaining balances, notifications, and history retention.
12. Validate production SSO/CORS/cookie configuration and remove unconditional development origins in production.

### P2 - Should Fix Shortly After

- Add applicant/member management UI for invitations, suspension, role changes, and ownership transfer.
- Add document replacement/supersession, expiry jobs, retention, export/deletion policy, and secure object-storage streaming.
- Add queue/provider health views, SLA assignment, reviewer workload, and incident correlation.
- Add pagination limits and query indexes based on production review-queue cardinality.
- Add accessibility and automated responsive-browser coverage.
- Replace raw object rendering in Admin with purpose-built evidence views and PII minimization.

### P3 - Hardening

- Migrate PHPUnit doc-comment metadata before PHPUnit 12.
- Add executable browser journey documentation and usability analytics.
- Normalize dense one-line KYB service/controller code for maintainability.

## Security Findings

- `SECURITY_BLOCKER P0`: capability activation is not tied to real custody/withdrawal readiness.
- `SECURITY_BLOCKER P0`: unscanned applicant evidence is downloadable and can support decisions.
- `PARTIAL P1`: applicant tenant checks are strong in tested routes, but no comprehensive BOLA matrix covers every message/review/attachment mutation.
- `PARTIAL P1`: all Listing internal duties share `listing.manage`; specialist least privilege is not enforced.
- `PARTIAL P2`: original filenames and extensive KYB/application objects enter audit/admin views; retention and data-minimization policy are absent.
- No hardcoded Listing provider credentials were found. Production `.env.example` values are development defaults and must not be deployed unchanged.

## Authentication / SSO Findings

Canonical identity, password hashing, verification, recovery, MFA-aware login, session logout, and credential cookies are reused. The Listing frontend does not store passwords or session tokens in local/session storage and uses `credentials: include`.

Production SSO is `PRODUCTION_CONFIG_REQUIRED P1`: `SESSION_DOMAIN`, secure cookies, intended `SameSite`, `SANCTUM_STATEFUL_DOMAINS`, `FRONTEND_URL`, Listing URL, TLS, and CSRF behavior are environment-owned. CORS merges localhost origins/patterns unconditionally and allows wildcard methods/headers with credentials.

## Organization / RBAC Findings

OWNER, ADMIN, CONTRIBUTOR, and VIEWER are enforced server-side. OWNER/ADMIN submit KYB/listings; contributors edit drafts/documents; viewers read. Invitations are hashed, expiring, replay-resistant, recipient-bound, and locked during acceptance. Ordinary member updates cannot demote the owner; ownership transfer requires recent authentication and a canonical active target.

Residuals: invitation delivery depends on email; no invitation revocation UI was found; member-change concurrency is incompletely tested; and organization status itself is not consistently evaluated in every service method.

## KYB Findings

The ten-stage model and persisted progress exist. Editing is blocked after submission except information-request/reverification states. UBO totals over 100 are rejected. Declarations are policy-versioned and evidence hashes are recorded.

`PARTIAL P1`: completeness is presence-based, UBO threshold configuration is not used to establish required owners, documents need not be scanned/approved/unexpired, and provider results are not produced by adapters. Approved KYB can remain indefinitely because no periodic/expiry/change-triggered jobs exist. Admin review supports visible/private notes and manual evidence but not reviewer conflicts or dual approval for high-risk organizations.

## External Provider Findings

| Dependency | Purpose | Adapter | Selected/configured/tested | Failure mode | Blocks launch |
|---|---|---|---|---|---|
| Company registry | Entity verification | No normalized adapter found | No/No/No | `NOT_CONNECTED`; manual evidence | Yes for automated KYB |
| Sanctions/PEP/adverse media | Person/org screening | No normalized adapter found | No/No/No | `NOT_CONNECTED`; manual evidence | Yes |
| Malware scanner | Evidence safety | Config name only | No/No/No | `NOT_CONNECTED`; upload retained | Yes |
| Blockchain analytics | Contract/address risk | Listing validation service is not a provider integration | Not proven | manual/operational status | Yes for production assets |
| Custody/signing | Deposits/withdrawals | Canonical custody interfaces exist | Production provider/credentials unverified | capability should fail closed | Yes |
| RPC/nodes | Chain validation/monitoring | Canonical provider interfaces exist | Production endpoints unverified | operational setup required | Yes |
| Market data | Tickers/order books | Canonical market services exist | New-asset live source unverified | should remain unavailable | Yes for trading |
| Market makers | Launch liquidity | Requirement records exist | Contracts/capital/live connectivity unverified | `LIQUIDITY_PENDING` unless manually set | Yes |
| Email | verification/invitations/notices | Laravel notification | `.env.example` uses log | delivery absent | Yes for operations |
| Object storage | KYB/listing documents | Laravel local disk | production private bucket unverified | local-node dependency | Yes |
| Monitoring/on-call | alerting/incident response | generic Phase 19 components | Listing wiring unproven | raw logs/manual inspection | Yes |

## Listing Application Findings

Structured React sections serialize to existing JSON columns and draft idempotency updates existing drafts. Raw JSON editors were not found. However, frontend required-field completion and backend section completion differ: backend submission checks only whether each top-level JSON section is non-empty. There is no schema-versioned payload contract, nested semantic validation, optimistic locking, autosave timer, draft conflict UI, or tested multi-editor behavior.

## Document Security Findings

Private local storage, MIME/size validation, UUIDs, version numbers, SHA-256 checksums, hidden paths, and tenant-authorized downloads exist. Remaining gaps are malware enforcement, object-storage production policy, content-disposition hardening evidence, expiry processing, supersession, retention/deletion/export, backup, and comprehensive unsafe-file adversarial tests.

## Admin / Reviewer Findings

Backend endpoints cover queues, reviews, recommendation, approval, asset/network/market setup, testing, scheduling, emergency controls, and audit views. Every controller operation resolves an Admin with `listing.manage`, but distinct legal/compliance/security/technical/liquidity/launch roles are absent.

The Admin frontend is `BROKEN P1` for operations: it fetches overview/applications but does not fetch KYB queues or render workflow-specific evidence. Its generic Listing actions post to `/listing-center/actions`, for which no route exists, and return a failure message. APIs alone are not a usable operations center.

## Asset Due-Diligence Findings

Applicant declarations and risk flags cover upgradeability/mint/freeze concepts. `ListingContractValidation` stores submitted and validated metadata, but the validator primarily compares supplied metadata and operational availability; no production blockchain analytics/RPC evidence was established. Contract existence, proxy implementation, ownership, holder/deployer concentration, token migration, supply behavior, and sanctions exposure are therefore `PARTIAL` or `EXTERNAL_DEPENDENCY`.

## Asset Registry, Custody, Ledger, Markets, Trading, and Liquidity

- **Asset registry (`PARTIAL`)**: Listing creates canonical `BlockchainAsset` and prevents duplicate configured contracts. Symbol uniqueness across all canonical consumers is not fully established.
- **Custody (`EXTERNAL_DEPENDENCY`)**: canonical custody services exist, but production providers/credentials, address generation, sweeps, signing, and reconciliation are not proven for a newly listed asset.
- **Ledger (`PARTIAL`)**: no Listing shadow wallet exists, but Listing's pre-launch test hardcodes canonical-ledger PASS. End-to-end listed-asset deposit/withdraw/trade accounting is not executed by this gate.
- **Market data (`PARTIAL`)**: canonical Market records start with zero price/pre-launch. No fabricated launch price is written, but live source freshness and symbol mapping are not validated by Listing tests.
- **Trading (`PARTIAL`)**: canonical Market records and engine fields are used; production matching behavior is inferred, not executed.
- **Liquidity (`PARTIAL`)**: requirements are persisted and must say `READY`, but the status can be supplied during market creation without proof of live capital, spread/depth, or market-maker connectivity.

## Maker-Checker and Launch Findings

Recommendation and approval require distinct admins, as do final recommendation and scheduling. This is meaningful but incomplete: all actors can hold the same broad permission, records are not consistently locked, and scheduled deposit/withdrawal capability changes do not receive their own dual approval.

Capability flags are separate (`deposit_enabled`, `withdrawal_enabled`, `trading_enabled`, market status), proving that approval alone does not activate everything. Launch remains `BROKEN`: no scheduler invokes due events, event processing is one transaction over all due rows, and failure/retry/dead-letter/partial-success behavior is inadequate. Manual `launch()` changes application, asset, and market records transactionally, but does not activate deposits/withdrawals.

## Monitoring and Delisting Findings

`listing_monitoring_alerts` exists but no Listing monitoring producer/service/job was found. Contract upgrades, supply anomalies, liquidity deterioration, market-maker downtime, regulatory events, and token migrations are not continuously evaluated by the Listing domain.

DELISTING states are declared but no route/service implements the full transition. There is no withdrawal-only period, deadline, user-balance resolution, notification plan, market archival workflow, or maker-checker delisting decision. Status names do not constitute a production delisting system.

## API, Database, Concurrency, Queue, and Cache Findings

- Listing applicant routes require authenticated Sanctum sessions and rate limits; sensitive ownership transfer also requires recent authentication.
- Tenant access is service-enforced and focused cross-org tests pass.
- Controller validation limits upload MIME and size, but semantic business validation remains shallow.
- Core tables have foreign keys and important uniqueness constraints.
- Invitation acceptance and due-event selection use row locks; asset/network/market creation use transactions.
- Draft updates, reviewer decisions, approval, and emergency controls lack optimistic versioning/consistent row locks.
- No Listing queue job, scheduler, backoff, dead-letter workflow, or worker-health requirement was found.
- No Listing-specific authorization cache was found; Redis failure behavior for broader dependencies remains a deployment test.

## Observability and Audit Logging

Listing mutations generally create `listing_audit_logs` with actor, before/after, reason, IP, and user agent. The logs are mutable database rows rather than cryptographically immutable evidence, and may retain broad PII snapshots. Request IDs exist in frontend errors, but Listing-specific metrics, provider health, queue lag, launch-event lag/failure alerts, dashboards, pager ownership, and incident correlation were not found.

## Notifications

Email verification/password reset and organization invitations use existing notifications. No authoritative notification flow was found for KYB requests/decisions, listing submission, information-request response, review assignment, integration failure, launch approval, emergency suspension, or delisting. State transitions do not depend on delivery, which avoids transactional corruption, but operational awareness is incomplete.

## Testing Findings

Current repository evidence from 2026-09-01: focused KYB/Phase 15A tests passed (11 tests, 179 assertions); full backend passed (605 passed, 0 failed, 1 skipped, 4,973 assertions); Listing typecheck and production build passed.

The single skip is `ProfileIdentityTest` when GD WebP support is unavailable and is not a Listing lifecycle test. Critical missing Listing coverage includes production SSO, semantic completion, draft conflicts, concurrent reviews/approvals, malware enforcement, real custody/ledger/matching, scheduler/worker restart, provider timeouts, partial launch failure, monitoring, and delisting. No production-equivalent load test was found.

## Frontend / Responsive QA

The Listing portal has responsive CSS, loading/error states, timeout handling, and a structured KYB/application UI. There is no automated browser/device evidence for 375x812, 390x844, 393x852, 430x932, 768, 1024, 1280, or 1440. Classification: `PRODUCTION_CONFIG_REQUIRED P2` (production browser validation). Test sign-in expiry, keyboard/focus, file upload, long translated content, mobile wizard navigation, draft recovery, and API partial failures at each viewport.

## Privacy / Data Governance

KYB stores directors, UBOs, dates of birth, addresses, regulatory data, documents, and reviewer evidence. Access controls exist, but retention, legal hold, deletion, subject export, encryption-at-rest proof, private bucket lifecycle, audit-log minimization, and reviewer access recertification are not implemented or verifiable. Legal/privacy review is required; this audit provides no legal certification.

## Manual vs Automated Controls

| Control | Classification |
|---|---|
| Tenant authorization | `AUTOMATED_ENFORCED` |
| KYB completeness | `AUTOMATED_ENFORCED` but presence-based |
| Registry/sanctions/PEP/adverse media | `MANUAL_ENFORCED` while disconnected |
| Malware screening | `NOT_IMPLEMENTED` as enforcement |
| Contract verification | `ADVISORY_ONLY` / partial record checks |
| Four review categories | `AUTOMATED_ENFORCED` by recorded statuses |
| Liquidity readiness | `MANUAL_ENFORCED` database status |
| Maker-checker | `AUTOMATED_ENFORCED` for two decision stages only |
| Launch readiness | `ADVISORY_ONLY` because synthetic tests feed the gate |
| Deposit/withdraw capability | `AUTOMATED_ENFORCED` flags, unsafe readiness criteria |
| Post-listing monitoring | `NOT_IMPLEMENTED` |
| Delisting | `NOT_IMPLEMENTED` |

## Failure-Injection Assessment

- KYB/scanner outage: records become `NOT_CONNECTED`; manual approval can proceed, leaving malware unaddressed.
- RPC/custody outage: not checked by deposit/withdraw launch events; unsafe capability activation is possible.
- Market maker disconnect: persisted `READY` can remain stale; no live revalidation is evident.
- Database failure: transactions protect several grouped writes; some events may remain pending, but no automatic retry worker exists.
- Queue/notification failure: invitation/notifications may be lost; no Listing delivery recovery is defined.
- Duplicate event request: event status/idempotency keys reduce repeat execution; manual concurrency around launch/decisions still needs production-database tests.
- Partial external activation: database transactions cannot roll back external custody/RPC/provider side effects; no saga/reconciliation design was found.

## Critical Invariants

| Invariant | Verdict | Evidence |
|---|---|---|
| Listing signup creates canonical users | `PROVEN` | canonical `/register`, `users`, test |
| Authentication does not imply organization access | `PROVEN` | `assertOrganizationAccess`, cross-org tests |
| User KYC does not imply organization KYB | `PROVEN` | separate KYB tables/status/gate |
| KYB approval does not imply asset approval | `PROVEN` | separate application state |
| Listing approval does not imply activation | `PROVEN` | asset/market initially disabled/pre-launch |
| Asset integration does not imply deposits | `PROVEN` | capability defaults false |
| Deposits do not imply withdrawals | `PROVEN` | independent events/flags |
| Market creation does not imply trading | `PROVEN` | pre-launch status |
| Trading activation requires explicit launch controls | `PARTIALLY_PROVEN` | explicit schedule/launch, but synthetic checks |
| One person cannot bypass maker-checker | `PARTIALLY_PROVEN` | distinct IDs at two stages, coarse role and concurrency gaps |
| Tenant isolation | `PARTIALLY_PROVEN` | strong service checks/focused tests, incomplete exhaustive matrix |
| Disconnected providers cannot produce fake CLEAR | `PROVEN` for KYB fields | forced `NOT_CONNECTED`; manual approval is separately evidenced |

## Readiness Matrix

| Capability | Status | Severity | Evidence | Remaining work | Blocks production |
|---|---|---:|---|---|---|
| Canonical identity | `PARTIAL` | P1 | shared User/Auth | production SSO validation | Yes |
| Organization/RBAC | `PRODUCTION_READY` | P2 | service checks/tests | concurrency/UI hardening | No |
| Tenant isolation | `PARTIAL` | P1 | cross-org tests | exhaustive BOLA suite | Yes |
| KYB software | `PARTIAL` | P1 | ten-stage service/UI | semantic rules/reverification | Yes |
| KYB operations | `EXTERNAL_DEPENDENCY` | P0 | providers disconnected | providers/staff/runbooks | Yes |
| Document security | `SECURITY_BLOCKER` | P0 | scan `NOT_CONNECTED` | enforce clean scan/quarantine | Yes |
| Application workflow | `PARTIAL` | P1 | structured UI/service | semantic schema/conflicts | Yes |
| Internal operations UI | `BROKEN` | P1 | generic actions fail | dedicated UI/endpoints | Yes |
| Due diligence | `PARTIAL` | P1 | declarations/validation rows | real verification | Yes |
| Asset registry | `PARTIAL` | P1 | canonical BlockchainAsset | uniqueness/production proof | Yes |
| Custody | `EXTERNAL_DEPENDENCY` | P0 | interfaces only for audit scope | production integration evidence | Yes |
| Ledger | `PARTIAL` | P0 | synthetic gate | executable invariants | Yes |
| Market data | `PARTIAL` | P1 | canonical Market | live source/freshness test | Yes |
| Trading | `PARTIAL` | P0 | synthetic matching gate | executable engine test | Yes |
| Liquidity | `PARTIAL` | P1 | manual READY status | live capital/quality checks | Yes |
| Maker-checker | `PARTIAL` | P1 | distinct admin IDs | specialist roles/locking | Yes |
| Launch automation | `BROKEN` | P0 | no scheduler | durable dispatcher/recovery | Yes |
| Emergency controls | `PARTIAL` | P1 | pause method | granular governance/UI | Yes |
| Monitoring | `MISSING` | P1 | table only | producers/alerts/runbooks | Yes |
| Delisting | `MISSING` | P1 | constants only | complete lifecycle | Yes |
| API security | `PARTIAL` | P1 | auth/rate/validation | CORS/BOLA/adversarial suite | Yes |
| Database integrity | `PARTIAL` | P1 | FKs/unique/transactions | locking/versioning/PG races | Yes |
| Observability | `PARTIAL` | P1 | audit logs | metrics/alerts/correlation | Yes |
| Frontend QA | `PRODUCTION_CONFIG_REQUIRED` | P2 | build/typecheck only | browser/device QA | Yes |
| Backup/retention | `OPERATIONAL_DEPENDENCY` | P1 | no Listing restore evidence | restore/retention proof | Yes |

## External Setup Checklist

- [ ] Production domain and TLS configured.
- [ ] Cross-subdomain Sanctum/CSRF/cookie behavior validated.
- [ ] Production CORS restricted to approved origins.
- [ ] Approved jurisdictions and legally approved policy versions populated.
- [ ] Registry, sanctions/PEP/adverse-media, and malware providers connected and tested.
- [ ] Blockchain analytics and production RPC configured.
- [ ] Custody/signing production credentials and network runbooks validated.
- [ ] Private object storage, encryption, lifecycle, backup, and restore validated.
- [ ] Production email delivery and bounce monitoring validated.
- [ ] Market-maker contracts, capital, connectivity, and fallback arrangements operational.
- [ ] Market data source and stale-data alerting operational.
- [ ] Queue workers, scheduler, failed jobs, and alerting deployed.
- [ ] Listing, compliance, legal, security, technical, custody, liquidity, risk, and launch staff assigned.
- [ ] Launch, rollback, provider-outage, custody-outage, incident, and delisting runbooks approved and rehearsed.
- [ ] Browser/device QA and accessibility review completed.
- [ ] PostgreSQL concurrency and production-equivalent load/failure tests completed.
- [ ] Backup/PITR and document restore drill completed.
- [ ] Privacy retention/deletion/export and reviewer-access policies approved.
- [ ] Independent application/security assessment completed.

## Remediation Plan

**Wave 1 - P0 integrity:** replace synthetic tests with executable adapters/invariants; hard-gate custody/deposit/withdrawal readiness; quarantine unscanned evidence; add failure-safe launch orchestration.

**Wave 2 - Governance:** specialist internal RBAC, reviewer conflicts, row locking/versioning, semantic schemas, KYB reverification, and complete information requests.

**Wave 3 - Operations:** dedicated Admin Listing Center, scheduler/workers, notifications, observability, monitoring, incident response, and delisting.

**Wave 4 - External production integration:** KYB/scanner/analytics, custody/RPC, market data, liquidity, storage, email, legal policies, and staffing.

**Wave 5 - Validation:** PostgreSQL races, end-to-end accounting/trading, provider failure injection, browser/device/accessibility, load, backup/restore, and independent security review.

Do not begin these waves from this audit document; each needs a separately approved implementation scope.

## Scorecard

| Area | Score |
|---|---:|
| Applicant UX | 76 |
| Authentication | 78 |
| Authorization/RBAC | 70 |
| Organization Management | 80 |
| KYB Software | 68 |
| KYB Operational Readiness | 20 |
| Compliance Controls | 48 |
| Document Security | 52 |
| Listing Workflow | 65 |
| Internal Review Operations | 38 |
| Asset Due Diligence | 35 |
| Asset Integration | 55 |
| Custody Readiness | 25 |
| Ledger Readiness | 40 |
| Market Data | 48 |
| Trading Integration | 45 |
| Liquidity Readiness | 35 |
| Maker-Checker | 62 |
| Launch Controls | 30 |
| Monitoring | 18 |
| Delisting | 12 |
| API Security | 67 |
| Database Integrity | 66 |
| Observability | 38 |
| Testing | 58 |
| Frontend/Responsive QA | 55 |
| Operational Readiness | 22 |

**Software readiness: 57/100**  
**Operational readiness: 22/100**  
**Overall production readiness: 40/100**

## Final Go/No-Go Decision

**NO_GO**

Material software/security/integrity blockers remain in launch certification, custody capability activation, malware enforcement, launch runtime, internal operations, monitoring, and delisting. External and operational dependencies are substantial, but they are not the only reason for the decision.

## Concise Summary

EXAEARN LISTING PORTAL AUDIT

OVERALL DECISION:  
NO_GO

SOFTWARE READINESS:  
57/100

OPERATIONAL READINESS:  
22/100

OVERALL PRODUCTION READINESS:  
40/100

P0 BLOCKERS:  
4

P1 BLOCKERS:  
12

EXTERNAL DEPENDENCIES NOT OPERATIONAL:  
11

SECURITY BLOCKERS:  
4

PRODUCTION CONFIGURATION ITEMS:  
8

MANUAL/OPERATIONAL REQUIREMENTS:  
10

TOP 10 REMAINING ACTIONS:

1. Replace synthetic Listing test results with executable canonical integration tests.
2. Hard-gate deposits on production custody, RPC, address, reconciliation, and risk readiness.
3. Hard-gate withdrawals on signer, fee, limit, security, liquidity, and maker-checker readiness.
4. Quarantine documents until an approved malware result or controlled safe-review process exists.
5. Implement a durable, observable, restart-safe launch-event dispatcher.
6. Build the real specialist Admin Listing Operations Center and least-privilege roles.
7. Connect and production-test KYB, screening, registry, analytics, and scanner providers.
8. Implement post-listing monitoring and a fund-safe delisting lifecycle.
9. Validate production SSO/CORS/cookies, storage, email, queues, monitoring, and backup/restore.
10. Run PostgreSQL concurrency, end-to-end financial, browser/device, load, and failure tests.

MOST IMPORTANT CONCLUSION:  
The Listing Portal has a credible applicant and governance foundation, but its current readiness records can certify integrations that were never executed and its scheduled financial capability activation is not tied to proven custody/risk readiness; real asset launch must remain disabled.
