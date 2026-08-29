# EXA Flight Fairness

## Current Model

EXA Flight uses `FlightFairnessService` to create server seeds, commitments, client seeds, nonces and deterministic crash multipliers.

For each round:

- A server seed is generated before the round result.
- A public server seed hash is stored as the commitment.
- A client seed and nonce are included.
- The final multiplier is derived deterministically from the seed material.
- Completed-round fairness data can expose the revealed seed for verification.

## Immutability

Round fairness fields are persisted on `flight_game_rounds`. Administrative game controls do not provide an endpoint for altering historical round outcomes.

## User Disclosure

The web game now labels demo mode and product-control state clearly. Fairness documentation must not claim an external audit until one exists.

## External Audit

External fairness audit remains required before public real-money launch.
