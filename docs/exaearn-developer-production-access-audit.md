# ExaEarn Developer Production Access Audit

## Scope

This audit covers the transition from an isolated Developer Sandbox to capability-scoped Production eligibility. It does not declare the complete Developer Platform production ready.

## Canonical Systems Reused

| Concern | Canonical implementation | Production Access use |
|---|---|---|
| Identity | `users`, Sanctum, Developer Profile | Applicant identity and account state |
| Individual verification | `KycVerification`, `users.kyc_level`, `kyc_verified_at` | KYC tier and configurable freshness |
| Business verification | `InstitutionalAccount` | Organization KYB, incorporation country, risk/compliance state |
| Legal authority | Developer organization plus authorized-representative status | Kept separate from workspace OWNER role |
| Jurisdiction | `CompliancePolicyService` and compliance jurisdiction/rule tables | Submission context and runtime product decision |
| Risk/AML | Canonical compliance restrictions and decision logs | No Developer-specific screening rules |
| Workspace authorization | `DeveloperWorkspaceService` | Project ownership and `production_access.*` permissions |
| Credentials | `DeveloperApiKeyService` | Approved-scope intersection, HMAC, IP rules, rotation and revocation |
| Realtime | `DeveloperRealtimeService` and persisted sessions | Production sessions revoked on capability changes |
| Recent authentication | `recent.auth` middleware | Submission and Production key/security operations |
| Admin authority | Canonical Admin RBAC | Read/review/approve/reject/suspend/revoke permissions |

## Previous Gaps

- Production existed only as a locked project environment; no durable request or review domain existed.
- Credential scope validation could block Production but could not consume an approval decision.
- Runtime middleware checked key scopes and parent state, but had no current capability decision to re-evaluate.
- There was no partial approval, action-required history, or revocation propagation model.
- Developer and admin surfaces had no real Production Access workflow.

## Data Model

- `developer_production_access_requests`: project/environment-bound lifecycle, applicant type, use case, jurisdiction, idempotency and optimistic version.
- `developer_production_capabilities`: one requested scope per request with independent decision and limits.
- `developer_production_access_reviews`: immutable user/admin actions with separate public and internal notes.
- `developer_organizations.institution_id`: explicit link to canonical KYB.
- `developer_organizations.authorized_representative_status`: legal authority kept separate from Developer RBAC.

Database uniqueness prevents duplicate submission identities and duplicate capabilities. Foreign keys preserve project, environment, reviewer and applicant integrity.

## Policy Findings

- Sandbox remains active without KYC/KYB.
- Personal Production requests require canonical KYC.
- Organization requests require an active, approved canonical institution and verified authorized representative.
- Product status overrides reviewer input. `PRIVATE_BETA` and `RESTRICTED` capabilities cannot become approved through this workflow.
- `wallet.withdraw` remains separately restricted and requires dedicated admin permission if product policy is later opened.
- Public market data remains independent of private Production approval.
- Approval creates eligibility only. It never creates an API credential.

## Runtime Authorization

For Production private REST, effective permission is:

`valid credential AND production environment active AND key scope AND current approved capability AND active parent resources AND current canonical compliance/jurisdiction decision`.

Production key creation and policy updates use the same approved-capability intersection. Capability suspension/revocation invalidates cached decisions and revokes active Production realtime sessions immediately. Sandbox credentials and sessions are not revoked by a Production-only suspension.

## External Boundaries

- KYC/KYB/AML provider operational configuration remains provider work; no success is fabricated.
- Trusted proxy behavior and final CORS/CSRF/origin configuration require deployment verification.
- Security notification delivery requires its configured provider and operations setup.
- Legal/API agreement text and versions require approved legal artifacts before mandatory acceptance can be enabled.

