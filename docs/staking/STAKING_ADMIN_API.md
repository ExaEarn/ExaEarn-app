# Staking Admin API

Admin v1 endpoints:

`GET /api/admin/v1/staking/assets`
`PATCH /api/admin/v1/staking/assets/{assetId}`
`POST /api/admin/v1/staking/assets/{assetId}/emergency-pause`
`POST /api/admin/v1/staking/assets/{assetId}/request-mainnet-activation`
`GET /api/admin/v1/staking/products`
`POST /api/admin/v1/staking/products`
`PUT /api/admin/v1/staking/products`
`GET /api/admin/v1/staking/validators`
`POST /api/admin/v1/staking/validators`
`GET /api/admin/v1/staking/provider-health/{symbol}`
`GET /api/admin/v1/staking/approvals`
`POST /api/admin/v1/staking/approvals/{publicId}/decision`
`GET /api/admin/v1/staking/wallets`
`GET /api/admin/v1/staking/delegation-batches`
`GET /api/admin/v1/staking/reward-batches`
`GET /api/admin/v1/staking/reconciliation-reports`
`GET /api/admin/v1/staking/exatoken-campaigns`
`GET /api/admin/v1/staking/audit-logs`

Mainnet activation and validator changes create pending dual-approval records. The requesting administrator cannot approve their own request.

Operational review endpoints are table-backed. They do not fabricate provider status, reward batches, wallet balances, or reconciliation success.

Still required before production: signer-change, large delegation, large reward distribution, ExaToken reserve movement, manual ledger adjustment, slashing adjustment, and emergency principal-release write paths must be connected to the same dual-approval table before those actions are enabled.
