# ExaEarn P2P Escrow

P2P escrow is reservation-backed and ledger-settled.

## Order Creation

When an order opens, ExaEarn:

1. Locks the advertisement row.
2. Validates limits and risk.
3. Reduces available ad inventory.
4. Creates the P2P trade row.
5. Reserves the seller funding account through `ReservationService`.
6. Stores `escrow_reservation_id` on the trade.
7. Writes a `p2p_escrows` record.

The reservation prevents the same crypto from being withdrawn, converted, sold, or reused by another P2P order.

## Release

Release is settlement. Seller crypto is moved to the buyer only through:

`SettlementService::p2pEscrowRelease()`

This posts a balanced ledger transaction:

- Debit seller funding account.
- Credit buyer funding account.
- Credit fee revenue where fees are configured.
- Consume the reservation.

The ledger reference is deterministic:

`p2p:release:{trade_uuid}`

Retries return or fail against the same reference and cannot double-credit the buyer.

## Cancellation / Expiration

Before buyer marks paid, safe cancellation releases the reservation and restores ad inventory.

After buyer marks paid, seller cannot unilaterally cancel. A dispute/review path is required.
