function normalizeBaseUrl(apiBaseUrl) {
  if (!apiBaseUrl) {
    throw new Error("Missing VITE_API_URL. Set it in your .env file.");
  }

  return apiBaseUrl.endsWith("/") ? apiBaseUrl.slice(0, -1) : apiBaseUrl;
}

async function request({ apiBaseUrl, token, path, method = "GET", body }) {
  const response = await fetch(`${normalizeBaseUrl(apiBaseUrl)}${path}`, {
    method,
    credentials: "include",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  let payload = {};
  try {
    payload = await response.json();
  } catch {
    payload = {};
  }

  if (!response.ok || payload?.status === "error") {
    throw new Error(payload?.message || `Request failed (${response.status})`);
  }

  return payload;
}

export function fetchCampaigns({ apiBaseUrl, token, params = {} }) {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      query.set(key, String(value));
    }
  });

  const suffix = query.toString() ? `?${query.toString()}` : "";
  return request({ apiBaseUrl, token, path: `/api/crowdfunding/campaigns${suffix}` });
}

export function fetchCampaignDetails({ apiBaseUrl, token, campaignId }) {
  return request({ apiBaseUrl, token, path: `/api/crowdfunding/campaigns/${campaignId}` });
}

export function createCampaign({ apiBaseUrl, token, payload }) {
  return request({ apiBaseUrl, token, path: "/api/crowdfunding/campaigns", method: "POST", body: payload });
}

export function contributeToCampaign({ apiBaseUrl, token, campaignId, payload }) {
  return request({ apiBaseUrl, token, path: `/api/crowdfunding/campaigns/${campaignId}/contributions`, method: "POST", body: payload });
}

export function createSpendingRequest({ apiBaseUrl, token, campaignId, payload }) {
  return request({ apiBaseUrl, token, path: `/api/crowdfunding/campaigns/${campaignId}/requests`, method: "POST", body: payload });
}

export function voteSpendingRequest({ apiBaseUrl, token, requestId, payload }) {
  return request({ apiBaseUrl, token, path: `/api/crowdfunding/requests/${requestId}/votes`, method: "POST", body: payload });
}

export function finalizeSpendingRequest({ apiBaseUrl, token, requestId, payload }) {
  return request({ apiBaseUrl, token, path: `/api/crowdfunding/requests/${requestId}/finalize`, method: "POST", body: payload });
}

export function refundCampaignContribution({ apiBaseUrl, token, campaignId, payload }) {
  return request({ apiBaseUrl, token, path: `/api/crowdfunding/campaigns/${campaignId}/refund`, method: "POST", body: payload });
}

export function fetchCampaignLogs({ apiBaseUrl, token, campaignId }) {
  return request({ apiBaseUrl, token, path: `/api/crowdfunding/campaigns/${campaignId}/logs` });
}

