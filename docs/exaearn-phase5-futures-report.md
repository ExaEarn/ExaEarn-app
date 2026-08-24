# ExaEarn Phase 5 Futures Report

Date: 2026-08-18

## A. Executive Summary

Phase 5 added a production Futures foundation for USDT-margined perpetuals: instrument specifications, risk tiers, deterministic margin/PnL formulas, mark/index price services, funding settlement, auditable liquidation events, insurance fund accounts, ADL queue ranking, and focused tests.

This is not an actual production market cutover.

## B. Existing Futures Infrastructure Reused

- `FuturesController`
- `FuturesOrderService`
- `FuturesRiskEngineService`
- `FuturesPositionService`
- `FuturesLiquidationService`
- `FuturesMarket`
- `FuturesOrder`
- `FuturesPosition`
- `FuturesTrade`
- `FuturesFundingPayment`
- `ReservationService`
- `SettlementService`
- `LedgerService`
- `BalanceProjectionService`

## C. Futures OMS

Order placement now validates risk, reserves canonical futures margin, supports TIF, reduce-only and post-only metadata, and fails closed from external Futures execution unless explicitly enabled.

## D. Contract Specification

`FuturesInstrumentService` centralizes contract specs and risk tiers.

## E. Position Engine

`FuturesPositionService` now uses `FuturesMarginService` for deterministic PnL, maintenance margin, liquidation price and bankruptcy price.

## F. Cross Margin

Foundation preserved; full account-level cross-margin portfolio health remains a remaining production item.

## G. Isolated Margin

Position-level isolated margin is supported in formula calculations.

## H. Risk Tiers

Tiered maintenance margin and max leverage are implemented through config/market metadata.

## I. Pre-Trade Risk

Risk checks include leverage range, tier leverage, margin availability, price bands, and reduce-only semantics.

## J. Mark Price

`FuturesMarkPriceService` separates mark price from last trade and clamps premium.

## K. Index Price

`FuturesIndexPriceService` filters stale and outlier constituents before calculating index.

## L. PnL

Linear USDT perpetual PnL formulas are documented and tested.

## M. Funding

`FuturesFundingService` calculates funding and settles idempotently through canonical ledger.

## N. Liquidation

Liquidation records auditable events and routes liquidation fees to the insurance fund. Partial liquidation remains a deeper production cutover item.

## O. Insurance Fund

`FuturesInsuranceFundService` uses canonical ledger accounts per settlement asset.

## P. ADL

`FuturesAdlService` implements deterministic queue ranking and event records.

## Q. Conditional Orders

Existing conditional order paths remain. Phase 5 preserved them and added order-level trigger-source fields. Full trigger sequencer hardening remains.

## R. Fees

Liquidation fee configuration added. Broader maker/taker Futures fee consolidation remains.

## S. Settlement

Funding and liquidation insurance credits use canonical double-entry ledger settlement.

## T. Reconciliation

`FuturesReconciliationService` records findings and does not auto-correct.

## U. Realtime

Existing Redis channel publication remains. Full public/private Futures stream hardening remains.

## V. Frontend

Backend now exposes server-authoritative fields needed by frontend after migration: mark price, index price, funding rate, leverage limits, margin and liquidation values.

## W. Tests

Focused Phase 5 tests:

```text
8 passed / 0 failed / 29 assertions
```

Phase 1-5 focused gate:

```text
65 passed / 0 failed / 284 assertions
```

Full backend suite:

```text
243 passed / 8 failed / 1 skipped
```

The 8 failures are the known `ExaEarnStakingRemovalTest` route failures and are not introduced by Phase 5 Futures hardening.

## X. Load Results

No exchange-scale load claim was made from this local environment.

## Y. External Dependencies

External spot/reference prices may be used for index constituents. External Futures execution is disabled by default.

## Z. Remaining Risks

- Dedicated production Futures matching/execution engine still needs controlled cutover.
- Full partial liquidation execution needs deeper production validation.
- Conditional trigger sequencing needs Phase 5B hardening.
- Full cross-margin account health loop needs production scheduler/stream integration.
- Full backend suite still has known pre-existing staking-route failures.

## AA. Phase 6 Readiness

Safe to begin Phase 6 Margin:

```text
NO
```

Reason: Futures foundation is improved, but actual production Futures cutover and full risk-loop operations are not complete.

## Final Decisions

```text
EXAEARN FUTURES ENGINE PRODUCTION FOUNDATION:
NOT READY

EXAEARN FUTURES POSITION/RISK ACCOUNTING INDEPENDENT FROM EXTERNAL EXCHANGE:
YES

ACTUAL PRODUCTION FUTURES MARKET CUTOVER PERFORMED:
NO

SAFE TO BEGIN PHASE 6 MARGIN:
NO
```
