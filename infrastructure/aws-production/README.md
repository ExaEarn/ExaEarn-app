# ExaEarn AWS budget-pilot proposal

This is an isolated, unprovisioned proposal for `us-east-1`. No AWS resource, DNS record, secret, production image, or customer traffic was created or changed for this redesign. **Do not deploy this stack yet.** The configuration is a low-traffic pilot candidate, not a financially safe or capacity-verified production system.

## Recommendation and feasibility

There is no defensible way to certify the complete requested service set under a hard $35/month all-in ceiling with the measurements currently available. The lowest cost candidate that has enough nominal memory to attempt the stack is `t4g.medium` (2 vCPU, 4 GiB), but it costs about **$24.53/month for compute alone** and is burstable. Its RAM/CPU requirements have not been verified by a production-shaped build, load test, or runtime measurement; its throughput after CPU credits are depleted in Standard mode is limited. The 2-GiB `t4g.small` is not proposed: it cannot be claimed to fit these containers plus Linux, Docker, SSM, monitoring, and database/cache overhead.

The itemized **planning subtotal is about $33.50/month before tax and account-level charges**, and variable snapshots, logs, backups, ECR image growth, egress, and RPC use can exceed it. This is not a guaranteed cap. The account audit previously observed a recurring Business Support charge and tax; if those remain part of the user's $35 account ceiling, this candidate already exceeds the ceiling. Do not remove encryption, backups, alerts, or access controls to force the estimate lower. If $35 is a firm total-bill cap, the safe recommendation is **no production EC2 deployment and no customer traffic** until the budget increases or the required backend scope is reduced.

The narrower scope for a later non-financial pilot is one API replica, one PostgreSQL, one Redis, one internal Node service, and Nginx on a single `t4g.medium`, with jobs and scheduler stopped. It still requires the capacity, ARM64, financial-route, restore, and operator-notification gates below. It offers no AZ redundancy, managed database, independent database failure domain, or guaranteed performance. It must not be used for custody, deposits, withdrawals, trading, settlement, or other real-money operations.

## Candidate host and bounded service allocation

| Component | Proposed limit | Notes |
|---|---:|---|
| Laravel PHP-FPM API | 768 MiB; 0.60 CPU | Three on-demand FPM children, 128 MiB PHP memory limit, OPcache enabled |
| PostgreSQL | 768 MiB; 0.40 CPU | `shared_buffers=256MB`, 50 connections, 4 MiB `work_mem`; local persistent volume |
| Redis | 256 MiB; 0.10 CPU | 192 MiB `maxmemory`, AOF enabled, password required, `noeviction` |
| Node blockchain/realtime service | 512 MiB; 0.20 CPU | 384 MiB V8 heap cap; private service network; transaction execution and event listener off |
| Nginx | 128 MiB; 0.10 CPU | TLS termination and FastCGI only |
| **Container ceilings** | **2,432 MiB; 1.40 CPU** | Limits are configuration ceilings, not measured consumption or reservations |
| Host, kernel, Docker, SSM, CloudWatch agent and headroom | Remaining nominal 1,664 MiB / 0.60 vCPU | Not benchmarked; must be measured under load |

`t4g.medium` has only 4 GiB total and 2 vCPU; over-limit memory causes container termination and CPU limits can throttle. In Standard credit mode, sustained CPU is constrained after earned credits are exhausted; the setting avoids unlimited-mode surplus CPU charges, not performance loss. The Terraform/Compose bounds are a starting point for measurement, not evidence that Laravel, the database, or Node fits. Worker and scheduler profiles remain disabled because Laravel jobs include financial and external side effects.

Official PHP-FPM, Node, PostgreSQL, Redis, and Nginx base images publish ARM64 variants, but that does not verify every locked dependency or the resulting application images. CI must build and run all five pinned images on ARM64 (or an ARM64 runner) before selecting Graviton. The Docker executable was unavailable during this local session, so image builds and a real Compose startup were not performed.

## Monthly cost estimate

Rates are indicative `us-east-1` on-demand planning rates at 730 hours/month; prices are before tax. No Free Tier, credits, or promotional discounts are assumed. AWS pricing pages and calculator must be refreshed before approval.

