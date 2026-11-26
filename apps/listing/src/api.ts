const apiBase = (import.meta.env.VITE_API_URL || "https://api.exaearn.com").replace(/\/$/, "");

export class ApiError extends Error {
  constructor(message: string, public status: number, public requestId?: string, public code?: string, public details?: unknown) { super(message); }
}

export async function api<T>(path: string, options: RequestInit = {}, timeoutMs = 15000): Promise<T> {
  const controller = new AbortController();
  const timer = window.setTimeout(() => controller.abort(), timeoutMs);
  try {
    const response = await fetch(`${apiBase}${path}`, {
      credentials: "include",
      headers: { Accept: "application/json", ...(options.body instanceof FormData ? {} : { "Content-Type": "application/json" }), ...(options.headers || {}) },
      ...options,
      signal: controller.signal,
    });
    const requestId = response.headers.get("x-request-id") || undefined;
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
      if ((response.status === 401 || response.status === 419) && body.code !== "INVALID_CREDENTIALS") throw new ApiError("Your session has expired. Sign in again.", response.status, requestId, body.code, body);
      if (response.status === 403) throw new ApiError(body.message || "You do not have permission to perform this action.", response.status, requestId, body.code, body);
      if (response.status === 422) {
        const first = Object.values(body.errors || {}).flat()[0];
        throw new ApiError(String(first || body.message || "Please review the information provided."), response.status, requestId, body.code, body.errors);
      }
      throw new ApiError(body.message || "The service is temporarily unavailable.", response.status, requestId, body.code, body);
    }
    return body as T;
  } catch (error) {
    if (error instanceof ApiError) throw error;
    if (error instanceof DOMException && error.name === "AbortError") throw new ApiError("The request timed out. Please retry.", 408);
    throw new ApiError("Unable to reach ExaEarn. Check your connection and retry.", 0);
  } finally { window.clearTimeout(timer); }
}
