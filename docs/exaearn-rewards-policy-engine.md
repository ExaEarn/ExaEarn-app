# ExaEarn Rewards Policy Engine

## Purpose

The Reward Policy Engine centralizes rewards, rebates, referral economics and promotion incentives. It is separate from the ledger: rewards can be approved, blocked or sent to review before any distribution path credits a user.

## Controls

- Fixed, percentage, revenue-share, tiered and milestone rewards.
- Daily user caps.
- Lifetime user caps.
- Campaign budget tracking.
- Reward-abuse inspection through existing `RewardSecurityService`.
- Decision snapshots in `reward_policy_decisions`.

## Compatibility

`RewardEngineService` now attempts to use an approved central reward policy. If no policy exists, the legacy `RewardActivity` configuration remains active so existing ExaEarn rewards continue to function.

## Launch Notes

Before public reward campaigns:

- Seed approved reward rules.
- Set campaign budgets.
- Monitor blocked decisions and reward abuse flags.
- Reconcile issued ExaPoints against approved decisions.
