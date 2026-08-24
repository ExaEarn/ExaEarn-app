# ExaEarn Phase 12 Copy Capacity And Surveillance

## Capacity

Lead trader capacity is enforced before a follower relationship is created.

Controls:

- `COPY_TRADING_MAX_FOLLOWERS_PER_LEAD`
- `COPY_TRADING_MAX_AUM_PER_LEAD`

Admin controls can pause a lead, pause new copy, or close the lead to new followers without blocking follower manual exits, liquidation, or risk-reducing close flows.

## Surveillance

Surveillance records are stored as both events and reviewable cases:

- `copy_surveillance_events`
- `copy_surveillance_cases`

Current automated signals:

- self-copy
- related-account signal based on available account relation indicators

The system stores reviewable evidence and does not automatically accuse or terminate a lead from one signal. More advanced market-impact, wash-trading, and performance-manipulation models can write to the same case table.
