# ExaEarn Admin RBAC Matrix

| Product | Read Permission | Write / Action Permission |
| --- | --- | --- |
| ExaCard | `finance.view` | card operation permissions plus audit where configured |
| Staking | `staking.manage` | `staking.manage` |
| ExaSkills | `edtech.manage` | `edtech.manage` |
| Giftcards | `giftcard.manage` | `giftcard.manage` |
| Crowdfunding | `crowdfunding.view` | `crowdfunding.review`, `crowdfunding.milestones`, `crowdfunding.release`, `crowdfunding.refund`, `crowdfunding.manage` |
| AgriTech | `agri.manage` | `agri.manage` |
| NFT | `nft.manage` | `nft.manage` |
| ExaPay | `finance.view` | merchant/payment permissions where configured |
| Affiliate / Rewards | `reward.manage` | `reward.manage` |
| Games / EXA Flight | `games.view` | `games.manage`, `games.pause`, `games.risk`, `games.reconcile`, `games.responsible_gaming`, `games.settings` |
| Notifications | `notifications.view` | notification send/schedule permissions |
| Support | `support.view` | support/live-chat operation permissions |
| Logs | `logs.view` | export/evidence permissions where configured |
| Security | `security.view` / `security.operations` | security operation permissions |

RBAC is enforced server-side. Frontend visibility is a convenience only and must never be treated as authorization.
