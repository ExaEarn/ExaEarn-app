# ExaEarn Developer Platform Final P1 Software Remediation Report

Date: 2026-08-30

## 1. Executive summary

The six repository-controlled P1 blockers identified by the final independent audit have been remediated. The Developer Platform is software-ready for a fresh LEVEL D audit, not authorized for Private Production Beta. Production webhooks remain disabled, organization Production Access remains backend-blocked, and Developer wallet withdrawal remains blocked.

Local evidence includes 73 focused Developer tests, 597 full backend tests, a complete 146-migration PostgreSQL rehearsal, Node realtime tests, SDK tests/typechecks, and Developer/Admin production builds. CI scanners, target-cluster policy behavior, production-equivalent realtime capacity, trusted proxy/egress proof, and restore evidence remain external gates.

## 2. Six findings addressed

1. **CI executability:** removed invalid Composer flags; added PostgreSQL 16 and Redis services with health checks; separated PostgreSQL migration rehearsal from isolated regression tests; added focused/full tests, migration artifact, pinned Redocly, SDK/portal/admin checks, and retained mandatory security jobs.
2. **Webhook runtime:** added `developer:webhooks:dispatch`, scheduler registration, dedicated bounded worker loop, graceful signal handling, active project/environment checks, production feature and egress gates, and recent-auth protection for endpoint creation.
3. **Realtime authority path:** added exact realtime-pod to API-pod TCP/8080 egress/ingress rules, retained default deny, and configured the internal authority service URL.
4. **Production Access state machine:** generic decisions now reject terminal states; suspension has an explicit resume-to-review operation; rejected/revoked requests require a new application; pending-second-review is revoked/suspended; production environment and realtime authority are invalidated immediately.
5. **Migration safety:** replaced blanket SAFE output with machine-readable classifications and PostgreSQL-specific rehearsal. Raw SQL, DDL, data backfills, constraints, index operations, destructive changes, and unknown patterns are no longer labelled safe.
6. **Beneficial-owner conflict:** canonical KYB has no durable beneficial-owner-to-user identity relation. The approved safe fallback is enforced: organization Production Access is disabled in backend policy by default. Individual applications remain eligible for controlled review.

## 3. Components changed

- `.github/workflows/developer-platform-gates.yml`
- `backend/api-gateway/app/Console/Commands/DispatchDeveloperWebhooks.php`
- `backend/api-gateway/app/Services/DeveloperWebhookService.php`
- `backend/api-gateway/app/Services/DeveloperProductionAccessService.php`
- `backend/api-gateway/app/Http/Controllers/Admin/DeveloperProductionAccessController.php`
- `backend/api-gateway/config/developer_api.php`
- `backend/api-gateway/.env.example`
- `backend/api-gateway/bootstrap/app.php`
- `backend/api-gateway/routes/api.php`
- `infrastructure/developer-platform/kubernetes/base.yaml`
- `infrastructure/developer-platform/kubernetes/network-policy.yaml`
- `scripts/classify-migrations.php`
- `scripts/tests/migration-classifier-fixtures.php`
- `scripts/generate-developer-openapi.php`
- focused regression tests and generated OpenAPI artifacts
- `docs/exaearn-developer-postgres-migration-policy.md`

## 4. CI remediation

The backend job now provisions PostgreSQL 16 and Redis 7 with health checks. Composer installs from the committed lock file using valid `composer install` syntax. The complete migration chain runs against PostgreSQL. Focused and full regression use explicit isolated SQLite/cache/queue settings to avoid coupling unit isolation to service state. The migration report is retained as a CI artifact.

Contracts, TypeScript/Python SDKs, Developer Portal, and Admin remain mandatory. Redocly is pinned to `1.34.0`. Dependency, secret, SBOM, CodeQL, and Trivy jobs remain fail closed. GitHub execution and network scanner results still require CI verification.

## 5. Webhook dispatcher architecture

`schedule:work` invokes a bounded one-pass command every minute with overlap protection and a single-server lock. The dedicated webhook workload runs the same command in a bounded polling loop. Every pass enters the existing atomic `claimDue()` lease path, revalidates the endpoint through the SSRF validator, checks project/environment/endpoint authority, and applies retry/dead-letter policy.

Production events require both `DEVELOPER_PRODUCTION_WEBHOOK_DELIVERY_ENABLED=true` and `DEVELOPER_PRODUCTION_WEBHOOK_EGRESS_VERIFIED=true`. Both default false. No production webhook was enabled during this phase.

## 6. Realtime authority networking

Default-deny remains. A dedicated policy permits only pods labelled `realtime-gateway` to reach pods labelled `api-gateway` over TCP/8080. The API receives a matching narrow ingress allowance. The runtime authority URL uses the internal `api-gateway` service; internal endpoints retain the node-service shared-secret middleware.

Node auth/replay/revocation/heartbeat/backpressure tests and Laravel authority regression pass separately. Target-cluster NetworkPolicy enforcement and production-equivalent end-to-end capacity remain deployment verification requirements.

## 7. Production Access state machine

Review actions are accepted only from `submitted`, `under_review`, `action_required`, or `partially_approved`. `suspend` is accepted only from active review/approved states. `revoke` cannot reuse rejected/revoked/expired requests. `resume` is accepted only from suspended and returns the request to `under_review`; suspended capabilities become pending and need fresh approval.

Reject, suspend, and revoke cover pending, pending-second-review, approved, and restricted capabilities. Nonactive states lock the production environment, clear effective capability cache, and revoke active Production realtime sessions. Rejected and revoked projects may submit a new idempotently distinct application.

## 8. PostgreSQL migration safety

The classifier now reports:

