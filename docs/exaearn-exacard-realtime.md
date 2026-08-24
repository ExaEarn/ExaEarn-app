# ExaCard Realtime

## Purpose

ExaCard private realtime is notification and synchronization infrastructure only. It does not authorize card issuance, move funds, settle funding, fabricate transactions, or repair balances. The card provider, canonical ledger, reservations, settlement services, and webhook/event tables remain authoritative.

## Durable Stream

User-facing ExaCard events are persisted through `DeveloperRealtimeEvent` using:

- Stream: `exacard.private`
- Scope: authenticated `user_id`
- Sequence: monotonic per user stream
- Payload: sanitized product data only

Operations events are persisted through:

- Stream: `exacard.operations`
- Scope: operations/admin stream
- Sequence: monotonic operations stream

Redis fanout is best-effort and is triggered after the database commit. If Redis or the websocket layer is unavailable, clients recover from the durable replay endpoint.

## User Events

Implemented event types include:

- `card.created`
- `card.funding.quoted`
- `card.funding.processing`
- `card.funding.completed`
- `card.funding.failed`
- `card.funding.provider_pending`
- `card.unload.processing`
- `card.unload.completed`
- `card.frozen`
- `card.unfrozen`
- `card.blocked`
- `card.terminated`
- `card.control.updated`
- `card.limit.updated`
- `card.authorization.updated`
- `card.transaction.completed`
- `card.transaction.reversed`
- `card.refund.completed`
- `card.chargeback.created`
- `card.chargeback.updated`

## Replay

Authenticated users can replay missed events:

```text
GET /api/cards/realtime/replay?after_sequence={sequence}&limit={limit}
```

The response includes:

- `stream`
- `after_sequence`
- `latest_sequence`
- `gap_detected`
- `reconcile_required`
- `events`

Clients should store the last processed sequence. If a gap is detected, the client must refresh the card snapshot and continue from the latest durable sequence.

## Frontend and Mobile Behavior

The web ExaCard console and mobile ExaCard screen poll the replay endpoint, apply safe card payload updates, and refresh card details when transaction, authorization, refund, or chargeback events arrive.

If realtime degrades, the UI shows a degraded state and the API snapshot remains the source of truth.

## Safety Rules

- No PAN or CVV is published.
- Realtime replay cannot create financial effects.
- Redis failures do not roll back ledger settlement.
- Database rollback prevents durable event persistence.
- Redis fanout runs only after commit.
