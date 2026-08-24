# ExaEarn Phase 3 Order Book Protocol

## Snapshot

`GET /api/v1/market/order-book/{symbol}`

Response:

```json
{
  "symbol": "BTC/USDT",
  "sequence": 42,
  "bids": [{"price": "100000.00", "quantity": "0.10"}],
  "asks": [{"price": "100100.00", "quantity": "0.20"}],
  "timestamp": "...",
  "source": "EXAEARN_INTERNAL"
}
```

## Sorting

- Bids sort highest price first.
- Asks sort lowest price first.
- Levels aggregate open resting order quantity at the same price.

## Zero Quantity

Realtime deltas may use zero quantity to represent level removal in future fanout. Snapshot responses omit zero quantity levels.

## Reference Fallback

If ExaEarn has no internal resting book, a provider book may be returned with:

```text
source = EXTERNAL_REFERENCE
is_internal = false
reference_provider = BINANCE
```

Clients must not treat these rows as ExaEarn orders.

