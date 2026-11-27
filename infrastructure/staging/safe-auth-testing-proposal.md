# Proposal: safe authentication and non-financial staging API tests

## Goal

Allow staging users and automated tests to exercise registration, login, token/session handling, profile reads, and explicitly approved non-financial API behavior while preserving the staging financial-operation lock.

## Proposed control

Keep `EXAEARN_STAGING_SAFETY_LOCK=locked` mandatory. Replace the current blanket rejection of all state-changing HTTP methods only after review with a route-level capability policy:

- Allow authentication and account recovery routes only when they use staging-only identity providers, staging email/SMS sinks, strict rate limits, and test accounts.
- Allow non-financial mutations only through an explicit route allowlist reviewed by the API owners. Default every unlisted route to deny.
- Continue returning `STAGING_FINANCIAL_SAFETY_LOCK` for all wallet, deposit, withdrawal, swap, trade, staking, card funding, payment, custody, blockchain broadcast, signing, and financial worker operations.
- Keep RPC endpoint, signing-secret, disabled-service, and worker startup validation unchanged. Staging RPC endpoints must remain testnet-only; signing material stays empty.
- Keep Node transaction execution disabled. Health/read-only endpoints can be separately allowlisted after endpoint review.

## Required implementation and acceptance checks

1. Define a named safe-route allowlist by HTTP method and route name, not broad path prefixes.
2. Add positive feature tests for staging login/session and each approved non-financial route.
3. Add negative tests proving every financial route and Node transaction execution remain denied with the existing lock code.
4. Verify login uses synthetic accounts and cannot send real email/SMS or invoke production identity callbacks.
5. Require security review of the route matrix and passing CI tests/audits before changing the middleware behavior.

This is a proposal only. The current blanket staging lock remains in effect until an explicitly reviewed follow-up change implements and tests the allowlist.
