# ExaEarn NFT Marketplace Audit

## Maturity

Current level: Level 1 foundation with a critical runtime gap.

## What Exists

- Web marketplace in `apps/web/src/NFTMarketplace`.
- API client in `apps/web/src/services/nftApi.js`.
- `NftController` exposes dashboard, collections, marketplace, mint, upgrade, subscribe, listing, buy, auction, bid, and finalize actions.
- Migrations and models exist for collections, NFTs, listings, sales, auctions, upgrades, subscriptions, staking positions, fiat profiles, credit lines, revenue events, and prices.
- Blockchain service methods exist for NFT-related operations.

## Critical Finding

`NftController` and `WebhookController` import `App\Services\NftService`, but no `NftService` class was found under `backend/api-gateway/app`. This likely makes NFT routes fail at container resolution/runtime.

## Production Blockers

- Missing service implementation.
- No proven canonical ledger settlement for mint, listing, bid escrow, purchase, royalty, fee, or refund.
- No confirmed on-chain event finality/reconciliation.
- No admin operations controller dedicated to NFT marketplace review, fraud, royalties, disputes, or delisting.
- No focused backend tests were found for NFT marketplace lifecycle.

## Required Next Work

1. Restore or implement `NftService` against the existing models and blockchain service.
2. Add canonical payment/reservation/settlement for every paid NFT action.
3. Add webhook idempotency and finality checks.
4. Add admin controls and tests.

