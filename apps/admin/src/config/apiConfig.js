function readRuntimeConfig() {
  if (typeof window === "undefined") {
    return {};
  }

  return window.__EXAEARN_CONFIG__ || {};
}

function cleanUrl(value) {
  return String(value || "").trim().replace(/\/+$/, "");
}

function cleanApiUrl(value) {
  const url = cleanUrl(value);
  if (import.meta.env.PROD && /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?(\/|$)/i.test(url)) {
    return "";
  }

  return url;
}

function productionApiFallback() {
  return import.meta.env.PROD ? "https://api.exaearn.com" : "";
}

export function getApiBaseUrl() {
  const runtimeConfig = readRuntimeConfig();
  return cleanApiUrl(runtimeConfig.API_URL || import.meta.env.VITE_API_URL) || productionApiFallback();
}

export function getAdminApiBaseUrl() {
  const runtimeConfig = readRuntimeConfig();
  return cleanApiUrl(
    runtimeConfig.ADMIN_API_URL ||
      import.meta.env.VITE_ADMIN_API_URL ||
      runtimeConfig.API_URL ||
      import.meta.env.VITE_API_URL
  ) || productionApiFallback();
}

export function getNodeServiceUrl() {
  const runtimeConfig = readRuntimeConfig();
  return cleanUrl(runtimeConfig.NODE_SERVICE_URL || import.meta.env.VITE_NODE_SERVICE_URL);
}

export function isDemoAdminEnabled() {
  const runtimeConfig = readRuntimeConfig();
  const rawValue = runtimeConfig.DEMO_ADMIN_ENABLED ?? import.meta.env.VITE_DEMO_ADMIN_ENABLED;
  return rawValue === true || String(rawValue || "").toLowerCase() === "true";
}
