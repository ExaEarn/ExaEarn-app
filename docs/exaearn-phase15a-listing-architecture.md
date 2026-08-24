# ExaEarn Phase 15A Listing Architecture

Core backend components:

- `ListingLifecycleService`
- `ListingPortalController`
- `Admin\ListingCenterController`
- `ListingOrganization`
- `ListingApplication`
- `ListingReview`
- `ListingAssetConfiguration`
- `ListingMarketConfiguration`
- `ListingLiquidityRequirement`
- `ListingTestRun`
- `ListingLaunchSchedule`
- `ListingAuditLog`

Integration uses existing ExaEarn tables:

- `blockchain_networks`
- `blockchain_assets`
- `markets`

New assets are registered with deposits, withdrawals, and trading disabled. New markets are created as `PRE_LAUNCH`.
