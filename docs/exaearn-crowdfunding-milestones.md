# ExaEarn Crowdfunding Milestones

Milestones provide controlled release from escrow.

## Lifecycle

`PENDING -> SUBMITTED -> APPROVED -> RELEASED`

Rejected or information-required milestones do not release funds.

## Release Controls

- Milestone must be approved.
- Maker and checker admins must be different.
- Release amount must be positive and covered by escrow.
- Release is idempotent through milestone `release_reference`.

