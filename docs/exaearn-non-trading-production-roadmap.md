# ExaEarn Non-Trading Production Roadmap

## Phase A: Truth and Runtime Safety

1. Restore missing `NftService` or disable NFT routes from production navigation.
2. Remove support ticket fake success and persist tickets.
3. Remove or label crowdfunding mock fallback in production.
4. Disable production paths that simulate provider success.

## Phase B: Financial Integrity

1. Migrate Giftcards to reservation/settlement/reversal.
2. Migrate AgriTech investment and harvest payout to ledger-backed escrow.
3. Build crowdfunding escrow, milestone release, refund, and dispute workflows.
4. Add NFT mint/listing/bid/purchase ledger settlement and webhook reconciliation.

## Phase C: Admin and Operations

1. Replace placeholder admin module routes.
2. Add product-specific reconciliation dashboards.
3. Add incident/audit workflows for every money module.
4. Add notification coverage and delivery observability.

## Phase D: Compliance and External Readiness

1. Add product-level jurisdiction rules.
2. Complete KYB/KYC requirements for instructors, merchants, farmers, creators, sponsors, and affiliates.
3. Complete external provider contracts and production credentials.
4. Establish operations runbooks and staffing.

## Phase E: Mobile and UX Parity

1. Keep mobile surfaces limited to products with backend truth.
2. Expand ExaSkills, NFT, Crowdfunding, AgriTech, Support, and ExaPay only after backend gates pass.

