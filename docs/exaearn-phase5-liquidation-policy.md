# ExaEarn Phase 5 Liquidation Policy

Liquidation is based on server-authoritative mark price and maintenance margin failure.

Phase 5 changed liquidation so it no longer directly mutates legacy futures wallet rows.

Liquidation now records:

- `liquidation_id`
- position
- mark price
- liquidation price
- quantity
- liquidation fee
- insurance impact
- ledger reference

Full production partial-liquidation execution remains a controlled cutover item.
