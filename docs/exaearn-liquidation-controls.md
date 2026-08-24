# ExaEarn Liquidation Controls

Phase 7 does not replace existing Futures and Margin liquidation engines.

## Existing Foundations

- Futures supports mark/index-price based risk, partial liquidation, insurance/ADL foundations and reconciliation tests.
- Margin supports unsafe-account detection, bad-debt records, liquidation execution through Spot, loan repayment and private realtime updates.

## Phase 7 Additions

- central readiness visibility
- negative-equity detection service
- incident creation for negative-equity cases
- unified reconciliation records for liquidation-related accounting failures

Future expansion can add automated liquidation scheduling and auction routes without changing the Phase 7 control tables.

