# ExaEarn Crowdfunding Financial Flow

## Pledge

1. Validate idempotency key.
2. Lock campaign.
3. Validate campaign status, classification, compliance and amount limits.
4. Reserve backer funding account with `ReservationService`.
5. Post double-entry ledger movement from user funding to `crowdfunding_escrow`.
6. Consume reservation.
7. Persist pledge as `HELD_IN_ESCROW`.

## Milestone Payout

1. Creator submits milestone evidence.
2. Admin reviews milestone.
3. Maker/checker release is required.
4. Ledger moves escrow to `crowdfunding_creator_payable`.
5. Ledger moves payable to creator funding.

## Refund

1. Campaign must be failed, cancelled, suspended, refunding or already refunded.
2. Refund batch is persisted.
3. Each held pledge is refunded by double-entry ledger movement from escrow to backer funding.
4. Retry returns the existing batch and does not create duplicate economic effects.

