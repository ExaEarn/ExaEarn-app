import { requestContracts } from "./openapiRequestSchemas.generated";

export type ApiStatus = "STABLE" | "BETA" | "RESTRICTED" | "DEPRECATED";
export type HttpMethod = "GET" | "POST" | "PATCH" | "DELETE";

export type Endpoint = {
  id: string;
  group: string;
  title: string;
  method: HttpMethod;
  path: string;
  status: ApiStatus;
  scope?: string;
  auth: "Public" | "Signed";
  rate: string;
  idempotency: string;
  description: string;
  parameters?: Array<[string, string, string, string]>;
  body?: string;
  response: string;
  events?: string[];
};

const success = (data: string) => `{
  "success": true,
  "data": ${data},
  "timestamp": 1787990400
}`;

const coreEndpoints: Endpoint[] = [
  { id: "server-time", group: "Market data", title: "Server time", method: "GET", path: "/api/developer/v1/time", status: "STABLE", auth: "Public", rate: "240 requests/minute", idempotency: "Not applicable", description: "Returns canonical UTC time for signed-request clock synchronization.", response: success('{ "unix_seconds": 1787990400, "unix_milliseconds": 1787990400000, "iso_8601": "2026-08-29T00:00:00.000000Z", "timezone": "UTC" }') },
  { id: "exchange-info", group: "Market data", title: "Exchange information", method: "GET", path: "/api/developer/v1/exchange-info", status: "STABLE", auth: "Public", rate: "240 requests/minute", idempotency: "Not applicable", description: "Returns supported symbols, trading metadata, timezone, and server time.", response: success('{ "symbols": [], "timezone": "UTC", "server_time": "2026-08-29T00:00:00Z" }') },
  { id: "tickers", group: "Market data", title: "All tickers", method: "GET", path: "/api/developer/v1/tickers", status: "STABLE", auth: "Public", rate: "240 requests/minute", idempotency: "Not applicable", description: "Returns normalized ExaEarn market tickers with explicit source semantics.", response: success("[]") },
  { id: "ticker", group: "Market data", title: "Ticker", method: "GET", path: "/api/developer/v1/ticker/{symbol}", status: "STABLE", auth: "Public", rate: "240 requests/minute", idempotency: "Not applicable", description: "Returns one normalized market ticker.", parameters: [["symbol", "string", "required", "Canonical pair, for example BTC-USDT."]], response: success('{ "symbol": "BTC-USDT", "source": "EXAEARN_INTERNAL" }') },
  { id: "orderbook", group: "Market data", title: "Order book snapshot", method: "GET", path: "/api/developer/v1/orderbook/{symbol}", status: "STABLE", auth: "Public", rate: "240 requests/minute", idempotency: "Not applicable", description: "Returns a sequenced authoritative order-book snapshot. Internal books contain ExaEarn resting orders only.", parameters: [["symbol", "string", "required", "Canonical pair."], ["limit", "integer", "optional", "Depth 5-100; default 50."]], response: success('{ "symbol": "BTC-USDT", "sequence": 1842, "bids": [], "asks": [] }'), events: ["market.{symbol}.book"] },
  { id: "trades", group: "Market data", title: "Recent trades", method: "GET", path: "/api/developer/v1/trades/{symbol}", status: "STABLE", auth: "Public", rate: "240 requests/minute", idempotency: "Not applicable", description: "Returns bounded authoritative public executions for an internal Spot market.", parameters: [["symbol", "string", "required", "Canonical pair."], ["limit", "integer", "optional", "1-1000; default 100."]], response: success("[]"), events: ["market.{symbol}.trade"] },
  { id: "klines", group: "Market data", title: "Candlesticks", method: "GET", path: "/api/developer/v1/klines/{symbol}", status: "STABLE", auth: "Public", rate: "240 requests/minute", idempotency: "Not applicable", description: "Returns candles built from authoritative ExaEarn executions.", parameters: [["symbol", "string", "required", "Canonical pair."], ["interval", "enum", "optional", "1m, 3m, 5m, 15m, 30m, 1h, 4h, 1d."], ["limit", "integer", "optional", "1-1000; default 500."]], response: success("[]"), events: ["market.{symbol}.kline.{interval}"] },
  { id: "balances", group: "Wallet", title: "Account balances", method: "GET", path: "/api/developer/v1/wallet/balances", status: "STABLE", scope: "account.read", auth: "Signed", rate: "120 requests/minute", idempotency: "Not applicable", description: "Returns sandbox-isolated balances for test projects or canonical wallets for production projects.", response: success('[{ "asset": "USDT", "available": "10000.00000000", "total": "10000.00000000", "environment": "sandbox" }]'), events: ["account.balance"] },
  { id: "spot-create-order", group: "Spot trading", title: "Create Spot order", method: "POST", path: "/api/developer/v1/spot/orders", status: "STABLE", scope: "spot.trade", auth: "Signed", rate: "60 trade requests/minute", idempotency: "Use unique client_order_id", description: "Submits an order through the same Spot OMS, reservations, matching, risk, and settlement used by ExaEarn clients.", parameters: [["symbol", "string", "required", "Canonical pair."], ["side", "enum", "required", "buy or sell."], ["type", "enum", "required", "market, limit, stop_loss, take_profit."], ["quantity", "decimal string", "required", "Must satisfy market step and minimum rules."], ["price", "decimal string", "conditional", "Required for price-based order types."], ["client_order_id", "string", "optional", "Maximum 80 characters; use for economic idempotency."]], body: '{\n  "symbol": "BTC-USDT",\n  "side": "buy",\n  "type": "limit",\n  "quantity": "0.001",\n  "price": "65000",\n  "client_order_id": "bot-20260829-001"\n}', response: success('{ "order_id": "...", "status": "NEW" }'), events: ["order", "trade", "account.balance"] },
  { id: "spot-query-order", group: "Spot trading", title: "Query Spot order", method: "GET", path: "/api/developer/v1/spot/orders/{orderId}", status: "STABLE", scope: "spot.read", auth: "Signed", rate: "120 requests/minute", idempotency: "Not applicable", description: "Returns an order owned by the authenticated account.", parameters: [["orderId", "string", "required", "ExaEarn order UUID."]], response: success('{ "order_id": "...", "status": "PARTIALLY_FILLED" }') },
  { id: "futures-create-order", group: "Futures", title: "Create Futures order", method: "POST", path: "/api/developer/v1/futures/orders", status: "RESTRICTED", scope: "futures.trade", auth: "Signed", rate: "60 trade requests/minute", idempotency: "Use unique client_order_id", description: "Routes an approved Futures request through canonical margin, risk, OMS, and settlement controls.", parameters: [["symbol", "string", "required", "Contract symbol."], ["side", "enum", "required", "long or short."], ["type", "enum", "required", "market, limit, stop-market, stop-limit, trailing-stop."], ["quantity", "decimal string", "required", "Contract quantity."], ["leverage", "integer", "required", "1 to market maximum."], ["reduce_only", "boolean", "optional", "Cannot increase exposure when true."]], body: '{\n  "symbol": "BTCUSDT",\n  "side": "long",\n  "type": "limit",\n  "quantity": "0.01",\n  "price": "65000",\n  "leverage": 3,\n  "client_order_id": "fut-001"\n}', response: success('{ "order_uuid": "...", "status": "NEW" }'), events: ["order", "trade", "position", "margin"] },
  { id: "futures-positions", group: "Futures", title: "Futures positions", method: "GET", path: "/api/developer/v1/futures/positions", status: "RESTRICTED", scope: "futures.read", auth: "Signed", rate: "120 requests/minute", idempotency: "Not applicable", description: "Returns positions for the authenticated account. Futures API access is controlled rollout.", response: success('{ "data": [], "current_page": 1 }'), events: ["position", "margin"] },
  { id: "margin-borrow", group: "Margin", title: "Borrow asset", method: "POST", path: "/api/developer/v1/margin/borrow", status: "RESTRICTED", scope: "margin.manage", auth: "Signed", rate: "60 trade requests/minute", idempotency: "Recommended: idempotency_key", description: "Borrows through the existing lending pool, account eligibility, liquidity, and margin-risk controls.", body: '{\n  "account_uuid": "...",\n  "asset": "USDT",\n  "amount": "100",\n  "idempotency_key": "borrow-001"\n}', response: success('{ "loan_uuid": "...", "status": "ACTIVE" }') },
  { id: "convert-quote", group: "Convert", title: "Create Convert quote", method: "POST", path: "/api/developer/v1/convert/quote", status: "STABLE", scope: "convert.execute", auth: "Signed", rate: "120 requests/minute", idempotency: "Not applicable; quotes expire", description: "Creates a bounded quote using existing pricing, liquidity, treasury, and source-priority controls.", response: success('{ "quote_id": "...", "expires_at": "..." }') },
  { id: "convert-execute", group: "Convert", title: "Execute Convert quote", method: "POST", path: "/api/developer/v1/convert/execute", status: "STABLE", scope: "convert.execute", auth: "Signed", rate: "60 trade requests/minute", idempotency: "Required by Convert contract", description: "Executes an unexpired quote through canonical reservations, settlement, and ledger services.", response: success('{ "swap_id": "...", "status": "completed" }') },
  { id: "staking-products", group: "Earn / staking", title: "Staking products", method: "GET", path: "/api/developer/v1/staking/products", status: "STABLE", scope: "staking.read", auth: "Signed", rate: "120 requests/minute", idempotency: "Not applicable", description: "Returns table-backed staking products and operational availability. Mainnet products remain fail-closed until provider setup.", response: success("[]") },
  { id: "staking-subscribe", group: "Earn / staking", title: "Create staking position", method: "POST", path: "/api/developer/v1/staking/positions", status: "STABLE", scope: "staking.manage", auth: "Signed", rate: "60 requests/minute", idempotency: "Required: idempotency_key", description: "Creates a position through canonical principal reservation and provider-readiness checks.", body: '{\n  "staking_product_id": 1,\n  "amount": "10",\n  "terms_version": "v1",\n  "idempotency_key": "stake-001"\n}', response: success('{ "public_id": "...", "status": "pending" }'), events: ["staking"] },
  { id: "realtime-session", group: "WebSocket", title: "Create realtime session", method: "POST", path: "/api/developer/v1/realtime/session", status: "STABLE", scope: "account.read", auth: "Signed", rate: "120 requests/minute", idempotency: "Not applicable", description: "Creates a ten-minute environment-bound session. Connect to /ws/developer/sandbox or /ws/developer/production and authenticate with the returned devws token. Topic-specific scopes and Production capabilities are revalidated by the gateway. Heartbeats run every 30 seconds; slow consumers are disconnected and recover using replay/REST reconciliation.", body: '{\n  "topics": ["account.balance"]\n}', response: success('{ "session_id": "devws_...", "environment": "sandbox", "heartbeat_seconds": 30, "max_subscriptions": 100, "queue_limit": 1000 }') },
  { id: "realtime-replay", group: "WebSocket", title: "Replay private events", method: "GET", path: "/api/developer/v1/realtime/replay", status: "STABLE", scope: "account.read", auth: "Signed", rate: "120 requests/minute", idempotency: "Not applicable", description: "Returns ordered durable events after the last confirmed per-stream sequence.", parameters: [["stream", "string", "required", "Private stream name."], ["after_sequence", "integer", "required", "Last confirmed sequence; minimum 0."], ["limit", "integer", "optional", "1-500."]], response: success('[{ "event_id": "...", "stream": "order", "sequence": 101, "event_type": "order.updated", "payload": {}, "timestamp": "..." }]') },
];

