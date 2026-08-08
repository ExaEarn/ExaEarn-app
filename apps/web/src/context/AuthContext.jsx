import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import { useGoogleLogin } from "@react-oauth/google";
import { useWebSocketConnection } from "../services/webSocketService";
import { getApiBaseUrl, isDemoAuthEnabled } from "../config/apiConfig";

const AuthContext = createContext(null);
const AUTH_USER_KEY = "exaearn_auth_user";
const AUTH_TOKEN_KEY = "exaearn_auth_token";
const DEMO_USERS_KEY = "exaearn_demo_users";
const API_UNREACHABLE_MESSAGE = "Unable to reach the API. Check that the backend is running.";
const API_NOT_CONFIGURED_MESSAGE = "API URL is not configured. Set VITE_API_URL or /env.js to your deployed Laravel backend URL.";
const API_TIMEOUT_MESSAGE = "The request took too long. Please try again.";

function readStoredUser() {
  try {
    const storedUser = localStorage.getItem(AUTH_USER_KEY);
    return storedUser ? JSON.parse(storedUser) : null;
  } catch {
    return null;
  }
}

function readDemoUsers() {
  try {
    return JSON.parse(localStorage.getItem(DEMO_USERS_KEY) || "[]");
  } catch {
    return [];
  }
}

function writeDemoUsers(users) {
  try {
    localStorage.setItem(DEMO_USERS_KEY, JSON.stringify(users));
  } catch {
    // ignore localStorage failures
  }
}

function createDemoUser({ name, email }) {
  return {
    id: `demo-${Date.now()}`,
    name: name?.trim() || email,
    email: email?.trim().toLowerCase(),
    picture: "",
    demo: true,
  };
}

