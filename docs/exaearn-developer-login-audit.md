# ExaEarn Developer Login Audit

## Decision

The Developer Portal consumes the canonical ExaEarn `users` identity, Laravel session/Sanctum authentication, login risk analysis, device recording, throttling, logout, password recovery, and audit infrastructure. It does not own passwords, 2FA secrets, KYC identity, or a second user database.

`developer_profiles` is a one-to-one product extension. It stores only developer lifecycle state and links to existing developer projects.

## Current Status

| Capability | Status | Resolution |
|---|---|---|
| Canonical email/password login | COMPLETE | Reused at `POST /api/login` |
| Generic credential errors | COMPLETE | Missing and incorrect accounts return the same public response |
| Login throttling and brute-force protection | COMPLETE | Existing IP and identifier rate limits reused |
| Device and suspicious-login checks | COMPLETE | Existing device and fraud services reused |
| Canonical session and logout | COMPLETE | Session regeneration/invalidation and Sanctum remain authoritative |
| Authenticator TOTP challenge | COMPLETE | Canonical login verifies an enabled TOTP before session/token issuance |
| Passkeys/WebAuthn | MISSING | Not displayed or claimed |
| Social login | MISSING | Not displayed or independently added |
| Developer profile | COMPLETE | Idempotent one-to-one profile resolution after authentication |
| Organization roles | PARTIAL | Projects are user-owned; organization RBAC remains future work |
| Recent-auth enforcement for key rotation | PARTIAL | Existing auth and ownership apply; dedicated recent-auth timestamp policy remains to be added |
| Email verification | PARTIAL | Developer UI blocks credential creation when unverified; canonical login remains available for account recovery |
| KYC policy | COMPLETE | KYC is not required for docs, login, profile initialization, or Sandbox |

## Request Flow

`/developers/login` submits credentials only to canonical `/api/login`. A successful secure session calls authenticated `/api/developer/session`, which idempotently resolves the linked developer profile. New profiles route to `/developers/onboarding`; completed profiles route to `/developers/console` or a validated internal `returnTo` path.

Only `/developers/console*` and `/developers/onboarding` return targets are accepted. Schemes, protocol-relative URLs, backslashes, colons, and unrelated paths fall back to the console.

## Boundaries

- Public docs and market API documentation remain unauthenticated.
- Developer authentication does not grant API scopes or organization permissions.
- Production financial access remains governed by project environment, key scopes, eligibility, KYC where applicable, IP controls, and product-specific risk gates.
- Authentication tokens and credentials are not persisted in browser storage by the Developer Portal.
