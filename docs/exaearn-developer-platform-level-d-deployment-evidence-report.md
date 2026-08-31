# ExaEarn Developer Platform Level D Deployment Evidence Report

Date: 2026-08-31  
Candidate: `exaearn-developer-level-d-rc.1`  
SHA: `1a340b7b5a0f33d9aa42286f8d3d9621de7cf18e`

## Executive result

RC1 is an immutable, tagged candidate, but it failed mandatory CI before any job was scheduled and was not promoted to a production-like environment. Level D evidence collection therefore stops fail closed. This report does not authorize Private Production Beta.

## Candidate identity

The commit and annotated tag were pushed to `origin`. OpenAPI SHA-256 is `55e360344179b3c37d52133828c0fe19d58f03bd069fe30342255f4ef875b36e`; migration-set SHA-256 is `46569774c42d9f41df0ff13c8dbca04450d6fe3207b66b9329a78ac22f9ecc26`. Lockfile hashes and the full identity record are in `docs/level-d-evidence/release-candidate.md`.

No image was built, so no image digest exists. No environment was deployed.

## CI and security

GitHub workflow runs `33350901543` and `33350898028` both failed with zero jobs. Pinned `actionlint 1.7.12` reproduced a YAML parse failure at `.github/workflows/developer-platform-gates.yml:131`; unquoted GitHub expressions occur inside flow mappings for container image tags. This is a newly discovered software P1 in RC1. Consequently no mandatory backend, migration, contract, SDK, frontend, Gitleaks, dependency, CodeQL, SBOM, image, or Trivy job executed.

GitHub reported 222 Dependabot vulnerabilities: 2 Critical, 83 High, 120 Moderate, and 17 Low. No accepted Critical or owned High risk record was supplied. Dependency security fails.

## Tests retained as local evidence

The exact source set, before the identity-only Git ignore addition and freeze, produced:

```text
Focused Developer/P1: 73 passed / 2035 assertions
Full backend: 597 passed / 1 skipped / 4886 assertions
Node realtime: 8 passed
Local in-memory WS auth: 1000/1000
Developer typecheck/build: PASS/PASS
Admin typecheck/lint/build: PASS/PASS/PASS
TypeScript SDK: PASS
Python SDK: 6 passed
OpenAPI generation: 93 paths
Fresh PostgreSQL 18.3 migrations: 146 passed
```

These results support software quality but cannot replace failed release CI or production-like deployment evidence.

## Deployment and capacity

Docker, Kubernetes tooling, scanner tooling, and a controlled cluster were unavailable. Manifests retain placeholder image registry, release SHA, trusted proxies, secret references, and external webhook proxy assumptions. Production configuration, trusted proxy, CORS/cookies, realtime authority networking, TLS/DNS/WAF, PostgreSQL/Redis operational settings, and failure behavior were not verified.

Production-equivalent WebSocket and REST capacity were not run. The 1K local harness uses an in-memory authority and is not accepted as Level D capacity evidence.

## Recovery and operations

No production-like backup, PITR, restore, RPO/RTO, rollback, monitoring, alert delivery, on-call, failure-injection, or incident-response drill was executed. Repository documentation is not substituted for operational evidence.

## Product recommendation

No production product is authorized from RC1. Continue Level C Public Sandbox Beta. Keep organizations, wallet withdrawal, production webhooks, Futures, Margin, Earn/Staking, ExaPay, Copy Trading, and ExaAI blocked or sandbox-only. Public Market Data and Spot may be reconsidered only after a new candidate passes all mandatory CI and the required production-like evidence program.

## Remaining gates

1. Remediate the proven workflow YAML failure and create RC2; do not alter RC1.
2. Triage/fix or formally accept all security findings; no unaccepted Critical is permitted.
3. Produce signed SBOMs and scanned immutable image digests.
4. Deploy RC2 into a controlled production-like stack with real PostgreSQL, Redis, workers, scheduler, API, realtime, ingress, NetworkPolicy, and secret injection.
5. Complete proxy/origin/TLS/WAF, realtime/REST capacity, webhook dispatch, database/Redis failure, backup/PITR/restore, rollback, monitoring/alert, incident, provider, independent-security, and legal evidence.

## Final evidence matrix

```text
IMMUTABLE RELEASE CANDIDATE: PASS
CI EXECUTION: FAIL
CI SECURITY SCANS: BLOCKED
SECRET SCAN: FAIL
DEPENDENCY SCAN: FAIL
CONTAINER SCAN: FAIL
SBOM: FAIL
TRUSTED PROXY: FAIL
CORS/COOKIE/ORIGIN: FAIL
REALTIME AUTHORITY PATH: FAIL
PRODUCTION-EQUIVALENT WEBSOCKET: FAIL
REST CAPACITY: FAIL
WEBHOOK DISPATCH: FAIL
PRODUCTION WEBHOOK EGRESS: DISABLED
POSTGRES MIGRATION REHEARSAL: FAIL
DATABASE READINESS: FAIL
REDIS READINESS: FAIL
BACKUP: FAIL
PITR: FAIL
RESTORE: FAIL
RPO: NOT VERIFIED
RTO: NOT VERIFIED
ROLLBACK: FAIL
FAILURE TESTING: FAIL
MONITORING: FAIL
ALERTING: FAIL
ON-CALL: NOT READY
INCIDENT RESPONSE: FAIL
PENETRATION TEST: REQUIRED
PROVIDER READINESS: FAIL
LEGAL/JURISDICTION: APPROVAL REQUIRED
ORGANIZATION PRODUCTION: BLOCKED
WALLET WITHDRAW: BLOCKED
```

## Phase status

```text
LEVEL D DEPLOYMENT EVIDENCE PHASE: BLOCKED
NEW SOFTWARE P0: 0
NEW SOFTWARE P1: 1
DEPLOYMENT P1 REMAINING: 6
EXTERNAL P1 REMAINING: 4
SOFTWARE REMEDIATION REQUIRED: YES
READY FOR FINAL LEVEL D AUTHORIZATION AUDIT: NO
NEXT ACTION: Fix the workflow YAML in a new immutable RC2, obtain a successful mandatory CI run, then execute the production-like evidence program against RC2 only.
```

## Conclusion

RC1 is not eligible for Level D authorization. The next release must be a new immutable candidate after CI remediation; RC1 evidence must not be combined with RC2.
