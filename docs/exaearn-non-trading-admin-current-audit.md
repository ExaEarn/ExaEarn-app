# ExaEarn Non-Trading Admin Current Audit

## Scope

This audit covers the existing non-trading admin operations surface for ExaCard, Staking, ExaSkills, Giftcards, Crowdfunding, AgriTech, NFT, ExaPay, Affiliate/Rewards, Games, Notifications, and Support.

## Findings

| Area | Current State | Action |
| --- | --- | --- |
| Legacy `/api/admin/*` module routes | Several routes returned placeholder JSON such as `"... module data"` | Replaced with real operations controllers or explicit `NOT_READY` responses |
| Admin UI module fallback | API failure previously showed mock fallback data and simulated action success | Changed to empty unavailable state; actions are disabled unless returned by a real API |
| Sports/Lottery admin menu | Legacy modules were visible despite not being part of the hardened non-trading admin standard | Removed from primary admin navigation and left direct routes as explicit `NOT_READY` |
| Notifications permission | Menu required send permission for a read overview | Changed to `notifications.view` |
| Non-trading admin controllers | Reviewed for direct balance mutation patterns | Added regression test preventing direct wallet/balance mutation in non-trading admin controllers |

## Real Controller Coverage

| Product | Controller / Page | Status |
| --- | --- | --- |
| ExaCard | `ExaCardOperationsController`, admin ExaCard module | Real backend |
| Staking | `StakingAdminController` | Real backend |
| ExaSkills | `ExaSkillsAdminController` | Real backend |
| Giftcards | `GiftCardAdminController::center` | Real backend |
| Crowdfunding | `CrowdfundingOperationsController`, dedicated admin page | Real backend |
| AgriTech | `AgriTechOperationsController` | Real backend |
| NFT | `NftOperationsController` | Real backend |
| ExaPay | `ExaPayOperationsController`, admin ExaPay module | Real backend |
| Affiliate/Rewards | `AffiliateOperationsController`, `PricingRewardsController` | Real backend |
| Games / EXA Flight | `FlightGameAdminController`, dedicated admin page | Real backend |
| Notifications | `NotificationOperationsController`, dedicated admin page | Real backend |
| Support | `SupportOperationsController`, `SupportLiveChatOperationsController` | Real backend |

## Non-Production Legacy Modules

Sports and Lottery are no longer promoted in the admin navigation for this standard. Their compatibility routes return explicit `NOT_READY` status instead of fake module data.
