# Staking API

User endpoints:

`GET /api/v1/staking/assets`
`GET /api/v1/staking/products`
`GET /api/v1/staking/products/{slug}`
`GET /api/v1/staking/portfolio`
`GET /api/v1/staking/positions`
`GET /api/v1/staking/positions/{publicId}`
`POST /api/v1/staking/positions`
`POST /api/v1/staking/positions/{publicId}/unstake`
`POST /api/v1/staking/positions/{publicId}/claim-native-rewards`
`POST /api/v1/staking/positions/{publicId}/claim-exatoken-rewards`
`PATCH /api/v1/staking/positions/{publicId}/auto-compound`
`GET /api/v1/staking/rewards`
`GET /api/v1/staking/transactions`
`GET /api/v1/staking/apy-history`
`GET /api/v1/staking/terms`
`POST /api/v1/staking/terms/accept`
`GET /api/v1/staking/exatoken-campaigns`
`GET /api/v1/staking/network-statuses`
`GET /api/v1/staking/unbonding-estimates`

Current implementation notes:

List endpoints read from the production staking tables and exclude XRP, BTC, USDT, USDC, and Pi from Native PoS Staking.

`POST /api/v1/staking/positions` reserves user-held assets through the internal ledger, creates a pending position, and refuses activation unless the network provider reports healthy and ready.

`POST /api/v1/staking/positions/{publicId}/unstake` creates an unstake request only for active positions. Principal release remains blocked until network or approved provider confirmation is recorded.

Native and ExaToken reward claim endpoints fail closed when no verified reward allocation or funded ExaToken reserve allocation exists. They must not generate balances from displayed APY.
