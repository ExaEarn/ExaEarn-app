# ExaEarn Phase 16 Compliance Architecture

The canonical compliance path is:

`request -> CompliancePolicyService -> decision log -> product service gate -> existing engine/service`

Core tables:
- `compliance_jurisdictions`
- `compliance_products`
- `compliance_policy_rules`
- `compliance_policy_changes`
- `compliance_user_restrictions`
- `compliance_policy_exceptions`
- `compliance_decision_logs`
- `compliance_cases`

Core services:
- `CompliancePolicyService`: central decision engine.
- `CompliancePolicyAdminService`: maker-checker policy activation.

Decision states:
- `ALLOW`
- `DENY`
- `REQUIRE_KYC`
- `REQUIRE_KYB`
- `REQUIRE_ENHANCED_REVIEW`
- `REDUCE_ONLY`
- `CLOSE_ONLY`
- `SELL_ONLY`
- `WITHDRAW_ONLY`
- `SUSPENDED`

Integrated enforcement points:
- Trading risk engine for Spot/Futures/Margin order checks.
- OTC RFQ eligibility.
- Developer API trading/withdrawal key creation.
- Token listing market launch readiness.
- Market-maker bot quoting risk gate.

Compatibility note:
In test environments only, if no compliance policy records exist, Phase 16 returns a compatibility allow decision so pre-existing phase regression suites remain stable. Production does not use that compatibility mode.
