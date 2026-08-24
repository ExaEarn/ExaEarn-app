# ExaEarn P2P Risk

`P2PRiskEngine` evaluates P2P actions and records decisions in `p2p_risk_events`.

Current decisions:

- `allow`
- `require_review`
- `block_order`

Current signals:

- Unverified email.
- New-user amount limit exceeded.
- High recent cancellation rate.
- High recent dispute rate.

Risk event records are internal. The frontend receives safe user-facing errors only.
