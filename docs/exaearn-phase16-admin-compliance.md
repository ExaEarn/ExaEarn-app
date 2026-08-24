# ExaEarn Phase 16 Admin Compliance Center

Routes:
- `GET /api/admin/v1/compliance/overview`
- `GET /api/admin/v1/compliance/products`
- `GET|POST /api/admin/v1/compliance/jurisdictions`
- `GET /api/admin/v1/compliance/rules`
- `POST /api/admin/v1/compliance/rules/submit`
- `POST /api/admin/v1/compliance/policy-changes/{changeId}/approve`
- `POST /api/admin/v1/compliance/policy-changes/{changeId}/reject`
- `POST /api/admin/v1/compliance/simulate`
- `POST /api/admin/v1/compliance/impact`
- `GET /api/admin/v1/compliance/users/{userId}/eligibility`
- `POST /api/admin/v1/compliance/emergency`

Security:
- Routes require Sanctum, `admin.security`, `admin.audit`, and `compliance.manage`.
- Policy activation uses maker-checker approval.
- Same admin cannot submit and approve a rule.
- Admin actions are recorded through existing admin audit infrastructure.
