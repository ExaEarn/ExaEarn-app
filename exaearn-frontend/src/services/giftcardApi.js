function normalizeBaseUrl(apiBaseUrl) {
  if (!apiBaseUrl) {
    throw new Error("Missing VITE_API_URL. Set it in your .env file.");
  }

  return apiBaseUrl.endsWith("/") ? apiBaseUrl.slice(0, -1) : apiBaseUrl;
}

async function request({ apiBaseUrl, token, path, method = "GET", body }) {
  const baseUrl = normalizeBaseUrl(apiBaseUrl);
  const response = await fetch(`${baseUrl}${path}`, {
    method,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    ...(body ? { body: JSON.stringify(body) } : {}),
  });

  let payload = {};
  try {
    payload = await response.json();
  } catch {
    payload = {};
  }

  if (!response.ok) {
    throw new Error(payload?.message || `Request failed (${response.status}).`);
  }

  return payload;
}

export function fetchGiftcardInventory({ apiBaseUrl, token, params = {} }) {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      query.set(key, String(value));
    }
  });

  const suffix = query.toString() ? `?${query.toString()}` : "";
  return request({
    apiBaseUrl,
    token,
    path: `/api/giftcards/inventory${suffix}`,
  });
}

export function submitGiftcardSell({ apiBaseUrl, token, payload }) {
  return request({
    apiBaseUrl,
    token,
    path: "/api/giftcards/sell",
    method: "POST",
    body: payload,
  });
}

export function submitGiftcardBuy({ apiBaseUrl, token, payload }) {
  return request({
    apiBaseUrl,
    token,
    path: "/api/giftcards/buy",
    method: "POST",
    body: payload,
  });
}

export function fetchGiftcardOrders({ apiBaseUrl, token, perPage = 10 }) {
  return request({
    apiBaseUrl,
    token,
    path: `/api/giftcards/orders/mine?per_page=${perPage}`,
  });
}

export function fetchGiftcardRate({ apiBaseUrl, token, brand, value }) {
  const query = new URLSearchParams({
    brand,
    value: String(value || 1),
  });

  return request({
    apiBaseUrl,
    token,
    path: `/api/rates?${query.toString()}`,
  });
}

export function lockGiftcardRate({ apiBaseUrl, token, brand, value, transactionType }) {
  return request({
    apiBaseUrl,
    token,
    path: "/api/rates/lock",
    method: "POST",
    body: {
      brand,
      value,
      transaction_type: transactionType,
    },
  });
}

export function fetchGiftcardRateLock({ apiBaseUrl, token, lockId }) {
  return request({
    apiBaseUrl,
    token,
    path: `/api/rates/locks/${lockId}`,
  });
}
