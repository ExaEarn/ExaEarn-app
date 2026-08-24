# ExaEarn Phase 15A Security

Security controls:

- Sanctum authenticated applicant routes.
- Applicant ownership checks on organizations, applications, and messages.
- Admin routes protected by `auth:sanctum`, `admin.security`, `admin.audit`, throttling, and RBAC.
- Maker-checker approval for application approval and scheduling.
- Duplicate contract prevention per supported network.
- Manual market price is prohibited.
- Unknown assets must not be credited as user balances.
- Listing actions are recorded in immutable listing audit logs.
