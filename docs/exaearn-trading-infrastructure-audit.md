# ExaEarn Trading Infrastructure Audit

Phase: 0  
Date: 2026-08-14  
Scope: repository discovery, trading infrastructure audit, gap analysis, and implementation roadmap.  
Constraint: no Phase 1 implementation was performed.

## A. Executive Summary

ExaEarn already contains a substantial trading and financial backend, but it is not yet an exchange-grade trading infrastructure platform.

The repository has real Laravel models, migrations, controllers, services, queues, tests, React/Vite trading terminals, an Expo mobile app, a Node realtime/blockchain service, wallet/deposit/withdrawal modules, a double-entry ledger foundation, spot order and trade tables, futures order/position tables, convert/swap services, smart order routing services, market-maker/liquidity tables, treasury services, admin controls, KYC/security modules, and WebSocket/SSE-style realtime plumbing.

The strongest existing foundations are:

- Laravel API gateway and authentication/security infrastructure.
- Market, order, trade, order-book, futures, account, wallet, transfer, swap, treasury, and ledger schemas.
- `LedgerService` double-entry transaction model.
- `TradeService` spot order placement, matching, fills, cancellation, settlement, and market data.
- `FuturesOrderService`, futures positions, conditional orders, margin mode, risk and liquidation services.
- Redis/Node realtime services for wallet, portfolio, price, game, futures and ledger events.
- React spot/futures terminals and shared market chart components.

The highest-risk gaps are:

- Money is currently handled by a hybrid of `accounts`, `ledger_entries`, `wallets`, `wallet_balances`, `balances`, and `internal_accounts`. Several services still mutate balances directly or through legacy wallet abstractions instead of one immutable financial source of truth.
- Spot matching is implemented inside Laravel database service code, not a production-grade sequenced matching engine with deterministic replay, snapshots, low-latency order books, event sourcing, and restart recovery.
- Futures exists, but risk, funding, liquidation, insurance, ADL, position accounting, mark/index price construction, and external/internal execution controls are not yet exchange-grade.
- Smart Order Routing exists, but it uses float arithmetic and the external Binance execution path is simulated by default. The non-simulated Binance order path is not a complete signed/reconciled trading venue adapter.
- Market data is mixed between ExaEarn database state, Binance, CoinGecko, direct browser Binance fallback, and frontend conversion. ExaEarn does not yet have one authoritative normalized market-data contract.
- Many financial APIs sit under `dev.auth` plus `security.layer`. `dev.auth` correctly falls back to Sanctum outside local/development/testing, but this route grouping still needs a production API/security review before public or institutional exposure.
- Critical decimal helpers often fall back to PHP floats when BCMath is missing. That is unacceptable for production financial accounting.
- WebSockets exist but do not yet provide full authenticated private-stream security, sequence numbers, replay/resync contracts, ordering guarantees, or developer-facing stream semantics.
- Developer platform infrastructure is mostly missing beyond Sanctum personal access tokens and admin RBAC.

Overall classification: ExaEarn is at Level 2 for several core subsystems: functional development implementation with real backend components. It is not yet Level 4 exchange-grade.

## B. Repository Architecture Map

Actual top-level structure observed:

```text
apps/
  admin/        Vite admin dashboard
  mobile/       Expo / React Native app
  web/          Vite React user web app
  website/      Vite marketing/public website

backend/
  api-gateway/  Laravel API gateway, models, controllers, services, migrations, tests
  database/     Prisma database folder
  queues/       queue-related placeholder/folder
  services/
    blockchain-service/ Node/Nest/Hardhat blockchain and realtime service
    reward-service/     Laravel/PHP reward service
  shared/       shared backend code
  tests/        backend-level tests folder
  websocket/    websocket folder

blockchain/     Hardhat/blockchain package
packages/
  config/
  eslint-config/
  sdk/
  tsconfig/
  types/
  ui/

docs/
infrastructure/
integrations/
scripts/
security/
tests/
web3/
```

Workspace packages:

```text
root package: exaearn-monorepo
apps/web: @exaearn/web
apps/admin: @exaearn/admin
apps/website: @exaearn/website
apps/mobile: @exaearn/mobile
packages/ui: @exaearn/ui
packages/types: @exaearn/types
packages/tsconfig: @exaearn/tsconfig
packages/sdk: @exaearn/sdk
packages/eslint-config: @exaearn/eslint-config
packages/config: @exaearn/config
backend/services/blockchain-service
backend/services/reward-service
blockchain
```

Root package manager: `pnpm@10.0.0`.

## C. Existing Trading Components

Backend models and tables discovered:

- Spot: `Market`, `Order`, `Trade`, `OrderBook`.
- Futures: `FuturesMarket`, `FuturesOrder`, `FuturesPosition`, `FuturesTrade`, `FuturesFundingPayment`, `FuturesConditionalOrder`.
- Wallet/accounting: `Account`, `LedgerTransaction`, `LedgerEntry`, `Wallet`, `WalletBalance`, `Balance`, `InternalAccount`, `InternalWalletTransaction`, `AccountTransfer`.
- Convert: `Swap`, `Quote`, `PaymentIntent`.
- Liquidity/SOR: `SmartOrderRoutingLog`, `LiquidityPool`, `LiquidityLog`, market maker config/log tables.
- Treasury/custody: treasury wallets, balances, transactions, withdrawal requests, deposit addresses.
- User risk/security: KYC, audit logs, security events, roles, permissions, personal access tokens.

