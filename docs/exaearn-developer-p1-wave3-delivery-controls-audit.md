# ExaEarn Developer Platform P1 Wave 3 Delivery Controls Audit

Date: 2026-08-30

## Pre-implementation state

- No GitHub Actions workflow existed.
- `infrastructure/` contained no production backend deployment definition.
- The repository had Composer and pnpm lockfiles and a Python SDK without external dependencies.
- Laravel exposed `/up`; the realtime service exposed `/health`; Phase 19 already supplied the canonical reliability/observability domain.
- The Developer OpenAPI generator existed but the checked-in specification had materially drifted from its 93 registered paths.
- A 1K WebSocket harness existed, but its ramp loop launched all handshakes concurrently and reproduced `360/1000` authentication.
- No repeatable PostgreSQL restore drill or production configuration/migration validation script existed.
- No repository secret-scanning policy existed.
- The canonical KYB schema still did not expose a beneficial-owner-to-User identity relation.

## Reused architecture

Wave 3 reuses Laravel queues/scheduler and `/up`, the Node realtime `/health` endpoint, Redis-backed durable realtime, the existing OpenAPI generator, SDK signing fixture, Phase 19 observability, P0 trusted-client-IP and SSRF controls, and Waves 1/2 authorization and governance.

## Risk classification

| Area | Repository state after remediation | Evidence boundary |
| --- | --- | --- |
| CI quality | Fail-closed backend, contract, client, portal, admin, realtime jobs | Workflow must run on protected GitHub branch |
| Security | Gitleaks, pnpm/Composer audit, CodeQL, Trivy, SBOM jobs | Network-enabled CI verification required |
| Deployment | Immutable API/realtime images; separate API, worker, webhook, scheduler, realtime workloads | Cluster render/apply and smoke evidence required |
| Proxy | Ingress-only origin policy and explicit trusted-proxy configuration | Deployed edge header-stripping test required |
| Webhook egress | Default-deny policy; webhook worker restricted to egress proxy; delivery disabled | Proxy denial and packet-level verification required |
| Migrations | Forward migration classifier and fresh migration validation | DBA review still required for future unsafe migrations |
| Recovery | Backup policy and safe non-production restore script | No approved disposable PostgreSQL source was available locally; production evidence required |
| Capacity | Corrected paced handshake ramp retained 1,000 concurrent authenticated clients | Production-equivalent ingress/Redis/authority test required |
| Beneficial owner | No reliable canonical identity relation | Remains partial; no inferred matching |

## Supply-chain policy

- CRITICAL findings block release.
- HIGH findings block release unless a time-bounded, owner-assigned security risk acceptance with compensating controls is approved.
- MEDIUM and LOW findings are triaged and tracked under normal remediation SLAs.
- Lockfile drift, OpenAPI drift, migration policy failure, secret detection, and scanner execution failure block CI.
- Scanner unavailability is not interpreted as a clean scan.

