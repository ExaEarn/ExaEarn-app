# ExaEarn Developer Platform P1 Wave 2 Governance Audit

Date: 2026-08-30

## Scope

This audit covers reviewer independence, four-eyes capability approval, developer request logging, and the admin Production Access workflow. It does not claim closure of Wave 3 delivery controls.

## Findings and disposition

| Finding | Prior state | Remediation | Result |
| --- | --- | --- | --- |
| Reviewer identity | Admin IDs were not linked to canonical users | Added unique nullable `admins.canonical_user_id`; reviews fail closed when absent | Closed |
| Applicant conflict | Submitted User and Admin IDs were compared across unrelated identity spaces | Conflict service compares canonical user identity to submitter, organization owner, and institutional master user | Closed for represented identities |
| Beneficial-owner conflict | No canonical beneficial-owner-to-User identity relation exists | No unreliable name/email matching was introduced | Partial, schema dependency documented |
| High-risk approval | One reviewer could activate any available capability | HIGH/RESTRICTED capabilities require two distinct canonical reviewers and append-only capability review evidence | Closed |
| Emergency controls | Suspension/revocation shared the normal review path | Suspension and revocation remain immediate and invalidate cached scopes and realtime sessions | Closed |
| Failed request logging | Exceptions could bypass request logging | Request context records thrown exceptions and uncategorized 5xx responses once with stable error classification | Closed |
| Environment attribution | Logs could inherit project context instead of credential context | Environment is read from the authenticated API key | Closed |
| Admin operations | APIs existed without a dedicated operational workspace | Existing admin app now has a Production Access queue, filters, detail, capability review, second-approval state, notes, timeline, suspend and revoke | Closed |

## Security boundaries

- Canonical identity is mandatory for approval/rejection decisions.
- A reviewer cannot satisfy both approvals for one high-risk capability.
- Capability authorization does not submit orders, move funds, mutate balances, or bypass product risk controls.
- Internal notes are visible only through the permission-protected admin endpoint.
- Developer-facing status serialization continues to hide internal notes.
- Request logs store identifiers and bounded metadata, not request bodies, authorization headers, signatures, secrets, or exception messages.

## Remaining identity limitation

The institutional model identifies an owner and institutional master user but does not maintain a canonical beneficial-owner User relation. Beneficial-owner self-review prevention is therefore not represented as complete. Adding such a relation belongs to canonical institutional identity/KYB ownership modeling; weak matching by names or email addresses was deliberately rejected.

