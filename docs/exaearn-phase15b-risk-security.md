# ExaEarn Phase 15B Risk and Security

Implemented controls:

- Authenticated institutional user routes through Sanctum.
- Admin routes require `admin.security`, `admin.audit`, and `institutional.manage`.
- Application approval and activation use maker-checker separation.
- Internal transfers require subaccount permission.
- Large transfers require a separate approver.
- Cross-institution subaccount transfers are rejected.
- API keys can be scoped to institution and subaccount.
- Institutional audit events record material lifecycle and treasury actions.

Risk profiles are stored in `institutional_risk_profiles` for operational limits such as products, markets, leverage, daily withdrawal and daily transfer limits. Current Phase 15B introduces the model and administrative status controls; product-specific OMS limit enforcement can be expanded per product without changing the account model.

