# ExaEarn AWS production preparation

This directory is deployment preparation only; no AWS resources were created during this work. The CloudFormation template would create a new single-AZ baseline and attach a narrowly scoped extension policy to the already-existing production EC2 role. It is not highly available and must not be presented as a production-ready financial platform until the release gates below are met.

## Repository findings

- The Laravel 12 API image is built from `backend/api-gateway/Dockerfile` (PHP 8.3); its current image starts `php artisan serve`, not PHP-FPM/Nginx.
- `backend/services/blockchain-service/Dockerfile` builds the Node 22 realtime/blockchain service. Its `/health` endpoint is available on port 4000, and its controller exposes transaction execution. It defaults its event listener to enabled unless `BLOCKCHAIN_EVENT_LISTENER_ENABLED=false`.
- The existing Kubernetes manifest declares three API replicas (each up to 2 vCPU/2 GiB), three queue workers (each up to 2 vCPU/2 GiB), one webhook worker (up to 1 vCPU/1 GiB), one scheduler (0.5 vCPU/768 MiB), and three realtime replicas (each up to 2 vCPU/2 GiB). Summed peak limits are about 19.5 vCPU and 19.75 GiB, excluding PostgreSQL, Redis, Docker, and the operating system; summed requests are about 3.25 vCPU/5 GiB. These are configuration limits, not measured utilization or a benchmark.
- Before this preparation the repository had no production Compose file; this PR adds a single-host file for a restricted pilot. Existing Kubernetes configuration is illustrative (`registry.example.invalid`, example hostnames); it is not evidence of running infrastructure.
- Laravel's scheduler includes staking, delegation, liquidity, market-maker, and game jobs. Do not start its scheduler or queue workers until every job and its external effects have been reviewed. The API also contains financial routes; setting a Node signing key to blank is not an API-level kill switch.
- The blockchain Dockerfile's `start:giftcard` script expects compiled Nest output that this Dockerfile does not build. Gift-card routing is intentionally not included in the prepared Compose stack.

## Deployment options

Costs are planning estimates in USD for `us-east-1`, Linux on-demand, 730 hours/month, before tax, support, substantial data transfer, and account-specific discounts. Validate them in the AWS Pricing Calculator immediately before approval. This estimate is not an AWS quote. The read-only audit found the existing account budget is $25/month, while Option A is estimated at $260–$320/month; do not provision until the budget and a separate spend limit are explicitly approved.

| Cost component | Option A: minimum single host | Option B: redundant application and managed data |
|---|---:|---:|
| Compute | 1 × `m7g.2xlarge` (8 vCPU, 32 GiB), about $238/month | 3 × `m7g.4xlarge` (16 vCPU/64 GiB each), about $1,430/month |
| EBS | 100 GiB gp3, about $8/month | 3 × 100 GiB gp3, about $24/month |
| Public IPv4 | 1 address, about $3.65/month | ALB and application addresses; budget about $18–$22/month |
| Database | PostgreSQL on the host/EBS; no separate DB fee | RDS PostgreSQL Multi-AZ, 2 × `db.m7g.large` compute roughly $140–$180/month, plus storage/IO/backups |
| Redis | Redis on the host/EBS; no separate cache fee | ElastiCache Redis primary + replica, roughly $180–$260/month depending on node/engine pricing |
| Edge/load balancer | Cloudflare proxy; no AWS load balancer | ALB roughly $20–$45/month before LCU and transfer |
| Monitoring, backup, snapshots, secret storage | CloudWatch/S3/AWS Backup/SSM Parameter Store roughly $11–$36/month at low volume | Roughly $26–$76/month, volume dependent |
| Approximate subtotal | **$260–$320/month** | **$1,840–$2,040/month** |

Both options incur data-transfer, Cloudflare, RPC-provider, mail/KYC, and possible support charges. A NAT Gateway is intentionally omitted: Option A uses a public subnet with no inbound SSH and SSM-only administration; Option B can use public application subnets with security groups restricted to the ALB and private database subnets. No EKS, NAT Gateway, or managed load balancer is needed for Option A.

**Option A is for a tightly capped pilot only.** Its 8 vCPU/32 GiB size has room for a constrained API, one worker/scheduler only after approval, blockchain realtime, and local Postgres/Redis; it does not meet the existing Kubernetes replica/peak limits, provide AZ redundancy, or isolate the database. Start with `m7g.2xlarge`; do not downsize to `t4g.medium` or claim it satisfies the declared workload. Benchmark with production-shaped traffic and database sizes before considering a smaller instance.

