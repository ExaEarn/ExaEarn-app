# ExaEarn Phase 5 Futures Architecture

Phase 5 establishes the production foundation for USDT-margined perpetual futures.

```text
Futures API
  -> FuturesOrderService
  -> FuturesRiskEngineService
  -> ReservationService
  -> Futures position/risk services
  -> SettlementService
  -> Canonical Ledger
```

## New Foundation

- `FuturesInstrumentService` centralizes symbol, settlement asset, precision, risk tier and contract specification.
- `FuturesMarginService` owns notional, initial margin, maintenance margin, unrealized PnL, liquidation price and bankruptcy price formulas.
- `FuturesIndexPriceService` builds index prices from filtered constituents.
- `FuturesMarkPriceService` builds mark prices from index plus clamped premium/funding basis.
- `FuturesFundingService` calculates funding rates and records idempotent funding payments.
- `FuturesInsuranceFundService` owns canonical insurance fund account access.
- `FuturesAdlService` creates deterministic ADL ranking based on profitability and leverage.
- `FuturesReconciliationService` records findings without auto-correction.

## Execution Authority

Futures external execution is disabled by default:

```text
FUTURES_ALLOW_EXTERNAL_EXECUTION=false
```

When disabled, Futures orders are accepted only into ExaEarn OMS state after risk and margin reservation. They are not submitted to the legacy blockchain/external execution endpoint.

## Current Limit

This is a production foundation, not a live market cutover. The dedicated Futures matching/execution venue still needs controlled cutover validation before any production market is migrated.
