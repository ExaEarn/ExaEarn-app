# ExaEarn Support Security

Support security controls:

- Authenticated users can only access their own tickets.
- Internal notes are excluded from user ticket detail responses.
- Attachments are stored on private storage, not public URLs.
- Executable and oversized uploads are rejected.
- Admin routes use existing RBAC permissions such as `support.view`, `support.reply`, `support.assign`, `support.escalate`, `support.resolve` and `support.manage_kb`.
- Support has no direct wallet, ledger, settlement or balance mutation route.

Sensitive evidence should remain minimized and governed by existing Phase 16 and Phase 18 policies.
