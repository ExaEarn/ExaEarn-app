# ExaEarn Phase 5 Futures Cutover

No actual production Futures market cutover was performed in Phase 5.

Safe sequence before live Futures migration:

1. Internal test perpetual.
2. Shadow validation against legacy formulas.
3. No open live positions, or fully proven migration state.
4. Low-risk market canary.
5. Larger markets only after reconciliation passes.

Open position migration must not be automatic unless position size, entry price, margin, funding history, PnL, liquidation state and ledger state are provably correct.
