# ExaEarn Developer Authentication Security Audit

## Scope and authority

Developer authentication uses the canonical `users` identity, Laravel session/Sanctum authentication, canonical TOTP fields, password broker, login-device records, fraud analysis, notification delivery, and audit logs. `developer_profiles` contains Developer product state only and does not duplicate passwords, reset tokens, TOTP secrets, KYC/KYB, or account status.

## Pre-change findings

| Control | Initial state | Finding |
|---|---|---|
| Email verification | Partial | Signed Laravel link and throttled resend existed; replay resolves as already verified rather than creating another effect. |
| Forgot/reset password | Unsafe | Existing-account requests exposed broker status; reset policy was weaker than registration and existing sessions remained active. |
| TOTP | Complete | Login issued no authenticated session/token until canonical TOTP succeeded. |
| Recovery codes | Missing | No canonical recovery-code storage or lifecycle exists. An isolated Developer store is prohibited. |
| Passkeys/WebAuthn | Missing | No canonical implementation exists. |
| SMS/email OTP login | Missing | No canonical login method exists beyond TOTP. |
| Session security | Partial | Session regeneration and logout existed; token/device visibility and bulk revocation were not exposed to Developers. |
| Recent authentication | Missing | High-risk Developer credential actions accepted an old authenticated session. |
| Brute-force controls | Partial | Login had IP/account controls; reset and resend needed explicit route throttles. |
| CAPTCHA | Partial | Provider-neutral risk/rate-limit services exist, but production provider verification is not fully integrated into canonical signup/login. |
| Account state | Complete | Locked/suspended/disabled canonical accounts are denied before session issuance. |
| Safe redirects | Complete | Developer `returnTo` accepts only internal console/onboarding destinations. |
| CSRF/CORS | Partial | Cookie sessions and configured CORS exist; production trusted-origin and cookie flags remain deployment configuration. |
| Audit | Partial | Login and reset auditing existed, but reset metadata included email and recent-auth/session events were absent. |

## Result

Password recovery is generic, reset tokens remain hashed/expiring/one-time through Laravel's canonical broker, reset passwords use the canonical strong policy, reset completion revokes sessions/tokens, and audit metadata stores an email hash rather than the address. Developer browser routes now establish session middleware. A configurable recent-auth marker gates key creation/rotation/disable and webhook-secret rotation. Canonical session/device visibility, individual token revocation, and logout-all are available through authenticated endpoints.

## Deployment checks

- Configure HTTPS, `SESSION_SECURE_COOKIE=true`, the intended SameSite policy, stateful domains, and explicit CORS origins.
- Configure a production mail provider; local log mail is not delivery readiness.
- Configure the CAPTCHA provider and complete canonical challenge verification before classifying bot protection as production ready.
- Keep passkeys, recovery codes, and alternate OTP hidden until canonical implementations exist.

