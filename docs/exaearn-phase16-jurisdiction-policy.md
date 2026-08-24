# ExaEarn Phase 16 Jurisdiction Policy

Jurisdictions are configured in `compliance_jurisdictions`.

Supported statuses:
- `SUPPORTED`
- `RESTRICTED`
- `BLOCKED`
- `REVIEW_REQUIRED`

Fail-closed behavior:
- High-risk products are denied when the user or institution jurisdiction is unconfigured.
- Blocked and restricted jurisdictions return `DENY`.
- Review-required jurisdictions return `REQUIRE_ENHANCED_REVIEW`.

Country source order:
- Institution `country_of_incorporation`
- User `verified_country`
- User `residence_country`
- Optional `COMPLIANCE_DEFAULT_COUNTRY`

Jurisdiction policy data must be populated by compliance/legal operators before production product launch.
