# ExaEarn Developer Platform Level D Readiness Audit

Date: 2026-08-31  
Posture: independent, repository-based, fail closed  
Decision scope: tightly allowlisted individual Private Production Beta

## 1. Executive verdict

**LEVEL D is not authorized. ExaEarn must remain at LEVEL C - Public Sandbox Beta.**

The six previously identified repository controls are materially remediated: webhook dispatch is executable and lease-based, realtime authority networking is narrowly described, terminal Production Access states fail closed, organization Production Access is blocked in backend policy, migration classification no longer labels unknown/raw SQL safe, and a clean PostgreSQL migration rehearsal passes. Focused and full regression also pass.

That is not sufficient evidence for real-money production use. This exact candidate has not run through mandatory CI, network-enabled security scans are unavailable, the production-like realtime/REST path was not exercised, trusted proxy behavior is unverified, restore and rollback drills are absent, deployed monitoring/alert delivery and on-call are unproven, and provider/legal/security-review gates remain open. The working tree is also materially uncommitted, so no GitHub run can attest the exact audited candidate.

Production webhooks remain disabled. Their dispatcher is real, but the HTTP client performs direct pinned delivery while the Kubernetes policy expects an egress proxy; no deployed egress proof exists. Organization Production Access and wallet withdrawal remain blocked.

## 2. Audit boundary and method

All prior Developer Platform audits and completion reports were read as claims. Source, configuration, routes, tests, generated contracts, deployment templates, and available local tooling were independently inspected. No production capability, user, credential, organization, webhook, or withdrawal was enabled.

No target Kubernetes cluster, CDN/WAF, production database/Redis, secret manager, provider account, monitoring platform, pager, backup repository, or legal evidence store was available. Missing evidence is recorded as missing rather than inferred from documentation.

## 3. Software re-verification

| Previous software P1 | Current evidence | Status | Level D impact |
|---|---|---|---|
| CI executability | Commands, PostgreSQL/Redis services, tests, clients, scanner jobs, and fail-closed steps are structurally credible. No actual GitHub/`act` execution exists for this uncommitted candidate. | EXTERNAL VERIFICATION REQUIRED | Blocking delivery evidence |
| Webhook dispatcher | Scheduler and dedicated worker invoke `developer:webhooks:dispatch`; `claimDue()` uses transactional leases and PostgreSQL `SKIP LOCKED`; retry/dead letter and recovery tests pass. | PASS | Production delivery still excluded |
| Realtime authority path | Default deny is retained and exact gateway-to-API TCP/8080 ingress/egress rules exist; internal route requires the node shared secret. No cluster test was run. | SOFTWARE PASS / DEPLOYMENT UNVERIFIED | Blocks production WS |
| Production Access state machine | Generic decisions reject rejected/suspended/revoked states; suspended requests require explicit resume to review; revoke covers second-review capabilities and invalidates effective access. | PASS | Software gate closed |
| PostgreSQL migration safety | Classifier returns 7 DATA_MIGRATION, 1 POSTGRES_REHEARSAL_REQUIRED, 136 REVIEW_REQUIRED, and 2 SAFE_AUTOMATED. Fixtures pass. Fresh PostgreSQL 18.3 rehearsal passes. | PASS | Deployment rehearsal still required per release |
| Beneficial-owner conflict | Both submission and runtime capability checks reject organization projects while `organization_enabled=false`. | BLOCKED BY DESIGN | Safe for individual-only cohort |

No new software P0 was found. No new application P1 was found for a Level C sandbox or an individual-only design. The webhook proxy/client mismatch is a production webhook blocker; because production webhooks remain disabled and are excluded from the proposed cohort, it does not block the rest of the software from re-audit.

## 4. CI and supply-chain evidence

The workflow has no `continue-on-error`. Composer syntax is valid; PostgreSQL and Redis include health checks; migration, focused/full backend, OpenAPI drift, SDK, portal/admin, realtime, Gitleaks, dependency audit, SBOM, CodeQL, image build, and Trivy are mandatory.

CI is not PASS because GitHub CLI/`act` is unavailable, no workflow result was supplied, and the audited remediation is not committed. OpenAPI generation produced 93 paths and the working generated artifact differs from committed `HEAD`; this is expected for an uncommitted remediation but prevents release attestation.

`pnpm audit` and Composer audit were attempted with network escalation. npm registry access timed out/refused and Packagist DNS resolution failed. Gitleaks, Docker, Trivy, Syft, CodeQL runner tooling, and `actionlint` are unavailable locally. A limited tracked-tree credential-pattern scan found no matching high-confidence token/private-key pattern; this is not equivalent to Gitleaks plus history scanning.

## 5. Webhook evidence

Dispatcher, atomic claim, expired-lease recovery, retry, dead letter, stable event identity, signing, project/environment authority, endpoint revalidation, and production feature gates pass focused tests. Production requires both delivery and egress-verification flags, which default false.