Backend controllers discovered:

- `TradeController`
- `FuturesController`
- `SwapController`
- `WalletController`
- `AccountController`
- `LedgerController`
- `WithdrawalController`
- `WithdrawalCenterController`
- `FiatWithdrawalController`
- Admin controllers for SOR, market maker, treasury, KYC, platform operations, and settings.

Backend services discovered:

- Trading: `TradeService`, `SpotTradingService`, `SmartOrderRoutingService`, `ExternalLiquidityProviderService`, `MarketMakerService`, `AdaptiveMarketMakerService`, `MarketDataCollectorService`, `MarketStreamService`, `RealtimeStreamService`, `OrderBookDepthService`, `OrderSplittingService`, `PriceAggregationService`, `PriceAnchorService`, `ExecutionDecisionService`, `SlippageProtectionService`.
- Futures: `FuturesOrderService`, `FuturesRiskEngineService`, `FuturesPositionService`, `FuturesLiquidationService`, `FuturesExecutionService`, `MarginModeService`.
- Money: `LedgerService`, `WalletService`, `TransactionService`, `TransferService`, `UnifiedTradingAccountService`, `UnifiedTradingReservationService`, `FeeCalculator`, `FeeTreasuryService`.
- Convert/liquidity: `SwapEngineService`, `CryptoLiquidityService`, `FxRateService`, `LiquidityService`, `SmartLiquidityService`.
- Treasury/custody: `TreasuryService`, `TreasuryRouterService`, `CryptoTreasuryService`, hot/cold/sweep/deposit watcher/withdrawal signer services.

Frontend trading components:

- `apps/web/src/pages/trade/TradeTerminal.tsx`
- `apps/web/src/pages/futures/Futures.jsx`
- `apps/web/src/components/market/TradingChart.tsx`
- `apps/web/src/services/marketDataService.ts`
- `apps/web/src/services/futuresApi.js`
- `apps/web/src/services/webSocketService.js`

Realtime services:

- Laravel Redis publish from ledger/trade/futures services.
- Node Socket.IO hub at `/ws/wallet`.
- Node wallet/futures Socket.IO hub at `/ws/futures`.
- Node ledger Socket.IO hub at `/ws/ledger`.
- Node raw WebSocket market hub at `/ws/markets`.
- Laravel SSE endpoint `GET /api/events/subscribe`.

## D. Feature Readiness Matrix

| Component | Exists | Backend | Real Execution | External Dependency | Readiness |
| --- | ---: | ---: | ---: | ---: | --- |
| Spot UI | Yes | Yes | Partial | Binance/browser fallback | Level 2 |
| Spot API | Yes | Yes | Partial internal matching | Binance/CoinGecko market data | Level 2 |
| Spot Matching | Yes | Laravel DB service | Partial | None for internal book | Level 2 |
| Production Matching Engine | Partial | No dedicated engine | No | N/A | Level 1 |
| OMS | Partial | Separate spot/futures order models | Partial | Blockchain service for futures | Level 2 |
| Futures UI | Yes | Yes | Partial | Binance futures data / blockchain service | Level 2 |
| Futures Contracts | Yes | Yes | Partial | Binance fallback metadata | Level 2 |
| Futures Risk | Yes | Minimal | Partial | Price feed | Level 1 |
| Futures Liquidation | Yes | Minimal | Partial | Mark price feed | Level 1 |
| Funding Rates | Table exists | Partial | Not complete | External/unknown | Level 1 |
| Advanced Futures | Partial | Conditional orders, TP/SL/trailing | Partial | Futures service | Level 1-2 |
| Margin Trading | Concepts only | No borrow/repay engine | No | N/A | Level 0-1 |
| Convert / Swap | Yes | Yes | Partial settlement | Binance/Fx provider | Level 2 |
| Smart Order Routing | Yes | Yes | Simulated external by default | Binance | Level 1-2 |
| Market Data | Yes | Yes | Mixed | Binance, CoinGecko, browser Binance | Level 2 |
| Ledger | Yes | Yes | Partial source of truth | Redis notifications | Level 2 |
| Wallet | Yes | Yes | Partial | Blockchain node/provider | Level 2 |
| Settlement | Yes | Fragmented | Partial | Internal/external | Level 2 |
| Fee Engine | Yes | Yes | Partial | Config | Level 2 |
| WebSocket | Yes | Node/Laravel | Partial | Redis | Level 1-2 |
| Developer API | Minimal | Sanctum only | No platform API | N/A | Level 0-1 |
| API Keys | Personal tokens only | Partial | Not developer-grade | N/A | Level 1 |
| Sandbox/Testnet | Simulate flags | Partial | Not isolated | Binance simulate | Level 1 |
| Monitoring | Health/logs | Partial | No full metrics | N/A | Level 1 |

## E. Futures Engine Assessment

Existing futures implementation:

- Tables: `futures_markets`, `futures_orders`, `futures_positions`, `futures_trades`, `futures_funding_payments`, `futures_conditional_orders`.
- Services: `FuturesOrderService`, `FuturesRiskEngineService`, `FuturesPositionService`, `FuturesLiquidationService`, `FuturesExecutionService`, `MarginModeService`.
- Controller/API: `FuturesController` under `/api/futures`.
- Frontend: `apps/web/src/pages/futures/Futures.jsx`.

What works as a development foundation:

