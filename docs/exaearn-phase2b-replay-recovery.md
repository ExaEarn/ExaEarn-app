# ExaEarn Phase 2B Replay And Recovery

Phase 2B adds a deterministic replay runner for the spot matching engine journal.

## Implemented Components

- Service: `App\Services\Spot\MatchingEngineReplayService`
- Command: `php artisan spot:replay {market}`
- Existing journal model: `App\Models\SpotExecutionEvent`
- Existing snapshot model: `App\Models\SpotOrderBookSnapshot`

## Replay Model

Replay starts from the latest valid order book snapshot. If no snapshot exists and the caller allows cold replay, replay starts from an empty book.

The runner then applies journal events in strict sequence order:

- `ORDER_OPENED`
- `ORDER_PARTIALLY_FILLED`
- `ORDER_FILLED`
- `ORDER_CANCELLED`
- `ORDER_REJECTED`

The output includes:

- `market`
- `last_sequence`
- `bids`
- `asks`
- `open_orders`
- `checksum`
- `gaps`

## Gap Handling

If a sequence gap is detected, replay fails closed by:

- marking the market `trading_status` as `halted`
- throwing a `SEQUENCE GAP` exception
- refusing to rebuild a potentially invalid book

## Snapshot Recovery

The replay runner can verify a rebuilt book against the latest stored snapshot checksum. This is the recovery primitive needed before restarting an authoritative engine instance.

## Test Coverage

`Tests\Feature\Phase2BAuthorityTest` covers sequence-gap detection and market halt behavior.

