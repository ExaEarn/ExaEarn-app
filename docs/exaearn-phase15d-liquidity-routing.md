# ExaEarn Phase 15D Liquidity Routing

OTC providers must be explicitly registered in `otc_liquidity_providers`.

Supported provider types:

- `EXAEARN_MARKET_MAKER`
- `INSTITUTIONAL_LP`
- `EXAEARN_TREASURY`
- `OTC_COUNTERPARTY`
- `EXTERNAL_VENUE`

Market makers from Phase 15C do not automatically become OTC liquidity providers. They require explicit OTC provider configuration and must be active and in normal safety mode.
