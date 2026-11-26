import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { Platform } from "react-native";
import * as SecureStore from "expo-secure-store";

import { getApiBaseUrl } from "../config/apiConfig";
import { AppApiError } from "../errors/errorTypes";
import { appErrorFromException, parseRetryAfter } from "../errors/errorMessageRegistry";

type ExaEarnUser = {
  id?: number | string;
  name?: string;
  email?: string;
  unique_user_id?: string;
  referral_code?: string;
  [key: string]: unknown;
};

type AuthResult = {
  success: boolean;
  message?: string;
};

type RequestOptions = RequestInit & {
  headers?: Record<string, string>;
};

type AuthContextValue = {
  user: ExaEarnUser | null;
  token: string;
  apiBaseUrl: string;
  authReady: boolean;
  authLoading: boolean;
  authError: string;
  isGoogleAuthLoading: boolean;
  googleAuthError: string;
  isGoogleConfigured: boolean;
  request: <T = Record<string, unknown>>(path: string, options?: RequestOptions) => Promise<T>;
  checkAccountAvailability: (details: {
    name: string;
    email: string;
    password: string;
    passwordConfirmation: string;
    referralCode?: string;
  }) => Promise<AuthResult & { exists?: boolean }>;
  login: (credentials: { email: string; password: string }) => Promise<AuthResult>;
  register: (details: {
    name: string;
    email: string;
    password: string;
    passwordConfirmation: string;
    referralCode?: string;
  }) => Promise<AuthResult>;
  logout: () => Promise<void>;
  startGoogleLogin: () => void;
};

const AuthContext = createContext<AuthContextValue | null>(null);
const GOOGLE_UNAVAILABLE_MESSAGE = "Google login is not configured for the mobile web build yet.";
const AUTH_TOKEN_KEY = "exaearn.auth.token.v1";
const AUTH_TOKEN_OPTIONS: SecureStore.SecureStoreOptions = {
  keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
};

