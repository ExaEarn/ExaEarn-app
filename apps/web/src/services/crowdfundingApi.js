function normalizeBaseUrl(apiBaseUrl) {
  if (!apiBaseUrl) {
    throw new Error("Missing VITE_API_URL. Set it in your .env file.");
  }

  return apiBaseUrl.endsWith("/") ? apiBaseUrl.slice(0, -1) : apiBaseUrl;
}

async function request({ apiBaseUrl, token, path, method = "GET", body, headers = {} }) {
  const response = await fetch(`${normalizeBaseUrl(apiBaseUrl)}${path}`, {
    method,
    credentials: "include",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
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
  const idempotencyKey = payload?.idempotency_key || `crowdfunding-pledge-${campaignId}-${Date.now()}`;
  return request({ apiBaseUrl, token, path: `/api/crowdfunding/campaigns/${campaignId}/contributions`, method: "POST", body: payload, headers: { "Idempotency-Key": idempotencyKey } });
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

export function fetchCampaignComments({ apiBaseUrl, token, campaignId }) {
  return request({ apiBaseUrl, token, path: `/api/crowdfunding/campaigns/${campaignId}/comments` });
}

export function createCampaignComment({ apiBaseUrl, token, campaignId, payload }) {
  return request({ apiBaseUrl, token, path: `/api/crowdfunding/campaigns/${campaignId}/comments`, method: "POST", body: payload });
}

export function reportCampaignComment({ apiBaseUrl, token, commentId, reason }) {
  return request({ apiBaseUrl, token, path: `/api/crowdfunding/comments/${commentId}/report`, method: "POST", body: { reason } });
}

export function fetchCreatorCrowdfundingDashboard({ apiBaseUrl, token }) {
  return request({ apiBaseUrl, token, path: "/api/crowdfunding/creator/dashboard" });
}

export function fetchBackerCrowdfundingDashboard({ apiBaseUrl, token }) {
  return request({ apiBaseUrl, token, path: "/api/crowdfunding/backer/dashboard" });
}

