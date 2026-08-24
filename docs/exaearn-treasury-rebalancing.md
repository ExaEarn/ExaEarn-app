# ExaEarn Treasury Rebalancing

`TreasuryRebalancingService` evaluates threshold-based actions.

Implemented first action:

- Recommend `TREASURY_TO_HOT` when withdrawal reserves fall below minimum.

Design constraints:

- Rebalancing is not automatic fund movement.
- Large movements require admin approval and audit.
- The service avoids sweeping every deposit or trade.
- Future modes can include venue transfers and market hedges once credentials and custody operations are approved.
