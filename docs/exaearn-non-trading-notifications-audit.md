# ExaEarn Non-Trading Notifications Audit

## Maturity

Current level: Level 2 shared service.

## What Exists

- `Notification` and `NotificationLog` tables.
- `NotificationService` creates in-app notifications and dispatches email/push jobs.
- User notification controller supports list, unread, mark read, delete, clear all, and stats.
- Admin platform can send notifications and list notification records.
- Product-specific notification services exist for ExaCard.

## Gaps

- Product coverage is inconsistent across non-trading modules.
- No unified notification preference/template center was confirmed.
- Notification delete/cleanup is destructive rather than archive-first.
- Retry logic exists but delivery attempt observability, deduplication, provider health, and template localization are incomplete.

## Required Next Work

1. Create product event notification matrix.
2. Add deduplication keys and immutable delivery logs per channel.
3. Add user channel preferences and localization.
4. Connect ExaSkills, Giftcards, AgriTech, Crowdfunding, NFT, Staking, ExaPay, Rewards, and Support events.

