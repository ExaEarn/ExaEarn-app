function normalizeBaseUrl(apiBaseUrl) {
  if (!apiBaseUrl) {
    throw new Error("Missing VITE_API_URL. Set it in your .env file.");
  }

  return apiBaseUrl.endsWith("/") ? apiBaseUrl.slice(0, -1) : apiBaseUrl;
}

async function apiRequest({ apiBaseUrl, token, path, method = "GET", body }) {
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

export function fetchAgriProjects({ apiBaseUrl, token, params = {} }) {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      query.set(key, String(value));
    }
  });

  const suffix = query.toString() ? `?${query.toString()}` : "";
  return apiRequest({
    apiBaseUrl,
    token,
    path: `/api/agriculture/projects${suffix}`,
  });
}

export function fetchAgriProject({ apiBaseUrl, token, projectId }) {
  return apiRequest({
    apiBaseUrl,
    token,
    path: `/api/agriculture/projects/${projectId}`,
  });
}

export function investInAgriProject({ apiBaseUrl, token, projectId, sharesOwned, metadata = {} }) {
  return apiRequest({
    apiBaseUrl,
    token,
    path: `/api/agriculture/projects/${projectId}/invest`,
    method: "POST",
    body: {
      shares_owned: sharesOwned,
      metadata,
    },
  });
}

export function applyAsFarmer({ apiBaseUrl, token, payload }) {
  return apiRequest({
    apiBaseUrl,
    token,
    path: "/api/agriculture/farmers/apply",
    method: "POST",
    body: payload,
  });
}
