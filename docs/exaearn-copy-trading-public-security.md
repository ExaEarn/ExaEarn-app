# ExaEarn Copy Trading Public Security

Public Copy Trading security controls include:

- Sanctum/session authentication.
- Existing API security middleware.
- Existing admin security and audit middleware.
- 2FA middleware on user copy routes when enabled.
- Server-side mode, product flag, jurisdiction, market, and terms enforcement.
- Complaint evidence stored as private structured metadata.
- Durable private realtime replay scoped by authenticated user ID.
- No Lead Trader access to follower balances or funds.
- No admin activation path mutates user balances.
- Existing ledger, OMS, risk, and settlement services remain authoritative.

The public activation layer does not bypass follower risk settings, capacity limits, OMS execution, matching, or settlement.
