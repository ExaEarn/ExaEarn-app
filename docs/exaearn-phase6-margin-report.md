# ExaEarn Phase 6 Margin Report

## A. Executive Summary

Phase 6 added a canonical Spot Margin backend foundation for ExaEarn. It introduces cross and isolated margin accounts, configurable collateral/borrow assets, real lending pool liquidity checks, utilization-based interest, idempotent borrow/repay flows, margin health checks, risk-guarded transfer out, liquidation state records, bad-debt records, reconciliation findings, API routes, and focused tests.

This checkpoint is a backend/product-core readiness step with an initial connected web Margin screen and admin navigation/monitoring entry. Production mobile Margin screens, fully wired admin data endpoints, Margin order routing, dedicated realtime, and automated Spot liquidation execution are not complete, so ExaEarn Margin must not be launched to customers yet.

## B. Existing Infrastructure Reused

- `LedgerService`
- `SettlementService`
- `BalanceProjectionService`
- existing Sanctum/2FA/throttle middleware
- existing Laravel model/controller/service conventions
- Phase 1 fixed-precision `FinancialDecimal`

## C. Margin Account Architecture

`margin_accounts` supports:

- `CROSS`
- `ISOLATED`

Cross ledger accounts use `margin_cross`. Isolated accounts use pair-specific account types such as `margin_isolated_btc_usdt`.

## D. Cross Margin

Cross margin combines eligible assets and liabilities inside the `margin_cross` ledger dimension.

Focused test coverage: PASS.

## E. Isolated Margin

Isolated margin creates/reuses one active account per user/pair. Isolated collateral is not shared across pairs.

Focused test coverage: PASS.

## F. Collateral Engine

Collateral factors live in `margin_asset_configs`. Health uses market value, collateral factor, adjusted collateral, gross liabilities, equity, health factor, and margin level.

## G. Borrow Engine

Borrowing validates:

- active margin account
- asset borrow config
- minimum/maximum borrow
- available lending pool liquidity
- projected health
- idempotency key

Settlement is posted through `SettlementService::marginBorrow`.

## H. Repay Engine

Repayment accrues interest, applies payment to interest first, then principal, posts canonical settlement, restores pool principal liquidity, and updates loan status.

## I. Lending Pools

`margin_lending_pools` tracks total, available, borrowed, reserve, and status. Borrow requests fail when available liquidity is insufficient.

## J. Liquidity Source

Initial software model supports treasury-funded pools. Real production lending liquidity is not funded by code alone and must be allocated operationally.

## K. Interest Model

The interest service uses a configurable kinked utilization model with `base_rate`, `slope_1`, `optimal_utilization`, `slope_2`, and `max_rate`.

## L. Interest Accrual

`margin_interest_accruals` records deterministic periods. Duplicate accrual for the same effective period does not double-charge.

## M. Margin Health

Health formula:

```text
adjusted_collateral_value / gross_liability_value
```

Zero debt returns a high healthy sentinel value. Projected borrow and transfer-out risk checks are server-authoritative.

## N. Spot Integration

Margin does not create a second matcher. The backend is structured to submit future Margin orders through the existing Spot OMS/matcher. Full Margin order facade and post-fill debt behavior are not complete in this checkpoint.

## O. Margin Orders

Not complete. Backend borrow/repay/accounting exists, but Margin-specific order mode, auto-borrow, auto-repay order routing, and execution attribution remain to be implemented before customer launch.

## P. Liquidation

Liquidation records and bad-debt detection are implemented. Production partial liquidation through Spot execution is not complete.

## Q. Bad Debt

Negative equity creates `margin_bad_debts` records. Reserve coverage accounting exists as repayment reserve tracking but automated bad-debt reserve settlement is not complete.

## R. Reserve Fund

Interest reserve share is credited to `margin_reserve_fund` through canonical settlement.

## S. Canonical Ledger

Borrow, repay, transfer, pool funding, reserve, and revenue movements are ledger-backed. Margin tables are not an independent balance authority.

## T. Reconciliation

`MarginReconciliationService` detects pool and loan invariant violations and stores findings.

## U. REST API

Authenticated APIs were added under `/api/margin/*` for overview, accounts, assets, pools, health, transfer, borrow, repay, interest, liquidations, and reconciliation.

## V. Realtime

Dedicated Margin realtime streams are documented but not implemented.

## W. Admin

Partially complete. The admin app now includes a Margin route/menu entry under the existing registry-based admin shell with monitoring-oriented actions and conservative non-financial fallback messaging. A fully wired `/api/admin/margin` data endpoint, RBAC-scoped sensitive actions, audit workflows, and live health views remain outstanding.

## X. Web UI/UX

Partially complete. The web app now exposes a connected Margin page through the Spot/Futures product navigation. It loads `/api/margin/overview` and supports account setup, collateral transfer, borrow, repay, pool visibility, active loans, and health display through the authenticated backend APIs. It is not yet a full production Margin trading terminal because Margin order routing, open order views, realtime risk updates, and liquidation workflow UI are still outstanding.

## Y. Mobile UI/UX

Not complete in this checkpoint.

## Z. Security

Routes use authenticated middleware, 2FA where configured, throttling, server-side ownership checks, idempotency, and fixed-precision money math. Admin pool funding/reconciliation routes require existing admin security middleware.

## AA. Concurrency

Pool rows and loan/account rows are locked during borrow/repay. Focused tests cover liquidity enforcement and idempotency. A heavier parallel stress harness remains outstanding.

## AB. Load/Stress Results

No dedicated Phase 6 load harness was completed. Local focused functional tests passed.

## AC. Full Tests

- Phase 6 focused: `10 passed / 0 failed / 44 assertions`
- Phase 1-6 focused gate: `72 passed / 0 failed / 311 assertions`
- Full backend suite: `260 passed / 8 failed / 1 skipped`
- The 8 failures are the known pre-existing `ExaEarnStakingRemovalTest` route failures.

## AD. External Dependencies

Production requires robust reference/index pricing integration and operational lending pool funding.

## AE. Production Liquidity Requirements

Real Margin lending liquidity is not funded. ExaEarn must allocate actual treasury or approved lending capital to enabled pools before users can borrow.

## AF. Remaining Risks

- Initial production-style web Margin console exists, but it is not a complete Margin trading terminal yet.
- Admin Margin navigation/monitoring entry exists, but fully wired admin backend views/actions are not complete.
- No production Margin mobile console yet.
- No dedicated Margin realtime stream yet.
- Margin order facade and Spot OMS integration are not complete.
- Production partial liquidation execution is not complete.
- No Phase 6 load/stress harness yet.
- Real lending pools are not funded.

## AG. Phase 7 Readiness

SAFE TO BEGIN PHASE 7: NO.

The backend foundation is useful and tested, but Phase 6 is not complete across UI/admin/realtime/liquidation/order integration. Phase 7 external developer APIs should wait until those product surfaces and operational gates are finished.

## Final Decisions

```text
EXAEARN MARGIN BACKEND:
PARTIALLY READY

EXAEARN MARGIN WEB UI/UX:
PARTIALLY READY

EXAEARN MARGIN MOBILE UI/UX:
NOT READY

EXAEARN MARGIN ADMIN:
PARTIALLY READY

EXAEARN MARGIN ENGINE INDEPENDENT:
YES

REAL MARGIN LENDING LIQUIDITY FUNDED:
NO

ACTUAL PRODUCTION MARGIN ENABLEMENT:
NO

SAFE TO BEGIN PHASE 7:
NO
```
