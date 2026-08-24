# ExaEarn Phase 4 Convert Architecture

Date: 2026-08-18

## Objective

Phase 4 hardens ExaEarn Convert/Swap around the canonical financial core and Phase 3 market-data layer.

```text
User request
  -> SwapPricingService
  -> ConvertBackingService
  -> Quote
  -> ReservationService
  -> SwapEngineService
  -> SettlementService::convert
  -> LedgerService
  -> SwapReconciliationService
```

## Services

- `SwapPricingService`: route, rate, fee, and price-source metadata.
- `ConvertBackingService`: treasury-backed destination capacity, liability, reserve, and policy checks.
- `SwapEngineService`: quote creation, idempotent execution queueing, reservation, settlement, failure release.
- `SwapReconciliationService`: detects missing settlements, unconsumed completed reservations, active failed reservations, and duplicate idempotency.

## Pricing

Crypto pricing consumes `MarketDataService`.

When ExaEarn internal trades exist, `last_trade_price` can price Convert.

When no ExaEarn internal price exists, `reference_price` from the Phase 3 reference adapter can price Convert, with metadata preserved:

```text
source = EXTERNAL_REFERENCE
reference_provider = BINANCE
```

Reference price is price only. It does not grant permission to create destination customer liabilities.

## Treasury-Backed Capacity

Before a quote is created, Convert checks safe destination-asset capacity:

```text
Controlled Assets
+ Approved Receivable
- Customer Liability
- Withdrawal Reserve
- Other Reserved Amounts
= Available Conversion Capacity
```

If destination capacity is insufficient, quote creation fails with:

```text
CONVERT_CAPACITY_UNAVAILABLE
```

This keeps Convert off-chain and simple for users while preventing unbacked synthetic balances.

Crypto destination capacity is read from `treasury_balances` first, with canonical treasury ledger accounts as a fallback.

Fiat destination capacity is read from active `treasury_accounts` provider balances, so NGN/USD/ZAR conversions require real provider/settlement liquidity before ExaEarn creates the destination fiat liability.

## Routes

Supported route types:

- `fiat_to_fiat`
- `fiat_to_crypto`
- `crypto_to_fiat`
- `crypto_direct_usdt`
- `crypto_via_usdt`

## Settlement

Execution reserves the source asset from the user's Funding account and posts convert settlement through `SettlementService::convert`.

Before reservation, existing legacy wallet balances may be bridged into the canonical Funding ledger with an auditable migration transaction. This keeps old users compatible while preserving the Phase 1 rule that Convert reservations and settlements are ledger-authoritative.

Settlement reference:

```text
convert:{swap_id}
```

The source reservation is consumed only after ledger settlement succeeds. Failures release active reservations.

## Precision

Phase 4 removes float fallback behavior from `CryptoLiquidityService` and `FxRateService`. Convert pricing uses `FinancialDecimal`.

## API

Authenticated user routes:

- `GET /api/swap/meta`
- `POST /api/swap/quote`
- `POST /api/swap/execute`
- `GET /api/swap/history`
- `GET /api/swap/{swapId}`

Admin route:

- `GET /api/swap/reconciliation`
