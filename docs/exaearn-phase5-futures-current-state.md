# ExaEarn Phase 5 Futures Current State

Date: 2026-08-18

## Audited Components

- `FuturesController`
- `FuturesOrderService`
- `FuturesRiskEngineService`
- `FuturesPositionService`
- `FuturesExecutionService`
- `FuturesLiquidationService`
- `MarginModeService`
- `FuturesMarket`
- `FuturesOrder`
- `FuturesPosition`
- `FuturesTrade`
- `FuturesFundingPayment`
- `FuturesConditionalOrder`
- `ReservationService`
- `SettlementService`
- `LedgerService`
- `BalanceProjectionService`

## Findings

- Existing Futures was a partial implementation, not a full production perpetual engine.
- Futures orders already used canonical `ReservationService` for initial margin reservation.
- Futures margin status already read from canonical ledger projections.
- Risk validation existed but was shallow and had legacy math behavior.
- Position PnL existed but used local helper math with float fallback.
- Liquidation existed but directly mutated legacy `InternalAccount` balances and used float conversion.
- Futures market bootstrapping used Binance Futures as a market catalog/reference source.
- `BlockchainService->submitFuturesOrder()` was the old execution handoff. Phase 5 now fails closed by default and does not treat external futures submission as production authority unless explicitly enabled.
- Conditional orders existed for stop/take-profit style workflows, but full production trigger sequencing still needs further hardening before live cutover.
- Funding payment records existed, but Phase 5 added a canonical funding calculation/settlement service.
- Insurance fund and ADL had no meaningful production foundation before Phase 5.

## Classification

Futures order entry: REUSED and partially hardened.

Futures margin reservation: REUSED.

Futures position engine: MIGRATED to deterministic Phase 5 formulas for PnL/liquidation values.

Mark/index price: ADDED as server-side Phase 5 services and snapshot tables.

Funding: ADDED calculation, idempotent payment records and ledger-backed settlement.

Liquidation: MIGRATED away from direct legacy balance mutation and into auditable liquidation events plus insurance fund ledger credit.

Insurance fund: ADDED canonical ledger account/service.

ADL: ADDED deterministic queue ranking/event foundation.

External futures execution: BLOCKED by default unless explicitly configured.

Actual production cutover: NOT PERFORMED.
