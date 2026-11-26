# ExaEarn Market Maker Bot Regression Remediation

## 1. Exact failing test

`Tests\Feature\Phase15EMarketMakerBotTest::test_futures_mm_hedging_cancel_replace_rebalance_shock_and_load_gates`

The failing assertion was at `tests/Feature/Phase15EMarketMakerBotTest.php:286`:

Expected load-run status: `PASS`

Actual load-run status: `FAIL`

## 2. Failed sub-gate

The failed sub-gate was `STALE_MARKET_DATA` inside `MarketMakerBotRiskService::assertCanQuote()`.

Diagnostics added to the load-run metrics showed:

```json
{
  "decisions": 26,
  "errors": 4,
  "error_reasons": {
    "RuntimeException: Market-maker bot risk gate blocked quoting: STALE_MARKET_DATA": 4
  },
  "elapsed_ms": 121559
}
```

## 3. Root cause

`MarketMakerFairValueService` computed market-data age with PHP's global `time()` while tests and Eloquent timestamps use Laravel's clock. The Phase15E load probe can take longer than the fixture's `max_market_data_age_seconds` limit of 120 seconds on this environment, so the last load decisions aged past the freshness limit while the test was still running.

The market-maker safety gate was doing the right thing for the clock it saw. The regression was the mismatch between testable Laravel time and unfreezable PHP wall-clock time in a long-running deterministic test fixture.

## 4. Classification

`TIMING_ASSUMPTION`

The test was environment-speed sensitive. It could pass on faster runs and fail on slower runs once the 120-second freshness boundary was crossed.

## 5. Why it appeared now

The full backend run had grown enough that the Phase15E market-maker load probe consistently exceeded the existing 120-second freshness window in this environment. The affected MarketMaker test failed in isolation too, proving it was not caused by test ordering or Listing P1 state contamination.

## 6. Files changed

- `backend/api-gateway/app/Services/MarketMakerFairValueService.php`
- `backend/api-gateway/app/Services/MarketMakerBotLoadTestService.php`
- `backend/api-gateway/tests/Feature/Phase15EMarketMakerBotTest.php`
- `docs/exaearn-market-maker-bot-regression-remediation.md`

## 7. Whether production logic changed

Yes, narrowly.

`MarketMakerFairValueService` now uses `now()->timestamp` instead of `time()` for age calculation. This preserves production freshness behavior while making the service obey Laravel's clock abstraction in tests and application contexts.

No market-maker safety thresholds, risk reasons, stale-data blocking behavior, hedge controls, cancel/replace behavior, rebalance behavior, shock controls, or load PASS criteria were weakened.

## 8. Whether test fixture changed

Yes, narrowly.

The Phase15E long load-probe segment now freezes Laravel time before the load run. This keeps fixture market data from aging while the test spends real wall-clock time exercising 30 quote-plan decisions. The separate stale-market-data fail-closed test still passes.

## 9. Determinism improvements

`MarketMakerBotLoadTestService` now stores additive `error_reasons` counts in load-run metrics. This does not change the PASS/FAIL criteria; it makes future aggregate load failures diagnosable without changing the expected assertion.

## 10. Focused regression results

Exact failing test:

`1 passed / 0 failed / 28 assertions`

Market maker tests:

`4 passed / 0 failed / 96 assertions`

Futures regression:

`15 passed / 0 failed / 57 assertions`

Listing Wave 1 governance, Listing KYB, Listing P0, MarketData/Convert combined:

`50 passed / 0 failed / 427 assertions`

## 11. Full backend result

`637 passed / 0 failed / 1 skipped / 5,145 assertions`

## 12. Listing Wave 1 regression result

PASS

`ListingP1GovernanceConcurrencyTest` remained green in focused regression and in the full backend run.

## Route and frontend validation

Route boot:

PASS

Route cache:

PASS

Listing frontend typecheck:

PASS

Listing production build:

PASS

Listing lint:

NOT_CONFIGURED

Developer frontend typecheck:

PASS

Developer frontend production build:

PASS

Developer frontend lint:

NOT_CONFIGURED
