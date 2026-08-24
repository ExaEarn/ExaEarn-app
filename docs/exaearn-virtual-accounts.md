# ExaEarn Virtual Accounts

Virtual accounts are stored in `phase10_virtual_accounts`.

Rules:

- Created through the provider abstraction.
- Tied to a user, country, currency and provider.
- Provider account references are stored server-side.
- Incoming bank transfer webhooks are matched by provider and destination account number.
- Unmatched incoming payments are marked for manual review instead of being silently credited.

Sandbox virtual accounts are deterministic and safe for local tests. They are not production bank accounts.
