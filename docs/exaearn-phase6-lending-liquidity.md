# ExaEarn Phase 6 Lending Liquidity

Borrowing requires real `margin_lending_pools.available_liquidity`.

The software does not fabricate liquidity or leverage. If a pool has insufficient available liquidity, borrow requests are rejected.

Production launch requires ExaEarn to allocate actual treasury or approved lending capital into enabled pools before users can borrow.
