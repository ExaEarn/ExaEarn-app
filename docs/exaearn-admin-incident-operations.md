# ExaEarn Admin Incident Operations

## Incident Model

Admin incident views should expose:

- incident or case identifier
- product/component
- severity
- status
- evidence
- affected users/accounts where permissible
- timestamps
- reviewer and resolution
- audit reference

## Statuses

Recommended statuses are `OPEN`, `ACKNOWLEDGED`, `MITIGATING`, `MONITORING`, `RESOLVED`, and `DISMISSED` where applicable to the product.

## Safety

Incident tooling must not fabricate events, delete evidence, or mutate financial records. Resolution records explain the decision and link to the canonical product workflow.
