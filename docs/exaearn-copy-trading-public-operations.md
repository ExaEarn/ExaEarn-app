# ExaEarn Copy Trading Public Operations

Public Copy Trading is activated through a two-step admin workflow:

1. `request-enable` records the target mode, reason, requester, and readiness snapshot.
2. `approve-enable` applies the mode and records approver, reason, and timestamp.

Emergency controls are separate from product mode and can move the system to:

- `NORMAL`
- `NEW_FOLLOWS_DISABLED`
- `NEW_RISK_DISABLED`
- `REDUCE_ONLY`
- `COPY_PAUSED`
- `EMERGENCY`

Risk-reducing exits, manual closes, and liquidation paths must not be blocked by public new-risk pauses.

Operations roles represented by the public readiness validator include:

- `copy.leads.review`
- `copy.leads.approve`
- `copy.leads.suspend`
- `copy.surveillance.view`
- `copy.surveillance.resolve`
- `copy.complaints.view`
- `copy.complaints.resolve`
- `copy.capacity.manage`
- `copy.emergency.manage`
- `copy.production.manage`
