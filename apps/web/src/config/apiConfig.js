function readRuntimeConfig() {
  if (typeof window === "undefined") {
    return {};
  }

  return window.__EXAEARN_CONFIG__ || {};
}

function cleanUrl(value) {
  return String(value || "").trim().replace(/\/+$/, "");
}

function isLocalBackendUrl(value) {
  return /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?(\/|$)/i.test(value);
}

function isPublicBrowserOrigin() {
  if (typeof window === "undefined") {
    return false;
  }

  return !["localhost", "127.0.0.1"].includes(window.location.hostname);
}

function isRuntimeLocalApiAllowed(runtimeConfig) {
  return import.meta.env.DEV && runtimeConfig.ALLOW_LOCAL_API_ON_PUBLIC_ORIGIN === true;
}

function cleanRuntimeUrl(value, runtimeConfig = {}) {
  const url = cleanUrl(value);
  if (url && isPublicBrowserOrigin() && isLocalBackendUrl(url) && !isRuntimeLocalApiAllowed(runtimeConfig)) {
    console.warn("Ignoring localhost API URL on a public ExaEarn deployment.");
    return "";
  }

  return url;
}

export function getApiBaseUrl() {
  const runtimeConfig = readRuntimeConfig();
  return (
    cleanRuntimeUrl(runtimeConfig.API_URL, runtimeConfig) ||
    cleanRuntimeUrl(import.meta.env.VITE_API_URL, runtimeConfig) ||
    (import.meta.env.PROD ? "https://api.exaearn.com" : "")
  );
}

export function getAdminApiBaseUrl() {
  const runtimeConfig = readRuntimeConfig();
  return (
    cleanRuntimeUrl(runtimeConfig.ADMIN_API_URL, runtimeConfig) ||
    cleanRuntimeUrl(import.meta.env.VITE_ADMIN_API_URL, runtimeConfig) ||
    cleanRuntimeUrl(runtimeConfig.API_URL, runtimeConfig) ||
    cleanRuntimeUrl(import.meta.env.VITE_API_URL, runtimeConfig) ||
    (import.meta.env.PROD ? "https://api.exaearn.com" : "")
  );
}

export function getNodeServiceUrl() {
  const runtimeConfig = readRuntimeConfig();
  return cleanRuntimeUrl(runtimeConfig.NODE_SERVICE_URL, runtimeConfig) || cleanUrl(import.meta.env.VITE_NODE_SERVICE_URL);
}

export function isLaravelSseEnabled() {
  const runtimeConfig = readRuntimeConfig();
  const rawValue = runtimeConfig.ENABLE_LARAVEL_SSE ?? import.meta.env.VITE_ENABLE_LARAVEL_SSE;
  return rawValue === true || String(rawValue || "").toLowerCase() === "true";
}


export function isLocalApiPreview() {
  const runtimeConfig = readRuntimeConfig();
  return import.meta.env.DEV && isRuntimeLocalApiAllowed(runtimeConfig) && isLocalBackendUrl(runtimeConfig.API_URL);
}
export function isDemoAuthEnabled() {
  const runtimeConfig = readRuntimeConfig();
  const rawValue = runtimeConfig.DEMO_AUTH_ENABLED ?? import.meta.env.VITE_DEMO_AUTH_ENABLED;
  return rawValue === true || String(rawValue || "").toLowerCase() === "true";
}
