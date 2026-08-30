# ExaEarn Non-Trading Financial Invariants

The following invariants apply across Giftcards, Staking, ExaSkills, ExaPay, ExaCard, Games, AgriTech, Crowdfunding, NFT, and Affiliate/Rewards:

1. The canonical ledger is the only authoritative user-money system.
2. Product tables cannot directly mutate authoritative balances.
3. Every settled movement is represented by a balanced ledger transaction with asset identity.
4. Every reservation is consumed, released, or explicitly unresolved.
5. Provider-unknown outcomes do not produce unsafe success or failure finality.
6. Stable idempotency identities prevent duplicate settlement and accounting.
7. Refunds, reversals, and chargebacks preserve original history through offsetting entries.
8. Payables and escrows are not available balances or revenue before valid settlement.
9. Estimated staking rewards, APY, campaign targets, and UI projections create no liability.
10. Claimed staking rewards cannot exceed verified payable rewards.
11. Staking principal cannot be released before verified unstake completion.
12. Every material accounting journal balances per asset using deterministic decimal arithmetic.
13. Accounting never overwrites ledger balances.
14. Treasury transfers are not revenue merely because cash moved.
15. ExaPoints are not cash, crypto, or withdrawable liabilities unless policy explicitly changes their classification.
16. Reconciliation detects and reports discrepancies; it does not silently repair history.