type SecondaryRoute = [string, string, string, HttpMethod, string, string, ApiStatus, string, string?];
const secondaryRoutes: SecondaryRoute[] = [
  ["api-status", "Market data", "API capability status", "GET", "/api/developer/v1/status", "", "STABLE", "Returns configured product status, scopes, websocket topics, and webhook events."],
  ["operational-status", "Market data", "Operational status", "GET", "/api/developer/v1/operational-status", "", "STABLE", "Returns sanitized Phase 19 service telemetry without internal dependency details."],
  ["markets", "Market data", "Market symbols", "GET", "/api/developer/v1/markets", "", "STABLE", "Returns supported market symbols."],
  ["futures-markets", "Futures", "Contract information", "GET", "/api/developer/v1/futures/markets", "futures.read", "RESTRICTED", "Returns the controlled Futures market catalog."],
  ["futures-open-orders", "Futures", "Open Futures orders", "GET", "/api/developer/v1/futures/open-orders", "futures.read", "RESTRICTED", "Returns current open orders, optionally filtered by symbol."],
  ["futures-orders", "Futures", "Futures order history", "GET", "/api/developer/v1/futures/orders", "futures.read", "RESTRICTED", "Returns paginated account Futures orders."],
  ["futures-order", "Futures", "Query Futures order", "GET", "/api/developer/v1/futures/orders/{orderUuid}", "futures.read", "RESTRICTED", "Returns one account-owned Futures order."],
  ["futures-trades", "Futures", "Futures trade history", "GET", "/api/developer/v1/futures/trades", "futures.read", "RESTRICTED", "Returns bounded Futures executions."],
  ["futures-margin-status", "Futures", "Futures margin status", "GET", "/api/developer/v1/futures/margin/status", "futures.read", "RESTRICTED", "Returns authoritative user Futures margin state."],
  ["futures-validate", "Futures", "Validate Futures order", "POST", "/api/developer/v1/futures/orders/validate", "futures.trade", "RESTRICTED", "Runs order and risk validation without submitting an order.", "FuturesOrderRequest"],
  ["futures-conditional", "Futures", "Create conditional order", "POST", "/api/developer/v1/futures/orders/conditional", "futures.trade", "RESTRICTED", "Creates an implemented stop-loss or take-profit conditional order.", "FuturesConditionalOrderRequest"],
  ["futures-batch-cancel", "Futures", "Batch cancel Futures orders", "POST", "/api/developer/v1/futures/orders/batch-cancel", "futures.trade", "RESTRICTED", "Cancels up to 50 account-owned Futures order UUIDs.", "BatchCancelRequest"],
  ["futures-cancel", "Futures", "Cancel Futures order", "DELETE", "/api/developer/v1/futures/orders/{orderUuid}", "futures.trade", "RESTRICTED", "Cancels an eligible account-owned Futures order."],
  ["futures-margin-type", "Futures", "Set Futures margin mode", "POST", "/api/developer/v1/futures/margin/type", "futures.trade", "RESTRICTED", "Changes an eligible position between cross and isolated margin.", "FuturesMarginTypeRequest"],
  ["margin-overview", "Margin", "Margin overview", "GET", "/api/developer/v1/margin/overview", "margin.read", "RESTRICTED", "Returns account, loan, order, and enabled lending-pool state."],
  ["margin-accounts", "Margin", "Margin accounts", "GET", "/api/developer/v1/margin/accounts", "margin.read", "RESTRICTED", "Returns account-owned margin accounts."],
  ["margin-assets", "Margin", "Supported margin assets", "GET", "/api/developer/v1/margin/assets", "margin.read", "RESTRICTED", "Returns configured margin assets and risk parameters."],
  ["margin-pools", "Margin", "Margin lending pools", "GET", "/api/developer/v1/margin/pools", "margin.read", "RESTRICTED", "Returns lending liquidity state without permitting pool administration."],
  ["margin-health", "Margin", "Margin risk state", "GET", "/api/developer/v1/margin/health", "margin.read", "RESTRICTED", "Returns authoritative health for an account selected by account_uuid."],
  ["margin-loans", "Margin", "Margin liabilities", "GET", "/api/developer/v1/margin/loans", "margin.read", "RESTRICTED", "Returns paginated margin loans."],
  ["margin-orders", "Margin", "Margin orders", "GET", "/api/developer/v1/margin/orders", "margin.read", "RESTRICTED", "Returns paginated margin orders and linked Spot orders."],
  ["margin-interest", "Margin", "Margin interest", "GET", "/api/developer/v1/margin/interest", "margin.read", "RESTRICTED", "Returns actual interest accrual records."],
  ["margin-realtime", "Margin", "Margin realtime snapshot", "GET", "/api/developer/v1/margin/realtime/snapshot", "margin.read", "RESTRICTED", "Returns bounded ordered margin realtime records."],
  ["margin-create-account", "Margin", "Create margin account", "POST", "/api/developer/v1/margin/accounts", "margin.manage", "RESTRICTED", "Creates or retrieves a CROSS or ISOLATED margin account.", "MarginAccountRequest"],
  ["margin-transfer", "Margin", "Transfer margin collateral", "POST", "/api/developer/v1/margin/transfer", "margin.manage", "RESTRICTED", "Moves collateral through canonical margin transfer and ledger controls.", "MarginTransferRequest"],
  ["margin-repay", "Margin", "Repay margin liability", "POST", "/api/developer/v1/margin/loans/{loanUuid}/repay", "margin.manage", "RESTRICTED", "Repays an account-owned margin loan idempotently.", "MarginRepayRequest"],
  ["margin-place-order", "Margin", "Create margin order", "POST", "/api/developer/v1/margin/orders", "margin.manage", "RESTRICTED", "Submits through canonical margin risk and Spot OMS paths.", "MarginOrderRequest"],
  ["margin-cancel-order", "Margin", "Cancel margin order", "POST", "/api/developer/v1/margin/orders/{marginOrderUuid}/cancel", "margin.manage", "RESTRICTED", "Cancels an eligible account-owned margin order."],
  ["convert-meta", "Convert", "Convert metadata", "GET", "/api/developer/v1/convert/meta", "convert.read", "STABLE", "Returns supported Convert assets and quote rules."],
  ["convert-history", "Convert", "Convert history", "GET", "/api/developer/v1/convert/history", "convert.read", "STABLE", "Returns paginated account Convert history."],
  ["convert-result", "Convert", "Query Convert", "GET", "/api/developer/v1/convert/{swapId}", "convert.read", "STABLE", "Returns one account-owned Convert result."],
  ["exapay-merchants", "ExaPay", "ExaPay merchants", "GET", "/api/developer/v1/exapay/merchants", "exapay.read", "BETA", "Returns merchants available to the authenticated owner."],
  ["exapay-overview", "ExaPay", "Merchant overview", "GET", "/api/developer/v1/exapay/merchants/{merchantId}/overview", "exapay.read", "BETA", "Returns merchant payment and settlement overview."],
  ["exapay-payments", "ExaPay", "Merchant payments", "GET", "/api/developer/v1/exapay/merchants/{merchantId}/payments", "exapay.read", "BETA", "Returns bounded merchant payment history."],
  ["exapay-links", "ExaPay", "Merchant payment links", "GET", "/api/developer/v1/exapay/merchants/{merchantId}/payment-links", "exapay.read", "BETA", "Returns merchant-owned payment links."],
  ["exapay-reconciliation", "ExaPay", "Merchant reconciliation", "GET", "/api/developer/v1/exapay/merchants/{merchantId}/reconciliation", "exapay.read", "BETA", "Returns real merchant reconciliation findings."],
  ["exapay-intent", "ExaPay", "Create payment intent", "POST", "/api/developer/v1/exapay/merchants/{merchantId}/payment-intents", "exapay.write", "BETA", "Creates a canonical merchant payment intent.", "ExaPayIntentRequest"],
  ["exapay-capture", "ExaPay", "Capture payment intent", "POST", "/api/developer/v1/exapay/payment-intents/{payIntent}/capture", "exapay.write", "BETA", "Captures an eligible intent through canonical settlement."],
  ["exapay-create-link", "ExaPay", "Create payment link", "POST", "/api/developer/v1/exapay/merchants/{merchantId}/payment-links", "exapay.write", "BETA", "Creates a merchant payment link.", "ExaPayLinkRequest"],
  ["exapay-refund", "ExaPay", "Refund merchant payment", "POST", "/api/developer/v1/exapay/merchants/{merchantId}/refunds", "exapay.refunds", "BETA", "Creates an idempotent canonical refund.", "ExaPayRefundRequest"],
  ["staking-assets", "Earn / staking", "Staking assets", "GET", "/api/developer/v1/staking/assets", "staking.read", "STABLE", "Returns supported native PoS asset configuration."],
  ["staking-product", "Earn / staking", "Staking product details", "GET", "/api/developer/v1/staking/products/{slug}", "staking.read", "STABLE", "Returns one table-backed product; changing rates are response data, not hardcoded documentation."],
  ["staking-portfolio", "Earn / staking", "Staking portfolio", "GET", "/api/developer/v1/staking/portfolio", "staking.read", "STABLE", "Returns principal and verified reward summary."],
  ["staking-positions", "Earn / staking", "Staking positions", "GET", "/api/developer/v1/staking/positions", "staking.read", "STABLE", "Returns account-owned staking positions."],
  ["staking-position", "Earn / staking", "Staking position status", "GET", "/api/developer/v1/staking/positions/{publicId}", "staking.read", "STABLE", "Returns one account-owned position."],
  ["staking-rewards", "Earn / staking", "Staking rewards", "GET", "/api/developer/v1/staking/rewards", "staking.read", "STABLE", "Returns estimated and verified reward fields with their actual lifecycle semantics."],
  ["staking-transactions", "Earn / staking", "Staking transaction history", "GET", "/api/developer/v1/staking/transactions", "staking.read", "STABLE", "Returns staking lifecycle transactions."],
  ["staking-terms", "Earn / staking", "Accept staking terms", "POST", "/api/developer/v1/staking/terms/accept", "staking.manage", "STABLE", "Records the required terms version.", "TermsAcceptanceRequest"],
  ["staking-unstake", "Earn / staking", "Request unstake", "POST", "/api/developer/v1/staking/positions/{publicId}/unstake", "staking.manage", "STABLE", "Moves eligible principal into canonical pending-unstake state.", "StakingUnstakeRequest"],
  ["staking-claim-native", "Earn / staking", "Claim verified native rewards", "POST", "/api/developer/v1/staking/positions/{publicId}/claim-native-rewards", "staking.manage", "STABLE", "Claims only verified payable native rewards through canonical ledger settlement."],
  ["staking-claim-exa", "Earn / staking", "Claim EXA campaign rewards", "POST", "/api/developer/v1/staking/positions/{publicId}/claim-exatoken-rewards", "staking.manage", "RESTRICTED", "Remains unavailable unless an approved ExaToken campaign is explicitly enabled."],
  ["staking-auto-compound", "Earn / staking", "Update auto-compound preference", "PATCH", "/api/developer/v1/staking/positions/{publicId}/auto-compound", "staking.manage", "BETA", "Updates the position preference; production compounding remains provider-policy dependent.", "AutoCompoundRequest"],
  ["copy-eligibility", "Copy trading", "Copy eligibility", "GET", "/api/developer/v1/copy/eligibility", "copy.read", "RESTRICTED", "Returns server-authoritative follower eligibility."],
  ["copy-leaders", "Copy trading", "Discover lead traders", "GET", "/api/developer/v1/copy/leaders", "copy.read", "RESTRICTED", "Returns only leaders allowed by public-mode and capacity controls."],
  ["copy-leader", "Copy trading", "Lead trader profile", "GET", "/api/developer/v1/copy/leaders/{id}", "copy.read", "RESTRICTED", "Returns one eligible public lead profile."],
  ["copy-relationships", "Copy trading", "Copy relationships", "GET", "/api/developer/v1/copy/relationships", "copy.read", "RESTRICTED", "Returns account-owned copy relationships."],
  ["copy-orders", "Copy trading", "Copied orders", "GET", "/api/developer/v1/copy/orders", "copy.read", "RESTRICTED", "Returns attributed follower orders."],
  ["copy-positions", "Copy trading", "Copied positions", "GET", "/api/developer/v1/copy/positions", "copy.read", "RESTRICTED", "Returns strategy-attributed positions, not a parallel wallet."],
  ["copy-pnl", "Copy trading", "Copy performance", "GET", "/api/developer/v1/copy/pnl", "copy.read", "RESTRICTED", "Returns actual attributed performance without promising returns."],
  ["copy-replay", "Copy trading", "Copy event replay", "GET", "/api/developer/v1/copy/realtime/replay", "copy.read", "RESTRICTED", "Returns durable copy events after sequence."],
  ["copy-terms", "Copy trading", "Accept Copy Trading terms", "POST", "/api/developer/v1/copy/terms/accept", "copy.manage", "RESTRICTED", "Records required follower terms.", "TermsAcceptanceRequest"],
  ["copy-follow", "Copy trading", "Follow lead trader", "POST", "/api/developer/v1/copy/follow", "copy.manage", "RESTRICTED", "Creates a capacity- and risk-controlled relationship.", "CopyFollowRequest"],
  ["copy-update", "Copy trading", "Update copy configuration", "PATCH", "/api/developer/v1/copy/follow/{id}", "copy.manage", "RESTRICTED", "Updates allowed follower allocation and risk controls.", "CopyUpdateRequest"],
  ["copy-stop", "Copy trading", "Stop copying", "DELETE", "/api/developer/v1/copy/follow/{id}", "copy.manage", "RESTRICTED", "Stops a relationship under existing close policy."],
  ["exaai-overview", "ExaAI", "ExaAI overview", "GET", "/api/developer/v1/exaai/overview", "exaai.read", "RESTRICTED", "Returns governed ExaAI product state without promising performance."],
  ["exaai-strategies", "ExaAI", "ExaAI strategies", "GET", "/api/developer/v1/exaai/strategies", "exaai.read", "RESTRICTED", "Returns strategies allowed by governance and market eligibility."],
  ["exaai-allocations", "ExaAI", "ExaAI allocations", "GET", "/api/developer/v1/exaai/allocations", "exaai.read", "RESTRICTED", "Returns account-owned allocations."],
  ["exaai-active", "ExaAI", "Active ExaAI allocation", "GET", "/api/developer/v1/exaai/allocations/active", "exaai.read", "RESTRICTED", "Returns current active allocation."],
  ["exaai-session-current", "ExaAI", "Current ExaAI session", "GET", "/api/developer/v1/exaai/sessions/current", "exaai.read", "RESTRICTED", "Returns current governed session."],
  ["exaai-sessions", "ExaAI", "ExaAI session history", "GET", "/api/developer/v1/exaai/sessions", "exaai.read", "RESTRICTED", "Returns paginated sessions."],
  ["exaai-portfolio", "ExaAI", "ExaAI portfolio", "GET", "/api/developer/v1/exaai/portfolio", "exaai.read", "RESTRICTED", "Returns attributed portfolio state."],
  ["exaai-positions", "ExaAI", "ExaAI positions", "GET", "/api/developer/v1/exaai/positions", "exaai.read", "RESTRICTED", "Returns actual attributed positions."],
  ["exaai-trades", "ExaAI", "ExaAI trades", "GET", "/api/developer/v1/exaai/trades", "exaai.read", "RESTRICTED", "Returns actual strategy-attributed executions."],
  ["exaai-performance", "ExaAI", "ExaAI performance", "GET", "/api/developer/v1/exaai/performance", "exaai.read", "RESTRICTED", "Returns historical performance without guarantees."],
  ["exaai-replay", "ExaAI", "ExaAI event replay", "GET", "/api/developer/v1/exaai/realtime/replay", "exaai.read", "RESTRICTED", "Returns durable private ExaAI events."],
  ["exaai-readiness", "ExaAI", "ExaAI operational readiness", "GET", "/api/developer/v1/exaai/readiness", "exaai.read", "RESTRICTED", "Returns safe product readiness, governance, and kill-switch state."],
  ["exaai-terms", "ExaAI", "Accept ExaAI terms", "POST", "/api/developer/v1/exaai/terms/accept", "exaai.manage", "RESTRICTED", "Records required product terms.", "TermsAcceptanceRequest"],
  ["exaai-create-allocation", "ExaAI", "Create ExaAI allocation", "POST", "/api/developer/v1/exaai/allocations", "exaai.manage", "RESTRICTED", "Creates an allocation under governance, risk, and market eligibility.", "ExaAiAllocationRequest"],
  ["exaai-start", "ExaAI", "Start ExaAI session", "POST", "/api/developer/v1/exaai/sessions", "exaai.manage", "RESTRICTED", "Starts an approved session subject to operational mode and kill switches.", "ExaAiSessionRequest"],
  ["exaai-pause", "ExaAI", "Pause ExaAI session", "POST", "/api/developer/v1/exaai/sessions/{id}/pause", "exaai.manage", "RESTRICTED", "Stops new strategy risk without fabricating position changes."],
  ["exaai-resume", "ExaAI", "Resume ExaAI session", "POST", "/api/developer/v1/exaai/sessions/{id}/resume", "exaai.manage", "RESTRICTED", "Resumes only after existing readiness validation."],
  ["exaai-stop", "ExaAI", "Stop ExaAI session", "POST", "/api/developer/v1/exaai/sessions/{id}/stop", "exaai.manage", "RESTRICTED", "Stops a governed session under canonical risk policy."],
];