| Item | Basis | USD/month |
|---|---|---:|
| EC2 `t4g.medium` | $0.0336/hour × 730 | $24.53 |
| Encrypted gp3 EBS | 8 GiB root + 20 GiB application/data = 28 GiB × about $0.08/GiB-month | $2.24 |
| Public IPv4 | One automatically assigned address; $0.005/hour × 730; no Elastic IP | $3.65 |
| EBS snapshots | Planning allowance for 20 GiB retained snapshot data at about $0.05/GiB-month | $1.00 |
| S3 PostgreSQL backups | Assumes up to about 4 GiB retained at about $0.023/GiB-month plus low request volume | $0.10 |
| CloudWatch custom metrics | Three standard-resolution metric series, estimated at $0.30 each | $0.90 |
| CloudWatch alarms | Three standard-resolution alarms, estimated at $0.10 each | $0.30 |
| CloudWatch logs | About 0.5 GiB ingested plus short retention | $0.28 |
| ECR image storage | Allowance for 5 GiB private image layers at about $0.10/GiB-month | $0.50 |
| SSM/KMS requests and SNS alarm delivery | Low-volume allowance; reuses existing SSM/KMS configuration, no new CMK | $0.00* |
| Internet data transfer out | Assumes below the AWS-published aggregate 100 GiB/month internet-egress allowance | $0.00* |
| **Estimated infrastructure subtotal** | **Before taxes, account support, growth, and unlisted external providers** | **$33.50** |

`*` These zeroes are conditional, not unlimited entitlements: KMS/SNS request costs, egress beyond the published allowance, and other usage can be billed. The 100 GiB data-transfer allowance is not Free Tier or a new-customer credit; it is an AWS pricing allowance. The estimate does not include Cloudflare account charges, paid RPC endpoints, email/KYC vendors, support-plan charges, sales tax, or unexpected data growth. The AWS Pricing Calculator must include actual snapshot churn, logs, ECR layers, KMS usage, and the account's support plan before any spend approval.

The $1 snapshot and $0.10 backup entries are allowances, not hard upper bounds. EBS snapshots are incremental and billed on stored changed blocks; a restore test, database growth, frequent changes, or a longer retention window may increase charges. PostgreSQL dump size beyond the assumed 4 GiB also increases S3 storage. Alarm delivery requires subscribing an approved operator to the stack's SNS topic; the template does not invent an email address.

