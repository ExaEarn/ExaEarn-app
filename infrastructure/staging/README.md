# ExaEarn AWS staging preparation

This directory contains a preparation-only deployment bundle for one isolated
ARM64 EC2 staging host in `us-east-1`. It does not provision AWS resources or
deploy the application. Never use it for production.

## Hard gates

- Keep `EXAEARN_STAGING_SAFETY_LOCK=locked`,
  `FINANCIAL_SERVICES_ENABLED=false`, `STAGING_WORKERS_ENABLED=false`,
  and `CUSTODY_PRODUCTION_ENABLED=false`.
- Use testnet RPC endpoints only. Do not add mainnet contract addresses, wallet
  addresses, signing keys, seed phrases, or payment-provider credentials.
- Do not start the application profile until all required HIGH/CRITICAL
  dependency and image security gates pass. Current dependency findings keep
  staging at **NO-GO**.
- Do not publish images, launch EC2, start application services, or expose DNS
  until separately approved.
- No application worker, scheduler, withdrawal, deposit, trade execution, or
  custody service is part of the Compose stack.

## Proposed host and network

- Ubuntu 24.04 ARM64, `t4g.small`, 30 GiB encrypted gp3, IMDSv2 required,
  and the `ExaEarnStagingInstanceProfile`.
- Use the existing public subnet without a NAT gateway so the instance can
  reach SSM and approved HTTPS dependencies. The attached staging security
  group must have **zero inbound rules** and only outbound TCP 443. Do not
  attach the existing partially configured staging group until an administrator
  has applied the prepared repair.
- Do not assign an SSH key or open SSH. Use SSM Session Manager.
- PostgreSQL, Redis, API, and blockchain ports are not published to the host.
  The Caddy `public` profile publishes 80/443 only inside the host network
  namespace; with zero security-group ingress those ports remain unreachable
  from the Internet. Keep this profile disabled until separately approved.
- No DNS changes or public ingress are included.

## Monthly cost estimate

A 730-hour month at the current us-east-1 on-demand rates is approximately:

| Item | Estimate |
| --- | ---: |
| Linux `t4g.small` at $0.0168/hour | $12.26 |
| 30 GiB gp3 at $0.08/GiB-month | $2.40 |
| One in-use public IPv4 at $0.005/hour | $3.65 |
| Daily 30 GiB EBS snapshot equivalent | $1.50 |
| **Estimated baseline** | **$19.81/month** |

This excludes taxes, data transfer, S3 backups, snapshots, CloudWatch ingestion,
and other usage; costs may exceed the current $25 monthly budget. Obtain explicit
budget and spend-limit approval before creating billable resources. A private
subnet without NAT would require VPC endpoints for SSM and image/package access;
their hourly charges must be quoted before choosing that design.

## Environment and secrets

Copy `staging.env.example` to `staging.env` on the host only. The real file
is ignored by Git. Supply fresh, staging-only secrets from SSM Parameter Store
SecureString; never reuse production values or print secrets in logs. Set a
strong `DB_PASSWORD` and a separate URL-safe `REDIS_PASSWORD`; Compose requires
both and enables Redis authentication.

The sample RPC endpoints are testnets. TON, Bitcoin, and TRON RPC endpoints
remain empty. Laravel and blockchain startup guards must reject missing locks,
mainnet endpoints, or signing secrets before any service starts.

## Controlled rollout and operations

1. An authorized administrator repairs the staging security group, creates
   the instance profile, attaches the existing SSM-enabled role, and prepares
   the launch template. The separate administrator script must be reviewed and
   run under an administrator profile; the staging deployer cannot perform
   these IAM operations.
2. Review the exact monthly estimate and approve the billable host before
   running the EC2 launch command. The preparation script must finish with
   `RunInstances --dry-run`; it must not launch the instance.
3. After the host exists, use SSM to install Docker and Compose and retrieve
   the staging environment. Keep the default data-service profile inactive
   until the application security gates pass.
4. Use a dedicated, encrypted S3 bucket and staging-only instance-role access
   for PostgreSQL backups. Set retention/lifecycle and test a restore before
   relying on backups. Do not store plaintext dumps on the host.
5. Configure CloudWatch log shipping, disk/CPU alarms, and backup-failure
   alerts before starting application containers.
6. After all dependency gates pass and startup checks are reviewed, obtain
   approval before pulling approved digest-pinned images and starting the
   `application` profile. Pass `--env-file staging.env` when running Compose
   so required database and Redis secrets are validated; keep the `public`
   profile disabled. Run migrations only against the `exaearn_staging` database.
7. Validate API `/up` and blockchain `/health` healthchecks, testnet-only
   settings, logs, SSM access, backup, and rollback before requesting any
   ingress or DNS change.

## Rollback

Stop the `application` profile and retain the encrypted data volume. Restore a
tested staging backup to a replacement staging volume if needed. Never point a
staging service at production databases, production secrets, or production RPC
credentials.
