# ExaEarn Listing Portal Authentication Architecture

Date: 2026-09-01

## Ownership

The Listing Portal owns its branded authentication experience and listing-resource authorization. ExaEarn Identity remains the sole owner of credentials, sessions, verification, password recovery, MFA, account status, and canonical user IDs.

No `listing_users` table, Listing-specific password, reset token, MFA secret, access token, or identity service exists or was introduced.

## Verified components

| Capability | Status | Canonical implementation |
|---|---|---|
| User identity | Existing, reused | `App\Models\User` and `users` |
| Browser authentication | Existing, reused | `AuthController`, Laravel session/Sanctum middleware |
| Registration | Existing, extended with Listing context, canonical country, and consent | `POST /api/register` |
| Login | Existing, reused | `POST /api/login` |
| Current user | Existing, reused | `GET /api/user` |
| Email verification | Existing, extended with Listing return URL | Signed verification route and resend endpoint |
| Password recovery | Existing, extended with Listing return URL | Password broker and `DeveloperPasswordResetNotification` |
| MFA | Existing, reused | Canonical 2FA challenge and verification path |
| Logout/session rotation | Existing, reused | Canonical logout and session invalidation |
| Account restrictions | Existing, reused | Canonical login/account-status checks |
| Listing authorization | Existing, hardened | Membership, role, and resource checks in Listing controllers/services |

## Browser flow

The standalone Listing application provides `/login`, `/signup`, `/forgot`, `/reset`, and `/verify`. Forms call the canonical API with credentialed requests and do not persist credentials or session tokens in browser storage.

Successful authentication resolves `GET /api/user`. Verified users enter the Listing workspace; unverified users enter the verification experience. New users create a canonical ExaEarn account with `registration_context=listing`. Email verification and password recovery return to the configured `LISTING_PORTAL_URL`.

Listing signup obtains country options from `GET /api/registration/metadata`, which reads the Phase 16 `compliance_jurisdictions` policy source. Registration accepts only an effective jurisdiction whose canonical status permits registration. The canonical user receives `residence_country`; blocked, expired, missing, or unconfigured jurisdictions fail closed.

Required account and Listing Portal acknowledgements are stored in `user_legal_consents` with exact document version, canonical user ID, acceptance time, context, and hashed IP/user-agent evidence. This is a shared ExaEarn legal-consent ledger, not a Listing identity table.

Safe return paths must be local absolute paths, cannot begin with `//`, and cannot point back to the login route. This prevents external open redirects.

## Authentication and authorization boundary

An authenticated ExaEarn user is not automatically authorized for a Listing organization or application. Listing requests enforce organization membership and role permissions server-side. Document access is resource scoped and unauthorized records are returned as not found to reduce enumeration risk.

Normal ExaEarn identity verification, organization/KYB review, and asset listing due diligence remain separate decisions.

## Shared-session deployment requirement

The application supports an existing ExaEarn session because it uses the same canonical current-user endpoint and credentialed cookie transport. Cross-subdomain SSO is conditional on deployment configuration: trusted stateful domains, CORS origins, HTTPS, cookie domain, `Secure`, and `SameSite` settings must cover the Exchange and Listing Portal origins. Without that deployment configuration, users still use the same identity but must establish a session on the Listing origin.

## Security properties

- Password validation, hashing, reset-token lifetime, and one-time reset semantics remain canonical.
- Login rate limiting, session regeneration, account restrictions, MFA, audit events, and security events remain canonical.
- Error responses do not disclose whether an account exists during recovery.
- Passwords, MFA secrets, and session credentials are not written to local storage or URLs.
- Listing registration persists its canonical residence country and versioned legal consents atomically with account creation.
- Session expiry is translated into a sign-in flow with a validated local return path.

## Operational configuration

Set `LISTING_PORTAL_URL` to the canonical HTTPS Listing Portal origin. Validate cookie/CORS/stateful-domain settings in the target environment before claiming seamless cross-subdomain SSO.
