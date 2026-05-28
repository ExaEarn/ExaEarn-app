export function createExaEarnClient(baseUrl: string) {
  const normalizedBaseUrl = baseUrl.replace(/\/+$/, "");

  return {
    get: (path: string) => fetch(`${normalizedBaseUrl}${path.startsWith("/") ? path : `/${path}`}`),
  };
}
