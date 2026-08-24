# ExaEarn Phase 15A Completion Report

## A. Changes Implemented

- Added applicant listing portal API.
- Added admin listing center API.
- Added listing lifecycle, review, integration, test, schedule, launch, and emergency-control tables.
- Added controlled registration of assets against existing custody registry.
- Added controlled creation of markets against existing market registry.
- Added dedicated `apps/listing` Vite/React portal.
- Added admin menu entry for Listing Center.
- Added focused Phase 15A feature tests.

## B. Safety Decisions

- Application approval does not make an asset or market live.
- New blockchain assets default to deposits and withdrawals disabled.
- New spot markets default to `PRE_LAUNCH`.
- Manual live price is prohibited.
- Launch requires tests and maker-checker scheduling.

## C. Remaining Operational Dependencies

- Real compliance approval remains external.
- Real security audit review remains external.
- Liquidity provider agreements remain operational/external.
- Deposit and withdrawal opening must be handled through custody operations after readiness.

## D. Readiness

## E. Validation Results

- Phase 15A focused backend tests: `2 passed / 0 failed / 54 assertions`.
- Full backend suite: `371 passed / 0 failed / 1 skipped / 2674 assertions`.
- Listing portal typecheck: `PASS`.
- Admin workspace typecheck: `PASS`.
- Listing/admin Vite production build: `PASS` when run with approved elevated execution outside the restricted command sandbox.
- Direct PowerShell execution of both installed `esbuild.exe` binaries works: `0.21.5` and `0.27.7`.
- Node `child_process.spawnSync` fails with `EPERM` for `esbuild.exe`, `C:\Windows\System32\cmd.exe`, and `C:\Program Files\nodejs\node.exe`.
- The same failure occurs with the Playwright-bundled Node `v22.13.1`, so this is not isolated to the system Node `v24.13.1`.
- `corepack pnpm rebuild esbuild`: `PASS`.
- `corepack pnpm --filter @exaearn/listing build`: `PASS`.
- `corepack pnpm --filter @exaearn/admin build`: `PASS`.

## F. Phase 15A Gate

Software-controlled backend listing lifecycle is ready. Frontend source/type safety is ready. Production builds pass when Node is permitted to spawn build helper executables.
