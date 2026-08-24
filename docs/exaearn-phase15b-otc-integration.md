# ExaEarn Phase 15B OTC Integration

Phase 15B prepares institutional OTC segregation without implementing a separate OTC dealing system.

Implemented hooks:

- `OTC` subaccount type
- canonical ledger ownership for OTC balances
- RBAC and audit controls
- fee profile support
- consolidated reporting

Future OTC execution should use the same financial path:

```text
quote/deal approval
→ reservation
→ settlement
→ canonical ledger
→ balance projection
```

No OTC route in Phase 15B may bypass financial core services.