function AuthProvider({ children }) {
  const [user, setUser] = useState(() => readStoredUser());
  const [token, setToken] = useState(() => {
    try {
      return localStorage.getItem(AUTH_TOKEN_KEY) || "";
    } catch {
      return "";
    }
  });
  const [isGoogleAuthLoading, setIsGoogleAuthLoading] = useState(false);
  const [googleAuthError, setGoogleAuthError] = useState("");
  const [authLoading, setAuthLoading] = useState(false);
  const [authError, setAuthError] = useState("");
  const [authReady, setAuthReady] = useState(false);

  const apiBaseUrl = getApiBaseUrl();
  const demoAuthEnabled = isDemoAuthEnabled();

  const googleClientId = import.meta.env.VITE_GOOGLE_CLIENT_ID?.trim();
  const isGoogleConfigured = Boolean(googleClientId);

  useEffect(() => {
    try {
      if (user) {
        localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
      } else {
        localStorage.removeItem(AUTH_USER_KEY);
      }
    } catch {
      // ignore localStorage failures
    }
  }, [user]);

  useEffect(() => {
    try {
      if (token) {
        localStorage.setItem(AUTH_TOKEN_KEY, token);
      } else {
        localStorage.removeItem(AUTH_TOKEN_KEY);
      }
    } catch {
      // ignore localStorage failures
    }
  }, [token]);

  useWebSocketConnection(apiBaseUrl);

  const request = useCallback(
    async (path, options = {}) => {
      const normalizedBase = apiBaseUrl.endsWith("/") ? apiBaseUrl.slice(0, -1) : apiBaseUrl;
      const normalizedPath = path.startsWith("/") ? path : `/${path}`;
      if (!normalizedBase) {
        throw new Error(API_NOT_CONFIGURED_MESSAGE);
      }
      const requestUrl = `${normalizedBase}${normalizedPath}`;

      const { headers: optionHeaders, timeoutMs = 15000, signal: externalSignal, ...restOptions } = options || {};

      const headers = {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(optionHeaders || {}),
      };

      if (restOptions.body && typeof FormData !== "undefined" && restOptions.body instanceof FormData) {
        delete headers["Content-Type"];
      }

      const controller = new AbortController();
      const abortSignal = externalSignal || controller.signal;
      const timeoutId = timeoutMs > 0 ? window.setTimeout(() => controller.abort(), timeoutMs) : null;

      let response;
      try {
        response = await fetch(requestUrl, {
          ...restOptions,
          headers,
          signal: abortSignal,
          credentials: 'include',
        });
      } catch (error) {
        if (timeoutId) window.clearTimeout(timeoutId);
        if (error?.name === "AbortError") {
          throw new Error(API_TIMEOUT_MESSAGE);
        }
        throw new Error(API_UNREACHABLE_MESSAGE);
      }

      if (timeoutId) window.clearTimeout(timeoutId);

      let payload = {};
      try {
        payload = await response.json();
      } catch {
        payload = {};
      }

      if (!response.ok || payload?.status === "error") {
        const message = payload?.message || `Request failed (${response.status})`;
        const error = new Error(message);
        error.status = response.status;
        error.code = payload?.code || "";
        error.payload = payload;
        throw error;
      }

      return payload;
    },
    [apiBaseUrl, token]
  );

  const fetchMe = useCallback(async () => {
    if (!token) {
      setUser(null);
      setAuthReady(true);
      return;
    }

    if (demoAuthEnabled && token.startsWith("demo-local-")) {
      setUser(readStoredUser());
      setAuthReady(true);
      return;
    }

    try {
      const payload = await request("/api/user", { method: "GET" });
      const nextUser = payload.user ?? payload.data?.user ?? null;
      if (nextUser) {
        setUser(nextUser);
      }
    } catch (error) {
      if (error?.status === 401 || error?.status === 403) {
        setUser(null);
        setToken("");
      } else {
        const storedUser = readStoredUser();
        if (storedUser) {
          setUser(storedUser);
        }
      }
    } finally {
      setAuthReady(true);
    }
  }, [demoAuthEnabled, request, token]);

  useEffect(() => {
    fetchMe();
  }, [fetchMe]);

  const googleLogin = useGoogleLogin({
    scope: "openid profile email",
    onSuccess: async (tokenResponse) => {
      try {
        const response = await fetch("https://www.googleapis.com/oauth2/v3/userinfo", {
          headers: { Authorization: `Bearer ${tokenResponse.access_token}` },
        });

        if (!response.ok) {
          throw new Error(`Failed to fetch Google profile: ${response.status}`);
        }

        const profile = await response.json();
        const googleUser = {
          name: profile.name ?? "",
          email: profile.email ?? "",
          picture: profile.picture ?? "",
        };
        setUser(googleUser);
        setToken(`google-local-${Date.now()}`);
        setGoogleAuthError("");
      } catch (error) {
        console.error("Google auth profile fetch failed:", error);
        setGoogleAuthError("Unable to fetch Google profile. Please try again.");
      } finally {
        setIsGoogleAuthLoading(false);
      }
    },
    onError: (errorResponse) => {
      console.warn("Google login failed:", errorResponse);
      setGoogleAuthError("Google login failed. Please try again.");
      setIsGoogleAuthLoading(false);
    },
    onNonOAuthError: (nonOAuthError) => {
      console.warn("Google login was interrupted:", nonOAuthError);
      setGoogleAuthError("Google login was interrupted. Please try again.");
      setIsGoogleAuthLoading(false);
    },
  });

  const startGoogleLogin = useCallback(() => {
    if (!isGoogleConfigured) {
      console.warn("Missing VITE_GOOGLE_CLIENT_ID. Google login cannot start.");
      setGoogleAuthError("Google login is not configured. Add VITE_GOOGLE_CLIENT_ID to your .env file.");
      return;
    }

    setGoogleAuthError("");
    setIsGoogleAuthLoading(true);

    try {
      googleLogin();
    } catch (error) {
      console.error("Google login start failed:", error);
      setGoogleAuthError("Unable to start Google login.");
      setIsGoogleAuthLoading(false);
    }
  }, [isGoogleConfigured, googleLogin]);

  const login = useCallback(
    async ({ email, password }) => {
      setAuthError("");
      setAuthLoading(true);
      try {
        if (demoAuthEnabled) {
          const normalizedEmail = email.trim().toLowerCase();
          const demoUser = readDemoUsers().find((item) => item.email === normalizedEmail);
          if (!demoUser || demoUser.password !== password) {
            setAuthError("No preview account found for this email. Please sign up first.");
            return { success: false };
          }

          const { password: _password, ...safeUser } = demoUser;
          setUser(safeUser);
          setToken(`demo-local-${Date.now()}`);
          setAuthReady(true);
          return { success: true };
        }

        const payload = await request("/api/login", {
          method: "POST",
          body: JSON.stringify({ email, password }),
        });

        const succeeded = payload.success === true || payload.status === "success";
        if (!succeeded) {
          setAuthError(payload.message || "Login failed.");
          return { success: false };
        }

        if (payload.token) {
          setToken(payload.token);
        }

        if (payload.user || payload.data?.user) {
          setUser(payload.user ?? payload.data.user);
        } else {
          await fetchMe();
        }

        setAuthReady(true);
        return { success: true };
      } catch (error) {
        if (demoAuthEnabled && error.message === API_UNREACHABLE_MESSAGE) {
          const normalizedEmail = email.trim().toLowerCase();
          const demoUser = readDemoUsers().find((item) => item.email === normalizedEmail);
          if (demoUser && demoUser.password === password) {
            const { password: _password, ...safeUser } = demoUser;
            setUser(safeUser);
            setToken(`demo-local-${Date.now()}`);
            setAuthReady(true);
            return { success: true };
          }
        }

        setAuthError(error.message || "Login failed.");
        return { success: false };
      } finally {
        setAuthLoading(false);
      }
    },
    [demoAuthEnabled, request, fetchMe]
  );

  const checkAccountAvailability = useCallback(
    async ({ name, email, password, passwordConfirmation, referralCode, validateCredentials = false }) => {
      setAuthError("");
      setAuthLoading(true);
      try {
        if (demoAuthEnabled) {
          const normalizedEmail = email.trim().toLowerCase();
          const exists = readDemoUsers().some((item) => item.email === normalizedEmail);
          return {
            success: true,
            exists,
            message: exists ? "Preview account already exists. Please login." : "",
          };
        }

        const payload = await request("/api/account/check", {
          method: "POST",
          body: JSON.stringify({
            email,
            ...(validateCredentials
              ? {
                  validate_credentials: true,
                  name,
                  password,
                  password_confirmation: passwordConfirmation,
                  referral_code: referralCode || undefined,
                }
              : {}),
          }),
        });

        return {
          success: payload.success === true || payload.status === "success",
          exists: Boolean(payload.exists),
          message: payload.message || "",
        };
      } catch (error) {
        const accountExists = error.code === "ACCOUNT_EXISTS" || error.status === 409;
        const message = error.message || "Unable to verify account.";
        setAuthError(message);
        return { success: false, exists: accountExists, message, code: error.code || "" };
      } finally {
        setAuthLoading(false);
      }
    },
    [demoAuthEnabled, request]
  );

  const register = useCallback(
    async ({ name, email, password, passwordConfirmation, referralCode }) => {
      setAuthError("");
      setAuthLoading(true);
      try {
        if (demoAuthEnabled) {
          const normalizedEmail = email.trim().toLowerCase();
          const demoUsers = readDemoUsers();
          if (demoUsers.some((item) => item.email === normalizedEmail)) {
            setAuthError("Account already exists. Please login.");
            return { success: false };
          }

          const safeUser = createDemoUser({ name, email: normalizedEmail });
          writeDemoUsers([...demoUsers, { ...safeUser, password }]);
          setUser(safeUser);
          setToken(`demo-local-${Date.now()}`);
          setAuthReady(true);
          return { success: true };
        }

        const payload = await request("/api/register", {
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
          setAuthError(payload.message || "Registration failed.");
          return { success: false };
        }

        if (payload.token) {
          setToken(payload.token);
        }
        if (payload.user || payload.data?.user) {
          setUser(payload.user ?? payload.data.user);
        }

        setAuthReady(true);
        return { success: true };
      } catch (error) {
        if (demoAuthEnabled && error.message === API_UNREACHABLE_MESSAGE) {
          const normalizedEmail = email.trim().toLowerCase();
          const demoUsers = readDemoUsers();
          if (demoUsers.some((item) => item.email === normalizedEmail)) {
            setAuthError("Account already exists. Please login.");
            return { success: false };
          }

          const safeUser = createDemoUser({ name, email: normalizedEmail });
          writeDemoUsers([...demoUsers, { ...safeUser, password }]);
          setUser(safeUser);
          setToken(`demo-local-${Date.now()}`);
          setAuthReady(true);
          return { success: true };
        }

        setAuthError(error.message || "Registration failed.");
        return { success: false };
      } finally {
        setAuthLoading(false);
      }
    },
    [demoAuthEnabled, request]
  );

  const logout = useCallback(async () => {
    try {
      await request("/api/logout", { method: "POST" });
    } catch {
      // best-effort logout
    } finally {
      setUser(null);
      setToken("");
      setAuthReady(true);
    }
  }, [request]);

  const value = useMemo(
    () => ({
      user,
      setUser,
      token,
      setToken,
      authReady,
      authLoading,
      authError,
      apiBaseUrl,
      request,
      fetchMe,
      login,
      checkAccountAvailability,
      register,
      logout,
      isGoogleAuthLoading,
      googleAuthError,
      isGoogleConfigured,
      startGoogleLogin,
    }),
    [
      user,
      token,
      authReady,
      authLoading,
      authError,
      apiBaseUrl,
      request,
      fetchMe,
      login,
      checkAccountAvailability,
      register,
      logout,
      isGoogleAuthLoading,
      googleAuthError,
      isGoogleConfigured,
      startGoogleLogin,
    ]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within AuthProvider");
  }
  return context;
}

export { AuthProvider, useAuth };
