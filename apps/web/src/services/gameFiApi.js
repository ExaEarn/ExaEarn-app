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

export function fetchLotteryGames({ apiBaseUrl, token }) {
  return request({
    apiBaseUrl,
    token,
    path: "/api/gamefi/lotteries",
  });
}

export function fetchLotteryGame({ apiBaseUrl, token, gameId }) {
  return request({
    apiBaseUrl,
    token,
    path: `/api/gamefi/lotteries/${gameId}`,
  });
}

export function joinLotteryGame({ apiBaseUrl, token, gameId, payload }) {
  return request({
    apiBaseUrl,
    token,
    path: `/api/gamefi/lotteries/${gameId}/join`,
    method: "POST",
    body: payload,
  });
}
