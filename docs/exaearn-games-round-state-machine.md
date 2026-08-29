# EXA Flight Round State Machine

## Authoritative States

EXA Flight persists `round_state` in addition to the legacy `status` field kept for client compatibility.

Formal states:

- `SCHEDULED`
- `OPEN`
- `LOCKED`
- `RUNNING`
- `ENDED`
- `SETTLING`
- `SETTLED`
- `CANCELLED`
- `FAILED`
- `MANUAL_REVIEW`

## Legal Transitions

```text
SCHEDULED -> OPEN | CANCELLED
OPEN -> LOCKED | CANCELLED
LOCKED -> RUNNING | CANCELLED | FAILED | MANUAL_REVIEW
RUNNING -> ENDED | FAILED | MANUAL_REVIEW
ENDED -> SETTLING | FAILED | MANUAL_REVIEW
SETTLING -> SETTLED | FAILED | MANUAL_REVIEW
FAILED -> MANUAL_REVIEW
```

Terminal states are `SETTLED`, `CANCELLED` and `MANUAL_REVIEW`.

## Operation Gates

- Entry is allowed only in `OPEN`.
- Cashout is allowed only in `RUNNING`.
- Settlement is allowed only from `ENDED` or `SETTLING`.
- Cancelled pre-running rounds refund/release eligible entries canonically.
- Invalid transitions throw server-side errors.

Timestamps can trigger transitions, but persisted state is authoritative.
