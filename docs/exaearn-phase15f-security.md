# ExaEarn Phase 15F Security

## Controls Added

- Admin Phase 15 endpoints use Sanctum, admin security, admin audit and rate limiting middleware.
- Bot risk gates now fail closed when linked institutional account, subaccount, assignment or developer API key is inactive.
- Revoked developer API keys cannot remain a hidden execution path for market-maker bots.
- Emergency actions require an authenticated admin and reason.

## Financial Safety

Phase 15F does not directly mutate balances. It relies on canonical ledger projections and existing services for settlement, reservations and balance authority.
