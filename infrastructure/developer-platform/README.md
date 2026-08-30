# Developer Platform Production Deployment

These manifests define repository-controlled workload intent; they are not proof of a deployed production environment. Replace every `RELEASE_SHA`, example hostname, trusted-proxy value, and external secret reference during the reviewed release process.

## Secret injection

`developer-platform-secrets` must be populated by the approved secret manager/CSI driver. It must contain Laravel `APP_KEY`, database and Redis credentials, service authentication secrets, and provider credentials. Secrets must never be committed or rendered into release logs.

## Required boundaries

- The ingress/load balancer strips client-supplied forwarding headers and writes the canonical forwarding chain. Origin services accept traffic only from ingress. `TRUSTED_PROXIES` names only those proxies.
- TLS terminates at the managed ingress boundary and is re-encrypted internally where platform policy requires it.
- PostgreSQL and Redis run in the managed data-services boundary with encryption, authentication, HA, monitoring, and backups. These manifests do not deploy single-node databases.
- Production webhook delivery remains disabled until the egress proxy denies RFC1918, loopback, link-local, metadata endpoints, non-HTTPS destinations, disallowed ports, redirects, and DNS rebinding in deployed tests.
- Prometheus/logging agents may scrape/collect through the canonical Phase 19 observability integration. Alert on 5xx rate, latency, queue depth/failed jobs, WS connections/auth failures, and webhook failures.

## Release sequence

1. Require protected-branch approval and green CI for the exact commit SHA.
2. Build and scan images; sign them and deploy immutable digests.
3. Run `validate-production-config.php` against rendered secret names/values without printing values.
4. Run `classify-migrations.php`; obtain explicit DBA approval for `REQUIRES REVIEW` migrations.
5. Back up and record the recovery point. Run forward-compatible migrations once.
6. Roll out API, workers, scheduler, then realtime with readiness gates.
7. Run `/up`, signed sandbox/production isolation smoke tests, WS authentication/replay, and a disabled webhook egress probe.
8. Record actor, commit, build digest, environment, timestamps, migration IDs, and outcome.

## Rollback

- Application failure: roll back to the prior signed image digest; do not reverse migrations automatically.
- Realtime failure: roll back realtime independently; clients reconnect and replay from durable sequence.
- Worker failure: stop the new consumers, restore the prior worker image, and retain queued jobs.
- Configuration failure: reject rollout at readiness/config validation and restore the last versioned configuration reference.
- Migration failure: stop rollout, preserve evidence, restore through the tested backup/PITR process when forward repair is unsafe. Destructive down-migrations are not automatic.