- Perpetual/futures symbols are represented in `futures_markets`.
- Leverage min/max exists at market level.
- Orders support `market`, `limit`, `stop-market`, `stop-limit`, and `trailing-stop`.
- Margin reservation uses `UnifiedTradingReservationService`.
- Conditional orders can be stored and triggered.
- Margin mode supports `cross` and `isolated`.
- Position service calculates unrealized PnL, maintenance margin, and liquidation price.
- Liquidation scan can close positions when effective margin breaches maintenance.
- Futures events publish to Redis and Node realtime services.

Gaps:

- No exchange-grade risk model: no leverage tiers, position limits, account exposure limits, price bands, volatility rules, market-order protection, per-symbol risk caps, or portfolio-level margin.
- No complete funding-rate engine: table exists, but no mature funding interval scheduling, index construction, long/short payment settlement, or audit/reconciliation pipeline.
- No insurance fund or ADL.
- Liquidation is all-or-nothing and simplified; no bankruptcy price, partial liquidation, liquidation fees, insurance settlement, ADL queue, or negative-equity management.
- Mark/index pricing is incomplete. `FuturesController` fetches Binance futures data and `FuturesUpdateSubscriber` posts ticks back to Laravel. A proper index/mark price service with sanity checks is still needed.
- Futures execution calls a `BlockchainService->submitFuturesOrder('/futures/orders')` path, but no production matching/execution engine was proven from the inspected code.
- Some legacy private methods still use `InternalAccount` futures wallet logic directly, showing migration debt.
- Decimal helpers include float fallbacks.

Classification: functional but incomplete, Level 2 overall; liquidation/funding/risk are Level 1.

## F. Advanced Futures Assessment

Implemented or partially implemented:

- Stop-market.
- Stop-limit.
- Trailing-stop.
- Conditional order table.
- TP/SL-like frontend controls.
- Batch cancel endpoint.
- Margin type switching.
- Copy-follow routes.

Not proven production-ready:

- Reduce-only enforcement.
- Post-only.
- IOC/FOK/GTC behavior.
- OCO/bracket order linkage.
- Hedge mode / one-way mode.
- Batch order placement.
- Cancel/replace.
- Trigger sequencing with market ticks.
- Price protection.
- Self-trade prevention.
- Risk-checked conditional execution at trigger time beyond basic validation.

Classification: Level 1-2. Some advanced order primitives exist, but execution semantics are not exchange-grade.

## G. Smart Order Router Assessment

Existing implementation:

- `SmartOrderRoutingService`
- `ExternalLiquidityProviderService`
- `PriceAggregationService`
- `ExecutionDecisionService`
- `OrderSplittingService`
- `SlippageProtectionService`
- `SmartOrderRoutingLog`
- Admin routes under `/api/admin/sor`.

Behavior:

- Reads internal order book from `TradeService`.
- Reads Binance depth using `ExternalLiquidityProviderService`.
- Aggregates sources and builds a route plan.
- Can split between internal and external sources.
- Executes internal slices through `TradeService->placeOrder`.
- External Binance execution is simulated by default via `services.binance.simulate = true`.
- If simulate is disabled, the code posts to `/api/v3/order`, but the inspected implementation does not show complete signed Binance order authentication, timestamp/signature handling, reconciliation, fee import, fills import, or withdrawal of external custody risk.

Gaps:

- Uses floats for quantity, weighted average, slippage, expected price, and routing totals.
- No durable route state machine.
- No venue credential isolation per environment.
- No venue reconciliation loop.
- No circuit breaker model.
- No venue-level rate-limit tracking.
- No failed external execution unwind/settlement policy.
- No production liquidity custody model documented in code.

Classification: routing prototype / development implementation, Level 1-2.

## H. Spot Trading Assessment

Existing implementation:

- Tables: `markets`, `orders`, `trades`, `order_books`.
- Main service: `TradeService`.
- Legacy service: `SpotTradingService`.
- APIs under `/api/trade`.
- Frontend terminal in `TradeTerminal.tsx`.

`TradeService` capabilities:

- Market listing with DB markets plus default live symbols.
- Binance and CoinGecko ticker fallback.
- Order book from DB snapshot; fallback to Binance depth if no internal book exists.
- Recent trades from DB; fallback to Binance recent trades.
- Candles from internal DB trades; fallback to Binance klines.
- Limit and market order placement.
- Conditional fields for stop-loss/take-profit.
- DB transaction around order placement and matching.
- Balance reservation through `UnifiedTradingReservationService`.
- Price-time matching against opposite open/partial orders.
- Partial fill support.
- Maker/taker fee calculation.
- Trade records and transaction/wallet records.
- Market last price update.
- Redis/realtime market publishing.

Gaps:

- Matching is not a dedicated sequenced engine.
- No event-sourced order book.
- No restart replay/snapshot strategy.
- No sequence IDs on book updates.
- No explicit self-trade prevention found.
- No full cancel/replace state machine.
- Market order behavior depends on internal liquidity and fallback order-book sources; execution semantics need hardening.
- Settlement still touches wallet/balance services instead of one consolidated ledger core.
- Price and amount math has float fallback.

`SpotTradingService` should be treated as legacy because it locks `Balance.spot_available/spot_locked` directly and creates legacy-style ledger entries.

Classification: Level 2.

## I. Matching Engine Assessment

NO PRODUCTION INTERNAL MATCHING ENGINE FOUND.

