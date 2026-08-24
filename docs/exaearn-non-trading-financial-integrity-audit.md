# ExaEarn Non-Trading Financial Integrity Audit

## Summary

The canonical financial core exists, but non-trading products are unevenly migrated. Some newer modules correctly use `LedgerService`, `SettlementService`, or product ledger services. Several older modules still mutate local balances or business records directly.

## Ledger-Backed or Mostly Ledger-Backed

- Staking: principal reservation, unstaking, activation, and reward allocation paths are ledger-aware and fail closed in key cases.
- ExaSkills: paid course purchases and challenge escrows use double-entry ledger entries.
- ExaPay/Fiat: Phase 10 payment tests prove settlement, reservation release, webhook idempotency, and refund reversal.
- ExaCard: funding/unload/authorization/reconciliation paths are documented and tested through ledger/reservation flows.
- EXA Flight: entries, cashout, and losses use ledger accounts.

## Not Yet Canonical Enough

- Giftcards: direct `Wallet.available_balance` and `locked_balance` mutations exist beside ledger entries.
- AgriTech: investments and harvest returns are domain-record backed, but user cash reservation/debit and investor payout settlement are not canonical.
- NFT Marketplace: service layer is missing, so financial integrity cannot be established.
- Crowdfunding: no complete escrow/disbursement/refund ledger workflow was identified.
- Support: no financial workflow, but disputes/refunds need integration with finance modules.

## Required Financial Gates

1. No non-trading product may directly mutate authoritative wallet balances.
2. Every paid action needs idempotency, reservation where appropriate, settlement, reversal, and reconciliation.
3. Provider success must not be simulated in production paths.
4. Every money product needs admin-visible reconciliation and incident handling.

