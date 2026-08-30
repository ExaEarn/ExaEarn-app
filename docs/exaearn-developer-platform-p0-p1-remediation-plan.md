# ExaEarn Developer Platform P0/P1 Remediation Plan

Date: 2026-08-30

This plan is advisory. The audit made no code or deployment changes.

## Exit criterion

Private Production Beta may be reconsidered only after every P0 is closed, every production-relevant P1 is closed or formally risk-accepted with compensating controls, focused/full regression is clean, and deployment evidence is attached. Production GA additionally requires independent security and capacity validation plus external provider/legal readiness.

## P0 sequence

### 1. Block webhook SSRF

- Owner: application security + platform networking
- Work type: code, deployment, security testing
- Implement canonical URL validation, HTTPS production policy, prohibited address ranges, DNS resolution checks before every delivery, redirect blocking or revalidation, DNS-rebinding protection, egress allowlisting/proxying, and metadata endpoint denial.
- Add IPv4/IPv6, encoded-host, redirect, rebinding, localhost, RFC1918, link-local, and cloud-metadata adversarial tests.
- Disable external webhook delivery until deployed controls are verified.
- Evidence: tests, egress policy, deployment packet capture/log proof, security review.

### 2. Establish a trusted client-IP chain

- Owner: SRE + security
- Work type: deployment and code configuration
- Declare exact trusted proxies, strip inbound forwarding headers at the edge, set canonical client-IP headers, and test direct/spoofed/multi-hop requests.
- Verify IP allowlists, rate limits, request logs, and security alerts all consume the same trusted address.
- Evidence: versioned ingress config and deployed integration tests.

### 3. Evaluate organization policy as the approved institution

- Owner: compliance engineering
- Work type: code, migration only if identity linkage is missing, tests
- Pass canonical institution/account-type/jurisdiction context into runtime compliance checks.
- Revalidate active organization capabilities after KYB status, institution status, jurisdiction, representative, sanctions, or ownership changes.
- Add tests proving an allowed owner cannot bypass a restricted organization and vice versa.
- Evidence: runtime tests and compliance decision audit records.

## P1 sequence

Progress as of 2026-08-30: Waves 1 and 2 software remediation are complete. Wave 2 evidence is recorded in `docs/exaearn-developer-p1-wave2-governance-completion-report.md`. Wave 3 remains open.

Wave 3 repository controls are now implemented. Deployment/network/restore/scanner evidence remains open as recorded in `docs/exaearn-developer-p1-wave3-delivery-controls-completion-report.md`; this does not authorize Private Production Beta.

### Wave 1: externally reachable interfaces

1. Implement or formally remove claims for the authenticated external Developer WebSocket gateway. Reuse durable sessions/events; add auth handshake, scope/environment checks, revocation, bounded queues, heartbeat, slow-client disconnect, replay, gap recovery, and 1K network tests.
2. Atomically claim webhook deliveries using transaction/locking semantics suitable for the production database. Preserve at-least-once identity while preventing uncontrolled simultaneous sends.
3. Bind webhook endpoints and events to the authenticated environment; prevent sandbox/production crossover.
4. Define central webhook event schemas and payload allowlists with sensitive-field tests.

### Wave 2: governance and auditability

5. **Complete for represented canonical identities.** Admin identities are linked to canonical users and applicant, organization-owner, and institutional-master conflicts fail closed. Canonical beneficial-owner linkage remains an institutional identity schema dependency.
6. **Complete.** HIGH/RESTRICTED capabilities require distinct canonical reviewers with append-only capability evidence; emergency suspension/revocation remains immediate.
7. **Complete.** Credential environment attribution and exception/5xx logging are covered without payload or secret persistence.
8. **Complete.** The existing admin app includes queue, filters, evidence, capability decisions, second-review status, suspension/revocation, and timeline views.

### Wave 3: delivery controls

9. Add CI workflows for backend/full regression, OpenAPI drift, TypeScript/Python SDK tests, portal build, migrations, dependency/SBOM scanning, secret scanning, and static analysis.
10. Add versioned backend deployment definitions covering ingress, workers, scheduler, health checks, resource limits, secret injection, and rollback.
11. Execute and retain backup/PITR restore-drill evidence with measured RPO/RTO.
12. Run dependency audits in a network-enabled trusted CI environment; triage all critical/high findings before launch.

## Required regression evidence

- Webhook SSRF adversarial suite and concurrency suite.
- Proxy/IP spoofing deployment integration suite.
- Individual and institutional Production Access runtime-policy suite.
- Reviewer-conflict and dual-control suite.
- WebSocket network auth/revocation/replay/backpressure/load suite.
- Sandbox-to-production isolation suite across REST, WS, webhook, data, ledger, and credentials.
- API-key signing/nonce/scope/IP/status/revocation regression.
- Product canonical-path and financial invariant regression.
- Full backend, SDK, portal typecheck/build, OpenAPI conformance, dependency and secret scans.

## External gates kept separate

The following cannot be made PASS by changing documentation: provider contracts and credentials, jurisdiction/legal approval, production staffing/on-call, penetration testing, DNS/TLS/WAF configuration, live backup/restore proof, and production capacity evidence.

## Target progression

```text
Current: LEVEL C - Public Sandbox Beta
After P0 + production P1 closure: consider LEVEL D - Private Production Beta
After sustained operations, independent security/capacity evidence, and external gates: consider LEVEL E - Production GA
```