**Option B is the recommendation before meaningful customer or financial traffic.** Use an ECS-on-EC2 capacity provider across three AZs behind an ALB, RDS PostgreSQL Multi-AZ with automated backups/PITR, and a Redis replication group with authentication, encryption, and failover. Three `m7g.4xlarge` hosts provide 48 vCPU/192 GiB aggregate and leave 32 vCPU/128 GiB after losing one host, above the manifest's summed container limits; actual service placement, reservations, and load tests still govern. Use public app subnets with no SSH and put RDS/Redis in private subnets. This still requires a separate financial-control, recovery, load, and failover review.

### Lower-cost restricted pilot

The lowest defensible AWS pilot identified is one `m7g.xlarge` (4 vCPU/16 GiB) with one Laravel API replica, local PostgreSQL and Redis on encrypted EBS, a TLS edge proxy, SSM, CloudWatch, daily PostgreSQL dumps, and daily EBS backups. Keep queues, the scheduler, blockchain service, WebSockets, and all financial execution disabled. It is only suitable for a low-traffic, non-custodial API pilot after representative load and recovery tests; it does not satisfy the existing Kubernetes peak limits and is not the recommendation for customer financial traffic.

Planning subtotal: compute about $119/month; 30 GiB root plus 100 GiB data gp3 about $10.40; one public IPv4 about $3.65; 35-day EBS snapshots about $5 at 100 GiB retained; S3 dumps and low-volume CloudWatch about $6–$17. **Estimated total: $144–$155/month**, before transfer, taxes, support, Cloudflare/RPC costs, or storage growth. No NAT Gateway is used. A `t4g.xlarge` has lower nominal compute pricing (about $98/month) but is burstable; sustained CPU near the 3.25-vCPU aggregate request can consume credits and make its total cost unpredictable, so it is not the defensible baseline without measurements.

There is **no safe production-capable architecture under the current $25/month budget**. The existing `t4g.medium` candidate costs about $24.50/month for compute alone and provides only 2 vCPU/4 GiB, below the manifests' 3.25-vCPU/5-GiB aggregate requests before PostgreSQL, Redis, the OS, EBS, IPv4, backups, and monitoring. Removing encryption, backups, SSM, or security monitoring to fit the budget is not acceptable. If $25 is a hard ceiling, do not provision AWS production compute or accept customer traffic; continue with CI/local development and seek explicit budget approval.

### Cost assumptions and exclusions

