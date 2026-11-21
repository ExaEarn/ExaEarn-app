# ExaEarn AWS production preparation

This directory is deployment preparation only. No AWS resources are created by these files. The stack is deliberately a single-AZ minimum-hosting baseline; it is not highly available and must not be presented as a production-ready financial platform until the release gates below are met.

## Repository findings

- The Laravel 12 API image is built from `backend/api-gateway/Dockerfile` (PHP 8.3); its current image starts `php artisan serve`, not PHP-FPM/Nginx.
- `backend/services/blockchain-service/Dockerfile` builds the Node 22 realtime/blockchain service. Its `/health` endpoint is available on port 4000, and its controller exposes transaction execution. It defaults its event listener to enabled unless `BLOCKCHAIN_EVENT_LISTENER_ENABLED=false`.
- The existing Kubernetes manifest declares three API replicas (each up to 2 vCPU/2 GiB), three queue workers (each up to 2 vCPU/2 GiB), one webhook worker (up to 1 vCPU/1 GiB), one scheduler (0.5 vCPU/768 MiB), and three realtime replicas (each up to 2 vCPU/2 GiB). Summed peak limits are about 19.5 vCPU and 19.75 GiB, excluding PostgreSQL, Redis, Docker, and the operating system; summed requests are about 3.25 vCPU/5 GiB. These are configuration limits, not measured utilization or a benchmark.
- The repository has no production Compose file. Existing Kubernetes configuration is illustrative (`registry.example.invalid`, example hostnames); it is not evidence of running infrastructure.
- Laravel's scheduler includes staking, delegation, liquidity, market-maker, and game jobs. Do not start its scheduler or queue workers until every job and its external effects have been reviewed. The API also contains financial routes; setting a Node signing key to blank is not an API-level kill switch.
- The blockchain Dockerfile's `start:giftcard` script expects compiled Nest output that this Dockerfile does not build. Gift-card routing is intentionally not included in the prepared Compose stack.

## Deployment options

Costs are planning estimates in USD for `us-east-1`, Linux on-demand, 730 hours/month, before tax, support, substantial data transfer, and account-specific discounts. Validate them in the AWS Pricing Calculator immediately before approval. This estimate is not an AWS quote, and current AWS inventory/pricing could not be verified because the configured SSO session expired.

| Cost component | Option A: minimum single host | Option B: redundant application and managed data |
|---|---:|---:|
| Compute | 1 × `m7g.2xlarge` (8 vCPU, 32 GiB), about $238/month | 3 × `m7g.4xlarge` (16 vCPU/64 GiB each), about $1,430/month |
| EBS | 100 GiB gp3, about $8/month | 3 × 100 GiB gp3, about $24/month |
| Public IPv4 | 1 address, about $3.65/month | ALB and application addresses; budget about $18–$22/month |
| Database | PostgreSQL on the host/EBS; no separate DB fee | RDS PostgreSQL Multi-AZ, 2 × `db.m7g.large` compute roughly $140–$180/month, plus storage/IO/backups |
| Redis | Redis on the host/EBS; no separate cache fee | ElastiCache Redis primary + replica, roughly $180–$260/month depending on node/engine pricing |
| Edge/load balancer | Cloudflare proxy; no AWS load balancer | ALB roughly $20–$45/month before LCU and transfer |
| Monitoring, backup, snapshots, secret storage | CloudWatch/S3/AWS Backup/one Secrets Manager secret roughly $11–$36/month at low volume | Roughly $26–$76/month, volume dependent |
| Approximate subtotal | **$260–$320/month** | **$1,840–$2,040/month** |

Both options incur data-transfer, Cloudflare, RPC-provider, mail/KYC, and possible support charges. A NAT Gateway is intentionally omitted: Option A uses a public subnet with no inbound SSH and SSM-only administration; Option B can use public application subnets with security groups restricted to the ALB and private database subnets. No EKS, NAT Gateway, or managed load balancer is needed for Option A.

