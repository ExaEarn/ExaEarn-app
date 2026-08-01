const normalizeBaseUrl = (apiBaseUrl = "") => apiBaseUrl.replace(/\/+$/, "");

async function request({ apiBaseUrl, token, path, method = "GET", body }) {
  const response = await fetch(`${normalizeBaseUrl(apiBaseUrl)}${path}`, {
    method,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload?.message || `Request failed (${response.status})`);
  }

  return payload;
}

export const getExaAiOverview = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/exaai/overview" });
export const getExaAiPlans = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/exaai/plans" });
export const getExaAiSubscription = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/exaai/subscription" });
export const createExaAiSubscription = ({ apiBaseUrl, token, body }) => request({ apiBaseUrl, token, path: "/api/exaai/subscription", method: "POST", body });
export const getExaAiStrategies = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/exaai/strategies" });
export const getExaAiAllocations = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/exaai/allocations" });
export const getExaAiActiveAllocation = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/exaai/allocations/active" });
export const createExaAiAllocation = ({ apiBaseUrl, token, body }) => request({ apiBaseUrl, token, path: "/api/exaai/allocations", method: "POST", body });
export const getCurrentExaAiSession = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/exaai/sessions/current" });
export const createExaAiSession = ({ apiBaseUrl, token, body }) => request({ apiBaseUrl, token, path: "/api/exaai/sessions", method: "POST", body });
export const pauseExaAiSession = ({ apiBaseUrl, token, id }) => request({ apiBaseUrl, token, path: `/api/exaai/sessions/${id}/pause`, method: "POST" });
export const resumeExaAiSession = ({ apiBaseUrl, token, id }) => request({ apiBaseUrl, token, path: `/api/exaai/sessions/${id}/resume`, method: "POST" });
export const stopExaAiSession = ({ apiBaseUrl, token, id }) => request({ apiBaseUrl, token, path: `/api/exaai/sessions/${id}/stop`, method: "POST" });
export const getExaAiPositions = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/exaai/positions" });
export const getExaAiTrades = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/exaai/trades" });
export const getExaAiPerformance = ({ apiBaseUrl, token, period = "30d" }) => request({ apiBaseUrl, token, path: `/api/exaai/performance?period=${encodeURIComponent(period)}` });
export const getUnifiedTradingBalances = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/accounts/unified-trading/balances" });
