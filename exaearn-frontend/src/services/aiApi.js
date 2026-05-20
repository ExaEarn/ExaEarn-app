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

export const getAiProfile = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/ai/profile" });
export const initAiProfile = ({ apiBaseUrl, token, body }) => request({ apiBaseUrl, token, path: "/api/ai/profile/init", method: "POST", body });
export const updateAiProfile = ({ apiBaseUrl, token, body }) => request({ apiBaseUrl, token, path: "/api/ai/profile", method: "PATCH", body });

export const getAiSignals = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/ai/signals" });
export const generateAiSignal = ({ apiBaseUrl, token, body }) => request({ apiBaseUrl, token, path: "/api/ai/signals/generate", method: "POST", body });

export const getRiskAssessment = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/ai/risk-assessment" });
export const validateTrade = ({ apiBaseUrl, token, body }) => request({ apiBaseUrl, token, path: "/api/ai/validate-trade", method: "POST", body });

export const chatWithAssistant = ({ apiBaseUrl, token, body }) => request({ apiBaseUrl, token, path: "/api/ai/assistant/chat", method: "POST", body });
export const getAssistantConversations = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/ai/assistant/conversations" });
export const getAssistantConversation = ({ apiBaseUrl, token, id }) => request({ apiBaseUrl, token, path: `/api/ai/assistant/conversations/${id}` });

export const getStrategies = ({ apiBaseUrl, token }) => request({ apiBaseUrl, token, path: "/api/ai/strategies" });
export const createStrategy = ({ apiBaseUrl, token, body }) => request({ apiBaseUrl, token, path: "/api/ai/strategies", method: "POST", body });
export const activateStrategy = ({ apiBaseUrl, token, strategyId }) => request({ apiBaseUrl, token, path: `/api/ai/strategies/${strategyId}/activate`, method: "POST" });
export const deactivateStrategy = ({ apiBaseUrl, token, strategyId }) => request({ apiBaseUrl, token, path: `/api/ai/strategies/${strategyId}/deactivate`, method: "POST" });