- Indicative rates used: Graviton `m7g.2xlarge` about $0.3264/hour and `m7g.4xlarge` about $0.6528/hour; gp3 about $0.08/GiB-month; public IPv4 $0.005/hour; S3 Standard about $0.023/GiB-month; internet egress starts around $0.09/GiB. Prices and tiers may change. References: [EC2 pricing](https://aws.amazon.com/ec2/pricing/on-demand/), [EBS pricing](https://aws.amazon.com/ebs/pricing/), [public IPv4 pricing](https://aws.amazon.com/vpc/pricing/), [S3 pricing](https://aws.amazon.com/s3/pricing/), and [AWS Pricing Calculator](https://calculator.aws/).
- Option A includes one dedicated data EBS volume and daily EBS snapshots via AWS Backup (35-day retention), plus encrypted PostgreSQL dumps to a new dedicated S3 bucket (35-day lifecycle). The estimate assumes modest database and log volume. The existing production backup bucket remains untouched.
- No production CPU/RAM utilization or database growth was measurable because the account has no running application instances or databases. Manifest requests (3.25 vCPU/5 GiB) and limits (about 19.5 vCPU/19.75 GiB for declared containers) are configuration values, not observed usage; database, Redis, Docker, and OS needs are additional.
- Option B estimate assumes `db.m7g.large` and `cache.m7g.large` class pricing for illustration. Confirm engine/version/class availability and current Multi-AZ/replication pricing in the AWS calculator.
- CloudWatch log ingestion, custom metrics, S3 backup storage, snapshot storage, ALB LCUs, RDS I/O, transfer, IPv4, and KMS/API charges are usage dependent. No cost ceiling is guaranteed.

## IaC baseline and controls

`cloudformation/single-host.yaml` defines an isolated VPC/public subnet, one Graviton EC2 instance, a dedicated encrypted gp3 data volume, an EIP, no SSH ingress, SSM-managed administration, a TLS-only S3 backup bucket, CloudWatch logs/alarms, and tagged daily EBS backups retained for 35 days. HTTPS ingress is internet-addressable at the security-group layer so the host can be reached through Cloudflare; Nginx must enforce Cloudflare Authenticated Origin Pulls (mTLS) and a verified origin certificate. The template does not restrict ingress to Cloudflare's changing CIDR list; origin-pull mTLS is mandatory and must be tested before DNS is pointed at the host. EBS snapshots are crash-consistent, not a substitute for the PostgreSQL dump and a restore drill.

The template reuses `ExaEarnProductionInstanceProfile` / `ExaEarnProductionInstanceRole` and attaches only host-log/metric and dedicated-backup-bucket permissions. It does not create a duplicate instance role/profile. It also reuses the existing immutable, scan-on-push repositories `exaearn-production-api` and `exaearn-production-blockchain`; it does not create ECR repositories. The existing role already has SSM core access, ECR pull access, and read/decrypt permissions for `/exaearn/production/*` using the production KMS key. No production parameters currently exist under that hierarchy. Create one SSM `SecureString` per environment variable under `/exaearn/production/app/`, using that approved existing KMS key and standard-tier values under the 4 KiB limit. `scripts/load-secrets.sh` fetches these without displaying values and writes a mode-600 environment file. A new backup bucket is created by the template so its retention can be controlled without modifying the existing bucket. There is no SSH key or inbound SSH rule. `ssm:StartSession` should be granted to a separate human operator role, not to the instance profile.

The template's runtime policy is attached to the existing production role; validate that the deploy operator can attach `AWS::IAM::Policy` to that role and that its permissions boundary, if any, permits the added actions. The existing production security group, IAM role/profile, ECR repositories, backup bucket, and staging resources are not edited by this preparation. The new host security group permits inbound TCP 443 only, outbound TCP 443, and VPC DNS; there is no SSH or NAT Gateway.

Compose keeps PostgreSQL and Redis on a private Docker network with persistent named volumes under Docker's data-root on the encrypted EBS volume, plus health checks. The public TLS proxy requires Cloudflare Origin CA certificate/key and the Cloudflare Authenticated Origin Pull CA to be installed out of band. Do not commit any certificate/private key or `.env.production`. Every image, including PostgreSQL, Redis, and Nginx, must be pinned to an approved SHA-256 digest.

## Safe release and recovery

1. Require a reviewed, protected-branch commit; build and scan API and blockchain images in CI and publish immutable SHA tags/digests to their respective existing ECR repositories with `scripts/publish-images.sh` (or an equivalent protected CI OIDC publisher). The script requires an operator attestation that the CI image scan passed; it is not itself proof of a workflow result.
2. Before any release, confirm the `exaeaen-admin` identity/account, refresh the read-only inventory, quotas, budget alarms, CIDR plan, and a separate written cost approval. The existing $25/month budget is insufficient for either proposed architecture. Never run `cloudformation deploy` as part of this preparation.
3. Configure the Cloudflare origin certificate, hostname, Authenticated Origin Pulls, and strict origin TLS, then independently verify the certificate chain and mTLS from an allowed Cloudflare edge. Do not change DNS in this task.
4. Create the required SSM `SecureString` parameters out of band under `/exaearn/production/app/`, each named as a direct uppercase environment key, using the existing production KMS key; do not put multiline certificates or large blobs in parameters. Populate server-side `.env.production` with `scripts/load-secrets.sh` (run host administration scripts as root), and keep all wallet/signer keys absent. Set `BLOCKCHAIN_EVENT_LISTENER_ENABLED=false`; do not enable the blockchain profile.
5. Install the TLS files and reviewed Cloudflare real-IP ranges, and supply `ENABLE_API_TRAFFIC`, `AWS_REGION`, `BACKUP_BUCKET`, and all digest-pinned image references as environment variables. `scripts/deploy.sh` requires explicit `ENABLE_API_TRAFFIC=true`, checks for the API secret file and TLS files, installs a daily PostgreSQL-backup timer, and never starts the blockchain service, scheduler, or queue workers. It performs no AWS provisioning. It must not be used until the API's financial routes and production environment have an approved feature-level disablement plan.
6. Run migrations only after reviewing `scripts/classify-migrations.php` output and taking a database backup. Do not run destructive down-migrations as rollback.
7. Roll back application code with `scripts/rollback.sh` and the prior immutable image digest; do not reverse migrations automatically. Restore the database only after an approved recovery decision and a tested restore drill. `backup-postgres.sh` creates a compressed dump and fails if `pg_dump` or the S3 upload fails. `restore-postgres.sh` intentionally refuses to overwrite an existing database; restore to a new database and verify before any cutover.

## Blockers before provisioning or customer traffic

- Obtain explicit budget approval and increase the current $25/month account budget before provisioning: current Option A estimate is $260–$320/month, and Option B is $1,840–$2,040/month before variable charges.
- Choose Option A pilot or Option B production; approve a current calculator estimate and a monthly spend limit.
- Confirm actual traffic, concurrent WebSocket sessions, queue rates, database growth, RPO/RTO, region/AZ requirements, and measured load-test resource usage.
- Resolve image publication: CI currently scans locally built API and realtime images but does not publish to ECR. Establish protected CI OIDC role permissions and immutable release digests; `publish-images.sh` is a guarded operator fallback, not an IAM provisioning script.
- Populate required SSM `SecureString` environment parameters under `/exaearn/production/app/` using the production KMS key. No parameters currently exist there; never store certificates or signing material in source control.
- Review the Laravel production configuration, all scheduled/queued financial jobs, mainnet RPC use, API financial routes, withdrawals/deposits, and fail-closed controls. There is no application-wide live-financial-execution kill switch in the inspected code.
- Decide on a supported production web runtime (current API image runs `artisan serve`) and complete production Docker health/termination tests.
- Provision and verify Cloudflare origin and Authenticated Origin Pull certificates, trusted proxy ranges, firewall policy, hostnames, and security ownership. DNS changes require separate approval.
- Connect CloudWatch alarms to an approved notification target; configure dashboard/alert thresholds, incident response, and restore drills.
- Approve a PostgreSQL/Redis HA and recovery design, restore drills, monitoring/alerts, incident response, and retention/compliance policies.
- Keep deposits, withdrawals, custody signing, live trading, and customer fund movements disabled until explicit security, operations, legal/compliance, and business approvals.

## PR #3 integration

This branch carries a selective snapshot of the dependency lockfile, required package patches, Node audit policy, production Dockerfile fixes, and the Web3/Zod compatibility test validated on PR #3. The PR #3 branch itself was not changed. Its application/UI, workflow, and unrelated test changes are not copied here. Do not merge either PR without approval. Prefer merging PR #3 first after its review; then update/rebase PR #4 onto the new `main` so the shared dependency/image fixes become base history and PR #4's remaining diff is infrastructure-only. Re-run all gates after that integration.

## Inventory status

Read-only inventory was completed after the user-approved browser SSO login. STS confirmed account `552466033756`, region `us-east-1`, as `ExaEarnAdministrator`. No AWS resource was created, modified, or deleted during the audit or implementation.

| Service | Verified findings |
|---|---|
| EC2/EBS/SSM | No running or stopped EC2 instances, EBS volumes/snapshots/EIPs, or SSM-managed instances. Two unattached candidate launch templates exist: `ExaEarnProductionMviCandidate` (`t4g.medium`) and `ExaEarnProductionCapacityNodeCandidate` (`t4g.xlarge`); both require IMDSv2 and have encrypted 30 GiB gp3 roots. Neither size meets the declared peak workload; no ASG is attached. |
| VPC/networking | Only the default `172.31.0.0/16` VPC was found, with six public `/20` subnets across `us-east-1a`–`1f`, one IGW, and no NAT Gateway. Existing production and staging security groups have no ingress. Staging security group was not modified. The new template creates a separate VPC and security group. |
| IAM | Existing `ExaEarnProductionInstanceProfile` and `ExaEarnProductionInstanceRole` are scoped to EC2 in this account/region and include SSM core, ECR pull, production SSM/KMS read, existing backup-bucket access, and API/blockchain log writes. The template reuses the profile and adds only host log/metric and new-bucket backup permissions. Staging IAM was not modified. |
| ECR | Existing empty repositories `exaearn-production-api` and `exaearn-production-blockchain` are immutable, scan-on-push enabled, and AES256 encrypted. The template and publisher use these names; no new ECR repository is created. |
| S3/backup | Existing `exaearn-production-backups-552466033756-use1` bucket is private, versioned, and SSE-S3 encrypted, but has no lifecycle configuration. The role already accesses its `postgres/*` prefix. It is left untouched; the template creates a separate bucket with a 35-day lifecycle and TLS-only bucket policy. |
| CloudWatch | Existing `/exaearn/production/api` and `/exaearn/production/blockchain` log groups have no retention configured and no stored logs; no alarms exist. The template creates a 30-day host log group and CPU alarm. |
| Database/cache | No RDS or ElastiCache resources, CloudFormation stacks, or AWS Backup plans were found. |
| Spend | Existing monthly account budget is $25. Cost Explorer showed $14.878 month-to-date for Nov 1–22, 2026, chiefly Business Support ($13.84) and tax ($1.04). Option A is estimated at $260–$320/month and Option B at $1,840–$2,040/month, excluding variable usage. Provisioning must wait for budget/cost approval. |
