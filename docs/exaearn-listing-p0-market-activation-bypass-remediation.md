# Listing P0 market-activation bypass remediation

## Bypass and root cause

Four unsafe production paths were confirmed: authenticated `POST /api/trade/markets` created active markets by default; legacy and current admin pair endpoints accepted client-controlled state; `TradeService::getMarket()` silently created an active market while resolving an order; and the operations resume endpoint directly wrote a tradeable state. The common root cause was that market configuration and market activation were the same database mutation, with no durable provenance or single activation authority.

## Market mutation map

| Path | Caller / authorization | Create | Activate | Classification after fix |
|---|---|---:|---:|---|
| `POST /api/trade/markets` | ordinary authenticated user | No | No | blocked |
| `POST /api/admin/trading` | admin + `trade.manage` | pre-launch only | No | safe configuration only |
| `POST, PUT /api/admin/admin/pairs` | admin + `trade.manage` | pre-launch only | No | safe configuration only |
| `POST /api/admin/v1/listing-center/applications/{reference}/markets` | Listing admin control plane | pre-launch | No | canonical governed configuration |
| Listing launch / durable `TRADING_OPEN` worker | approved Listing lifecycle | No | Yes | canonical governed activation |
| `POST /api/admin/v1/operations/markets/{symbol}/resume` | admin + `trade.manage` | No | prior activation only | controlled resume |
| pause, emergency and replay controls | operations/system | No | No | fail-safe disable only |
| load harness | CLI, local/testing guarded | test configuration | test only | development/test only |

## Authority and state controls

`App\Services\MarketActivationService` is the sole production transition authority into `active` + `TRADING`. Listing-created markets persist `LISTING_GOVERNED`, `listing_application_id`, optional durable `listing_launch_event_id`, and `activated_at`. General creation always strips status and provenance inputs and creates `pre_launch` / `PRE_LAUNCH`. Unknown migrated provenance remains `UNKNOWN_REVIEW_REQUIRED`; the migration records observed activation time without silently granting system authority.

The Listing lifecycle retains its full certification, current fingerprint, approval, launch-readiness, durable dispatch and postcondition controls. It delegates only the final market transition to the authority. Trading activation does not modify deposit or withdrawal capability.

## API, order-entry, and matching enforcement

Legacy admin mutation now requires `trade.manage`; generic create/update rejects status, trading status, provenance, Listing references and activation timestamps. Customer market creation is forbidden. Market lookup no longer auto-creates pairs. Canonical order entry rejects every state except `active` + `TRADING`, and the matching engine repeats this assertion for persisted incoming orders.

## Migration and compatibility

The forward migration adds provenance and activation references without deactivating existing production rows. Markets already linked to Listing configurations are classified from that durable relationship. Other rows are queued conceptually for governance review and cannot use the controlled resume path until explicitly classified by a future reviewed migration/runbook. Unsafe immediate activation compatibility was intentionally not preserved.

## Tests and remaining P1

`ListingMarketActivationBypassP0Test` covers ordinary-user denial, legacy RBAC, prohibited mass assignment, server-controlled provenance, direct configuration safety and pre-launch trading rejection. Existing Listing certification, durable launch, deposit, withdrawal, document and cancellation suites provide regression coverage.

Specialist permissions beyond existing `trade.manage`, a reviewed legacy-market classification runbook, and richer dedicated audit-event storage remain P1. They were not implemented here.

## Route boot

Route boot: **FAIL**. `php artisan route:list` reaches the pre-reported fatal: `DeveloperRealtimeAuthorityController::authorize(Request): JsonResponse` is incompatible with inherited `Controller::authorize($ability, $arguments = [])`. This is outside the market-bypass remediation and was left unchanged as required.

Validation completed with 43 focused Listing/MarketData/Convert tests passing (340 assertions), followed by the complete backend suite: 630 passed, 0 failed, 1 skipped (5,099 assertions). Listing typecheck and production build pass; no Listing lint script is configured.
