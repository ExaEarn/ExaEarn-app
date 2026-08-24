# ExaEarn Non-Trading Admin Operations Audit

## Stronger Admin Coverage

- ExaCard: dedicated operations controller and reports.
- Staking: asset/product/provider/approval/wallet/batch/reconciliation/audit endpoints.
- ExaSkills: operational overview and challenge winner payout route.
- Giftcards: admin buy/rate/inventory/orders and admin controller coverage.
- Pricing/Rewards: maker-checker pricing and reward policy operations.
- Flight Game: admin summary/settings controller coverage.

## Weak or Placeholder Admin Coverage

- Some `/admin/modules/*` routes return empty placeholder responses for staking, rewards, NFT, AgriTech, crowdfunding, giftcard, campaigns, and notifications rather than real module operations.
- NFT lacks a discovered dedicated admin operations controller.
- Crowdfunding lacks a complete campaign approval, escrow, milestone, and refund admin console.
- Support lacks a unified ticket/dispute admin console.
- AgriTech lacks full financial settlement/reconciliation admin controls.

## Required Admin Gates

1. Replace placeholder module routes with real controllers or remove from production navigation.
2. Ensure every product with money movement has operations, reconciliation, incident, and audit views.
3. Require RBAC and admin audit for all product state changes.

