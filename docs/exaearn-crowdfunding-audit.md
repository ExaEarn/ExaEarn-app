# ExaEarn Crowdfunding Audit

## Maturity

Current level: Level 1 foundation.

## What Exists

- Web pages for listing, creating, viewing, and supporting campaigns.
- `useCrowdfunding` hook and `crowdfundingApi`.
- Static fallback data in `apps/web/src/pages/Crowdfunding/campaignData.js`.
- Backend campaign generation service and broadcast service.
- Tests for campaign generation from NFT, user behavior, and announced partnerships.

## Production Blockers

- The frontend explicitly falls back to mock campaign data.
- No complete pledge/escrow/disbursement/refund ledger product was identified.
- No campaign owner verification, milestone approval, backer refund, or fund release policy was confirmed.
- Campaign generation is marketing/notification logic, not a complete crowdfunding financial engine.

## Required Next Work

1. Build real campaign, pledge, escrow, milestone, update, comment, refund, and payout flows.
2. Route pledges and disbursements through canonical reservations and settlement.
3. Add compliance controls for investment/equity-like campaigns.
4. Remove or clearly label mock fallback data in production.