const pathParameters = (path: string): Array<[string, string, string, string]> =>
  Array.from(path.matchAll(/\{([^}]+)\}/g)).map((match) => [match[1], "string", "required", "Canonical resource identifier."]);

const secondaryEndpoints: Endpoint[] = secondaryRoutes.map(([id, group, title, method, path, scope, status, description, schema]) => {
  const contract = (requestContracts as Record<string, { example?: unknown }>)[`${method} ${path}`];
  return {
    id, group, title, method, path, scope: scope || undefined, status,
    auth: scope === "" ? "Public" : "Signed",
    rate: method === "GET" ? (scope === "" ? "240 requests/minute" : "120 requests/minute") : "60 requests/minute",
    idempotency: method === "GET" ? "Not applicable" : (schema?.includes("Request") ? "Use the operation's stable idempotency/client identity where provided" : "Not applicable"),
    description,
    parameters: pathParameters(path),
    body: contract?.example === undefined ? undefined : JSON.stringify(contract.example, null, 2),
    response: success('{ "status": "See endpoint response schema" }'),
    events: group === "Futures" ? ["order", "trade", "position", "margin"] : group === "Margin" ? ["margin"] : group === "Earn / staking" ? ["staking"] : group === "Copy trading" ? ["copy"] : group === "ExaAI" ? ["exaai"] : undefined,
  };
});

