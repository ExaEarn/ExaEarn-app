# ExaEarn Admin Reconciliation Operations

## Standard

Non-trading reconciliation views expose findings from the product reconciliation services. They do not silently modify ledger, reservation, treasury, fulfillment, or user-balance records.

## Product Coverage

| Product | Reconciliation Source |
| --- | --- |
| Giftcards | `GiftCardReconciliationService`, Giftcard admin center |
| Crowdfunding | `CrowdfundingReconciliationService` |
| Games / EXA Flight | game reconciliation/admin operations |
| ExaPay | merchant payment reconciliation |
| ExaCard | card treasury/provider reconciliation |
| NFT | media/storage and marketplace reconciliation |
| AgriTech | milestone/disbursement/refund reconciliation |
| Notifications | delivery/read-state health |
| Support | SLA/live-chat operations health |

Critical findings must remain visible until resolved by the appropriate audited workflow.