```text
DATA_MIGRATION: 7
POSTGRES_REHEARSAL_REQUIRED: 1
REVIEW_REQUIRED: 136
SAFE_AUTOMATED: 2
```

This intentionally replaces the false `146 SAFE` claim. The complete 146-migration chain ran successfully on PostgreSQL 18.3 locally. PostgreSQL-backed `RefreshDatabase` regression exceeded the temporary server's default `max_locks_per_transaction`; this does not invalidate the successful clean migration rehearsal, but confirms that CI/full test isolation should remain separate and production parameters need deployment review.

## 9. Beneficial-owner solution

No canonical beneficial-owner/controller relation linked to ExaEarn `User` exists. No Developer-specific identity table was invented. `DEVELOPER_ORGANIZATION_PRODUCTION_ACCESS_ENABLED` defaults false; both submission and runtime capability checks fail with `ORGANIZATION_PRODUCTION_ACCESS_BLOCKED`. The organization path may be enabled only after canonical KYB identity linkage and reviewer-conflict tests exist.

## 10. Security regression

```text
Final P1 + Developer P0/P1/auth/credential/RBAC/Phase 14:
73 passed / 0 failed / 2035 assertions

Node realtime:
8 passed / 0 failed

Local 1K WebSocket authentication:
1000 authenticated / 0 failed / 6.115 seconds / 83 MB RSS
```

Coverage includes recent auth, API credential lifecycle, HMAC fixture, nonce replay, scopes, IP restrictions, Sandbox isolation, Production Access transitions, organization blocking, webhook SSRF/environment/claim/dispatch, request logging, RBAC/IDOR, realtime replay, and revocation.

## 11. PostgreSQL validation

Clean PostgreSQL migration execution: **PASS**, 146 migrations on PostgreSQL 18.3.  
PostgreSQL production parameter/capacity rehearsal: **DEPLOYMENT VERIFICATION REQUIRED**.  
PostgreSQL-backed full `RefreshDatabase`: **INCOMPLETE**, temporary server lock limit was insufficient.

## 12. Build and test results

| Validation | Result |
|---|---|
| Focused Developer/P1 | PASS - 73 tests, 2035 assertions |
| Full backend | PASS - 597 passed, 0 failed, 1 skipped, 4886 assertions |
| PostgreSQL clean migration chain | PASS - 146 migrations |
| Migration classifier fixtures | PASS |
| OpenAPI generation | PASS - 93 paths, GenericRequest 0 |
| TypeScript SDK | PASS |
| Python SDK | PASS - 6 tests |
| Developer typecheck/build | PASS / PASS |
| Admin typecheck/lint/build | PASS / PASS / PASS |
| Node realtime | PASS - 8 tests |
| Workflow syntax | Static review PASS; GitHub execution required |
| Network scanners | NETWORK VERIFICATION REQUIRED |

## 13. Remaining P2/P3

Untouched broad items include production KYC freshness policy, production CORS tightening, dependency-aware readiness, realtime publish observability/fanout scaling/sequence concurrency, production payload retention, live SLO/CAPTCHA/notification-provider evidence, passkeys, recovery-code UX, and executable SDK example conformance. The directly touched P2 items fixed here are webhook recent-auth, pinned Redocly, and removal of unused `GenericRequest`.

## 14. External/deployment gates

- GitHub Actions execution, required-check/branch protection, and network scanner evidence
- target-cluster NetworkPolicy and internal authority verification
- trusted proxy/header-chain verification
- webhook egress proxy and SSRF packet/log verification
- production-equivalent REST/WS/webhook/Redis/PostgreSQL capacity and failure tests
- backup/PITR restore evidence with measured RPO/RTO
- DNS/TLS/WAF and monitoring/on-call/incident evidence
- penetration testing, provider/legal/jurisdiction approvals
- canonical beneficial-owner identity before any organization cohort

## 15. Honest readiness matrix

P1-1 CI EXECUTABILITY:
EXTERNAL VERIFICATION REQUIRED

P1-2 WEBHOOK DELIVERY RUNTIME:
PASS

P1-3 REALTIME AUTHORITY NETWORK PATH:
DEPLOYMENT VERIFICATION REQUIRED

P1-4 PRODUCTION ACCESS STATE MACHINE:
PASS

P1-5 POSTGRES MIGRATION SAFETY:
DEPLOYMENT VERIFICATION REQUIRED

P1-6 BENEFICIAL OWNER CONFLICT:
ORGANIZATION PRODUCTION ACCESS BLOCKED

SOFTWARE-CONTROLLED P0 REMAINING:
0

SOFTWARE-CONTROLLED P1 REMAINING:
0

FULL BACKEND REGRESSION:
PASS

DEVELOPER PORTAL:
PASS

ADMIN:
PASS

OPENAPI:
PASS

SANDBOX:
READY

PRODUCTION WEBHOOKS:
DISABLED

ORGANIZATION PRODUCTION ACCESS:
BLOCKED

WALLET WITHDRAW:
BLOCKED

FINAL P1 SOFTWARE REMEDIATION:
COMPLETE

SOFTWARE READY FOR LEVEL D RE-AUDIT:
YES

REMAINING SOFTWARE P0:
0

REMAINING SOFTWARE P1:
0

REMAINING EXTERNAL/DEPLOYMENT GATES:
CI/scanners, target-cluster networking, trusted proxy and webhook egress verification, production capacity, restore proof, monitoring/on-call, penetration testing, provider/legal approvals, and canonical beneficial-owner identity for organizations.

NEXT ACTION:
Perform the independent LEVEL D re-audit against a controlled production-like deployment without enabling withdrawals or Production webhooks.