export const endpoints: Endpoint[] = [...coreEndpoints, ...secondaryEndpoints];

export const scopes = [
  ["market.read", "LOW", "Public market metadata and streams."],
  ["account.read", "LOW", "Balances and private realtime session/replay."],
  ["spot.read", "LOW", "Read the account's Spot orders."],
  ["spot.trade", "HIGH", "Submit Spot orders through canonical OMS controls."],
  ["futures.read", "MEDIUM", "Read restricted Futures orders, positions, margin, and trades."],
  ["futures.trade", "HIGH", "Submit and cancel restricted Futures orders."],
  ["margin.read", "MEDIUM", "Read margin accounts, liabilities, orders, and interest."],
  ["margin.manage", "HIGH", "Borrow, repay, transfer, and submit margin orders."],
  ["convert.read", "LOW", "Read Convert metadata and history."],
  ["convert.execute", "HIGH", "Create and execute controlled Convert quotes."],
  ["wallet.read", "MEDIUM", "Read wallet records where exposed."],
  ["wallet.transfer", "HIGH", "Initiate supported internal transfers."],
  ["wallet.withdraw", "RESTRICTED", "Disabled by default; requires production approval and IP allowlist."],
  ["staking.read", "LOW", "Read staking products, positions, rewards, and history."],
  ["staking.manage", "HIGH", "Subscribe, unstake, and claim verified rewards."],
  ["copy.read / copy.manage", "RESTRICTED", "Controlled Copy Trading access with eligibility and risk enforcement."],
  ["exaai.read / exaai.manage", "RESTRICTED", "Controlled ExaAI access subject to governance and kill switches."],
] as const;

