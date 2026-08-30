# ExaEarn Developer Workspace and RBAC Audit

## Scope

This audit verified the existing Developer identity, onboarding, project, API-key, webhook, logging, and Sandbox Explorer implementation before workspace hardening. Canonical ExaEarn users, sessions, TOTP, email verification, and `developer_profiles` remain authoritative and were reused.

## Existing State

| Capability | Prior state | Finding | Action |
|---|---|---|---|
| Canonical identity | Production ready | Developer login already resolved the canonical user | Reused |
| Developer profile | Partial | One-to-one intent existed and needed explicit lifecycle integration | Preserved unique user constraint |
| Organization | Partial | Signup created an organization and owner membership | Normalized into organization workspaces |
| Membership | Partial | Membership existed but authorization remained mixed with direct ownership checks | Made membership authoritative |
| Projects | Partial | Projects were directly user-owned and carried one ambiguous environment string | Added canonical workspace ownership and explicit environments |
| API keys | Production ready/partial | Secure credentials existed and were project scoped | Added active project/environment enforcement |
| Webhooks | Production ready/partial | Delivery lifecycle existed but environment identity was implicit | Added environment column, Sandbox default |
| Sandbox Explorer | Production ready/partial | Signed execution existed | Added centralized project authorization and explicit Sandbox checks |
| Console | Partial | Authentication and onboarding existed, but no workspace/team console | Added responsive workspace console |
| RBAC | Missing | No centralized Developer organization permission matrix | Added `DeveloperWorkspaceService::PERMISSIONS` |
| Invitations | Missing | No secure organization invitation lifecycle | Added encrypted recipient, hashed token, expiry, revoke, and one-time acceptance |

## Security Findings Closed

- Project authorization no longer trusts a submitted project ID or original creator alone.
- Organization access requires an active membership on every request.
- Viewer mutations, Developer member administration, and Admin ownership transfer fail closed.
- Final-owner removal and demotion are blocked.
- Ownership transfer uses recent authentication, a transaction, and keyed row locks.
- Sandbox is active by default; Production is a separate `not_activated` environment.
- Archived projects disable active keys and webhooks and reject new API activity.
- Invitation tokens are never stored in plaintext; recipient email is encrypted and separately hashed for lookup.

## Deferred by Design

- Production Access approval, KYC, and KYB remain a separate phase.
- API-key cryptographic redesign remains in its dedicated phase.
- Real production deployment configuration and external email deliverability remain operational concerns.
