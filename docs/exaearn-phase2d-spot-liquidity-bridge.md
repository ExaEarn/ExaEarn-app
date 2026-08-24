# ExaEarn Phase 2D Spot Liquidity Bridge

Date: 2026-08-18

## Objective

This note maps the requested "Spot independent with optional external liquidity" architecture onto the existing Phase 2/2B/2C Spot engine.

ExaEarn Spot remains the customer-facing exchange:

```text
User
  -> ExaEarn Spot API
  -> OMS
  -> Risk / reservation
  -> ExaEarn order book
  -> ExaEarn matching engine
  -> ExaEarn ledger settlement
  -> ExaEarn market data
```

External venues are optional support infrastructure, not ExaEarn's matching engine.

## Implemented In This Bridge

- Added per-market `liquidity_mode`.
- Added per-market `price_authority_mode`.
- Added per-market `external_routing_enabled`.
- Added per-market `external_routing_policy`.
- Added `SpotLiquidityPolicyService`.
- Added `ExternalSpotVenue` adapter contract.
- Added `BinanceSpotVenueAdapter` behind the adapter boundary.
- Added persisted external venue balance/account records.
- Added persisted external venue order records.
- Added persisted internal/external execution leg records.
- Added ledger-backed external Spot fill settlement.
- Added controlled hybrid market-order fallback for unfilled ExaEarn order-book remainder.
- Hardened old external liquidity service so simulated fills are prohibited in production.
- Added focused tests proving internal ExaEarn matching continues without external venues and external fallback remains policy-gated.

## Liquidity Modes

```text
INTERNAL_ONLY
HYBRID
EXTERNAL_ASSISTED
DISABLED
```

Default:

```text
INTERNAL_ONLY
```

External fallback requires all of:

- global `SPOT_EXTERNAL_ROUTING_ENABLED=true`
- market `external_routing_enabled=true`
- market `liquidity_mode` is `HYBRID` or `EXTERNAL_ASSISTED`
- `shadow_only=false`

This prevents accidental Binance dependency.

## Price Authority Modes

```text
REFERENCE_ASSISTED
HYBRID
EXAEARN_PRIMARY
```

This is intentionally separate from liquidity mode.

Price reference answers "what is fair value?"

Liquidity mode answers "where can execution liquidity come from?"

## External Venue Boundary

`ExternalSpotVenue` defines:

- markets
- ticker
- order book
- balances
- order placement
- cancellation
- order lookup
- trade lookup
- health

Binance is one adapter, not the platform core.

## Current Safety Position

Internal ExaEarn matching is live in the new engine path.

External fallback execution is implemented as a controlled bridge, not as a replacement for ExaEarn Spot. It is fail-closed unless the global liquidity switch, per-market switch, market mode, non-shadow policy and funded venue inventory all pass.

External fallback is only used after the ExaEarn internal order book is checked first. It does not inject Binance/provider depth into the ExaEarn internal order book and it does not label external venue volume as ExaEarn volume.

The production safety requirements still remaining are operational rather than architectural:

- no-double-fill timeout recovery
- venue order reconciliation
- venue balance reconciliation
- admin venue kill switch
- production credential storage and IP restrictions
- signed live Binance/venue order adapter credentials
- runbook for execution-unknown investigation

## Validated

Focused test:

```text
tests/Feature/Phase2DSpotIndependenceTest.php
6 passed / 0 failed / 25 assertions
```

Broader trading/market/convert gate:

```text
Phase2SpotEngineTest
Phase2BAuthorityTest
Phase2CControlledCutoverTest
Phase2DSpotIndependenceTest
Phase3MarketDataTest
Phase4ConvertEngineTest
SwapAndPaymentFlowTest

51 passed / 0 failed / 230 assertions
```

The test proves:

- two ExaEarn users match without external venue calls
- limit orders rest on an empty book as real ExaEarn liquidity
- internal-only market orders do not fake fills
- HYBRID routing remains fail-closed while in shadow mode
- HYBRID routing can use a configured external venue only after the internal book is insufficient
- external fallback rejects when the venue inventory record is not funded

## Remaining Operational Work Before External Fallback Production

External fallback should only be enabled in production for a market after Phase 2E/operational work adds:

- external venue trade import/reconciliation
- automatic execution-unknown recovery
- admin external venue dashboard
- venue health and reconciliation jobs
- strict slippage and economic controls
- verified custody/treasury funding procedures for each external venue asset

## Decision

ExaEarn independent Spot:

```text
READY
```

Optional external liquidity fallback:

```text
ARCHITECTURE READY
PRODUCTION ENABLEMENT REQUIRES LIVE VENUE CREDENTIALS, FUNDING, RECONCILIATION AND ADMIN KILL SWITCH
```
