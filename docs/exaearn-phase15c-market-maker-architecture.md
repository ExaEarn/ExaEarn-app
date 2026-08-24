# ExaEarn Phase 15C Market Maker Architecture

Phase 15C introduces a market-maker program and operations layer without creating a second exchange.

## Flow

```text
Institutional Account
  -> Dedicated MARKET_MAKER Subaccount
  -> Market Maker Program Application
  -> Admin Technical / Risk / Commercial Review
  -> Maker-Checker Activation
  -> Market Maker Profile
  -> Market Assignment + Liquidity Agreement
  -> API Key / OMS / Risk / Ledger
```

## Source Of Truth

Market-maker capital is measured from canonical institutional subaccount ledger accounts:

```text
Account.owner_type = institutional_subaccount
Account.owner_id   = institutional_subaccount.id
```

No Phase 15C service directly mutates wallet balances.

## No Fake Liquidity Policy

Phase 15C does not fabricate trades, order-book volume or fills. Market-maker quote records are operational/liquidity records. Actual trading must pass through the existing OMS, matching/routing, risk and ledger settlement infrastructure.

## Added Services

- `MarketMakerProgramService`
- `MarketMakerInventoryService`
- `MarketLiquidityHealthService`
- `MarketMakerRebateService`
- `MarketMakerSurveillanceService`
- `MarketMakerMassCancelService`

## Admin Routes

Routes are exposed under:

```text
/api/admin/v1/market-makers/*
```

All routes require existing admin security, audit middleware and the `liquidity.manage` permission.
