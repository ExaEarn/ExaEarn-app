import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import { useGoogleLogin } from "@react-oauth/google";
import { useWebSocketConnection } from "../services/webSocketService";

const AuthContext = createContext(null);
const AUTH_USER_KEY = "exaearn_auth_user";
const AUTH_TOKEN_KEY = "exaearn_auth_token";
const LOCAL_USERS_KEY = "exaearn_local_users";
const API_UNREACHABLE_MESSAGE = "Unable to reach the API. Check that the backend is running.";

function readJson(key, fallback) {
  try {
    const value = localStorage.getItem(key);
    return value ? JSON.parse(value) : fallback;
  } catch {
    return fallback;
  }
}

function createLocalUser({ name, email }) {
  const normalizedEmail = String(email || "").trim().toLowerCase();
  return {
    id: `local-${Date.now()}`,
    name: name?.trim() || normalizedEmail.split("@")[0] || "ExaEarn User",
    email: normalizedEmail,
    picture: "",
    unique_user_id: `EXA-${Math.random().toString(36).slice(2, 10).toUpperCase()}`,
    isLocalDemoUser: true,
  };
}

function isApiUnreachable(error) {
  return error?.message === API_UNREACHABLE_MESSAGE || error?.message === "API URL is not configured.";
}

function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
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

  const apiBaseUrl = import.meta.env.VITE_API_URL?.trim() || "";

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

  // Initialize WebSocket connection for real-time events
  useWebSocketConnection(apiBaseUrl);

  const request = useCallback(
    async (path, options = {}) => {
      const normalizedBase = apiBaseUrl.endsWith("/") ? apiBaseUrl.slice(0, -1) : apiBaseUrl;
      const normalizedPath = path.startsWith("/") ? path : `/${path}`;
      if (!normalizedBase) {
        throw new Error("API URL is not configured.");
      }

      const { headers: optionHeaders, ...restOptions } = options || {};

      const headers = {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(optionHeaders || {}),
      };

      // Avoid breaking FormData requests.
      if (restOptions.body && typeof FormData !== "undefined" && restOptions.body instanceof FormData) {
        delete headers["Content-Type"];
      }

      let response;
      try {
        response = await fetch(`${normalizedBase}${normalizedPath}`, {
          ...restOptions,
          headers,
          credentials: 'include', // Include cookies for Sanctum session auth
        });
      } catch {
        throw new Error(API_UNREACHABLE_MESSAGE);
      }

      let payload = {};
      try {
        payload = await response.json();
      } catch {
        payload = {};
      }

      if (!response.ok || payload?.status === "error") {
        const message = payload?.message || `Request failed (${response.status})`;
        throw new Error(message);
      }

      return payload;
    },
    [apiBaseUrl, token]
  );

  const fetchMe = useCallback(async () => {
    try {
      const payload = await request("/api/user", { method: "GET" });
      setUser(payload.user ?? payload.data?.user ?? null);
    } catch {
      const cachedUser = readJson(AUTH_USER_KEY, null);
      setUser(cachedUser);
    }
  }, [request]);

  useEffect(() => {
    // Hydrate user session when the app boots.
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
        const payload = await request("/api/login", {
          method: "POST",
          body: JSON.stringify({
            email,
            password,
          }),
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
          await fetchMe(); // Fetch user after successful login
        }

        return { success: true };
      } catch (error) {
        if (import.meta.env.DEV && isApiUnreachable(error)) {
          const normalizedEmail = String(email || "").trim().toLowerCase();
          const localUsers = readJson(LOCAL_USERS_KEY, []);
          const localAccount = localUsers.find((account) => account.email === normalizedEmail);

          if (!localAccount || localAccount.password !== password) {
            setAuthError("Local account not found. Create one first, or start the backend API.");
            return { success: false };
          }

          setUser(localAccount.user);
          setToken(`local-dev-${localAccount.user.id}`);
          return { success: true, local: true };
        }

        setAuthError(error.message || "Login failed.");
        return { success: false };
      } finally {
        setAuthLoading(false);
      }
    },
    [request, fetchMe]
  );

  const checkAccountAvailability = useCallback(
    async ({ email }) => {
      setAuthError("");
      setAuthLoading(true);
      try {
        const payload = await request("/api/account/check", {
          method: "POST",
          body: JSON.stringify({ email }),
        });

        return {
          success: payload.success === true || payload.status === "success",
          exists: Boolean(payload.exists),
          message: payload.message || "",
        };
      } catch (error) {
        setAuthError(error.message || "Unable to verify account.");
        return { success: false, exists: true, message: error.message || "Unable to verify account." };
      } finally {
        setAuthLoading(false);
      }
    },
    [request]
  );

  const register = useCallback(
    async ({ name, email, password, passwordConfirmation, referralCode }) => {
      setAuthError("");
      setAuthLoading(true);
      try {
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

        return { success: true };
      } catch (error) {
        if (import.meta.env.DEV && isApiUnreachable(error)) {
          const normalizedEmail = String(email || "").trim().toLowerCase();
          const localUsers = readJson(LOCAL_USERS_KEY, []);

          if (localUsers.some((account) => account.email === normalizedEmail)) {
            setAuthError("A local demo account already exists for this email. Login with it instead.");
            return { success: false };
          }

          const localUser = createLocalUser({ name, email: normalizedEmail });
          const nextUsers = [
            ...localUsers,
            {
              email: normalizedEmail,
              password,
              user: localUser,
            },
          ];

          localStorage.setItem(LOCAL_USERS_KEY, JSON.stringify(nextUsers));
          setUser(localUser);
          setToken(`local-dev-${localUser.id}`);
          return { success: true, local: true };
        }

        setAuthError(error.message || "Registration failed.");
        return { success: false };
      } finally {
        setAuthLoading(false);
      }
    },
    [request]
  );

  const logout = useCallback(async () => {
    try {
      await request("/api/logout", { method: "POST" });
    } catch {
      // best-effort logout
    } finally {
      setUser(null);
      setToken("");
    }
  }, [request]);

  const value = useMemo(
    () => ({
      user,
      setUser,
      token,
      setToken,
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