Price references: [EC2 On-Demand](https://aws.amazon.com/ec2/pricing/on-demand/), [EBS](https://aws.amazon.com/ebs/pricing/), [public IPv4/VPC](https://aws.amazon.com/vpc/pricing/), [CloudWatch](https://aws.amazon.com/cloudwatch/pricing/), [S3](https://aws.amazon.com/s3/pricing/), [ECR](https://aws.amazon.com/ecr/pricing/), and [AWS Pricing Calculator](https://calculator.aws/).

## Deployment architecture and security

- One ARM64 Amazon Linux 2023 host in a single public subnet; no NAT Gateway, ALB, Elastic IP, RDS, ElastiCache, EKS, or second AZ. The auto-assigned public IPv4 is billable and changes when the instance is replaced/stopped; DNS remains out of scope.
- 8 GiB encrypted gp3 root plus a 20 GiB encrypted gp3 data volume. Docker data, database files, and bounded container logs use the data volume. Root is reconstructible and is not included in the application-volume snapshot.
- Security group has no SSH ingress and exposes only TCP 443. HTTPS must use the Cloudflare origin certificate and Authenticated Origin Pulls (mTLS); keep origin DNS unpublished until mTLS, trusted proxy ranges, and certificate checks succeed. Nginx real-IP configuration is intentionally empty until the current Cloudflare ranges are reviewed. SSM is the only host administration path; the existing production instance profile is reused with a scoped policy for this stack.
- IMDSv2 is required, hop limit is one, EBS is encrypted, PostgreSQL and Redis have no published host ports, and they run on an internal Docker network. Node is authenticated with a required 32-byte random service secret; its own network permits only internal dependencies plus outbound HTTPS for read-only RPC calls. It is not exposed through Nginx.
- The API now uses PHP-FPM (not `artisan serve`) behind Nginx/FastCGI. FPM workers run as `www-data`; the FPM master runs as container root, as required by the normal FPM privilege-drop model. Application secrets are environment-file-only, mode `0600`, sourced from approved SSM SecureString parameters. Never commit certificates, `.env.production`, credentials, or wallet material.
- `BLOCKCHAIN_EVENT_LISTENER_ENABLED=false` and `BLOCKCHAIN_TRANSACTION_EXECUTION_ENABLED=false` are required. The Node `/contracts/execute` route fails closed unless a separate explicit application setting enables it. Compose includes the blockchain service but does not publish its port. No signing keys are allowed.
- Queue workers and scheduler are profile-gated and not started. The Laravel API still contains financial routes and has no verified global financial-operations kill switch. An environment flag alone is not a substitute. Do not set `ENABLE_API_TRAFFIC=true` or deploy until the financial routes have a reviewed, tested application-level disablement and non-financial pilot approval.
- PostgreSQL receives daily compressed logical dumps in the dedicated private S3 bucket (SSE-S3, TLS-only policy, 35-day lifecycle); AWS Backup takes daily data-volume snapshots with seven-day retention. CloudWatch host logs are retained for 14 days. CPU, memory, and data-disk alarms publish to an SNS topic; an approved operator subscription/notification test remains required.

## Deploy, rollback, backup, and recovery procedure

These are future operator steps, **not an authorization to execute them**:

1. Obtain written cost and deployment approval; verify account/region, a current calculator estimate, budget alarms, quotas, IAM permissions boundaries, and a hard monthly spend alert. Do not assume the account's existing budget or free credits will absorb charges.
2. Complete ARM64 image builds and security scans; publish immutable SHA-256 digests to the existing ECR repositories. Verify API, Node, PostgreSQL, Redis, and Nginx images and the database migration classification. No `latest` tag.
3. Configure and verify Cloudflare origin and Authenticated Origin Pull certificates, current Cloudflare CIDR/Trusted Proxies lists, operator SNS subscription, and the approved hostname. DNS changes need separate approval.
4. Create only approved SSM SecureString environment parameters under `/exaearn/production/app/`, using the existing approved KMS key. Require `APP_ENV=production`, `APP_DEBUG=false`, a 64-hex-character service secret, encoded private Redis URL, both blockchain flags false, and no wallet/signing keys. Run `scripts/load-secrets.sh` on the host as root.
5. Before any API exposure, complete the application-level disablement of deposits, withdrawals, custody signing, trading, settlement, and other money movement; review all API routes, scheduled jobs, and queued side effects. As written, this gate is not satisfied.
6. Only after approvals, capture and verify a PostgreSQL backup, review migration classification, deploy immutable digests, and run health/security checks. Never execute the deploy script as part of this proposal.
7. Roll back application code with `scripts/rollback.sh` and the previously validated immutable digest. Do not reverse migrations automatically. Restore from S3 to a **new database name**, verify integrity and app compatibility, and obtain an explicit recovery/cutover decision before changing traffic.
8. Perform a restore drill and confirm RPO/RTO, backup retention/deletion controls, log alerts, disk alarms, and incident ownership before customer use.

## Scaling path

1. After measured utilization, load-test results, database growth, and revenue support it, move to a larger Graviton EC2 class and separate the application from stateful services.
2. Move PostgreSQL to private-subnet RDS PostgreSQL with Multi-AZ, PITR, tested restores, and a planned migration window; Laravel already uses standard PostgreSQL environment settings.
3. Move Redis to private ElastiCache with authentication/encryption and a tested failover plan. Keep queues and WebSocket/realtime workloads separate from the API host.
4. Move stateless API/Node services to ECS across at least two AZs behind an ALB. Add autoscaling, controlled deployment/rollback, centralized secrets, and notification/incident integrations.
5. Add a WAF/edge design, multi-AZ capacity, and cross-region recovery only after threat, availability, regulatory, cost, and operational requirements are approved. Do not scale through EKS, NAT Gateway, or managed-service defaults without a new cost review.

## Validation and release blockers

- Docker/Compose and `cfn-lint` were not available locally. Compose configuration and image builds therefore remain unvalidated; run `docker compose config`, build all images for ARM64, and perform startup/health/shutdown tests in isolated CI.
- Run `cfn-lint` and the read-only `aws cloudformation validate-template` against the final template. Validation must not be confused with creating a stack.
- Run backend tests, blockchain tests, Node/Composer dependency audits, image scans, and the protected GitHub PR checks after these changes.
- Execute representative API/Node traffic and database/cache load tests on the exact candidate instance. Record CPU-credit balance/throttling, RSS, FPM saturation, Postgres connections/I/O, Redis memory, disk growth, and recovery behavior. Do not treat Compose ceilings or Kubernetes manifest requests as measured capacity.
- Verify all required PHP extensions/FPM runtime behavior and all transitive package/native dependency support on ARM64.
- Finish the application-level financial-operation kill switch and review all scheduled/queued financial jobs. Deposits, withdrawals, custody, settlement, trading, and customer fund movements remain disabled.
- Populate and protect SSM secrets; no production parameters or TLS files are created by this change.
- Subscribe an approved operator to SNS and test alarms, monitoring retention, backup upload/download, restore, and incident escalation.
- Approve the actual recurring charge. The current estimate is close to the maximum before variable charges; the previously observed account support/tax charges mean the all-in $35 cap is not established.
- Obtain separate approvals for AWS provisioning, DNS, production image publication, API exposure, backup restore/cutover, and any financial operation. No PR is merged by this work.

## PR #3 integration

PR #4 retains the validated PR #3 dependency, audit-policy, lockfile, and production-image security fixes already carried in commit `841b906`; the PR #3 branch itself remains untouched. Do not merge either PR without approval. After PR #3 is approved and merged to `main`, update/rebase PR #4 onto that new base, resolve only genuine conflicts, and re-run all dependency, backend, container, workflow, CloudFormation, and integration checks. Keep this PR's remaining diff focused on the budget-pilot infrastructure and runtime compatibility.
