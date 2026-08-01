type MobileRequestOptions = RequestInit & {
  headers?: Record<string, string>;
};

type MobileRequest = <T>(path: string, options?: MobileRequestOptions) => Promise<T>;

export type GiftcardRate = {
  brand?: string;
  buy_rate?: number;
  sell_rate?: number;
  payout?: number;
  price?: number;
  demand_level?: string;
  inventory_status?: string;
  market_feedback?: string;
};

export type GiftcardInventoryItem = {
  id?: number;
  card_type?: string;
  brand?: string;
  amount?: number | string;
  currency?: string;
};

export type GiftcardOrder = {
  id?: number;
  reference?: string;
  status?: string;
  risk_level?: string;
  created_at?: string;
  metadata?: {
    delivery?: {
      masked_code?: string;
    };
  };
};

export function normalizeBrand(brand: string) {
  return String(brand || "")
    .trim()
    .toLowerCase()
    .replace(/\s+/g, "_");
}

export function formatNaira(value: unknown) {
  return new Intl.NumberFormat("en-NG", {
    style: "currency",
    currency: "NGN",
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

export function formatLockTime(seconds: number) {
  const safeSeconds = Math.max(0, Number(seconds || 0));
  const mins = Math.floor(safeSeconds / 60).toString().padStart(2, "0");
  const secs = Math.floor(safeSeconds % 60).toString().padStart(2, "0");
  return `${mins}:${secs}`;
}

function unwrapArray(payload: Record<string, unknown>) {
  const data = payload.data;
  if (Array.isArray(data)) return data;
  if (data && typeof data === "object") {
    const nested = data as Record<string, unknown>;
    if (Array.isArray(nested.data)) return nested.data;
  }
  return [];
}

export async function fetchGiftcardInventory(request: MobileRequest) {
  const payload = await request<Record<string, unknown>>("/api/giftcard/inventory?per_page=50", { method: "GET" });
  return unwrapArray(payload) as GiftcardInventoryItem[];
}

export async function fetchGiftcardRate(request: MobileRequest, brand: string, value: number): Promise<GiftcardRate | null> {
  const payload = await request<Record<string, unknown>>(
    `/api/giftcard/rates?brand=${encodeURIComponent(normalizeBrand(brand))}&value=${encodeURIComponent(String(value || 1))}`,
    { method: "GET" },
  );
  const data = payload.data;
  if (Array.isArray(data)) {
    const normalized = normalizeBrand(brand);
    const match = data.find((item) => {
      if (!item || typeof item !== "object") return false;
      const source = item as Record<string, unknown>;
      return normalizeBrand(String(source.brand ?? source.card_type ?? source.name ?? "")) === normalized;
    });
    return (match as GiftcardRate | undefined) ?? (data[0] as GiftcardRate | undefined) ?? null;
  }
  return data && typeof data === "object" ? (data as GiftcardRate) : null;
}

export async function submitGiftcardSell(
  request: MobileRequest,
  payload: {
    brand: string;
    card_value: number;
    currency: string;
    card_code: string;
    card_pin?: string;
  },
) {
  return request<Record<string, unknown>>("/api/giftcard/sell", {
    method: "POST",
    body: JSON.stringify({
      ...payload,
      card_type: payload.brand,
      provider: normalizeBrand(payload.brand),
      amount: payload.card_value,
      source_mode: "manual_upload",
      payment_method: "wallet_credit",
      device_id: "exaearn-mobile",
      geo_location: "mobile",
      is_vpn: false,
    }),
  });
}

export async function submitGiftcardBuy(
  request: MobileRequest,
  payload: {
    brand: string;
    card_value: number;
    quantity: number;
    currency: string;
    payment_wallet_currency?: string;
    giftcard_id?: number;
    payment_method?: string;
  },
) {
  return request<Record<string, unknown>>("/api/giftcard/buy", {
    method: "POST",
    body: JSON.stringify({
      brand: normalizeBrand(payload.brand),
      card_value: payload.card_value,
      quantity: payload.quantity,
      currency: payload.currency,
      payment_wallet_currency: payload.payment_wallet_currency ?? payload.currency,
      giftcard_id: payload.giftcard_id,
      payment_method: payload.payment_method,
    }),
  });
}

export async function fetchGiftcardOrders(request: MobileRequest, perPage = 1) {
  const payload = await request<Record<string, unknown>>(`/api/giftcard/orders?per_page=${perPage}`, { method: "GET" });
  return unwrapArray(payload) as GiftcardOrder[];
}
