# ExaEarn Support Live Chat Security

Live chat follows the existing ExaEarn support, admin RBAC, notification and audit boundaries.

## Controls

- User messages are scoped to the authenticated conversation owner.
- Admin actions require support live-chat permissions.
- Internal notes are never returned to user replay.
- Message idempotency prevents duplicate retry inserts.
- Basic secret redaction protects passwords, private keys, API secrets and CVV-like disclosures.
- Attachments continue to use private support attachment infrastructure.
- Chat agents cannot directly perform financial mutations.

Security incidents must be escalated to Phase 18 workflows rather than handled by ad hoc chat actions.