What exists is a Laravel service-level matcher inside `TradeService`. It appears useful for development and early internal markets, but it is not a production matching engine.

Missing production engine requirements:

- Single sequencer per market or deterministic ordering model.
- Low-latency in-memory order book with durable journal.
- Event sourcing or append-only execution log.
- Snapshot/replay after restart.
- Idempotent command handling.
- Strict client order ID uniqueness.
- Price-time priority with stable sequence numbers.
- Cancel/replace state transitions.
- Self-trade prevention.
- Market protection, post-only, IOC/FOK.
- Partial fill and settlement event separation.
- Backpressure strategy.
- Matching metrics and monitoring.

Recommended path: keep `TradeService` as the compatibility/order API layer during Phase 2, but introduce a dedicated matching core behind it.

## J. Convert Assessment

Existing implementation:

- `SwapController`
- `SwapEngineService`
- `Quote`
- `Swap`
- `ExecuteSwapJob`
- `CryptoLiquidityService`
- `FxRateService`
- `LiquidityService`

What exists:

- Quote generation.
- Quote TTL/expiration.
- Fee calculation.
- Route/provider metadata.
- Idempotency key for swap execution.
- Queued execution.
- Wallet freeze/debit/credit settlement.
- Failure unfreeze path.
- Crypto price from Binance.
- Optional external liquidity execution.

Gaps:

- External crypto execution is simulated by default when `services.binance.simulate` is true.
- External execution/reconciliation is incomplete for production.
- Fiat and crypto routing rules are not one mature global pricing engine.
- Settlement goes through wallet service paths rather than one consolidated double-entry ledger.
- Spread/fee disclosure is present in code but needs centralized policy and auditability.
- Decimal fallback to floats remains.

Classification: Level 2 development implementation.

## K. Margin Assessment

Margin trading as a separate product is mostly missing.

Found:

- Futures margin concepts.
- `MarginModeService` for futures cross/isolated calculations.
- Unified account descriptions mention future margin products.
- NFT credit-line concepts have interest fields, but these are not a margin trading engine.

Not found as production margin:

- Borrow engine.
- Repay engine.
- Interest accrual.
- Loan balances.
- Collateral factor model.
- Margin health.
- Cross/isolated spot margin accounts.
- Liquidation for borrowed assets.
- Borrow limits.
- Lending pool/treasury funding source.

Classification: Level 0-1.

## L. Ledger Assessment

Existing double-entry foundation:

- `accounts`
- `ledger_transactions`
- `ledger_entries`
- `LedgerService`
- `LedgerEngineTest`

Strengths:

- Balanced transaction enforcement.
- Account row locks on entry posting.
- Rejects negative balances for user-owned accounts.
- Idempotent transaction reference creation.
- Redis event publishing.
- Tests assert unbalanced transaction rejection and internal transfer behavior.

Critical gaps:

- The platform still uses multiple parallel balance systems: `accounts`, `wallets`, `wallet_balances`, `balances`, and `internal_accounts`.
- Several services mutate balances directly through `Wallet`, `WalletBalance`, `Balance`, or `InternalAccount`.
- `TransferService` can move `WalletBalance` values and record `InternalWalletTransaction` without consistently routing through the double-entry ledger.
- `UnifiedTradingAccountService` bridges legacy balances and the ledger, including seeding ledger balances from legacy wallet state. This is a migration bridge, not a final source of truth.
- `LedgerService->rollbackTransaction()` deletes ledger entries. Financial audit history should be immutable; corrections should be reversal transactions.
- Legacy columns are added to `ledger_entries`, mixing old and new ledger representations.
- Float fallbacks exist in financial helper methods.

Classification: Level 2 foundation with high-risk technical debt.

## M. Wallet/Custody Assessment

Existing components:

- Wallet tables and `Wallet` model.
- `WalletBalance` and `Balance` legacy/hybrid tables.
- Deposit address generation.
- HD wallet fields added to deposit addresses.
- Blockchain service with address generation, deposit monitor, withdrawal broadcaster, HD wallet service, non-EVM chain service, XRP bridge, contract interaction services.
- Treasury wallet/account/balance/transaction tables.
- Hot/cold/sweep/deposit watcher/withdraw signer services.
- Withdrawal queue/status flows.
- Fiat deposit/withdrawal and virtual account infrastructure.

Strengths:

- Clear separation is starting to emerge between treasury/custody and internal wallet/accounting.
- Deposit and withdrawal flows have controllers, services, migrations, tests, and provider abstractions.
- Treasury admin and monitoring endpoints exist.

Gaps:

- Internal ledger balances and blockchain custody balances are not fully consolidated around one audited accounting model.
- Several wallet services still directly mutate available/locked balances.
- No proven MPC/multisig production custody flow.
- Withdrawal signing/broadcast requires deeper security review before production.
- Provider-specific failures and reversals are handled in parts of the codebase, but reconciliation must be centralized.

Classification: Level 2.

## N. Market Data Assessment

Current sources:

- Internal `trades` table for recent trades/candles when available.
- Internal `order_books` table for snapshots when available.
- Binance REST for spot tickers, order books, recent trades, and klines.
- CoinGecko simple price fallback.
- Binance Futures REST in `FuturesController`.
- Frontend direct browser Binance fallback in `apps/web/src/services/marketDataService.ts`.
- TradingView/lightweight chart rendering from frontend candle data.
- Redis market stream publishing through Laravel/Node.

