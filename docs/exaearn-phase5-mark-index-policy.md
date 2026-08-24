# ExaEarn Phase 5 Mark / Index Policy

## Index Price

Index price is built from external/reference spot constituents only after validation.

Invalid constituents are ignored when they are:

- stale
- zero or negative
- malformed
- outside configured deviation limits

If too few healthy constituents remain, the index calculation fails closed.

## Mark Price

Mark price is distinct from last traded price.

```text
mark_price = index_price * (1 + clamped_premium_rate + funding_basis)
```

Mark price is used for risk, PnL display, funding, and liquidation checks. It is not directly controlled by one last trade.
