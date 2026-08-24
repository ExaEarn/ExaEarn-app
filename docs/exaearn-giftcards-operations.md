# ExaEarn Giftcards Operations

Operators should monitor:

- `provider_unknown` orders with active reservations
- failed provider attempts
- Giftcard reconciliation findings
- duplicate delivery findings
- treasury account projections
- provider balance and provider readiness
- admin review queue

Operational actions should never directly mutate user wallets. Complete, release, reverse, or refund through canonical settlement and reservation services.