Gaps:

- No single authoritative normalized market-data contract across Markets, Spot, Futures, Convert, and dashboards.
- Frontend still calls Binance directly, which bypasses ExaEarn backend normalization.
- No complete internal ticker service that calculates 24h stats from ExaEarn trades.
- No sequence IDs or snapshots for order-book streams.
- No market data freshness model.
- No clear separation between spot and perpetual feeds in all frontend paths.

Classification: Level 2.

## O. External Dependency Matrix

| Provider | Purpose | Files | Credentials | Read/Write | Criticality | Risk if unavailable |
| --- | --- | --- | --- | --- | --- | --- |
| Binance Spot REST | Tickers, depth, recent trades, klines, SOR external depth, crypto liquidity | `TradeService`, `ExternalLiquidityProviderService`, `CryptoLiquidityService`, `PriceAnchorService`, `apps/web/src/services/marketDataService.ts` | API key/secret for writes | Read and simulated/write path | High currently | Markets/charts/SOR/convert fallback degrade |
| Binance Futures REST | Futures market metadata/tickers | `FuturesController`, `apps/web/src/pages/futures/Futures.jsx` | None for public data | Read | Medium-high | Futures markets/stats degrade |
| CoinGecko | Spot price fallback | `TradeService` | None observed | Read | Medium | Backup prices unavailable |
| TradingView / Lightweight Charts | Chart visualization | `TradingChart.tsx`, trading pages | None for lightweight charts | Read/visual | Medium | Chart UI degrades |
| Flutterwave | Fiat payments/withdrawals | payment/fiat services/controllers/config/tests | Secret keys/webhook secrets | Read/write/webhook | High for fiat | Fiat rails fail |
| Nomba | Fiat payments/virtual accounts/withdrawals | payment/fiat/virtual account services/controllers/config/tests | API token/keys/webhook secret | Read/write/webhook | High for fiat | Fiat rails fail |
| Paystack | Payment router option | `PaymentRouterService`, treasury config | Provider credentials likely | Read/write planned | Medium | Provider option unavailable |
| Nium/Airwallex | Mentioned provider abstractions in fiat requirements; not proven in code | N/A | N/A | N/A | Low-current | Future expansion missing |
| EVM/RPC providers | Blockchain event listening and transactions | blockchain service config | RPC URLs/private keys | Read/write | High for custody | Deposit/withdraw/contract operations fail |
| XRPL | XRP bridge/network | blockchain service | RPC/WSS config | Read/write | Medium-high | XRP-specific operations fail |

No secret values were printed or inspected.

## P. API Assessment

Key public/auth route groups:

- Auth: `/api/auth/*`, Sanctum protected profile/session routes.
- Public market: `GET /api/v1/market/klines`.
- Event stream: `GET /api/events/subscribe`.
- Webhooks: payment, deposit, fiat withdrawal.
- Accounts: `/api/accounts/*`.
- Wallet: `/api/wallet/*`.
- Transactions: `/api/transactions/*`.
- Trading: `/api/trade/*`.
- Swap: `/api/swap/*`.
- Ledger: `/api/ledger/*`.
- Withdrawal: `/api/withdrawals/*`.
- Fiat withdrawals: `/api/fiat-withdrawals/*`.
- Futures: `/api/futures/*`.
- Admin SOR: `/api/admin/sor/*`.
- Admin market maker: `/api/admin/market-maker/*`.
- Admin treasury: `/api/admin/treasury/*`.

Important route security observation:

- A large financial route group is wrapped in `['dev.auth', 'security.layer']`.
- `DevAuthBypass` only bypasses authentication in `local`, `development`, or `testing`; outside those environments it delegates to Laravel `Authenticate` with Sanctum.
- This is acceptable for local development, but production hardening should replace or explicitly separate dev routes and production API authentication to avoid accidental environment misconfiguration.

Developer API suitability:

- Current APIs are app-facing, not developer-platform-facing.
- No mature developer organization/application/key/scope/usage-metering model was found.
- Withdrawal APIs should not be exposed to third parties until signing, scopes, IP allowlists, rate limits, approvals, and monitoring exist.

## Q. WebSocket Assessment

Existing:

- Node Socket.IO `realtimeHub` at configurable `/ws/wallet`.
- Node Socket.IO wallet/futures hub at `/ws/futures`.
- Node Socket.IO ledger hub at `/ws/ledger`.
- Raw WebSocket market hub at `/ws/markets`.
- Redis subscribers for realtime, futures, ledger, ExaPoint, and market streams.
- Frontend `webSocketService.js` connects to Node if configured, otherwise Laravel SSE `/api/events/subscribe`.

Gaps:

- User subscription accepts `user_id` payload in the socket message; no authenticated private stream handshake was proven.
- No stream sequence numbers.
- No replay window.
- No order-book snapshot/delta protocol.
- No resynchronization protocol after reconnect beyond frontend refresh patterns.
- No heartbeat contract exposed to clients.
- No private execution/order/position stream model suitable for external developers.
- Market stream hub exists but was not attached in `src/index.js` in the inspected entrypoint; blockchain controller references it, so startup wiring should be verified.

Classification: Level 1-2.

## R. Security Findings

CRITICAL:

- No developer API key/security platform exists for third-party trading access. Current app APIs must not be exposed as public infrastructure APIs.
- Private WebSocket subscription security is insufficient for production if user room access is controlled only by client-supplied `user_id`.

