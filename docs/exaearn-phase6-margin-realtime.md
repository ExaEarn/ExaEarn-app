# ExaEarn Phase 6 Margin Realtime

Realtime publication is not yet implemented as a dedicated Margin stream.

Recommended topics for the existing realtime layer:

- `margin.account.updated`
- `margin.health.updated`
- `margin.loan.opened`
- `margin.loan.repaid`
- `margin.interest.accrued`
- `margin.liquidation.pending`

Phase 6 backend state is ready for these events, but the stream adapter remains incomplete.
