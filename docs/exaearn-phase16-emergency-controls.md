# ExaEarn Phase 16 Emergency Controls

Emergency compliance controls submit high-precedence policy changes.

Supported emergency decisions:
- `DENY`
- `REDUCE_ONLY`
- `CLOSE_ONLY`
- `SELL_ONLY`
- `WITHDRAW_ONLY`
- `SUSPENDED`

Emergency controls still use maker-checker policy activation. This avoids silent single-operator changes to product access unless an existing operational emergency process explicitly authorizes the approval flow.

Risk-reducing actions remain available where the decision state supports them.
