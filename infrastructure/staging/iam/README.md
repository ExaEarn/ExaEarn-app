# IAM preparation

These policies are templates, not commands. Replace the example account ID
`111122223333` and every placeholder ARN, subnet, security-group, and KMS key
before review. Attach them only after an
AWS administrator confirms the resource IDs and tag boundaries.

Roles:

- `ExaEarnStagingDeployer`: assumed by the SSO deployment group. It can manage
  only staging-tagged EC2/EBS resources and issue bounded SSM commands.
- `ExaEarnStagingInstanceRole`: instance profile only. It has SSM connectivity,
  CloudWatch Logs write access, and read-only access to staging Parameter Store
  SecureStrings. It cannot manage EC2, DNS, IAM, or production secrets.

No Route 53, ACM, RDS, ElastiCache, broad IAM, or production access is included.
