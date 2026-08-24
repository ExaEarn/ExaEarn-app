# ExaEarn Phase 13 ExaAI Risk

ExaAI risk is fail-closed. A decision must pass:

- active portfolio
- active session
- active subscription
- automated-trading terms acceptance
- strategy version production eligibility
- global state not blocking new risk
- enabled market/product eligibility
- fresh market data
- positive reference price
- minimum confidence
- available allocated ExaAI capital
- portfolio cap
- market max exposure

Global states:

- `NORMAL`
- `NEW_RISK_DISABLED`
- `REDUCE_ONLY`
- `PAUSED`
- `EMERGENCY`

Only `NORMAL` permits new risk-increasing ExaAI decisions.
