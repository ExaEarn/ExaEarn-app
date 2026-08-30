# ExaEarn Developer Signup Audit

## Architecture Decision

Developer signup uses the canonical `users` identity, registration controller, password policy, Sanctum session, device/security infrastructure, and Laravel email verification. It does not insert users from a Developer-specific controller and does not store passwords, KYC, 2FA, or recovery data in Developer tables.

## Capability Map

| Capability | Status | Implementation |
|---|---|---|
| Canonical registration | COMPLETE | `POST /api/register` with `registration_context=developers` |
| Canonical password policy | COMPLETE | Existing 10-character mixed-case/number/symbol policy |
| Developer signup UI | COMPLETE | `/developers/signup` |
| Existing-account handling | COMPLETE | Duplicate identity is rejected; UI routes the user to canonical sign-in |
| Email verification delivery | COMPLETE | Laravel signed verification notification and throttled resend |
| Developer intent after verification | COMPLETE | Signed verification redirects to `/developers/onboarding` |
| Developer profile | COMPLETE | One-to-one extension of canonical user identity |
| Individual onboarding | COMPLETE | Creates one default Sandbox project |
| Organization onboarding | COMPLETE | Creates organization, owner membership, and Sandbox project |
| Onboarding idempotency | COMPLETE | Profile lock and existing default-project resolution prevent duplicate workspace creation |
| Sandbox without KYC | COMPLETE | Verification is required; KYC is not |
| Production automatic activation | DISABLED | New organizations and projects cannot receive production activation through signup |
| CAPTCHA/bot challenge | PARTIAL | CAPTCHA service exists but canonical registration does not currently invoke it |
| Passkeys/social signup | MISSING | Not exposed because canonical identity does not support them |
| Full organization RBAC/invitations | PARTIAL | Owner membership exists; team invitations and role administration remain Developer Console work |
| Production KYC/KYB request | SEPARATE GATE | Not part of basic signup or Sandbox onboarding |

## Signup Flow

1. `/developers/signup` submits name, email and password to canonical `/api/register`.
2. Canonical user initialization, audit, session creation and verification notification execute.
3. The portal displays a masked email-verification waiting state with throttled resend.
4. The signed verification route marks the canonical email verified and returns to Developer onboarding.
5. Onboarding captures optional use case and individual/company context.
6. The server creates exactly one Sandbox project and, for a company, an owner organization membership.
7. Production remains `not_activated`; no KYC/KYB is performed or implied.

## Security Boundaries

- Verification links are signed and bound to the authenticated canonical user and email hash.
- Verification and resend routes are throttled.
- Developer terms acceptance and onboarding completion are stored server-side.
- Project environment is hardcoded server-side to `sandbox` during onboarding.
- Repeated onboarding requests return the existing project and do not create another identity, organization, or project.
- API-key scopes, production approval, KYC/KYB, IP controls and product risk checks remain separate authorization gates.
