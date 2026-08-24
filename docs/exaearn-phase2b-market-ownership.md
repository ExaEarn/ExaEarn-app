# ExaEarn Phase 2B Market Ownership

Phase 2B introduces a per-market authority lease so only one spot-engine instance may sequence and mutate a market at a time.

## Implemented Components

- Migration: `backend/api-gateway/database/migrations/2026_08_17_000002_create_phase2b_authority_tables.php`
- Model: `App\Models\SpotMarketEngineLease`
- Service: `App\Services\Spot\MarketEngineLeaseService`
- OMS integration: `App\Services\Spot\OrderManagementService`

## Lease Contract

Each market has one lease row keyed by `market_id`.

Important fields:

- `owner_instance_id`
- `lease_token`
- `generation`
- `heartbeat_at`
- `expires_at`
- `status`

An active, non-expired lease blocks a competing engine instance. If the lease expires, a new owner may acquire it. Takeover increments `generation` and rotates `lease_token`.

## Fencing

The OMS acquires the market lease before placing or cancelling orders, then asserts the current lease before sequence-sensitive work. Stale owners are rejected if:

- the owner id changed
- the generation changed
- the lease token changed
- the lease expired

## Split-Brain Protection

Split-brain protection is enforced by:

- one unique lease row per market
- row-level transaction locking during acquisition
- monotonic lease `generation`
- opaque lease token fencing
- per-market sequencer rows

## Test Coverage

`Tests\Feature\Phase2BAuthorityTest` covers:

- active owner blocks competing acquisition
- expired-owner takeover increments generation
- stale owner token is rejected after takeover
- multiple markets keep isolated leases and sequences

