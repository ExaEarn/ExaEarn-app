# ExaEarn Phase 17 Backing

Backing calculations use:
- Customer/counterparty liabilities from canonical user accounts.
- Explicit verified asset sources from `finance_asset_sources`.

Restricted or stale sources are excluded from eligible backing.

Snapshot fields:
- liability
- gross assets
- restricted assets
- eligible backing
- surplus/deficit
- coverage ratio
- status
- freshness

No external bank, custody, chain, or provider balance is fabricated.