**Option A is for a tightly capped pilot only.** Its 8 vCPU/32 GiB size has room for a constrained API, one worker/scheduler only after approval, blockchain realtime, and local Postgres/Redis; it does not meet the existing Kubernetes replica/peak limits, provide AZ redundancy, or isolate the database. Start with `m7g.2xlarge`; do not downsize to `t4g.medium` or claim it satisfies the declared workload. Benchmark with production-shaped traffic and database sizes before considering a smaller instance.

**Option B is the recommendation before meaningful customer or financial traffic.** Use an ECS-on-EC2 capacity provider across three AZs behind an ALB, RDS PostgreSQL Multi-AZ with automated backups/PITR, and a Redis replication group with authentication, encryption, and failover. Three `m7g.4xlarge` hosts provide 48 vCPU/192 GiB aggregate and leave 32 vCPU/128 GiB after losing one host, above the manifest's summed container limits; actual service placement, reservations, and load tests still govern. Use public app subnets with no SSH and put RDS/Redis in private subnets. This still requires a separate financial-control, recovery, load, and failover review.

### Cost assumptions and exclusions

- Indicative rates used: Graviton `m7g.2xlarge` about $0.3264/hour and `m7g.4xlarge` about $0.6528/hour; gp3 about $0.08/GiB-month; public IPv4 $0.005/hour; S3 Standard about $0.023/GiB-month; internet egress starts around $0.09/GiB. Prices and tiers may change. References: [EC2 pricing](https://aws.amazon.com/ec2/pricing/on-demand/), [EBS pricing](https://aws.amazon.com/ebs/pricing/), [public IPv4 pricing](https://aws.amazon.com/vpc/pricing/), [S3 pricing](https://aws.amazon.com/s3/pricing/), and [AWS Pricing Calculator](https://calculator.aws/).
- Option A includes one dedicated data EBS volume and weekly EBS snapshots via AWS Backup (35-day retention), plus encrypted PostgreSQL dumps to S3 (35-day lifecycle). The estimate assumes modest database and log volume.
- Option B estimate assumes `db.m7g.large` and `cache.m7g.large` class pricing for illustration. Confirm engine/version/class availability and current Multi-AZ/replication pricing in the AWS calculator.
- CloudWatch log ingestion, custom metrics, S3 backup storage, snapshot storage, ALB LCUs, RDS I/O, transfer, IPv4, and KMS/API charges are usage dependent. No cost ceiling is guaranteed.

## IaC baseline and controls

`cloudformation/single-host.yaml` defines an isolated VPC/public subnet, one Graviton EC2 instance, a dedicated encrypted gp3 data volume, an EIP, no SSH ingress, SSM-managed administration, an immutable/scanned ECR repository, a TLS-only S3 backup bucket, CloudWatch logs/alarms, and tagged daily EBS backups retained for 35 days. HTTPS ingress is internet-addressable at the security-group layer so the host can be reached through Cloudflare; Nginx must enforce Cloudflare Authenticated Origin Pulls (mTLS) and a verified origin certificate. The template is not parameterized to limit the security group to Cloudflare's changing CIDR list; origin-pull mTLS is mandatory and must be tested before DNS is pointed at the host. EBS snapshots are crash-consistent, not a substitute for the PostgreSQL dump and a restore drill.

The EC2 role has SSM core access, ECR pull access to the stack repository, S3 access limited to this backup bucket, CloudWatch log writes limited to the stack log group, and `secretsmanager:GetSecretValue` on one exact, pre-created application-environment secret ARN. Use the AWS-managed Secrets Manager encryption key; the role has no general KMS decrypt permission. It does not create or populate the secret. `scripts/load-secrets.sh` fetches it without printing values and writes a mode-600 environment file. There is no SSH key or inbound SSH rule. `ssm:StartSession` should be granted to a separate human operator role, not to the instance profile.

Compose keeps PostgreSQL and Redis on a private Docker network with persistent named volumes under Docker's data-root on the encrypted EBS volume, plus health checks. The public TLS proxy requires Cloudflare Origin CA certificate/key and the Cloudflare Authenticated Origin Pull CA to be installed out of band. Do not commit any certificate/private key or `.env.production`. Every image, including PostgreSQL, Redis, and Nginx, must be pinned to an approved SHA-256 digest.

## Safe release and recovery

1. Require a reviewed, protected-branch commit; build and scan API and blockchain images in CI and publish immutable SHA tags/digests to ECR with `scripts/publish-images.sh` (or an equivalent protected CI OIDC publisher).
2. Before any release, confirm the `exaeaen-admin` identity/account, AWS inventory, quotas, budget alarms, CIDR plan, and a separate written cost approval. Never run `cloudformation deploy` as part of this preparation.
3. Configure the Cloudflare origin certificate, hostname, Authenticated Origin Pulls, and strict origin TLS, then independently verify the certificate chain and mTLS from an allowed Cloudflare edge. Do not change DNS in this task.
4. Create the Secrets Manager secret `exaearn/production/application-environment` out of band, pass its exact ARN to the template, populate the server-side `.env.production` with `scripts/load-secrets.sh` (run host administration scripts as root), and keep all wallet/signer keys absent. Set `BLOCKCHAIN_EVENT_LISTENER_ENABLED=false`; do not enable the blockchain profile.
5. Install the TLS files and reviewed Cloudflare real-IP ranges, and supply `ENABLE_API_TRAFFIC`, `AWS_REGION`, `BACKUP_BUCKET`, and all digest-pinned image references as environment variables. `scripts/deploy.sh` requires explicit `ENABLE_API_TRAFFIC=true`, checks for the API secret file and TLS files, installs a daily PostgreSQL-backup timer, and never starts the blockchain service, scheduler, or queue workers. It performs no AWS provisioning. It must not be used until the API's financial routes and production environment have an approved feature-level disablement plan.
6. Run migrations only after reviewing `scripts/classify-migrations.php` output and taking a database backup. Do not run destructive down-migrations as rollback.
7. Roll back application code with `scripts/rollback.sh` and the prior immutable image digest; do not reverse migrations automatically. Restore the database only after an approved recovery decision and a tested restore drill. `backup-postgres.sh` creates a compressed dump and fails if `pg_dump` or the S3 upload fails. `restore-postgres.sh` intentionally refuses to overwrite an existing database; restore to a new database and verify before any cutover.

## Blockers before provisioning or customer traffic

- Re-authenticate the named AWS SSO profile and perform the requested read-only inventory. No EC2, IAM, VPC, SG, EBS, SSM, ECR, CloudWatch, or existing cost data could be inspected in this session.
- Choose Option A pilot or Option B production; approve a current calculator estimate and a monthly spend limit.
- Confirm actual traffic, concurrent WebSocket sessions, queue rates, database growth, RPO/RTO, region/AZ requirements, and measured load-test resource usage.
- Resolve image publication: CI currently scans locally built API and realtime images but does not publish to ECR. Establish protected CI OIDC role permissions and immutable release digests; `publish-images.sh` is a guarded operator fallback, not an IAM provisioning script.
- Create the Secrets Manager secret `exaearn/production/application-environment` out of band. The instance profile can read only its exact ARN; the stack does not create or populate a secret.
- Review the Laravel production configuration, all scheduled/queued financial jobs, mainnet RPC use, API financial routes, withdrawals/deposits, and fail-closed controls. There is no application-wide live-financial-execution kill switch in the inspected code.
- Decide on a supported production web runtime (current API image runs `artisan serve`) and complete production Docker health/termination tests.
- Provision and verify Cloudflare origin and Authenticated Origin Pull certificates, trusted proxy ranges, firewall policy, hostnames, and security ownership. DNS changes require separate approval.
- Connect CloudWatch alarms to an approved notification target; configure dashboard/alert thresholds, incident response, and restore drills.
- Approve a PostgreSQL/Redis HA and recovery design, restore drills, monitoring/alerts, incident response, and retention/compliance policies.
- Keep deposits, withdrawals, custody signing, live trading, and customer fund movements disabled until explicit security, operations, legal/compliance, and business approvals.

## Inventory status

AWS inventory is **not verified**. `aws sts get-caller-identity --profile exaeaen-admin --region us-east-1` failed because the SSO token had expired and could not refresh. No AWS resource listing or mutation was performed. Re-run the read-only inventory only after an operator refreshes the SSO session with `aws sso login --profile exaeaen-admin`.
