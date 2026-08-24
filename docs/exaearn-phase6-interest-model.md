# ExaEarn Phase 6 Interest Model

The initial software model uses a configurable kinked utilization curve:

```text
utilization = borrowed / total liquidity
```

Below optimal utilization:

```text
rate = base_rate + slope_1 * utilization / optimal_utilization
```

Above optimal utilization:

```text
rate = base_rate + slope_1 + slope_2 * excess_utilization / remaining_utilization
```

Rates are capped by `max_rate`. Accrual uses fixed-precision decimal math and stores explicit accrual periods in `margin_interest_accruals`.
