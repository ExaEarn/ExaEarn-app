# ExaEarn Phase 16 Policy Precedence

Policy precedence is intentionally conservative.

Order:
1. Unconfigured high-risk jurisdiction fail-closed and blocked/restricted jurisdiction controls.
2. Active product/jurisdiction/account/asset/market/network/currency rules.
3. Active approved policy exception.
4. Active user or institutional restriction.
5. KYC/KYB verification enforcement.
6. Risk-reducing transition allowance.

Risk-reducing transitions:
- `REDUCE_ONLY` and `CLOSE_ONLY` allow `REDUCE`, `CLOSE`, and `CANCEL`.
- `SELL_ONLY` allows `SELL`.
- `WITHDRAW_ONLY` allows `WITHDRAW`.

Rules can open controlled high-risk products in supported jurisdictions, but cannot override blocked/unconfigured fail-closed jurisdiction controls.
