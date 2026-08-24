export type ExaEarnEnvironment = "sandbox" | "production";

export type ExaEarnClientOptions = {
  baseUrl: string;
  apiKey?: string;
  apiSecret?: string;
  passphrase?: string;
  environment?: ExaEarnEnvironment;
  fetcher?: typeof fetch;
};

export type ExaEarnApiError = {
  code: string;
  message: string;
  request_id?: string;
};

export type ExaEarnApiResponse<T> = {
  success: boolean;
  data?: T;
  error?: ExaEarnApiError;
  timestamp?: number;
};

export type ExaEarnBalance = {
  asset: string;
  available: string;
  reserved?: string;
  locked?: string;
  total: string;
  environment: ExaEarnEnvironment;
};

export type ExaEarnSpotOrderRequest = {
  symbol: string;
  side: "buy" | "sell";
  type: "market" | "limit" | "stop_loss" | "take_profit";
  quantity: string;
  price?: string;
  client_order_id?: string;
};

export type ExaEarnSpotOrder = {
  order_id: string;
  client_order_id?: string | null;
  symbol: string;
  side: string;
  type: string;
  price: string;
  quantity: string;
  executed_quantity: string;
  status: string;
  created_at?: string | null;
  updated_at?: string | null;
};

export type ExaEarnFuturesOrderRequest = {
  symbol: string;
  type: "market" | "limit" | "stop-market" | "stop-limit" | "trailing-stop";
  side: "long" | "short";
  quantity: string;
  leverage: number;
  price?: string;
  stop_price?: string;
  reduce_only?: boolean;
  post_only?: boolean;
  time_in_force?: "GTC" | "IOC" | "FOK";
  client_order_id?: string;
};

export type ExaEarnMarginBorrowRequest = {
  account_uuid: string;
  asset: string;
  amount: string;
  idempotency_key?: string;
};

export type ExaEarnStakingPositionRequest = {
  staking_product_id: number;
  amount: string;
  terms_version: string;
  auto_compound?: boolean;
  idempotency_key: string;
};

export type ExaEarnCopyFollowRequest = {
  trader_id: number;
  amount_allocated: string;
  risk_level?: "low" | "medium" | "high";
  product_scope?: "spot" | "futures" | "all";
  copy_mode?: "fixed_amount" | "proportional" | "fixed_ratio";
  max_amount_per_trade?: string;
  max_daily_loss?: string;
  max_drawdown?: string;
  allowed_symbols?: string[];
};

export type ExaEarnAiAllocationRequest = {
  asset: string;
  amount: string;
};

export class ExaEarnRequestError extends Error {
  public readonly status: number;
  public readonly code: string;
  public readonly requestId?: string;

  constructor(status: number, error: ExaEarnApiError) {
    super(error.message);
    this.name = "ExaEarnRequestError";
    this.status = status;
    this.code = error.code;
    this.requestId = error.request_id;
  }
}

export class ExaEarnClient {
  private readonly baseUrl: string;
  private readonly fetcher: typeof fetch;
  private readonly apiKey?: string;
  private readonly apiSecret?: string;
  private readonly passphrase?: string;

  constructor(options: ExaEarnClientOptions) {
    this.baseUrl = options.baseUrl.replace(/\/+$/, "");
    this.fetcher = options.fetcher ?? fetch;
    this.apiKey = options.apiKey;
    this.apiSecret = options.apiSecret;
    this.passphrase = options.passphrase;
  }

  exchangeInfo() {
    return this.request("GET", "/api/developer/v1/exchange-info");
  }

  markets() {
    return this.request("GET", "/api/developer/v1/markets");
  }

  tickers() {
    return this.request("GET", "/api/developer/v1/tickers");
  }

  ticker(symbol: string) {
    return this.request("GET", `/api/developer/v1/ticker/${encodeURIComponent(symbol)}`);
  }

  orderBook(symbol: string, limit = 50) {
    return this.request("GET", `/api/developer/v1/orderbook/${encodeURIComponent(symbol)}?limit=${limit}`);
  }

  trades(symbol: string, limit = 100) {
    return this.request("GET", `/api/developer/v1/trades/${encodeURIComponent(symbol)}?limit=${limit}`);
  }

  klines(symbol: string, interval = "1m", limit = 500) {
    return this.request("GET", `/api/developer/v1/klines/${encodeURIComponent(symbol)}?interval=${encodeURIComponent(interval)}&limit=${limit}`);
  }

  balances() {
    return this.request<ExaEarnBalance[]>("GET", "/api/developer/v1/wallet/balances", undefined, true);
  }

  createSpotOrder(order: ExaEarnSpotOrderRequest) {
    return this.request<ExaEarnSpotOrder>("POST", "/api/developer/v1/spot/orders", order, true);
  }

  getSpotOrder(orderId: string) {
    return this.request<ExaEarnSpotOrder>("GET", `/api/developer/v1/spot/orders/${encodeURIComponent(orderId)}`, undefined, true);
  }

  futuresMarkets() {
    return this.request("GET", "/api/developer/v1/futures/markets", undefined, true);
  }

  futuresOpenOrders(params = "") {
    return this.request("GET", `/api/developer/v1/futures/open-orders${params ? `?${params}` : ""}`, undefined, true);
  }

  futuresPositions(params = "") {
    return this.request("GET", `/api/developer/v1/futures/positions${params ? `?${params}` : ""}`, undefined, true);
  }

  createFuturesOrder(order: ExaEarnFuturesOrderRequest) {
    return this.request("POST", "/api/developer/v1/futures/orders", order, true);
  }