HIGH:

- Financial route grouping uses `dev.auth`; although the middleware falls back to Sanctum outside local/development/testing, production must fail closed if environment is misconfigured.
- Multiple services can mutate balances outside the double-entry ledger.
- Float fallbacks exist in money/trading helpers.
- External Binance write path in `ExternalLiquidityProviderService` is incomplete for production signed execution and reconciliation.
- `LedgerService->rollbackTransaction()` deletes ledger entries instead of writing reversing entries.

MEDIUM:

- Admin route modules include placeholder endpoints for some areas. They must not be mistaken for production controls.
- Some frontend market data reads directly from Binance, bypassing backend normalization/security.
- Webhook protection exists for fiat providers in parts of the codebase, but provider coverage must be uniformly verified.
- KYC/2FA/RBAC systems exist but need product-specific policy enforcement for trading, withdrawals, futures, and developer APIs.

LOW:

- Some UI pages still present production-looking controls backed by partial services. This is a product disclosure and QA issue.

## S. Financial Integrity Findings

Highest risks:

- Hybrid money model: `accounts`, `ledger_entries`, `wallets`, `wallet_balances`, `balances`, and `internal_accounts` can disagree.
- Mutable balances are updated in multiple services.
- Some reservation paths are ledger-backed, others are wallet-transaction-backed, and others are legacy balance-column-backed.
- Float fallbacks can produce non-deterministic rounding for prices, quantities, fees, PnL, funding, and settlement.
- Spot and futures settlement are not yet one atomic central settlement engine.
- External execution and internal settlement are not fully reconciled before balance updates in all flows.
- Ledger rollback deletes rows.

Required direction:

- One canonical double-entry ledger.
- One account/balance projection derived from ledger plus reservation holds.
- Immutable correction entries, never deletion.
- Decimal/fixed-point arithmetic as a hard dependency.
- Central reservation/hold service for all products.
- Central settlement service for spot, futures, convert, fees, liquidations, withdrawals, and transfers.

## T. UI Claims VS Backend Reality

| UI Claim | Backend Reality | Risk |
| --- | --- | --- |
| Spot trading terminal | Real APIs and DB matching exist | Development-grade, not production engine |
| Spot chart | Backend candles plus Binance fallback; frontend direct Binance fallback exists | Mixed source of truth |
| Order book | DB snapshot or Binance fallback | ExaEarn book may be empty/mixed with external data |
| Market prices | Binance/CoinGecko/internal mix | Not one ExaEarn market-data contract |
| Futures trading | Backend tables/services exist | Risk/funding/liquidation incomplete |
| Leverage | Enforced by futures service against market min/max | Needs tiered risk and exposure controls |
| Margin mode | Cross/isolated field/service exists | Simplified and futures-only |
| TP/SL/trailing | Conditional order primitives exist | Trigger/execution semantics need hardening |
| Convert | Backend quote/execution exists | External liquidity simulated by default |
| Unified Trading Account | Frontend and service exist | Still backed by hybrid wallet/internal-account state |
| Wallet balances | Real wallet/account data exists | Multiple balance sources can diverge |
| WebSocket live updates | Redis/Node/SSE exists | No full authenticated, sequenced stream contract |
| SOR | Backend routing exists | External execution simulated/incomplete |
| Developer APIs | App APIs exist | Developer platform missing |

## U. Missing Infrastructure

To reach independent exchange-grade infrastructure, ExaEarn still needs:

- Canonical ledger/account/reservation architecture.
- Migration plan from legacy balances to canonical ledger projections.
- Dedicated matching engine.
- Central OMS.
- Central settlement engine.
- Central risk engine.
- Market data service with normalized contract.
- Public and private WebSocket stream protocols.
- Exchange instrument service.
- Spot self-trade prevention and advanced order semantics.
- Futures risk tiers, funding engine, mark/index engine, insurance fund, ADL, liquidation engine.
- Margin borrow/repay/interest/collateral engine.
- Developer organizations/applications/API keys/API secrets/scopes/IP allowlists.
- Usage metering and API monetization.
- Sandbox/testnet environment isolated from production.
- Webhook delivery service with signing/retries/logs.
- Production SOR venue adapters with signing, reconciliation, circuit breakers.
- Observability: metrics, tracing, health checks, alerting, ledger imbalance detection.

## V. Components That Must Be Reused

Do not rebuild these blindly:

- Laravel `api-gateway` application.
- User/auth/session/Sanctum foundation.
- 2FA, KYC, audit, admin RBAC/security layers.
- Existing migrations as historical schema.
- `LedgerService` as the starting double-entry foundation.
- `Account` / `LedgerTransaction` / `LedgerEntry` concepts.
- Wallet/deposit/withdrawal/treasury services as migration sources.
- `TradeService` API/matching logic as a development baseline and compatibility layer.
- Futures models and services as the futures migration base.
- `SwapEngineService` quote/execution workflow.
- SOR and market-maker service classes as prototypes/foundations.
- Redis/Node realtime service.
- React trading terminals and chart components.
- Existing automated tests.

## W. Components That Need Refactoring

