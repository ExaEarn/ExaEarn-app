# ExaEarn Phase 16 KYC/KYB

Phase 16 separates profile identity, KYC, KYB, and product eligibility.

KYC enforcement:
- User tier comes from `users.kyc_level`.
- Policy rules can require `required_kyc_level`.
- Unsatisfied KYC returns `REQUIRE_KYC`.
- Satisfied KYC turns the product decision into `ALLOW`.

KYB enforcement:
- Institutional eligibility uses `institutional_accounts.kyb_status`.
- Policies can require `required_kyb_tier`.
- Unsatisfied KYB returns `REQUIRE_KYB`.

Phase 16 does not create a new identity verification provider. It consumes the existing verified status fields and makes them authoritative for product eligibility decisions.
