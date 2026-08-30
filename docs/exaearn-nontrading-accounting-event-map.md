# ExaEarn Non-Trading Accounting Event Map

Phase 17 events classify canonical product ledger transactions. Naming follows implemented service events; an event is not permission to mutate a user balance.

| Product | Material event families | Economic treatment |
|---|---|---|
| Giftcards | purchase, provider cost, fee, refund, sell payout | customer settlement, provider payable/cost, earned fee, offsetting refund |
| Staking | `STAKING_PRINCIPAL_RESERVED`, `STAKING_ACTIVATED`, `STAKING_REWARD_RECOGNIZED`, `STAKING_REWARD_CLAIMED`, `STAKING_UNSTAKED` | principal liability transition, verified reward payable, claim settlement |
| ExaSkills | course purchase, instructor payable/payout, challenge escrow/payout | deferred/earned fee and beneficiary payable |
| ExaPay | capture, refund, chargeback, merchant settlement | merchant payable, platform fee, processor movement, reversal |
| ExaCard | funding, unload, authorization, settlement, refund, chargeback | card liability transition and provider settlement |
| Games | entry, payout/cashout, treasury settlement, refund | locked liability, payout, treasury result; free-play excluded |
| AgriTech | investment, escrow release, revenue, investor payout, refund | escrow/liability and beneficiary settlement |
| Crowdfunding | pledge, escrow, creator payout, refund | escrow liability and creator settlement |
| NFT | purchase, marketplace fee, seller payable, royalty payable, network cost | consideration, fee revenue, beneficiary payables, provider cost |
| Affiliate/rewards | commission, reversal/clawback, payout | commission payable/cost and offsetting reversal |

Every event preserves asset identity, source reference, source service, timestamp, and metadata. ExaPoints retain their configured non-cash classification and are not implicitly treated as withdrawable crypto.

