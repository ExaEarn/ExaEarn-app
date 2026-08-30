# ExaEarn Non-Trading Financial Integrity Current Audit

Status date: 2026-08-29

## Scope

This audit reviewed current non-trading money-moving products against the canonical ExaEarn financial core:

- `LedgerService`
- `ReservationService`
- `SettlementService`
- `BalanceProjectionService`
- `FinancialDecimal`
- Phase 16 compliance controls
- Phase 17 accounting/reconciliation patterns
- Phase 18 security controls
- Phase 19 recovery/operations patterns

The audit focused on product business services and controllers. Canonical wallet, transaction, treasury, ledger and balance projection infrastructure is intentionally excluded from the no-direct-mutation rule because it is the authorized compatibility/core layer.

## Direct Mutation Scan

The static guard in `NonTradingFinancialInvariantsTest` now checks the non-trading product services for prohibited direct authoritative balance mutation patterns:

- assignment to `available_balance`
- assignment to `locked_balance`
- balance assignment derived from wallet balance fields
- `increment()` / `decrement()` on authoritative balance columns
- `DB::raw()` balance changes against wallet balance columns

Current result: no prohibited direct wallet mutation in the guarded non-trading product services.

## Product Classification

| Product | Classification | Current Basis |
|---|---:|---|
| Giftcards | CANONICAL | Purchase uses canonical reservation and `SettlementService::giftcardPurchaseSettle`; provider unknown keeps reservation active; refunds use ledger-backed credit. Fee calculator now returns decimal strings rather than authoritative PHP float amounts. |
| Staking | MOSTLY_CANONICAL | Principal reservation, activation, unstake/release and reward allocation are ledger-aware. Existing staking-domain services still contain legacy decimal fallback helpers and require continued cleanup, but core money paths fail closed. |
| ExaSkills | CANONICAL | Paid course purchases, challenge escrow, instructor payable/payout and subscriptions use ledger/account state and product reconciliation. Instructor earnings are treated as payables/domain accounting, not a wallet source of truth. |
| ExaPay / Fiat | CANONICAL | Payment intents, capture, merchant payables, refunds, dispute paths and merchant settlement flow through settlement/ledger-backed services with idempotency and reconciliation. |
| ExaCard | CANONICAL | Funding, unload, authorization, provider-result handling and reconciliation use card settlement/reservation services. Provider setup remains operational. |
| Games / EXA Flight | CANONICAL | Entry, locked game funds, cashout, loss settlement, refunds and treasury exposure use canonical ledger accounts and game reconciliation. Real-money launch remains legally gated. |
| AgriTech | CANONICAL | Investment purchase coordinates share reservation with user fund reservation and `SettlementService::agriInvestmentEscrow`; disbursement, harvest revenue, investor payout and refunds have ledger-backed services. Real farm revenue/provider evidence remains operational. |
| Crowdfunding | CANONICAL | Pledges reserve and settle into crowdfunding escrow; milestone release recognizes creator payable and payout; refunds reverse escrow to backers with idempotent batches. Investment crowdfunding stays disabled without legal enablement. |
| NFT Marketplace | CANONICAL | Purchases reserve buyer funds, split seller proceeds/fee/royalty/network cost through ledger accounts, update ownership atomically, and record reconciliation breaks for chain/finality problems. Real mainnet/finality provider remains operational. |
| Affiliate / Rewards | CANONICAL | Qualified events create commission/payable state; releases, payouts, clawbacks and reconciliation are traceable. ExaPoints remain non-cash unless policy changes. |
| Support | NOT_APPLICABLE | Support creates tickets/chat/disputes and escalates to authoritative product/finance workflows. It does not expose a wallet adjustment endpoint. |

## Remaining Notes

Legacy core wallet/transaction/treasury services still contain direct balance mutation by design as compatibility or authorized core infrastructure. This task did not rewrite those systems. Future hardening should keep reducing legacy compatibility paths, but product business services must not depend on them as independent financial truth.