  cancelFuturesOrder(orderUuid: string) {
    return this.request("DELETE", `/api/developer/v1/futures/orders/${encodeURIComponent(orderUuid)}`, undefined, true);
  }

  marginOverview() {
    return this.request("GET", "/api/developer/v1/margin/overview", undefined, true);
  }

  marginAccounts() {
    return this.request("GET", "/api/developer/v1/margin/accounts", undefined, true);
  }

  marginLoans(params = "") {
    return this.request("GET", `/api/developer/v1/margin/loans${params ? `?${params}` : ""}`, undefined, true);
  }

  marginBorrow(payload: ExaEarnMarginBorrowRequest) {
    return this.request("POST", "/api/developer/v1/margin/borrow", payload, true);
  }

  marginRepay(loanUuid: string, payload: { amount: string; idempotency_key?: string }) {
    return this.request("POST", `/api/developer/v1/margin/loans/${encodeURIComponent(loanUuid)}/repay`, payload, true);
  }

  stakingProducts() {
    return this.request("GET", "/api/developer/v1/staking/products", undefined, true);
  }

  stakingPositions() {
    return this.request("GET", "/api/developer/v1/staking/positions", undefined, true);
  }

  createStakingPosition(payload: ExaEarnStakingPositionRequest) {
    return this.request("POST", "/api/developer/v1/staking/positions", payload, true);
  }

  unstake(publicId: string, payload: { amount?: string; idempotency_key: string }) {
    return this.request("POST", `/api/developer/v1/staking/positions/${encodeURIComponent(publicId)}/unstake`, payload, true);
  }

  copyLeaders(params = "") {
    return this.request("GET", `/api/developer/v1/copy/leaders${params ? `?${params}` : ""}`, undefined, true);
  }

  copyRelationships() {
    return this.request("GET", "/api/developer/v1/copy/relationships", undefined, true);
  }

  followCopyTrader(payload: ExaEarnCopyFollowRequest) {
    return this.request("POST", "/api/developer/v1/copy/follow", payload, true);
  }

  stopCopyRelationship(id: number, payload?: { action?: string; reason?: string }) {
    return this.request("DELETE", `/api/developer/v1/copy/follow/${id}`, payload, true);
  }

  exaAiStrategies() {
    return this.request("GET", "/api/developer/v1/exaai/strategies", undefined, true);
  }

  exaAiPortfolio() {
    return this.request("GET", "/api/developer/v1/exaai/portfolio", undefined, true);
  }

  exaAiAllocate(payload: ExaEarnAiAllocationRequest) {
    return this.request("POST", "/api/developer/v1/exaai/allocations", payload, true);
  }

  pauseExaAiSession(id: number) {
    return this.request("POST", `/api/developer/v1/exaai/sessions/${id}/pause`, {}, true);
  }

  private async request<T = unknown>(
    method: string,
    pathWithQuery: string,
    body?: unknown,
    signed = false
  ): Promise<T> {
    const [path, query = ""] = pathWithQuery.split("?", 2);
    const serializedBody = body === undefined ? "" : JSON.stringify(body);
    const headers: Record<string, string> = {
      Accept: "application/json",
      "X-Exa-Sdk": "@exaearn/sdk",
    };

    if (serializedBody !== "") {
      headers["Content-Type"] = "application/json";
    }

    if (signed) {
      Object.assign(headers, await this.signedHeaders(method, path, query, serializedBody));
    }

    const response = await this.fetcher(`${this.baseUrl}${pathWithQuery}`, {
      method,
      headers,
      body: serializedBody === "" ? undefined : serializedBody,
    });
    const payload = (await response.json()) as ExaEarnApiResponse<T>;

    if (!response.ok || payload.success === false) {
      throw new ExaEarnRequestError(response.status, payload.error ?? {
        code: "EXAEARN_API_ERROR",
        message: `ExaEarn request failed with HTTP ${response.status}.`,
      });
    }

    return payload.data as T;
  }

  private async signedHeaders(method: string, path: string, query: string, body: string): Promise<Record<string, string>> {
    if (!this.apiKey || !this.apiSecret) {
      throw new Error("Signed ExaEarn requests require apiKey and apiSecret.");
    }

    const timestamp = Math.floor(Date.now() / 1000).toString();
    const nonce = randomNonce();
    const bodyHash = await sha256Hex(body);
    const canonical = `${method.toUpperCase()}\n${path}\n${query}\n${timestamp}\n${nonce}\n${bodyHash}`;
    const signature = await hmacSha256Hex(this.apiSecret, canonical);

    return {
      "EXA-API-KEY": this.apiKey,
      "EXA-API-TIMESTAMP": timestamp,
      "EXA-API-NONCE": nonce,
      "EXA-API-SIGNATURE": signature,
      ...(this.passphrase ? { "EXA-API-PASSPHRASE": this.passphrase } : {}),
    };
  }
}

export function createExaEarnClient(options: ExaEarnClientOptions): ExaEarnClient {
  return new ExaEarnClient(options);
}

async function sha256Hex(value: string): Promise<string> {
  const buffer = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(value));
  return toHex(buffer);
}

async function hmacSha256Hex(secret: string, value: string): Promise<string> {
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"]
  );
  const signature = await crypto.subtle.sign("HMAC", key, new TextEncoder().encode(value));
  return toHex(signature);
}

function toHex(buffer: ArrayBuffer): string {
  return Array.from(new Uint8Array(buffer))
    .map((byte) => byte.toString(16).padStart(2, "0"))
    .join("");
}

function randomNonce(): string {
  const bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);
  return `exa_nonce_${Array.from(bytes).map((byte) => byte.toString(16).padStart(2, "0")).join("")}`;
}
