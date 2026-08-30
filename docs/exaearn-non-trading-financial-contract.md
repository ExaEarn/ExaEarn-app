# ExaEarn Non-Trading Financial Contract

Every real unit of non-trading value must have a traceable source, state, owner, liability, settlement, accounting entry and reconciliation path.

## Canonical Flow

```text
Business event
-> eligibility and security
-> pricing snapshot
-> reservation where funds must be held
-> product/provider action
-> settlement
-> double-entry ledger
-> balance projection
-> Phase 17 accounting
-> reconciliation
-> incident/recovery if needed
```

## Required Services

Money-moving product services must use:

- `FinancialDecimal` for authoritative backend arithmetic.
- `PricingPolicyEngine` for commercial terms where the product has fees, spreads, provider costs, rewards or promotions.
- `ReservationService` before uncertain operations that hold user funds.
- `SettlementService` or product-specific settlement wrappers for economic completion.
- `LedgerService` for double-entry posting.
- `BalanceProjectionService` for available/reserved/total views.
- Product reconciliation services for post-settlement verification.

## Prohibited Behavior

Product business services must not:

- directly credit or debit wallet balances;
- directly mutate `available_balance` or `locked_balance`;
- create withdrawable rewards without a funding source and ledger entry;
- recognize escrow as revenue;
- pay a merchant, creator, instructor, seller, affiliate or investor without a payable/source account;
- silently repair unexplained financial differences;
- retry provider/chain unknown states as if failure were proven;
- use PHP float or JavaScript numbers for authoritative financial settlement.

## Provider Unknown

Timeout does not equal failure. If a request may have reached a provider or chain, the product must move into `PROVIDER_UNKNOWN`, `PENDING_FINALITY`, `MANUAL_REVIEW` or an equivalent safe state. Funds must remain reserved or otherwise protected until reconciliation proves the outcome.

## Reversals

Historical ledger rows are immutable. Reversals and refunds use compensating double-entry transactions with deterministic references.