function getPayloadUser(payload: Record<string, unknown>): ExaEarnUser | null {
  const directUser = payload.user;
  const data = payload.data;

  if (directUser && typeof directUser === "object") {
    return directUser as ExaEarnUser;
  }

  if (data && typeof data === "object" && "user" in data) {
    const nestedUser = (data as { user?: unknown }).user;
    return nestedUser && typeof nestedUser === "object" ? (nestedUser as ExaEarnUser) : null;
  }

  return null;
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<ExaEarnUser | null>(null);
  const [token, setToken] = useState("");
  const [authReady, setAuthReady] = useState(Platform.OS === "web");
  const [authLoading, setAuthLoading] = useState(false);
  const [authError, setAuthError] = useState("");
  const [isGoogleAuthLoading] = useState(false);
  const [googleAuthError, setGoogleAuthError] = useState("");
  const apiBaseUrl = getApiBaseUrl();
  const isGoogleConfigured = false;

  const requestWithToken = useCallback(
    async <T,>(path: string, options: RequestOptions = {}, bearerToken = "") => {
      const normalizedBase = apiBaseUrl.replace(/\/+$/, "");
      const normalizedPath = path.startsWith("/") ? path : `/${path}`;
      const { headers: optionHeaders, ...restOptions } = options;

      const headers = {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(bearerToken ? { Authorization: `Bearer ${bearerToken}` } : {}),
        ...(optionHeaders || {}),
      };

      let response: Response;
      try {
        response = await fetch(`${normalizedBase}${normalizedPath}`, {
          ...restOptions,
          headers,
        });
      } catch (error) {
        const timeout = error instanceof Error && (error.name === "AbortError" || /timeout/i.test(error.name));
        const apiError = new AppApiError({ status: 0, backendCode: timeout ? "request_timeout" : "network_error", method: String(restOptions.method ?? "GET") });
        apiError.message = appErrorFromException(apiError).message;
        throw apiError;
      }

      let payload: Record<string, unknown> = {};
      try {
        payload = (await response.json()) as Record<string, unknown>;
      } catch {
        payload = {};
      }

      if (!response.ok || payload.status === "error") {
        // Keep only allowlisted diagnostic fields. Raw backend text may contain PII or internal details.
        const safePayload = {
          ...(typeof payload.code === "string" ? { code: payload.code.slice(0, 80) } : {}),
          ...(typeof payload.error_code === "string" ? { error_code: payload.error_code.slice(0, 80) } : {}),
          ...(typeof payload.reference === "string" ? { reference: payload.reference.slice(0, 100) } : {}),
          ...(typeof payload.transaction_reference === "string" ? { transaction_reference: payload.transaction_reference.slice(0, 100) } : {}),
        };
        const apiError = new AppApiError({
          status: response.status,
          backendCode: typeof payload.code === "string" ? payload.code : typeof payload.error_code === "string" ? payload.error_code : undefined,
          retryAfterMs: parseRetryAfter(response.headers.get("Retry-After")),
          method: String(restOptions.method ?? "GET"),
          payload: safePayload,
        });
        apiError.message = appErrorFromException(apiError).message;
        throw apiError;
      }

      return payload as T;
    },
    [apiBaseUrl],
  );

  const request = useCallback(
    <T,>(path: string, options: RequestOptions = {}) => requestWithToken<T>(path, options, token),
    [requestWithToken, token],
  );

  const establishSession = useCallback(async (nextToken: string, nextUser: ExaEarnUser | null) => {
    if (!nextToken || !nextUser) {
      throw new Error("The authentication service did not return a valid session.");
    }

    if (Platform.OS !== "web") {
      let storageError: unknown;
      try {
        if (!(await SecureStore.isAvailableAsync())) {
          throw new Error("OS-backed secure storage is unavailable.");
        }
        await SecureStore.setItemAsync(AUTH_TOKEN_KEY, nextToken, AUTH_TOKEN_OPTIONS);
      } catch (error) {
        storageError = error;
      }

      if (storageError) {
        try {
          await requestWithToken("/api/logout", { method: "POST" }, nextToken);
        } catch {
          throw new Error("Secure storage is unavailable and the new server session could not be revoked. Contact support or revoke it from another signed-in device.");
        }
        throw new Error("Secure storage is unavailable; sign-in was not completed.");
      }
    }

    setToken(nextToken);
    setUser(nextUser);
    setAuthError("");
  }, [requestWithToken]);

  useEffect(() => {
    let active = true;

    async function restoreSession() {
      try {
        if (Platform.OS === "web") return;
        if (!(await SecureStore.isAvailableAsync())) {
          if (active) setAuthError("OS-backed secure storage is unavailable. Sign in will not be persisted on this device.");
          return;
        }

        const storedToken = await SecureStore.getItemAsync(AUTH_TOKEN_KEY, AUTH_TOKEN_OPTIONS);
        if (!storedToken) return;

        const payload = await requestWithToken<Record<string, unknown>>("/api/user", { method: "GET" }, storedToken);
        const restoredUser = getPayloadUser(payload);
        if (!restoredUser) throw new Error("The account service did not return a valid user session.");
        if (active) {
          setToken(storedToken);
          setUser(restoredUser);
          setAuthError("");
        }
      } catch (error) {
        const status = (error as Error & { status?: number }).status;
        if (status === 401 || status === 419) {
          try {
            await SecureStore.deleteItemAsync(AUTH_TOKEN_KEY, AUTH_TOKEN_OPTIONS);
          } catch {
            if (active) setAuthError("The saved session expired, but could not be cleared from secure device storage.");
          }
        } else if (active) {
          setAuthError(error instanceof Error ? error.message : "The saved account session could not be restored.");
        }
      } finally {
        if (active) setAuthReady(true);
      }
    }

    void restoreSession();
    return () => {
      active = false;
    };
  }, [requestWithToken]);

  const startGoogleLogin = useCallback(() => {
    setGoogleAuthError(GOOGLE_UNAVAILABLE_MESSAGE);
  }, []);

  const login = useCallback(
    async ({ email, password }: { email: string; password: string }) => {
      setAuthError("");
      setGoogleAuthError("");
      setAuthLoading(true);
      try {
        const payload = await request<Record<string, unknown>>("/api/login", {
          method: "POST",
          body: JSON.stringify({
            email,
            password,
            device_name: `exaearn-${Platform.OS}`,
          }),
        });

        const succeeded = payload.success === true || payload.status === "success";
        if (!succeeded) {
          const message = String(payload.message || "Login failed.");
          setAuthError(message);
          return { success: false, message };
        }

        const nextToken = typeof payload.token === "string" ? payload.token : "";
        const nextUser = getPayloadUser(payload);
        await establishSession(nextToken, nextUser);
        return { success: true };
      } catch (error) {
        const message = error instanceof Error ? error.message : "Login failed.";
        setAuthError(message);
        return { success: false, message };
      } finally {
        setAuthLoading(false);
      }
    },
    [establishSession, request],
  );

  const checkAccountAvailability = useCallback(
    async ({
      name,
      email,
      password,
      passwordConfirmation,
      referralCode,
    }: {
      name: string;
      email: string;
      password: string;
      passwordConfirmation: string;
      referralCode?: string;
    }) => {
      setAuthError("");
      setAuthLoading(true);
      try {
        const payload = await request<Record<string, unknown>>("/api/account/check", {
          method: "POST",
          body: JSON.stringify({
            name,
            email,
            password,
            password_confirmation: passwordConfirmation,
            referral_code: referralCode || undefined,
            validate_credentials: true,
          }),
        });

        const succeeded = payload.success === true || payload.status === "success";
        const exists = Boolean(payload.exists);
        const message = String(payload.message || "");
        if (!succeeded || exists) {
          setAuthError(message || "Account details could not be accepted.");
          return { success: false, exists, message };
        }

        return { success: true, exists: false, message };
      } catch (error) {
        const message = error instanceof Error ? error.message : "Unable to verify account.";
        const status = (error as Error & { status?: number }).status;
        setAuthError(message);
        return { success: false, exists: status === 409, message };
      } finally {
        setAuthLoading(false);
      }
    },
    [request],
  );

  const register = useCallback(
    async ({
      name,
      email,
      password,
      passwordConfirmation,
      referralCode,
    }: {
      name: string;
      email: string;
      password: string;
      passwordConfirmation: string;
      referralCode?: string;
    }) => {
      setAuthError("");
      setGoogleAuthError("");
      setAuthLoading(true);
      try {
        const payload = await request<Record<string, unknown>>("/api/register", {
          method: "POST",
          body: JSON.stringify({
            name,
            email,
            password,
            password_confirmation: passwordConfirmation,
            referral_code: referralCode || undefined,
          }),
        });

        const succeeded = payload.success === true || payload.status === "success";
        if (!succeeded) {
          const message = String(payload.message || "Registration failed.");
          setAuthError(message);
          return { success: false, message };
        }

        const nextToken = typeof payload.token === "string" ? payload.token : "";
        const nextUser = getPayloadUser(payload);
        await establishSession(nextToken, nextUser);
        return { success: true };
      } catch (error) {
        const message = error instanceof Error ? error.message : "Registration failed.";
        setAuthError(message);
        return { success: false, message };
      } finally {
        setAuthLoading(false);
      }
    },
    [establishSession, request],
  );

  const logout = useCallback(async () => {
    let logoutError = "";
    try {
      if (token) {
        await request("/api/logout", { method: "POST" });
      }
    } catch (error) {
      logoutError = error instanceof Error ? error.message : "The server session could not be revoked.";
    } finally {
      if (Platform.OS !== "web") {
        try {
          await SecureStore.deleteItemAsync(AUTH_TOKEN_KEY, AUTH_TOKEN_OPTIONS);
        } catch {
          logoutError = "Signed out in this app, but the saved session could not be removed from secure device storage.";
        }
      }
      setUser(null);
      setToken("");
      setAuthError(logoutError);
      setGoogleAuthError("");
    }
  }, [request, token]);

  const value = useMemo(
    () => ({
      user,
      token,
      apiBaseUrl,
      authReady,
      authLoading,
      authError,
      isGoogleAuthLoading,
      googleAuthError,
      isGoogleConfigured,
      request,
      checkAccountAvailability,
      login,
      register,
      logout,
      startGoogleLogin,
    }),
    [
      apiBaseUrl,
      authReady,
      authError,
      authLoading,
      checkAccountAvailability,
      googleAuthError,
      isGoogleAuthLoading,
      isGoogleConfigured,
      login,
      logout,
      request,
      register,
      startGoogleLogin,
      token,
      user,
    ],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within AuthProvider");
  }

  return context;
}
