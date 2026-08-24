# ExaEarn Fiat Crypto Convert

Phase 10 introduces a fiat-aware quote boundary for fiat-to-crypto and crypto-to-fiat conversion.

Execution remains gated through the Phase 4 Convert Engine and canonical ledger settlement. Phase 10 does not create a second conversion ledger path.

This prevents:

- double spending
- duplicate quote execution
- direct frontend price authority
- untracked fiat conversion settlement

At least one conversion side must be a configured fiat currency.
