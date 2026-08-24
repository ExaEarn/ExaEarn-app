# ExaEarn Phase 5B Bankruptcy and Insurance

Bankruptcy handling is separate from liquidation-price logic.

If liquidation creates surplus, the liquidation fee/surplus path credits:

```text
futures_insurance_fund:{asset}
```

If liquidation creates deficit, `FuturesInsuranceFundService` attempts canonical ledger-backed insurance coverage.

If the insurance fund cannot cover the deficit, the engine enters ADL evaluation.

No ordinary customer Futures account is left silently negative as a resolved state.