Production egress is **DISABLED**. The manifest advertises `WEBHOOK_EGRESS_PROXY`, but `DeveloperWebhookService` does not configure an HTTP proxy; it uses direct cURL address pinning. NetworkPolicy permits the worker only toward the expected proxy. No deployed private-address, metadata, redirect, rebinding, mixed-answer, or public-HTTPS test evidence exists. Do not enable production delivery.

## 6. Realtime and capacity evidence

Node realtime tests pass 8/8. The local harness authenticated 1,000/1,000 sandbox sockets in 2.144 seconds at 84 MB RSS with zero failures. It uses an in-memory authority and exercises neither Laravel authority, Redis fanout, production database, NetworkPolicy, financial events, replay under load, revocation under load, nor slow consumers.

Production-equivalent WebSocket testing: **NOT RUN**. REST capacity, burst, rate-limit, and controlled write capacity: **NOT RUN**. Webhook capacity is not required while production delivery is disabled and was not run.

## 7. Database, Redis, restore, and rollback

A fresh audit-only PostgreSQL 18.3 database completed the full migration chain and was removed afterward. This proves clean migration compatibility, not production lock behavior, pooling, HA, storage, slow-query handling, or rollback compatibility under traffic.

No production-like Redis topology, persistence, eviction, HA, namespace isolation, interruption test, or failure-mode evidence exists. No backup/PITR restore drill was executed, no representative Developer records were restored, and actual RPO/RTO are not measured. No safe application, realtime, worker, or configuration rollback drill is evidenced.

## 8. Observability and incident response

The repository has generic Phase 19 readiness/incident models and process health probes. The Developer manifests do not prove dependency-aware readiness, scrape configuration, dashboards, alert rules, queue/worker watchdogs, pager routing, or tested alerts. No operator receipt test, incident commander, security/compliance escalation roster, or measured emergency exercise was supplied.

Credential revocation, project suspension, Production Access suspension/revocation, realtime invalidation, product capability controls, and disabled production webhooks exist in software. Operational ability to execute and observe these controls in the target environment is unverified.

## 9. Trusted proxy, TLS, DNS, WAF, and deployment

Canonical IP code and unit tests are sound when trusted proxies are configured. The manifest still contains `TRUSTED_PROXIES: REQUIRED_AT_DEPLOYMENT`. There is no deployed test for spoofed forwarding headers, multi-hop IPv4/IPv6, direct-origin access, or consistent IP use across allowlisting, rate limits, security, and logs.

Images, hosts, TLS, secret injection, webhook proxy, CORS/Sanctum origins, WAF, DDoS, request limits, and timeouts are templates or external values rather than verified production configuration. CORS source still always includes localhost origins/patterns and wildcard methods/headers with credentials support; this remains a P2 requiring explicit production risk acceptance or closure.

## 10. Compliance, providers, legal, and independent security

Canonical individual KYC/compliance policy is reused, but live KYC, sanctions, AML/risk, jurisdiction credentials, contracts, callback handling, monitoring, and failure behavior were not demonstrated. Production KYC freshness also defaults to zero days and needs an approved policy.

No evidence was supplied for approved Developer/API terms, Production Beta agreement, jurisdiction/product approval, or required disclosures. Legal status is **APPROVAL REQUIRED**. No independent penetration test, application-security review, or infrastructure review evidence was supplied; penetration testing remains required.

## 11. Product Level D decisions

| Product | Verdict | Basis |
|---|---|---|
| Public Market Data | SANDBOX ONLY | Software path exists; production capacity/SLO not proven |
| Spot Read | SANDBOX ONLY | Candidate launch itself is not authorized |
| Spot Trade | SANDBOX ONLY | Canonical OMS path passes; deployment/ops gates open |
| Futures Read | SANDBOX ONLY | Product and deployment operations not approved |
| Futures Trade | BLOCKED | High-risk product excluded from initial cohort |
| Margin | BLOCKED | High-risk product excluded from initial cohort |
| Wallet Read | SANDBOX ONLY | Production candidate not authorized |
| Wallet Transfer | BLOCKED | No Developer route |
| Wallet Withdraw | BLOCKED | Explicitly unavailable |
| Earn/Staking | BLOCKED | Provider/product operations unproven |
| Convert | SANDBOX ONLY | Canonical software exists; launch gates open |
| ExaPay | BLOCKED | External payment operations unproven |
| Copy Trading | BLOCKED | Restricted product and operations |
| ExaAI | BLOCKED | Restricted product and regulatory configuration |
| WebSockets | SANDBOX ONLY | Production-equivalent path not tested |
| Webhooks | SANDBOX ONLY | Production egress disabled and unverified |

## 12. Remaining P1 gates

### Deployment P1 (6)

1. Execute mandatory CI for a committed immutable candidate and enforce required checks/branch protection.
2. Verify target-cluster realtime authority networking and production-equivalent WS/REST capacity and failure behavior.
3. Verify trusted proxy/origin shielding plus TLS, DNS, WAF, request limits, and production configuration.
4. Execute backup/PITR restore and rollback drills with measured RPO/RTO.
5. Deploy and test monitoring, alert delivery, worker/scheduler health, emergency controls, and on-call response.
6. Verify production PostgreSQL/Redis topology, HA/persistence/pooling, interruption behavior, and secret/config delivery.