export const errors = [
  ["INVALID_API_KEY", "401", "No valid active key was supplied.", "No"],
  ["TIMESTAMP_EXPIRED", "401", "Timestamp is outside the 300-second window.", "Yes, after clock sync"],
  ["INVALID_SIGNATURE", "401", "Canonical payload or signature does not match.", "No, fix signing"],
  ["NONCE_REPLAYED", "401", "Nonce has already been consumed by this key.", "Yes, with a new nonce"],
  ["IP_NOT_ALLOWED", "403", "Caller IP is outside the key allowlist.", "No"],
  ["PERMISSION_DENIED", "403", "Required scope is missing.", "No"],
  ["ORDER_NOT_FOUND", "404", "Order is absent or not owned by this account.", "No"],
  ["ORDER_REJECTED", "422", "OMS, validation, balance, or risk rejected the order.", "Only after correcting request"],
  ["WEBSOCKET_TOPIC_REJECTED", "422", "Topic is malformed, unsupported, or exceeds limits.", "Only after correcting topic"],
  ["RATE_LIMITED", "429", "The applicable request budget is exhausted.", "Yes, after Retry-After"],
] as const;

export const navigation = [
  ["Getting started", [["Overview", "/docs"], ["Quickstart", "/docs/quickstart"], ["Environments", "/docs/environments"], ["Authentication", "/docs/authentication"], ["Scopes", "/docs/scopes"], ["Rate limits", "/docs/rate-limits"], ["Errors", "/docs/errors"], ["Precision", "/docs/precision"]]],
  ["Market data", endpoints.filter((e) => e.group === "Market data").map((e) => [e.title, `/reference/${e.id}`])],
  ["Spot trading", endpoints.filter((e) => e.group === "Spot trading").map((e) => [e.title, `/reference/${e.id}`])],
  ["Futures", endpoints.filter((e) => e.group === "Futures").map((e) => [e.title, `/reference/${e.id}`])],
  ["Margin & Convert", endpoints.filter((e) => ["Margin", "Convert"].includes(e.group)).map((e) => [e.title, `/reference/${e.id}`])],
  ["ExaPay", endpoints.filter((e) => e.group === "ExaPay").map((e) => [e.title, `/reference/${e.id}`])],
  ["Wallet & Earn", endpoints.filter((e) => ["Wallet", "Earn / staking"].includes(e.group)).map((e) => [e.title, `/reference/${e.id}`])],
  ["Copy trading", endpoints.filter((e) => e.group === "Copy trading").map((e) => [e.title, `/reference/${e.id}`])],
  ["ExaAI", endpoints.filter((e) => e.group === "ExaAI").map((e) => [e.title, `/reference/${e.id}`])],
  ["Realtime", endpoints.filter((e) => e.group === "WebSocket").map((e) => [e.title, `/reference/${e.id}`]).concat([["Order book recovery", "/docs/orderbook-recovery"], ["Webhooks", "/docs/webhooks"]])],
  ["Operations", [["SDKs", "/docs/sdks"], ["Sandbox", "/docs/sandbox"], ["Changelog", "/docs/changelog"], ["API status", "/docs/status"]]],
] as Array<[string, string[][]]>;
