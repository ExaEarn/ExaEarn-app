# ExaEarn Non-Trading Financial Path Inventory

| Product | Action | Source Funds | Reservation | Settlement/Ledger | Reversal | Reconciliation | External State | Status |
|---|---|---|---|---|---|---|---|---|
| Giftcards | Buy card | User funding account | `giftcard_purchase` | `giftcardPurchaseSettle` to provider settlement and fee revenue | `giftcardRefundCredit` | `GiftCardReconciliationService` | Provider can be `SUCCESS`, `FAILED`, `PROVIDER_UNKNOWN` | CANONICAL |
| Giftcards | Sell card payout | Giftcard payout treasury | Not required after approval | `giftcardSellPayout` | Clawback/manual finance workflow | Giftcard reconciliation/admin center | Provider validation required | CANONICAL |
| Staking | Stake/unstake/reward | User principal/reward payable | Principal reservation | Staking ledger service and settlement paths | Unstake/reward reversal paths | Staking reconciliation jobs | Validator/provider states | MOSTLY_CANONICAL |
| ExaSkills | Paid course | User funding account | Purchase/escrow where needed | Ledger-backed enrollment/revenue/payable | Refund workflow | Skills reconciliation incidents | Media/provider none for money | CANONICAL |
| ExaSkills | Challenge escrow | Participant funding | Challenge escrow | Winner payout through ledger | Challenge refund | Skills reconciliation | Domain judging state | CANONICAL |
| ExaPay | Merchant payment capture | Payer funding/provider payment method | Intent/capture dependent | Merchant payable and fees through settlement | `PaymentRefundService` | Merchant reconciliation | Provider/checkout state | CANONICAL |
| ExaCard | Fund/unload/authorization | User funding/card liability | Funding/authorization holds | `CardSettlementService` | unload/refund/reversal | `CardReconciliationService` | Card provider webhooks | CANONICAL |
| Games | Entry/cashout/loss | User funding/game locked liability | Game entry hold | Flight game settlement accounts | Refund/cancel round | `GameReconciliationService` | Legal mode/fairness state | CANONICAL |
| AgriTech | Investment | User funding | `agritech_investment` | `agriInvestmentEscrow` | `agriInvestmentRefund` | `AgriTechReconciliationService` | Farm/legal/evidence required | CANONICAL |
| AgriTech | Harvest payout | Verified harvest revenue | Not user-funded | `agriVerifiedRevenue`, deductions, investor payout | Finance correction | AgriTech reconciliation | Real revenue verification | CANONICAL |
| Crowdfunding | Pledge | User funding | `crowdfunding_pledge` | Crowdfunding escrow ledger | Campaign refund batch | `CrowdfundingReconciliationService` | Campaign/milestone state | CANONICAL |
| Crowdfunding | Creator payout | Crowdfunding escrow | Milestone gate | Creator payable then payout | Finance/product reversal | Crowdfunding reconciliation | Maker-checker/admin review | CANONICAL |
| NFT | Fixed price purchase | Buyer funding | `nft_purchase` | Seller payable, royalty, fee, network cost ledger split | Manual/chain-aware reversal | `NftService::reconciliation` | Chain finality/reorg | CANONICAL |
| NFT | Auction bid | Bidder funding | `nft_bid` | Finalization pending sale settlement | Outbid release | NFT reconciliation | Auction/finality state | CANONICAL |
| Affiliate | Commission payout | Commission payable/treasury | Hold/maturity state | Affiliate payout ledger | Clawback | Affiliate reconciliation | Qualified source event | CANONICAL |
| Support | Refund/dispute escalation | Product-owned workflow | N/A | Product/finance service only | Product-owned workflow | Ticket audit trail | Support case state | NOT_APPLICABLE |