### External P1 (4)

1. Complete network-enabled dependency, secret/history, CodeQL, SBOM, and container/image scans with no unaccepted Critical findings and owned High findings.
2. Demonstrate live individual compliance-provider operations for the allowed products.
3. Obtain legal/jurisdiction and Production Beta agreement approval.
4. Complete or formally approve policy for independent penetration/application/infrastructure security testing before limited real-money exposure.

## 13. P2 risk-acceptance candidates

- Production CORS localhost/wildcard policy.
- Nonzero KYC freshness policy.
- Dependency-aware API/realtime readiness and worker hung detection.
- Realtime publish-failure metrics, global connection caps, fanout scaling, sequence concurrency, and jittered reauthorization.
- Webhook payload/error retention and payload-size enforcement.
- SHA-pinned GitHub Actions and immutable tool provenance.
- Live CAPTCHA, security-notification delivery, and public SLO evidence.

Each requires a named owner, expiry, compensating control, and explicit Level D approval; none is silently accepted here.

## 14. Level D matrix

```text
CI EXECUTION: EXTERNAL VERIFICATION REQUIRED
CI SECURITY SCANS: BLOCKED
SECRET SCANNING: FAIL
DEPENDENCY SECURITY: BLOCKED
WEBHOOK DISPATCH: PASS
PRODUCTION WEBHOOK EGRESS: DISABLED
REALTIME AUTHORITY PATH: FAIL
PRODUCTION-EQUIVALENT WS TEST: NOT RUN
REST CAPACITY: NOT RUN
PRODUCTION ACCESS STATE MACHINE: PASS
ORGANIZATION PRODUCTION ACCESS: BLOCKED
INDIVIDUAL PRODUCTION ACCESS: NOT READY
POSTGRES MIGRATION GATE: PASS
POSTGRES MIGRATION REHEARSAL: PASS
TRUSTED PROXY: FAIL
BACKUP: FAIL
PITR: FAIL
RESTORE DRILL: FAIL
RPO/RTO: NOT VERIFIED
MONITORING: PARTIAL
ALERTING: FAIL
ON-CALL: NOT READY
ROLLBACK: NOT VERIFIED
TLS/DNS/WAF: PARTIAL
REDIS: PARTIAL
DATABASE: PARTIAL
COMPLIANCE PROVIDERS: NOT READY
LEGAL/JURISDICTION: APPROVAL REQUIRED
PENETRATION TEST: REQUIRED
```

## 15. Independent validation results

```text
Focused Developer/P1: 73 passed, 0 failed, 2035 assertions
Full backend: 597 passed, 0 failed, 1 skipped, 4886 assertions
PostgreSQL fresh migration rehearsal: PASS
Migration classifier fixtures/config policy: PASS
Node realtime: 8 passed, 0 failed
Local WS harness: 1000/1000 authenticated, 2.144 s, 84 MB RSS
TypeScript SDK typecheck: PASS
Python SDK: 6 passed
Developer Portal typecheck/build: PASS/PASS
Admin typecheck/lint/build: PASS/PASS/PASS
OpenAPI generation: 93 paths; candidate not committed/CI-attested
Dependency audits: BLOCKED by registry/network access
Gitleaks/CodeQL/Trivy/SBOM validation: NOT RUN, tooling unavailable
Production-equivalent WS/REST: NOT RUN
Restore/PITR/rollback drills: NOT RUN
```

The backend skip is the existing environment-dependent test. Four PHPUnit doc-comment metadata deprecation warnings remain nonblocking.

## 16. Exact cohort recommendation

No real-money cohort is authorized yet. Continue public Sandbox Beta only. After all P1 evidence closes, reconsider an individual-only, manually allowlisted cohort with Spot read/trade and Wallet read at low limits; exclude organizations, withdrawals, production webhooks, Futures, Margin, staking, ExaPay, Copy Trading, and ExaAI initially.

FINAL LEVEL D VERDICT:
NOT AUTHORIZED

RECOMMENDED LAUNCH LEVEL:
LEVEL C

UNRESOLVED SOFTWARE P0:
0

UNRESOLVED SOFTWARE P1:
0

UNRESOLVED DEPLOYMENT P1:
6

EXTERNAL P1 GATES:
4

ORGANIZATION PRODUCTION ACCESS:
BLOCKED

INDIVIDUAL PRODUCTION ACCESS:
BLOCKED

PRODUCTION WEBHOOKS:
DISABLED

WALLET WITHDRAW:
BLOCKED

PRODUCTION GA:
NOT AUTHORIZED

CONTROLLED LEVEL D COHORT:
NONE

NEXT ACTION:
Commit an immutable candidate and close the CI/scanner, production-like network/capacity, trusted-proxy, restore/rollback, monitoring/on-call, provider, legal, and independent-security evidence gates.
