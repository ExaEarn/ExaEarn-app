type MobileRequestOptions = RequestInit & {
  headers?: Record<string, string>;
};

type MobileRequest = <T>(path: string, options?: MobileRequestOptions) => Promise<T>;

export type ExaCardProduct = {
  product_code: string;
  type?: string;
  currency?: string;
  enabled?: boolean;
};

export type ExaCard = {
  card_uuid: string;
  product?: string;
  type?: string;
  currency?: string;
  network?: string;
  last_four?: string;
  status?: string;
  controls?: Record<string, boolean>;
  limits?: Record<string, string | number>;
  balance?: {
    total?: string;
    available?: string;
    reserved?: string;
  };
};

export type ExaCardQuote = {
  quote_uuid: string;
  source_asset: string;
  card_currency: string;
  card_amount: string;
  card_fee: string;
  provider_fee: string;
  total_debit: string;
};

export type ExaCardActivity = {
  transaction_uuid?: string;
  authorization_uuid?: string;
  type?: string;
  merchant?: string;
  status?: string;
  billing_amount?: string;
  billing_currency?: string;
  amount?: string;
  currency?: string;
};

export type ExaCardRealtimeReplay = {
  latest_sequence?: number;
  gap_detected?: boolean;
  reconcile_required?: boolean;
  events?: Array<{
    event_id: string;
    sequence: number;
    event_type: string;
    payload?: {
      card?: ExaCard;
      [key: string]: unknown;
    };
  }>;
};

function unwrap<T>(payload: Record<string, unknown>): T {
  return (payload.data ?? payload) as T;
}

function idempotencyKey(prefix: string) {
  return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export async function fetchExaCardProducts(request: MobileRequest) {
  return unwrap<{ products: ExaCardProduct[]; provider?: Record<string, unknown> }>(
    await request<Record<string, unknown>>("/api/cards/products", { method: "GET" }),
  );
}

export async function fetchExaCards(request: MobileRequest) {
  return unwrap<ExaCard[]>(await request<Record<string, unknown>>("/api/cards", { method: "GET" }));
}

export async function issueExaCard(request: MobileRequest, productCode: string) {
  return unwrap<ExaCard>(
    await request<Record<string, unknown>>("/api/cards", {
      method: "POST",
      headers: { "Idempotency-Key": idempotencyKey("mobile-issue-card") },
      body: JSON.stringify({ product_code: productCode }),
    }),
  );
}

export async function quoteExaCardFunding(request: MobileRequest, cardUuid: string, sourceAsset: string, amount: string) {
  return unwrap<ExaCardQuote>(
    await request<Record<string, unknown>>(`/api/cards/${cardUuid}/funding-quotes`, {
      method: "POST",
      body: JSON.stringify({ source_asset: sourceAsset, amount }),
    }),
  );
}

export async function fundExaCard(request: MobileRequest, quoteUuid: string) {
  return unwrap<Record<string, unknown>>(
    await request<Record<string, unknown>>("/api/cards/funding-requests", {
      method: "POST",
      headers: { "Idempotency-Key": idempotencyKey("mobile-fund-card") },
      body: JSON.stringify({ quote_uuid: quoteUuid }),
    }),
  );
}

export async function unloadExaCard(request: MobileRequest, cardUuid: string, amount: string) {
  return unwrap<Record<string, unknown>>(
    await request<Record<string, unknown>>(`/api/cards/${cardUuid}/unload`, {
      method: "POST",
      headers: { "Idempotency-Key": idempotencyKey("mobile-unload-card") },
      body: JSON.stringify({ amount }),
    }),
  );
}

export async function updateExaCardControls(request: MobileRequest, cardUuid: string, controls: Record<string, boolean>) {
  return unwrap<ExaCard>(
    await request<Record<string, unknown>>(`/api/cards/${cardUuid}/controls`, {
      method: "PUT",
      body: JSON.stringify(controls),
    }),
  );
}

export async function fetchExaCardTransactions(request: MobileRequest, cardUuid: string) {
  return unwrap<ExaCardActivity[]>(
    await request<Record<string, unknown>>(`/api/cards/${cardUuid}/transactions`, { method: "GET" }),
  );
}

export async function fetchExaCardRealtimeReplay(request: MobileRequest, afterSequence = 0) {
  return unwrap<ExaCardRealtimeReplay>(
    await request<Record<string, unknown>>(`/api/cards/realtime/replay?after_sequence=${encodeURIComponent(String(afterSequence))}`, { method: "GET" }),
  );
}
