# ExaEarn Phase 15F Liquidity Capital

Market-maker capital readiness uses canonical institutional subaccount ledger accounts through `MarketMakerProgramService::capitalReadiness`.

## Policy

- Listing liquidity agreements define required base and quote commitments.
- Institutional subaccount balances are projected from the canonical ledger.
- A market maker is capital-ready only when both base and quote available balances meet commitments.
- Direct wallet or mutable balance fields are not used as authority.

## Remaining Operational Dependency

External liquidity contracts, actual external funding and OTC counterparty capital commitments must be confirmed operationally before public launch.