- Balance model consolidation around one canonical ledger.
- `UnifiedTradingAccountService` and `UnifiedTradingReservationService` into ledger-backed projections/holds.
- `TransactionService`, `WalletService`, `TransferService` to stop bypassing ledger.
- `TradeService` into OMS + matching adapter + settlement adapter.
- `SpotTradingService` should be deprecated or rewritten against the canonical trading account/ledger.
- `FuturesOrderService` to integrate with central OMS/risk/settlement.
- `FuturesRiskEngineService` into a full risk engine.
- `FuturesLiquidationService` into full liquidation/insurance/ADL.
- `SmartOrderRoutingService` to eliminate float math and add venue state/reconciliation.
- Market data service to centralize Binance/CoinGecko/internal feeds.
- WebSocket server to add auth, sequence, replay and developer stream contracts.
- Frontend market data to stop direct provider fallback in production.

## X. Components That Need Replacement

Replace only where justified:

- Production matching core: current Laravel matcher should not be the final matching engine.
- Float fallback decimal utilities in financial/trading services.
- Simulated external order execution as a production path.
- Ledger rollback deletion behavior.
- Any direct mutable balance writes that bypass ledger/reservation/settlement.
- Unauthenticated/private WebSocket room subscription model.

## Y. Recommended Target Architecture

Target architecture should evolve from the existing repo, not around a disconnected rewrite:

```text
apps/web, apps/mobile, apps/admin, packages/sdk
        |
Laravel API Gateway
        |
Auth / KYC / 2FA / RBAC / Security Layer
        |
Trading API Facade
        |
Central OMS
        |
Command Bus / Sequencer
        |
Matching Engine              Smart Order Router
        |                         |
Execution Events            External Venue Adapters
        |                         |
Settlement Engine <---------- Reconciliation
        |
Reservation / Holds
        |
Double-Entry Ledger
        |
Account Balance Projections
        |
Wallet / Custody / Treasury
```

Supporting services:

```text
Market Data Service
  - internal trades/order books/candles
  - external reference feeds
  - mark/index price construction

Risk Service
  - pre-trade checks
  - account/exposure checks
  - futures margin/liquidation
  - product eligibility

Realtime Service
  - public streams
  - private authenticated streams
  - sequence/replay/resync

Developer Platform
  - organizations
  - apps
  - API keys/secrets
  - scopes
  - webhooks
  - sandbox
  - metering
```

## Z. Implementation Roadmap

### Phase 0: Audit

Objectives:

- Complete repository and infrastructure audit.
- Produce this report.

Definition of Done:

- `docs/exaearn-trading-infrastructure-audit.md` exists.
- No Phase 1 code changes made.

### Phase 1: Financial Core

Objectives:

- Make double-entry ledger the canonical source of truth.
- Create canonical accounts and reservation/hold model.
- Remove unsafe direct money mutations from trading paths.

Existing code to reuse:

- `LedgerService`, `Account`, `LedgerTransaction`, `LedgerEntry`.
- `UnifiedTradingAccountService` as migration reference.
- `WalletService`, `TransactionService`, `TransferService` as legacy sources.

Existing code to modify:

- Wallet, transfer, swap, spot, futures settlement paths.
- Ledger rollback behavior.

New services required:

- `BalanceProjectionService`.
- `ReservationService`.
- `SettlementService`.
- `LedgerReversalService`.

Database changes:

- Reservation/hold table.
- Ledger migration/audit mapping tables.
- Strong idempotency keys.
- Unique client operation references.

Security:

- Fail-closed production auth.
- Operation-level authorization.
- Admin ledger adjustment RBAC.

Tests:

- Balanced entries.
- Idempotency.
- Concurrent reservations.
- Transfers.
- Reversals.
- Legacy balance migration.

Risks:

- Balance migration must not duplicate or erase value.

Definition of Done:

- Every financial movement posts immutable balanced entries.
- Available/locked/transferable balances are projections, not separate sources of truth.

### Phase 2: Spot Trading Foundation

Objectives:

- Introduce central OMS.
- Build or integrate dedicated matching engine.
- Keep existing app APIs stable during migration.

Existing code to reuse:

- `TradeService`, `Order`, `Trade`, `Market`, `OrderBook`.

Existing code to modify:

- `TradeService` becomes API/adapter layer.
- Settlement moves into `SettlementService`.

New services required:

- `InstrumentService`.
- `OrderManagementService`.
- `MatchingEngine`.
- `ExecutionJournal`.
- `OrderBookSnapshotService`.

Database changes:

- Client order IDs.
- Execution events.
- Sequence numbers.
- Order state transition audit.

Security:

- Pre-trade checks.
- Self-trade prevention.
- Rate limits.

Tests:

- Limit/market matching.
- Partial fills.
- Cancel/release.
- Self-trade prevention.
- Deterministic replay.
- Concurrent orders.

Dependencies:

- Phase 1 ledger/reservations.

Risks:

- Migration from Laravel matcher to dedicated engine.

Definition of Done:

- Spot orders execute deterministically and settle atomically through ledger-backed settlement.

### Phase 3: Market Data

Objectives:

- Build normalized ExaEarn market-data contract.
- Calculate tickers/candles/order books from internal trades where available.
- Use external feeds only as reference/fallback/index data.

Existing code to reuse:

- `TradeService` market-data methods.
- `MarketStreamService`.
- Node realtime service.
- Frontend `marketDataService`.

Database changes:

- Ticker snapshots.
- Kline/candle tables or materialized aggregates.
- Market data freshness table.

API changes:

- `/api/v1/market/tickers`
- `/api/v1/market/order-book`
- `/api/v1/market/trades`
- `/api/v1/market/klines`

