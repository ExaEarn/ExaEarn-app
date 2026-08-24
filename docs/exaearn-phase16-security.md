# ExaEarn Phase 16 Security

Security controls:
- Server-side enforcement through `CompliancePolicyService`.
- No client-provided eligibility value is trusted.
- Admin routes require Sanctum and admin middleware.
- Maker-checker approval prevents single-admin high-impact policy activation.
- Decision logs preserve policy source, reason, product, jurisdiction, actor, and timestamp.
- High-risk products fail closed when jurisdiction policy is not configured.

Phase 16 does not expose secrets or private financial controls in eligibility responses.
