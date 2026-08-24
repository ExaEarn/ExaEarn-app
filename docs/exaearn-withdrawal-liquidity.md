# ExaEarn Withdrawal Liquidity

`WithdrawalLiquidityReserveService` protects customer payout capacity.

Per asset it calculates:

- Minimum reserve.
- Target reserve.
- Stress reserve.
- Pending withdrawals.
- Available operational withdrawal liquidity.
- Policy formula version.

Policy:

- Ordinary market-making and routing must not breach minimum withdrawal reserve.
- Reserve status can be `BELOW_MINIMUM`, `BELOW_TARGET` or `FUNDED`.
- Funding status is reported separately from software readiness.
