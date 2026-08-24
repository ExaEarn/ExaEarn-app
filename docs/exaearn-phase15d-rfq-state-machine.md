# ExaEarn Phase 15D RFQ State Machine

Supported RFQ states:

- `REQUESTED`
- `QUOTING`
- `QUOTED`
- `APPROVAL_REQUIRED`
- `ACCEPTED`
- `EXECUTING`
- `SETTLING`
- `SETTLED`
- `EXPIRED`
- `CANCELLED`
- `FAILED`
- `MANUAL_REVIEW`

Invalid transitions throw and do not create financial effects. Expired RFQs and expired quotes cannot be accepted.
