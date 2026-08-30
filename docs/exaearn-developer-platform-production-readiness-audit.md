# ExaEarn Developer Platform Production Readiness Audit

Date: 2026-08-30

## Executive verdict

**READY FOR PUBLIC SANDBOX ONLY**

The repository contains a substantial Developer Platform foundation: canonical authentication, workspaces and organization RBAC, sandbox isolation, one-time API secrets, HMAC request signing, durable nonce replay protection, capability-scoped Production Access, REST adapters over existing product services, OpenAPI documentation, TypeScript and Python SDKs, webhook persistence, and durable private-event replay. Focused tests pass.

It is not ready for trusted production access or GA. Three P0 production blockers remain: developer webhook SSRF, unverified proxy-derived client IPs used by security controls, and organization runtime compliance being evaluated as the owner individual instead of the approved institution. The repository also does not contain an externally reachable developer WebSocket gateway, production deployment manifests, or CI enforcement evidence.

This is an evidence audit only. No implementation was changed.

## Audit method and evidence boundary

Reviewed actual routes, middleware, services, migrations, models, tests, OpenAPI, SDKs, portal source, environment examples, Phase 14 reports, Phase 16-19 operational documents, and repository deployment/CI files. Prior completion reports were treated as claims until corroborated by source or an executed test.

Evidence cannot establish live infrastructure configuration, provider contracts, staffing, legal approval, secrets management, backups, restore drills, DNS/TLS, WAF behavior, production Redis/database topology, or deployed telemetry. Those items are explicitly classified below.

The worktree was already materially dirty before this audit. The audit did not revert or modify those changes.

## Severity summary

| Severity | Count | Meaning |
|---|---:|---|
| P0 | 3 | Blocks any production developer launch |
| P1 | 12 | Required before private production beta |
| P2 | 9 | Important hardening or product completeness |
| P3 | 4 | Non-blocking quality improvements |

## P0 blockers

### P0-1: Developer webhook SSRF

- Classification: **FAIL**
- Evidence: `DeveloperWebhookService::register()` validates HTTPS only in production. Delivery sends directly to the stored URL. There is no loopback, RFC1918, link-local, cloud metadata, DNS rebinding, redirect, or resolved-address control.
- Risk: an authenticated developer can cause server-side requests into private infrastructure and potentially disclose metadata or reach privileged services.
- Affected: webhook registration and delivery workers.

### P0-2: Security controls rely on unverified proxy-derived client IP

- Classification: **DEPLOYMENT VERIFICATION REQUIRED**
- Evidence: API-key IP allowlisting and request controls use Laravel request IP, while `bootstrap/app.php` has no explicit trusted-proxy policy. No backend ingress manifest proves safe forwarding-header normalization.
- Risk: depending on production topology, client IP may be spoofed or may resolve to the proxy for every caller, defeating allowlists, attribution, and rate controls.
- Affected: production API authentication, IP allowlists, rate limiting, audit evidence.

### P0-3: Organization runtime compliance uses the owner individual

- Classification: **FAIL**
- Evidence: organization submission verifies `InstitutionalAccount` KYB, but `assertCapabilities()` later selects `$project->organization->owner` and calls `CompliancePolicyService` without institution context.
- Risk: runtime jurisdiction, restriction, sanctions, or product eligibility can diverge from the approved legal entity.
- Affected: every organization-owned production API capability.

## P1 required findings

1. **External developer WebSocket gateway absent:** durable sessions/events/replay exist, but no network gateway consumes `devws_` sessions. Existing Node hubs serve `/ws/markets` and `/ws/p2p`, not authenticated developer private topics. **FAIL**.
2. **Webhook concurrent claim absent:** `deliverDue()` reads eligible deliveries without an atomic claim, row lock, or skip-locked discipline. Concurrent workers can send the same delivery simultaneously. **PARTIAL**.
3. **Webhook environment isolation incomplete:** endpoint registration does not persist/select the key environment; endpoint rows can default to sandbox while production use is documented. **FAIL**.
4. **Production Access self-review/four-eyes not enforceable:** the guard compares a `DeveloperProductionAccessRequest.submitted_by` user ID to an `Admin` ID and requires the admin object to be a `User`, an impossible condition. Configured withdrawal dual control is not implemented as two distinct approvals. **FAIL**.
5. **No repository CI workflow:** no `.github/workflows` evidence enforces tests, OpenAPI drift, SDK checks, dependency audit, or build gates. **FAIL**.
6. **No backend production deployment specification:** no container, ingress, worker supervisor, queue scheduler, TLS, trusted-proxy, or secret-injection manifest was found. **DEPLOYMENT VERIFICATION REQUIRED**.
7. **Failed requests may evade developer request logs:** request logging occurs after downstream response creation and does not demonstrate exception-path logging. **PARTIAL**.
8. **Request-log environment can be wrong:** logging derives environment from the legacy project field rather than the authenticated API key/environment record. Production traffic may be labeled sandbox. **FAIL**.
9. **Webhook payload policy is decentralized:** `safePayload()` wraps arbitrary caller payloads without a central field allowlist or redaction contract. **PARTIAL**.
10. **Backup and disaster-recovery proof absent:** Phase 19 software records operational evidence, but no production backup execution, PITR validation, or restore-drill artifact is present. **DEPLOYMENT VERIFICATION REQUIRED**.
11. **Dependency vulnerability audit unavailable:** Composer and npm audits could not reach their registries; no CI artifact or current SBOM scan proves the dependency graph clean. **BLOCKED**.
12. **Production operations UI incomplete:** review APIs exist, but no complete Developer Production Access administration workspace was verified in the admin frontend. **PARTIAL**.

