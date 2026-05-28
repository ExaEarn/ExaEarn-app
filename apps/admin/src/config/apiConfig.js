function readRuntimeConfig() {
  if (typeof window === "undefined") {
    return {};
  }

  return window.__EXAEARN_CONFIG__ || {};
}

function cleanUrl(value) {
  return String(value || "").trim().replace(/\/+$/, "");
}

export function getApiBaseUrl() {
  const runtimeConfig = readRuntimeConfig();
  return cleanUrl(runtimeConfig.API_URL || import.meta.env.VITE_API_URL);
}

export function getAdminApiBaseUrl() {
  const runtimeConfig = readRuntimeConfig();
  return cleanUrl(
    runtimeConfig.ADMIN_API_URL ||
      import.meta.env.VITE_ADMIN_API_URL ||
      runtimeConfig.API_URL ||
      import.meta.env.VITE_API_URL
  );
}

export function getNodeServiceUrl() {
  const runtimeConfig = readRuntimeConfig();
  return cleanUrl(runtimeConfig.NODE_SERVICE_URL || import.meta.env.VITE_NODE_SERVICE_URL);
}
