# ExaEarn AWS staging preparation

This directory is a **preparation-only** deployment bundle for an isolated
`us-east-1` staging host. It creates no AWS resources and must not be used for
production.

## Safety gates

`docker compose up` starts only PostgreSQL and Redis. The API, blockchain
service, and public reverse proxy are in the `application` profile and are
intentionally blocked pending the gates below.

Do not start the application profile until all of these are complete:

1. The code-level staging lock has passed Laravel and Node runtime tests in CI.
   Startup requires the explicit lock, disabled financial services and
   workers, testnet RPC configuration, and empty signing credentials. HTTP
   state-changing operations and Node transaction execution remain blocked.
2. Mainnet RPC URLs, mainnet contract addresses, wallet addresses, private
   keys, seed phrases, signing keys, and payment-provider credentials are
   absent from the host and from AWS Parameter Store.
3. The security review has no unresolved HIGH or CRITICAL findings that affect
   the images or deployed services.
4. The host has passed ARM64 image build/pull, health, migration, backup, and
   rollback tests.

The Laravel and Node applications both retain mainnet defaults outside
staging. The staging startup validators reject missing or unexpected staging
RPC endpoints before those defaults can be used. The application profile must
remain disabled until CI proves those startup checks and the required scans.

## Host plan

- Ubuntu 24.04 ARM64 on one `t4g.small`, with 30 GiB encrypted gp3 EBS.
- No SSH ingress. Use a pre-created EC2 role with AWS Systems Manager (SSM).
- Security group ingress: TCP 80/443 only after DNS and HTTPS approval. The
  database, Redis, and Node service have no host ports.
- Docker Compose named volumes remain on encrypted EBS. Database backups are
  encrypted EBS snapshots; test restoration before relying on them.
- Caddy obtains TLS only after `api-staging.exaearn.com` resolves to the host.
  DNS is deliberately outside this bundle.

## Secrets and environment

Copy `staging.env.example` to `staging.env` on the server only. It is ignored
by Git. Populate values from SSM Parameter Store SecureString parameters under
`/exaearn/staging/`; never commit or print them.

The example contains only testnet endpoints:

- Base Sepolia
- Ethereum Sepolia
- BSC Testnet

No queue worker or scheduler is included. Do not add one until job allowlists
and the financial kill switch have been proven.

## Controlled rollout

1. Create SSM SecureString values and the EC2 instance role.
2. Launch the approved host with encryption, IMDSv2 required, no SSH key, and
   the `ExaEarnStagingInstanceRole` only.
3. Install Docker Engine and Compose through SSM, pull/build ARM64 images, and
   run only the default data-service profile.
4. Restore a disposable, non-production database backup or initialize an empty
   `exaearn_staging` database. Do not copy production financial or customer data.
5. After the code-level financial gate is merged and tested, run migrations
   manually (`php artisan migrate --force`) only after confirming the target
   database name is `exaearn_staging`.
6. Enable the application profile, validate `/up` and `/health`, then obtain
   explicit DNS approval before exposing 80/443.

## Rollback

Stop the `application` profile, retain the EBS volume, and restore the most
recent tested snapshot to a replacement staging volume. Do not roll back by
pointing any staging service at production databases, secrets, or RPC signing
credentials.

