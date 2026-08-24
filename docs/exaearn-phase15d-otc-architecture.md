# ExaEarn Phase 15D OTC Architecture

```text
Institutional Client
  -> OTC RFQ
  -> Eligibility / RBAC / Market Config
  -> Explicit OTC Liquidity Providers
  -> Firm Quotes
  -> Best Execution
  -> Client Acceptance
  -> ReservationService
  -> Internal MM / Treasury / External Settlement Model
  -> Canonical Ledger
  -> Reconciliation / Audit / Realtime
```

OTC is not a second Spot engine and not Convert. It is a private liquidity execution layer for block trades.

## Internal Settlement

When client and provider are internal ExaEarn accounts, `OtcRfqService` posts balanced ledger entries between institutional subaccount ledger accounts.

## Public Market Data Isolation

OTC trades are stored in `otc_trades` and do not update Spot last price, candles, public order book, or public trade tape by default.
