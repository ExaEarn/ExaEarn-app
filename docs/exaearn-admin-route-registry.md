# ExaEarn Admin Route Registry

| Route | Source | Status |
| --- | --- | --- |
| `/api/admin/p2p` | `P2POperationsController::overview` | Real |
| `/api/admin/staking` | `StakingAdminController::assets` | Real |
| `/api/admin/rewards` | `PricingRewardsController::overview` | Real |
| `/api/admin/nft` | `NftOperationsController::overview` | Real |
| `/api/admin/agritech` | `AgriTechOperationsController::summary` | Real |
| `/api/admin/edtech` | `ExaSkillsAdminController::overview` | Real |
| `/api/admin/crowdfunding` | `CrowdfundingOperationsController::overview` | Real |
| `/api/admin/giftcard` | `GiftCardAdminController::center` | Real |
| `/api/admin/notifications` | `NotificationOperationsController::overview` | Real |
| `/api/admin/logs` | `ActivityLogController::allLogs` | Real |
| `/api/admin/security` | `SecurityOperationsController::overview` | Real |
| `/api/admin/system` | `ReliabilityOperationsController::overview` | Real |
| `/api/admin/sports` | explicit `NOT_READY` | Not production-enabled under this standard |
| `/api/admin/lottery` | explicit `NOT_READY` | Not production-enabled |

The generic `/api/admin/module/{module}` endpoint remains a compatibility/read aggregation path, but product operations should use the real product-specific controllers listed above.
