# Level D Evidence Pack

Candidate: `exaearn-developer-level-d-rc.1`  
Commit: `1a340b7b5a0f33d9aa42286f8d3d9621de7cf18e`  
Collected: 2026-08-31

This directory records evidence for the immutable RC1 candidate. A missing artifact is recorded as `NOT PRODUCED` or `NOT RUN`; absence is never treated as a pass. No secrets, provider credentials, API secrets, or customer data are stored here.

RC1 failed mandatory CI before any job was scheduled. It was therefore not promoted to a production-like deployment and remains unauthorized for Level D.

## Index

- `release-candidate.md`: immutable identity and hashes
- `environment-audit.md`: actual local, deployment, provider, and human evidence boundaries
- `ci-results.md`: GitHub workflow evidence
- `security-scans.md`: dependency, secret, static, and container evidence
- `sbom-images.md`: SBOM and image status
- `migration-rehearsal.md`: database migration evidence
- `network-proxy-origin.md`: ingress, proxy, CORS, TLS, and NetworkPolicy evidence
- `capacity-realtime-rest.md`: WebSocket and REST capacity evidence
- `webhooks.md`: dispatcher and egress evidence
- `backup-pitr-restore-rollback.md`: recovery evidence
- `monitoring-alerting-incident.md`: operational evidence
- `providers-security-legal.md`: external evidence
