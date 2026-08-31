# Available Deployment Environment Audit

## Executable locally

- PHP/Laravel focused and full tests using the existing local toolchain
- PostgreSQL 18.3 fresh migration rehearsal using repository-local test data
- Node realtime unit tests and in-memory-authority connection harness
- TypeScript/Python SDK tests and typechecks
- Developer/Admin typecheck, lint, and production builds
- OpenAPI generation, migration classification, configuration-policy source checks
- Git/GitHub API release identity and workflow metadata collection
- `actionlint 1.7.12` workflow syntax validation

## Requires cloud/deployment environment

- immutable container builds, registry digests, image scans, and deployable secret injection
- Kubernetes NetworkPolicy/authority-path validation
- ingress/trusted-proxy, direct-origin, IPv4/IPv6, TLS/DNS/WAF testing
- production-like PostgreSQL/Redis topology and failure testing
- production-equivalent WebSocket and REST load testing
- deployed scheduler, queue, and webhook-worker lifecycle testing
- webhook egress proxy/network adversarial testing
- backup, PITR, restore, rollback, RPO/RTO, monitoring, alert delivery, and incident drills

## Requires external provider

- network-enabled package advisory services where unavailable locally
- KYC, AML/sanctions, jurisdiction, notifications, market data, custody, and payment provider activation relevant to the eventual cohort
- independent penetration/application/infrastructure security review

## Requires human/legal action

- named primary/secondary on-call and escalation ownership
- vulnerability risk acceptance with owner, controls, and expiry where permitted
- Developer/API and Private Beta terms approval
- privacy/data-processing and jurisdiction/product legal approval
- final independent Level D authorization

No Docker, Kubernetes client/cluster, Gitleaks, Trivy, Syft, deployed monitoring platform, backup target, or provider credential set was available. The local environment is not production-equivalent.
