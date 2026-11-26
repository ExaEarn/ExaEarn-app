# ExaEarn Listing P1 Wave 1 Governance + Concurrency Remediation

## Root cause

Listing governance decisions were concentrated behind broad admin access. The server already had strong P0 controls for certification, document malware gates, launch dispatch, deposit readiness, withdrawal readiness, and market activation, but reviewer authority was not granular enough for P1 governance: a broad Listing-capable admin path could perform specialist review actions without explicit domain permission, explicit assignment, current revision binding, or persisted immutable decision events.

## Existing governance architecture discovered

- Applicant organization roles are `OWNER`, `ADMIN`, `CONTRIBUTOR`, and `VIEWER` in Listing organization/team membership records.
- Internal staff use the canonical Admin RBAC stack: `Admin`, `Role`, `Permission`, `PermissionService`, and `check.permission` middleware.
- Listing admin operations are exposed under `/api/admin/v1/listing-center`.
- P0 financial/launch safety is enforced in services, especially `ListingIntegrationCertificationService`, `ListingDocumentSecurityService`, `ListingDepositReadinessService`, `ListingWithdrawalReadinessService`, `ListingLaunchDispatcherService`, and `MarketActivationService`.
- Deposit and withdrawal activation already require requester/approver separation plus current configuration fingerprints.
- Production market activation remains centralized through `MarketActivationService`.

## Governance action matrix

| Action | Current role/permission before Wave 1 | Required server-side permission after Wave 1 | Self-action allowed? | Second approver required? | Concurrency/version protected? | Audited? | Risk |
|---|---|---|---|---|---|---|---|
| KYB review | broad Listing admin | `kyb.review` | No when canonical conflict is known | No for non-terminal review movement | State transition checked | Yes | High |
| KYB approval | broad Listing admin | `kyb.approve` | No when canonical conflict is known | Evidence/manual provider fallback required | Document malware gate checked | Yes | Critical |
| KYB rejection | broad Listing admin | `kyb.reject` | No when canonical conflict is known | No | State transition checked | Yes | High |
| Listing review | broad Listing admin | `listing.review` or specialist permission | No when canonical conflict is known | Domain-specific | Bound to `application_revision` | Yes + decision event | High |
| Listing approval/recommendation | broad Listing admin | `listing.approve` | Recommender cannot approve | Yes | `recommended_revision` must match current revision | Yes | Critical |
| Listing rejection | broad Listing admin | `listing.reject` | No when canonical conflict is known | No | Terminal-state protected | Yes | High |
| Information request | broad Listing admin | `listing.review` | No when canonical conflict is known | No | Increments revision and invalidates current review approvals | Yes | High |
| Document replacement | applicant org permissions | applicant org permissions | Applicant may upload; old review does not carry forward | Re-review required | Increments revision and invalidates current review approvals | Yes | Critical |
| Compliance review | broad Listing admin | `listing.compliance.review` | No when canonical conflict is known | Specialist required | Assigned/current revision | Yes + decision event | Critical |
| Security review | broad Listing admin | `listing.security.review` | No when canonical conflict is known | Specialist required | Assigned/current revision | Yes + decision event | Critical |
| Technical review/certification/config | broad Listing admin | `listing.technical.review` | No when canonical conflict is known | Final scheduling separate | Existing fingerprint checks preserved | Yes | Critical |
| Liquidity review | broad Listing admin | `listing.liquidity.review` | No when canonical conflict is known | Specialist required | Assigned/current revision | Yes + decision event | High |
| Launch scheduling | broad Listing admin | `listing.launch.schedule` | Scheduler cannot be final maker | Yes | Existing launch fingerprint checks preserved | Yes | Critical |
| Launch cancellation | broad Listing admin | `listing.launch.cancel` | Allowed to stop unsafe/undesired launch before execution boundary | No | Row lock and terminal boundary preserved | Yes | Critical |
| Terminal recovery/process due | broad Listing admin | `listing.launch.recover` | N/A | N/A | Durable claim/fencing preserved | Yes | Critical |
| Deposit readiness approval | broad Listing admin | `listing.deposit.approve` | Requester cannot approve | Yes | Readiness fingerprint must match | Yes | Critical |
| Deposit activation | broad Listing admin/service | existing P0 decision service | N/A | Current approved request required | Current readiness/fingerprint required | Yes | Critical |
| Withdrawal readiness approval | broad Listing admin | `listing.withdrawal.approve` | Requester cannot approve | Yes | Readiness fingerprint must match | Yes | Critical |
| Withdrawal activation | broad Listing admin/service | existing P0 decision service | N/A | Current approved request required | Current readiness/fingerprint required | Yes | Critical |
| Market activation | generic market mutations risk | `MarketActivationService` Listing-governed authority | N/A | Launch governance required | Launch checks/fingerprint preserved | Yes | Critical |
| Market pause/emergency suspension | broad Listing admin | `listing.emergency.pause` | Stop action can be immediate | No | Existing state updates/audits | Yes | Critical |
| Market/deposit/withdrawal resume | broad Listing admin | `market.resume` plus fresh P0 readiness where applicable | No P0 bypass | Fresh approvals for deposit/withdrawal | Current readiness/fingerprint required | Yes | Critical |

