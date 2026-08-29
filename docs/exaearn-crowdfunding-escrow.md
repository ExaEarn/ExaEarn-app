# ExaEarn Crowdfunding Escrow

Crowdfunding escrow is a canonical ledger account type, not a product wallet.

## Account Types

- Backer: user `funding` account for the campaign asset.
- Escrow: system `crowdfunding_escrow`.
- Creator payable: system `crowdfunding_creator_payable`.
- Creator payout: creator user `funding` account.

## Rules

- Pledges require reservations.
- Held pledge funds remain liabilities until released or refunded.
- No direct wallet balance mutations are allowed.
- Reconciliation checks pledge ledger references and open escrow totals.

