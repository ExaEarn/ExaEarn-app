# ExaSkills Tax Policy

ExaSkills now stores instructor tax profile metadata without exposing raw tax identifiers. Tax identifiers are hashed before persistence.

The `skills_tax_policies` registry supports country, entity type, income category, payout asset, outcome, withholding rate, policy version and effective dates. The default no-policy result is manual review.

This is software readiness only. Real tax/legal policy approval remains external and must be supplied before production withholding rules are enabled.