## RBAC changes

The canonical Admin RBAC permission registry now includes granular Listing/KYB permissions:

- `listing.view`
- `listing.review`
- `listing.review.assign`
- `listing.approve`
- `listing.reject`
- `listing.compliance.review`
- `listing.security.review`
- `listing.technical.review`
- `listing.liquidity.review`
- `kyb.review`
- `kyb.approve`
- `kyb.reject`
- `listing.documents.review`
- `listing.launch.schedule`
- `listing.launch.cancel`
- `listing.launch.recover`
- `listing.deposit.approve`
- `listing.withdrawal.approve`
- `market.manage`
- `market.resume`
- `listing.emergency.pause`

Applicant organization roles remain separate from internal staff permissions. An applicant `OWNER`/`ADMIN`/`CONTRIBUTOR`/`VIEWER` cannot satisfy Admin RBAC for internal Listing Center endpoints.

## Reviewer assignment

Wave 1 adds `listing_review_assignments` with:

- application
- review domain
- reviewer admin ID
- assigned-by admin ID
- assigned timestamp
- status
- reassignment reason

Reassignment marks the previous active assignment as `REASSIGNED` and creates a new active row. Historical assignments and review decisions remain attributable to the original reviewer.

## Separation of duties and conflict rules

Server-side checks now enforce:

- explicit permission for each sensitive action;
- active internal admin account;
- assigned reviewer for a review domain when assignment exists, and assignment permission when no assignment exists;
- canonical conflict-of-interest prevention when an admin’s `canonical_user_id` matches the applicant owner, applicant team member, or linked KYB person;
- recommendation maker and senior approver separation;
- deposit/withdrawal requester and approver separation from existing P0 services;
- duplicate current-revision review decisions rejected with `REVIEW_ALREADY_DECIDED`.

## Application revision strategy

`listing_applications.application_revision` is server authoritative. Material applicant changes and document replacement increment the revision and clear current recommendation evidence. Reviews store:

- `application_revision`
- `configuration_fingerprint`
- reviewer
- decision/status
- scorecard/risk evidence

Final approval requires `recommended_revision` to match the current application revision. Review submission may include `expected_revision`; stale submissions return `STALE_REVISION`.

## Stale approval and document replacement prevention

Material applicant edits, information requests, and Listing document uploads invalidate current passed/conditional review rows and audit `listing.approvals.invalidated`. Replacement document evidence cannot be authorized by a review bound to the old application revision/fingerprint.

KYB person and KYB document changes after approval move KYB back to `REVERIFICATION_REQUIRED` and audit `listing.kyb.approval.invalidated`.

## Decision concurrency strategy

Sensitive review decisions are made inside transactions with row locks on the application and review row. This prevents lost updates and contradictory current-revision decisions. A unique decision-event key prevents the same reviewer/decision/revision combination from being recorded twice for the same review.

Critical launch, deposit, withdrawal, and market activation concurrency protections from P0 were preserved.

## Audit logging

Wave 1 adds or preserves audit events for:

- review assignment/reassignment
- review completion
- immutable review decision event creation
- approval invalidation
- KYB invalidation
- final recommendation/approval/rejection
- information request
- launch cancellation/recovery
- deposit/withdrawal request/approval/opening
- emergency control

No secrets or private document contents are logged.

## Tests added

Added `ListingP1GovernanceConcurrencyTest` covering:

- applicant owner cannot reach internal approval actions;
- general admin without specialist permission is denied;
- removed/revoked permission takes effect on the next request;
- reviewer assignment and reassignment preserve history;
- assigned reviewer enforcement;
- immutable decision event creation;
- duplicate same-revision review decision rejection;
- conflicted reviewer assignment is blocked;
- stale review after document replacement is blocked;
- KYB person change after approval invalidates KYB approval.

## Route boot validation

Laravel route boot and route cache were rerun after the Listing route/controller updates.

## Historical migration behavior

The schema migration adds revision/evidence fields and new history tables without fabricating historical reviewers, fake second approvers, synthetic approvals, or default approved decisions. Legacy rows without specialist evidence remain identifiable by missing Wave 1 revision/event metadata.

## Remaining Wave 2+ work

- Full Listing Operations Center UI is intentionally not built in this wave.
- External provider integration remains out of scope.
- Post-listing monitoring and delisting workflows remain out of scope.
- Additional domain-specific policy nuance can be layered on the new primitives without weakening P0 controls.
