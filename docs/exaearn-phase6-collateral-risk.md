# ExaEarn Phase 6 Collateral Risk

Collateral eligibility is configured per asset in `margin_asset_configs`.

Risk valuation separates:

- normal market value
- collateral factor
- adjusted collateral value
- liabilities
- health factor

Health factor:

```text
adjusted_collateral_value / gross_liability_value
```

No new borrow or transfer-out is allowed if projected health breaches configured thresholds.