## P2 backlog

1. Production KYC freshness defaults to disabled (`0` days); legal/risk policy must select an explicit value.
2. CORS configuration includes development origins/patterns and wildcard methods/headers; production values require environment-specific verification.
3. Private Network Access response is broadly enabled when requested; narrow it to approved development use.
4. CAPTCHA is not proven to use a live production provider and fail-closed policy.
5. API-key lifecycle security notifications are not proven end to end with a live delivery provider.
6. Public status telemetry is sanitized but live uptime/SLO publication is not verified.
7. Webhook retry error storage may retain sensitive remote/network details without a documented retention/redaction policy.
8. WebSocket 1K tests exercise durable event/replay services, not 1K network connections or slow consumers.
9. Public API performance has no independent production-like p95/p99 capacity evidence.

## P3 backlog

1. Passkeys are not implemented.
2. Recovery-code UX is incomplete.
3. Portal status copy describes webhooks as stable despite production blockers.
4. SDK/documentation examples need automated executable conformance in CI.

## Production readiness matrix

| Area | Result | Evidence summary |
|---|---|---|
| Documentation | PASS | Portal catalog and detailed reports exist |
| OpenAPI | PASS | Versioned specification; targeted contract tests pass |
| Login / signup | PASS | Canonical auth and generic reset behavior tested |
| Auth security / sessions | PASS | Recent-auth, TOTP checks, session revocation tested |
| TOTP | PASS | Required for configured sensitive operations |
| Workspace | PASS | Personal/company onboarding and isolation tested |
| Organization RBAC | PASS | Owner/admin/developer/viewer behavior tested |
| Projects | PASS | Ownership, archival, IDOR, environment provisioning tested |
| Environment isolation | PARTIAL | Sandbox finance isolation passes; webhook environment gap remains |
| Sandbox | PASS | Isolated balances and server-side explorer restrictions tested |
| API credentials / secret security | PASS | One-time secret, encrypted storage, rotation/revocation tested |
| Scopes | PASS | Canonical registry and middleware enforcement tested |
| IP allowlisting | DEPLOYMENT VERIFICATION REQUIRED | CIDR logic passes; source IP trust is unproven |
| HMAC | PASS | Shared fixture, timestamp and constant-time verification evidence |
| Replay protection | PASS | Durable DB nonce uniqueness tested |
| Rate limiting | PARTIAL | Credential-aware limits exist; proxy/runtime capacity unverified |
| Idempotency | PASS | Product write adapters use existing idempotent paths/tests |
| Production Access | PARTIAL | Capability gate works; institutional runtime and governance gaps |
| KYC | PARTIAL | Canonical KYC reused; freshness policy defaults disabled |
| KYB | PARTIAL | Submission validates KYB; runtime entity policy is wrong |
| Jurisdiction | PARTIAL | Individual path exists; organization runtime context fails |
| AML / sanctions | PARTIAL | Canonical compliance called; provider/runtime evidence required |
| Admin review | PARTIAL | Audited API exists; self-review/four-eyes gap |
| Admin UI | PARTIAL | General admin exists; complete production-access center unverified |
| REST runtime enforcement | PASS | Signature, status, env, scopes, capability checks covered |
| WebSocket runtime enforcement | FAIL | No external authenticated developer WS transport found |
| Webhook infrastructure | FAIL | SSRF and production isolation blockers |
| TypeScript SDK | PASS | Implemented and previously typechecked/built |
| Python SDK | PARTIAL | Source exists; package publication and independent integration unverified |
| Market data | PASS | Public REST uses canonical market-data layer |
| Spot | PASS | Existing OMS/risk/settlement adapters tested |
| Futures | PARTIAL | Explicit `PRIVATE_BETA` |
| Margin | PARTIAL | Explicit `PRIVATE_BETA` |
| Wallet | PARTIAL | Read/transfer available; withdrawal restricted |
| Earn / staking | PARTIAL | Software available; external validator/provider operations remain |
| Convert | PASS | Existing quote/execute path exposed |
| ExaPay | PARTIAL | API available; external payment operations remain |
| Copy Trading | PARTIAL | Explicit `PRIVATE_BETA` |
| ExaAI | PARTIAL | Explicit `PRIVATE_BETA`; governance remains controlling |
| Ledger integration | PASS | Adapters reuse canonical product services |
| OMS integration | PASS | Developer orders do not create a parallel OMS |
| Wallet/custody integration | PASS | No developer-specific production wallet found |
| Observability | PARTIAL | Request/status services exist; labeling and exception gaps |
| Status page | PARTIAL | Capability status exists; live uptime not proven |
| CI | FAIL | No workflow evidence found |
| Deployment security | DEPLOYMENT VERIFICATION REQUIRED | Backend deployment definition absent |
| Trusted proxies | DEPLOYMENT VERIFICATION REQUIRED | Security-critical configuration unproven |
| Secrets management | DEPLOYMENT VERIFICATION REQUIRED | Env-driven; no production secret-store evidence |
| Backups / DR | DEPLOYMENT VERIFICATION REQUIRED | Architecture documented; drill evidence absent |
| Performance testing | PARTIAL | Service-level loads only, not production network capacity |
| Failure testing | PARTIAL | Product tests exist; infrastructure failover not independently observed |
| Reconciliation | PASS | Existing financial reconciliation reused |
| Compliance providers | PROVIDER OPERATIONS REQUIRED | Software adapters are not proof of live contracts/configuration |
| Security notifications | PROVIDER OPERATIONS REQUIRED | Delivery provider/operational response unverified |
| Legal/policy dependencies | PROVIDER OPERATIONS REQUIRED | Production terms and jurisdiction approvals are external |

