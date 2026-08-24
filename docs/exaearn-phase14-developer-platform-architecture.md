# ExaEarn Phase 14 Developer Platform Architecture

## Objective

Phase 14 exposes controlled external interfaces around ExaEarn's completed internal infrastructure.

```text
Developer client
  -> Developer API gateway
  -> HMAC auth / permissions / request log
  -> Existing ExaEarn service
  -> Canonical result
```

Phase 14 does not create a parallel matching engine, wallet, ledger, custody system, market data engine or risk engine.

## Implemented Components

- Developer project model: `developer_projects`
- Developer API keys: `developer_api_keys`
- Key permissions: `developer_api_key_permissions`
- IP whitelist: `developer_api_key_ip_whitelists`
- Request logs: `developer_api_request_logs`
- Sandbox faucet claims: `developer_sandbox_faucet_claims`
- Isolated sandbox balances: `developer_sandbox_balances`
- Webhook endpoint and delivery schema: `developer_webhook_endpoints`, `developer_webhook_deliveries`
- Audit logs: `developer_audit_logs`
- Middleware: `DeveloperApiRequestContext`, `DeveloperApiAuth`
- Controllers: `DeveloperApiController`, `DeveloperPortalController`
- SDK: `@exaearn/sdk`
- Developer portal app: `@exaearn/developers`

## Authentication

Private developer routes require:

```text
EXA-API-KEY
EXA-API-TIMESTAMP
EXA-API-SIGNATURE
EXA-API-PASSPHRASE when configured
```

Canonical payload:

```text
METHOD
/api/developer/v1/path
query-string
timestamp
sha256(request_body)
```

The server verifies timestamp tolerance, key state, passphrase, IP whitelist, HMAC signature and endpoint permissions.

## Environments

`sandbox` projects generate `exa_test_` keys and read `developer_sandbox_balances`.

`production` projects generate `exa_live_` keys and read real ExaEarn wallet balances. Production order APIs submit through existing OMS and risk controls.

## Permission Model

Initial permissions are configured in `config/developer_api.php`.

- `market.read`
- `account.read`
- `spot.read`
- `spot.trade`
- `wallet.read`
- `wallet.withdraw`
- `webhook.manage`

High-risk `wallet.withdraw` keys require IP whitelist controls before creation.

## API Surface Implemented

Public:

- `GET /api/developer/v1/exchange-info`
- `GET /api/developer/v1/markets`
- `GET /api/developer/v1/tickers`
- `GET /api/developer/v1/ticker/{symbol}`
- `GET /api/developer/v1/orderbook/{symbol}`
- `GET /api/developer/v1/trades/{symbol}`
- `GET /api/developer/v1/klines/{symbol}`

Signed:

- `GET /api/developer/v1/wallet/balances`
- `POST /api/developer/v1/spot/orders`
- `GET /api/developer/v1/spot/orders/{orderId}`

Portal:

- Project listing and creation
- API key creation, rotation and disable
- Sandbox faucet
- Webhook endpoint listing

## Rollout-Gated Surfaces

Futures, margin, custody withdrawals, full webhook delivery, OAuth app review and public WebSocket gateway remain gated until product/security operations explicitly enable them.
# Final API Surface Completion

The developer API now exposes product families through signed `developer/v1` routes while reusing the existing first-party controllers and services:

- Futures routes use `FuturesController`, `FuturesOrderService` and existing Futures risk/margin paths.
- Margin routes use `MarginController` and Margin borrow, repay, transfer, health, order and realtime services.
- Staking routes use `StakingController` and native user staking services only.
- Copy Trading routes use `CopyTradingController`, public-mode eligibility, capacity and follower risk controls.
- ExaAI routes use `ExaAiController`, strategy governance, operational mode and kill-switch controls.

No developer route writes directly to balances, positions, ledger entries, fills or PnL.
