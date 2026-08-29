# EXA Flight Treasury Risk

## Service

`GameTreasuryRiskService` evaluates real-money entry exposure before funds are locked.

## Checks

The service checks:

- Available game treasury
- Required treasury reserve
- Existing open round exposure
- New entry maximum payout
- Maximum permitted round liability
- Maximum permitted platform exposure
- Treasury coverage

## Rejection

If accepting an entry would exceed configured risk limits, the service rejects the entry before ledger mutation and creates a `flight_game_risk_incidents` record.

## Principle

Customer liabilities are not treated as company risk capital.
