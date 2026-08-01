import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from "react";
import { Platform } from "react-native";

import { getApiBaseUrl } from "../config/apiConfig";

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
const API_UNREACHABLE_MESSAGE = "Unable to reach the API. Check your backend URL and server.";
const GOOGLE_UNAVAILABLE_MESSAGE = "Google login is not configured for the mobile web build yet.";

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
  const [authLoading, setAuthLoading] = useState(false);
  const [authError, setAuthError] = useState("");
  const [isGoogleAuthLoading] = useState(false);
  const [googleAuthError, setGoogleAuthError] = useState("");
  const apiBaseUrl = getApiBaseUrl();
  const isGoogleConfigured = false;

  const request = useCallback(
    async <T,>(path: string, options: RequestOptions = {}) => {
      const normalizedBase = apiBaseUrl.replace(/\/+$/, "");
      const normalizedPath = path.startsWith("/") ? path : `/${path}`;
      const { headers: optionHeaders, ...restOptions } = options;

      const headers = {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(optionHeaders || {}),
      };

      let response: Response;
      try {
        response = await fetch(`${normalizedBase}${normalizedPath}`, {
          ...restOptions,
          headers,
        });
      } catch {
        throw new Error(API_UNREACHABLE_MESSAGE);
      }

      let payload: Record<string, unknown> = {};
      try {
        payload = (await response.json()) as Record<string, unknown>;
      } catch {
        payload = {};
      }

      if (!response.ok || payload.status === "error") {
        const error = new Error(String(payload.message || `Request failed (${response.status})`));
        (error as Error & { status?: number; payload?: Record<string, unknown> }).status = response.status;
        (error as Error & { status?: number; payload?: Record<string, unknown> }).payload = payload;
        throw error;
      }

      return payload as T;
    },
    [apiBaseUrl, token],
  );

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
        setToken(nextToken);
        setUser(nextUser);
        return { success: true };
      } catch (error) {
        const message = error instanceof Error ? error.message : "Login failed.";
        setAuthError(message);
        return { success: false, message };
      } finally {
        setAuthLoading(false);
      }
    },
    [request],
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
        setToken(nextToken);
        setUser(nextUser);
        return { success: true };
      } catch (error) {
        const message = error instanceof Error ? error.message : "Registration failed.";
        setAuthError(message);
        return { success: false, message };
      } finally {
        setAuthLoading(false);
      }
    },
    [request],
  );

  const logout = useCallback(async () => {
    try {
      if (token) {
        await request("/api/logout", { method: "POST" });
      }
    } catch {
      // Logout should still clear the local app state.
    } finally {
      setUser(null);
      setToken("");
      setAuthError("");
      setGoogleAuthError("");
    }
  }, [request, token]);

  const value = useMemo(
    () => ({
      user,
      token,
      apiBaseUrl,
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
