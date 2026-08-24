# ExaEarn Phase 14 Developer Platform Audit

## Existing Infrastructure Reused

| Domain | Existing Source | Phase 14 Usage |
| --- | --- | --- |
| Public market data | `MarketDataService` | Exposed through `/api/developer/v1/*` public endpoints. |
| Spot trading | `TradeService`, Spot OMS, risk and settlement path | Signed `spot.trade` API submits orders through the existing service. |
| Wallet balances | Existing user `wallets` relationship | Production developer keys read existing wallet balance fields. |
| Sandbox balances | New `developer_sandbox_balances` read model | Sandbox keys read isolated test balances, not real wallet rows. |
| Authenticated portal | Laravel auth/Sanctum | Developer project/key/faucet routes use existing user auth. |
| Request logging | Laravel middleware | New request IDs and developer API request logs. |

## Current Source Map

| API Field | Source | Authoritative | Notes |
| --- | --- | --- | --- |
| symbols | `MarketDataService::symbols()` | Yes for ExaEarn market contract | Preserves internal/reference source semantics from Phase 3. |
| ticker | `MarketDataService::ticker()` | Yes for normalized market data | No provider-specific schema exposed. |
| orderbook | `MarketDataService::orderBook()` | Yes for internal markets | Provider depth must remain separate by Phase 3 policy. |
| trades | `MarketDataService::recentTrades()` | Yes for internal markets | External/reference data is not labelled as ExaEarn volume. |
| klines | `MarketDataService::candles()` | Yes for internal markets | Uses Phase 3 candle engine policy. |
| production balances | `User->wallets()` | Compatibility read | Future migration can swap to `BalanceProjectionService` without changing API shape. |
| sandbox balances | `developer_sandbox_balances` | Sandbox-only | Prevents simulated faucet funds from touching real wallets. |
| Spot order create | `TradeService::placeOrder()` | Yes | Metadata includes `source=developer_api` and request ID. |

## Gaps Classified

| Gap | Classification | Status |
| --- | --- | --- |
| Futures external trading API | Software/product gating | Not exposed in Phase 14 foundation. |
| Custody withdrawal developer API | Security/operations gating | Not exposed until stronger controls are approved. |
| Webhook delivery worker | Software follow-up | Storage schema exists; delivery jobs are rollout-gated. |
| Public WebSocket gateway | Infrastructure/deployment | Protocol documented separately; external fanout not activated here. |
| OAuth developer app marketplace | Product follow-up | API key flow implemented first. |

## Safety Decision

Phase 14 foundation reuses canonical systems and does not introduce a duplicate exchange, wallet, ledger or matching engine. Sandbox balances are isolated from real wallets.
