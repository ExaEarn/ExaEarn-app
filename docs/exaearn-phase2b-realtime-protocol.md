# ExaEarn Phase 2B Realtime Market Data Protocol

Phase 2B introduces a sequenced realtime event log for market-data consumers.

## Implemented Components

- Migration table: `spot_market_data_events`
- Model: `App\Models\SpotMarketDataEvent`
- Service: `App\Services\Spot\SpotRealtimeSequenceService`
- OMS integration for book deltas, best bid/ask and trades

## Event Contract

Each realtime event includes:

- `event_id`
- `market_id`
- `market_symbol`
- `sequence`
- `event_type`
- `payload`
- `occurred_at`

Implemented event types:

- `BOOK_DELTA`
- `BEST_BID_ASK`
- `TRADE`
- `ORDER_REMOVED`

## Client Recovery Contract

Clients should:

1. Load a snapshot with the latest sequence.
2. Subscribe to or poll deltas after that sequence.
3. Apply events only in sequence order.
4. Resync from snapshot if a gap is detected.

The backend `deltasAfter` method fails with a resync-required error if it detects a missing sequence.

## Test Coverage

`Tests\Feature\Phase2BAuthorityTest::test_realtime_gap_detection_requires_resync` verifies that a missing sequence cannot be silently skipped.

