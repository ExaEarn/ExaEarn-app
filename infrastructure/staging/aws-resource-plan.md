# AWS resource, monitoring, and backup plan

This is a review artifact only. Do not apply it until the financial-operation
gate and security review blockers in `README.md` are closed.

## Resources

| Resource | Configuration | Guardrail |
| --- | --- | --- |
| EC2 | `t4g.small`, Ubuntu 24.04 ARM64, one instance | IMDSv2 required; no SSH key; SSM instance profile only |
| EBS | 30 GiB gp3, encrypted | Retain volume until backup restore is tested |
| IPv4 | One public address while instance runs | Never leave an Elastic IP unassociated |
| Security group | `exaearn-staging-edge` | Inbound 80/443 only; no 22, 5432, 6379, or 4000 |
| SSM Parameter Store | SecureStrings under `/exaearn/staging/` | Separate random staging values; no production paths |
| CloudWatch Logs | `/exaearn/staging/*`, 14-day retention | Never log credentials or financial/customer payloads |

The default VPC is acceptable only for this constrained, non-production host.
PostgreSQL, Redis, and Node remain Docker-internal. Production needs private
subnets and managed data stores.

## Security-group rules

| Direction | Protocol/port | Source/destination | Purpose |
| --- | --- | --- | --- |
| Inbound | TCP 80 | `0.0.0.0/0`, `::/0` | ACME validation and redirect, only after DNS approval |
| Inbound | TCP 443 | `0.0.0.0/0`, `::/0` | Staging HTTPS API, only after DNS approval |
| Inbound | TCP 22 | none | SSM replaces SSH |
| Inbound | TCP 5432/6379/4000 | none | Docker-internal services only |
| Outbound | TCP 443 | Internet | Patches, registries, SSM, ACME, testnet read-only RPCs |
| Outbound | TCP 53 | VPC resolver | DNS resolution |

Do not allow mainnet RPC destinations. Restrict outbound further only after
recording each required AWS endpoint, registry, ACME endpoint, and testnet RPC
hostname.

## Monitoring and alarms

Create staging-only alarms after the host exists:

- EC2 `StatusCheckFailed` greater than zero for five minutes.
- CPU above 80% for 15 minutes.
- available memory below 250 MiB for 10 minutes (CloudWatch agent).
- free EBS disk below 20% for 15 minutes.
- API `/up` or Node `/health` failure from an external staging-only check.
- Docker restart count above three in 15 minutes.

## Backup and recovery

- Take a daily, tagged EBS snapshot after database health verification.
- Keep seven daily snapshots initially and monitor snapshot growth.
- Restore a snapshot to a disposable staging volume before trusting it.
- An encrypted `pg_dump` needs a separately approved storage design if a
  recovery point objective below daily snapshots is required.

## Cost limits

At 730 hours/month the fixed baseline is approximately:

| Item | Estimate |
| --- | ---: |
| `t4g.small` | $12.26 |
| 30 GiB gp3 | $2.40 |
| one public IPv4 | $3.65 |
| 30 GiB snapshot equivalent | $1.50 |
| **subtotal** | **$19.81** |

CloudWatch ingestion, outbound data transfer, snapshot growth, additional
storage, and an unassociated Elastic IP are variable and can exceed $25. Set a
$20 forecast budget alert before provisioning; it is an alert, not a cap.
