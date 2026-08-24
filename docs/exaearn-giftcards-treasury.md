# ExaEarn Giftcards Treasury

Giftcards uses explicit system accounts:

- `giftcard_provider_settlement`
- `giftcard_payout_treasury`
- `giftcard_fee_revenue`
- `giftcard_refund_liability`

`GiftCardTreasuryService::overview()` returns provider balance, ledger projections for these accounts, and whether a real provider is configured.

Treasury funds and customer liabilities remain separate through account type and ownership.