Tests:

- Snapshot load.
- 24h stats.
- Stale data.
- Symbol normalization.
- Spot/perp separation.

Definition of Done:

- Markets, Spot, Futures, Convert and dashboard read from one normalized ExaEarn market-data layer.

### Phase 4: Convert / Swap

Objectives:

- Harden quote engine, pricing, spread, execution and settlement.

Existing code to reuse:

- `SwapEngineService`, `Quote`, `Swap`, `CryptoLiquidityService`, `FxRateService`.

New services required:

- `QuoteLockService`.
- `ConvertPricingService`.
- `ConvertSettlementService`.

Database changes:

- Quote audit/versioning.
- Provider route records.

Tests:

- Expired quote rejection.
- Fee/spread calculations.
- External failure unwind.
- Idempotency.

Definition of Done:

- Convert executes from real quote through atomic ledger-backed settlement without fake provider success.

### Phase 5: Futures Hardening

Objectives:

- Preserve existing futures code while building exchange-grade risk, position, funding and liquidation infrastructure.

Existing code to reuse:

- Futures models, migrations, `FuturesOrderService`, `FuturesPositionService`, `MarginModeService`.

Existing code to modify:

- `FuturesRiskEngineService`.
- `FuturesLiquidationService`.
- `FuturesExecutionService`.

New services required:

- Mark/index price service.
- Funding scheduler.
- Insurance fund service.
- ADL service.
- Position accounting service.
- Futures settlement service.

Database changes:

- Risk tiers.
- Funding intervals.
- Funding index.
- Insurance ledger accounts.
- Liquidation events.
- ADL queue.

Tests:

- Initial/maintenance margin.
- Funding settlement.
- Liquidation.
- Partial liquidation.
- Position close/open.
- Cross/isolated margin.

Dependencies:

- Phase 1 ledger and Phase 3 market data.

Definition of Done:

- Futures PnL, margin, funding and liquidation are server-authoritative and ledger-settled.

### Phase 6: Margin

Objectives:

- Build spot/cross/isolated margin from scratch on the canonical ledger.

New services required:

- Borrow service.
- Repay service.
- Interest accrual service.
- Collateral service.
- Margin liquidation service.

Database changes:

- Loan accounts.
- Collateral balances.
- Interest accrual records.
- Margin risk records.

Tests:

- Borrow/repay.
- Interest accrual.
- Margin level.
- Liquidation.

Definition of Done:

- Margin product works independently from futures margin and has full risk/ledger coverage.

### Phase 7: Unified API Gateway

Objectives:

- Formalize versioned exchange APIs.

Existing code to reuse:

- Laravel route/controller structure.
- Sanctum/security middleware.

New services required:

- API request signing.
- API scopes.
- API rate limiter.
- Idempotency middleware.

API changes:

- `/api/v1/market`
- `/api/v1/spot`
- `/api/v1/futures`
- `/api/v1/margin`
- `/api/v1/convert`
- `/api/v1/account`
- `/api/v1/wallet`
- `/api/v1/transfer`

Tests:

- Auth.
- Scopes.
- Rate limits.
- Signed requests.
- Replay protection.

Definition of Done:

- App and future external APIs are clearly separated and secure.

### Phase 8: Developer Platform

Objectives:

- Support approved third-party developers.

New infrastructure:

- Developer organizations.
- Applications.
- API keys/secrets.
- Hashed secret storage.
- Scopes.
- IP allowlists.
- Usage metering.
- Billing hooks.

Security:

- Withdrawal scopes disabled by default.
- Strong approval workflows.

Definition of Done:

- Developers can create sandbox apps and receive scoped credentials.

### Phase 9: Sandbox/Testnet

Objectives:

- Create isolated sandbox environment.

New infrastructure:

- Sandbox accounts.
- Sandbox matching markets.
- Sandbox fake assets clearly isolated from production.
- Testnet WebSocket.

Definition of Done:

- Developers can test without touching production balances.

### Phase 10: Webhooks

Objectives:

- Build outbound webhook delivery.

New services:

- Webhook subscriptions.
- Signing.
- Retry queue.
- Delivery logs.
- Dead-letter handling.

Tests:

- Signature verification.
- Retries.
- Idempotency.

Definition of Done:

- Developers can receive reliable signed events.

### Phase 11: Developer Portal

Objectives:

- Documentation, API reference, SDK quickstarts, webhook docs.

Existing code to reuse:

- `packages/sdk`.
- Website app.

Definition of Done:

- Developers can self-serve sandbox integrations.

### Phase 12: Liquidity and Market-Making Infrastructure

Objectives:

- Harden internal market maker, liquidity pools and SOR.

Existing code to reuse:

- Market-maker services/tables.
- Liquidity services/tables.
- SOR services/logs.

New requirements:

- Inventory/capital limits.
- Circuit breakers.
- Venue reconciliation.
- Signed venue adapters.
- Treasury/custody coordination.

Definition of Done:

- Internal books can be bootstrapped safely while external liquidity remains controlled.

### Phase 13: Institutional Infrastructure

Objectives:

- Build institutional-grade access.

New infrastructure:

- Subaccounts.
- Organization-level limits.
- FIX gateway.
- Higher-limit approvals.
- Institutional risk controls.
- API SLA monitoring.

Definition of Done:

- ExaEarn can safely support institutional clients and approved infrastructure partners.

