# EXA Flight Reservations

## Real-Money Entry

Real-money EXA Flight entries now create a canonical `ReservationService` reservation before ledger locking.

Flow:

```text
Eligibility
-> treasury risk
-> reserve user funding account
-> create entry with reservation_id
-> ledger lock funding -> game_locked
-> consume reservation
-> Phase 17 accounting event
```

The entry stores `reservation_id`, and duplicate idempotency keys return the existing entry without creating another reservation.

## Demo Mode

Demo/free-play entries do not create reservations, do not write ledger entries, and do not create withdrawable liabilities.

## Cancelled Round Recovery

If a pre-running round is cancelled:

- Active reservations are released.
- Already locked real-money principal is refunded from `game_locked` back to `funding`.
- Demo entries are marked cancelled.

## Tested Invariants

- Real-money entry creates exactly one reservation.
- Retry does not duplicate reservations or entries.
- Demo mode creates no financial reservation.
- Cancelled round refunds locked principal.
