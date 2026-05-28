function normalizeBaseUrl(apiBaseUrl) {
  if (!apiBaseUrl) {
    throw new Error("Missing VITE_API_URL. Set it in your .env file.");
  }

  return apiBaseUrl.endsWith("/") ? apiBaseUrl.slice(0, -1) : apiBaseUrl;
}

async function adminRequest({ apiBaseUrl, token, path, method = "GET", body }) {
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
    throw new Error(payload?.message || `Admin API request failed (${response.status}).`);
  }

  return payload;
}

export const adminAuthApi = {
  login: ({ apiBaseUrl, email, password, deviceName = "admin-web" }) =>
    adminRequest({
      apiBaseUrl,
      path: "/api/admin/login",
      method: "POST",
      body: { email, password, device_name: deviceName },
    }),

  logout: ({ apiBaseUrl, token }) =>
    adminRequest({
      apiBaseUrl,
      token,
      path: "/api/admin/logout",
      method: "POST",
    }),

  me: ({ apiBaseUrl, token }) =>
    adminRequest({
      apiBaseUrl,
      token,
      path: "/api/admin/me",
    }),
};

export const adminUsersApi = {
  list: ({ apiBaseUrl, token, perPage = 25 }) =>
    adminRequest({ apiBaseUrl, token, path: `/api/admin/users?per_page=${perPage}` }),
  get: ({ apiBaseUrl, token, id }) => adminRequest({ apiBaseUrl, token, path: `/api/admin/users/${id}` }),
  freeze: ({ apiBaseUrl, token, userId, reason }) =>
    adminRequest({ apiBaseUrl, token, path: "/api/admin/users/freeze", method: "POST", body: { user_id: userId, reason } }),
  unfreeze: ({ apiBaseUrl, token, userId }) =>
    adminRequest({ apiBaseUrl, token, path: "/api/admin/users/unfreeze", method: "POST", body: { user_id: userId } }),
  adjustBalance: ({ apiBaseUrl, token, payload }) =>
    adminRequest({ apiBaseUrl, token, path: "/api/admin/users/adjust-balance", method: "POST", body: payload }),
  logs: ({ apiBaseUrl, token, userId }) => adminRequest({ apiBaseUrl, token, path: `/api/admin/users/logs?user_id=${userId}` }),
  wallets: ({ apiBaseUrl, token, userId }) => adminRequest({ apiBaseUrl, token, path: `/api/admin/users/wallets?user_id=${userId}` }),
  trades: ({ apiBaseUrl, token, userId }) => adminRequest({ apiBaseUrl, token, path: `/api/admin/users/trades?user_id=${userId}` }),
  rewards: ({ apiBaseUrl, token, userId }) => adminRequest({ apiBaseUrl, token, path: `/api/admin/users/rewards?user_id=${userId}` }),
};

export const adminTreasuryApi = {
  overview: ({ apiBaseUrl, token }) => adminRequest({ apiBaseUrl, token, path: "/api/admin/treasury" }),
  move: ({ apiBaseUrl, token, payload }) =>
    adminRequest({ apiBaseUrl, token, path: "/api/admin/treasury/move", method: "POST", body: payload }),
  approveWithdraw: ({ apiBaseUrl, token, payload }) =>
    adminRequest({ apiBaseUrl, token, path: "/api/admin/treasury/approve-withdraw", method: "POST", body: payload }),
  logs: ({ apiBaseUrl, token, perPage = 25 }) =>
    adminRequest({ apiBaseUrl, token, path: `/api/admin/treasury/logs?per_page=${perPage}` }),
};

export const adminSystemApi = {
  logs: ({ apiBaseUrl, token, perPage = 25 }) => adminRequest({ apiBaseUrl, token, path: `/api/admin/logs?per_page=${perPage}` }),
  adminLogs: ({ apiBaseUrl, token, perPage = 25 }) =>
    adminRequest({ apiBaseUrl, token, path: `/api/admin/admin-logs?per_page=${perPage}` }),
  securityLogs: ({ apiBaseUrl, token, perPage = 25 }) =>
    adminRequest({ apiBaseUrl, token, path: `/api/admin/security-logs?per_page=${perPage}` }),
  notifications: ({ apiBaseUrl, token, perPage = 25 }) =>
    adminRequest({ apiBaseUrl, token, path: `/api/admin/notifications?per_page=${perPage}` }),
  sendNotification: ({ apiBaseUrl, token, payload }) =>
    adminRequest({ apiBaseUrl, token, path: "/api/admin/notifications/send", method: "POST", body: payload }),
  settings: ({ apiBaseUrl, token }) => adminRequest({ apiBaseUrl, token, path: "/api/admin/settings" }),
  updateSettings: ({ apiBaseUrl, token, payload }) =>
    adminRequest({ apiBaseUrl, token, path: "/api/admin/settings", method: "PUT", body: payload }),
};

