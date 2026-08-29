# ExaEarn Four-Step Onboarding Audit

## Previous State

`apps/web/src/pages/auth/Register.jsx` contained eight legacy presentation stages after account setup: welcome, broad interests, experience, goals, ecosystem education, personalization, security, and ready. The reachable path skipped most of them and registered after the old interest screen, leaving dead UI and an ambiguous progress indicator.

Account creation already accepted `dashboard_preferences`, and `users.preferences.dashboard` was already the canonical preference store. Dashboard preference read/update/reset APIs and the Home personalization modal were also already present. No new preference table or authentication system was required.

## Reused Contracts

- Existing `/api/check-account` credential and availability validation
- Existing `/api/register` account creation and authentication response
- Existing `/api/preferences/dashboard` authenticated preference update
- Existing user preference JSON storage
- Existing `UniversalHome`, personalized-content ranking, compliance eligibility, and Settings entry point

## Removed From Registration

- Marketing welcome stage
- Ecosystem feature tour
- Separate security presentation stage
- Duplicate ready/setup actions
- Local storage as onboarding preference authority

KYC, security, compliance, product eligibility, and financial permissions remain separate authoritative backend controls.

## New Model

Account details are followed by exactly four personalization stages:

1. Crypto experience: new, intermediate, or experienced.
2. Main goal: one of six bounded product goals.
3. Interests: zero to three selections; skipping deterministically infers interests from the main goal.
4. Result: deterministic Lite/Pro recommendation with an explicit user override.

The client previews the recommendation. The backend recomputes and stores `recommended_mode`, preserving a valid `selected_mode` override. Legacy dashboard intent fields remain populated as a compatibility bridge.