## Product-by-product API readiness

| Product | State | Notes |
|---|---|---|
| Public Market Data | **GA READY** | REST only; live capacity/deployment still must be verified |
| Spot Read | **PRIVATE BETA ONLY** | Runtime foundation strong; platform-wide P0s block GA |
| Spot Trade | **PRIVATE BETA ONLY** | Canonical OMS path; production blockers remain |
| Futures Read | **PRIVATE BETA ONLY** | Matches configured status |
| Futures Trade | **PRIVATE BETA ONLY** | Matches configured status and risk gate |
| Margin | **PRIVATE BETA ONLY** | Matches configured status |
| Wallet Read | **PRIVATE BETA ONLY** | Production access controlled |
| Wallet Transfer | **PRIVATE BETA ONLY** | High-risk capability; production controls need closure |
| Wallet Withdraw | **BLOCKED** | Explicitly restricted; dual control incomplete |
| Earn / Staking | **PRIVATE BETA ONLY** | Provider/mainnet operations required |
| Convert | **PRIVATE BETA ONLY** | Canonical flow exists; overall production blockers apply |
| ExaPay | **PRIVATE BETA ONLY** | Provider operations and production verification required |
| Copy Trading | **PRIVATE BETA ONLY** | Configured private beta |
| ExaAI | **PRIVATE BETA ONLY** | Configured private beta and operational governance applies |
| WebSockets | **BLOCKED** | Session/replay foundation is not a network gateway |
| Webhooks | **BLOCKED** | SSRF P0 and delivery/isolation P1s |

## Deployment verification items

- Reverse-proxy trust chain and forwarded-header stripping.
- TLS termination, HSTS, WAF, DDoS controls, request-size limits, and ingress timeouts.
- Production CORS/Sanctum origins and Private Network Access behavior.
- Database HA, Redis persistence/topology, queue workers, scheduler, dead-letter operations.
- Secret manager, encryption-key custody/rotation, environment separation.
- Backup execution, PITR, restore drill, measured RPO/RTO.
- Public/private WebSocket transport deployment and network load tests.
- p50/p95/p99 REST capacity, slow-consumer, queue-backlog, and failover tests.
- Production logs, metrics, alerts, retention, redaction, and incident routing.

## Provider and external operations items

- KYC/KYB, sanctions and jurisdiction provider contracts/configuration.
- Email/SMS/security notification deliverability and escalation staffing.
- Custody, chain, staking validator, banking, fiat and payment-provider activation.
- Product terms, privacy, developer agreement, data processing terms, and jurisdiction approvals.
- Penetration test and independent infrastructure/security review.

## Tests and audits executed

Executed on 2026-08-30:

```text
DeveloperAuthSecurityTest
DeveloperApiCredentialSecurityTest
DeveloperWorkspaceRbacTest
DeveloperProductionAccessTest
Phase14DeveloperPlatformTest

53 passed / 0 failed / 1934 assertions
```

A fresh full-backend run was also started. It produced only passing progress output but exceeded the 180-second audit command limit before PHPUnit emitted a final count. It is therefore recorded as **INCOMPLETE (TIMEOUT), not PASS and not FAIL**. PHPUnit also emitted four pre-existing deprecation warnings for doc-comment metadata in `GiftCardAutoDecisionTest`.

Dependency audits were attempted but **BLOCKED** by registry DNS/network failure (`Packagist` and `registry.npmjs.org`). `pip-audit` was not installed. This report does not claim a clean dependency graph.

Previously reported evidence was cross-checked but not reclassified as newly executed: full backend `577 passed / 0 failed / 1 skipped / 4785 assertions`, OpenAPI targeted `30 passed / 1838 assertions`, Developer portal typecheck/build PASS.

## Safest launch tier

**LEVEL C: Public Sandbox Beta**

Production credentials and production webhooks should remain unavailable until all P0 findings are remediated and P1 deployment/operations controls are evidenced. Sandbox webhook egress should also be disabled or restricted until SSRF protection exists.
