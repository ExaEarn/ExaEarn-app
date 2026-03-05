import { createContext, useCallback, useContext, useMemo, useState } from "react";
import { useGoogleLogin } from "@react-oauth/google";

const AuthContext = createContext(null);

function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [isGoogleAuthLoading, setIsGoogleAuthLoading] = useState(false);
  const [googleAuthError, setGoogleAuthError] = useState("");

  const googleClientId = import.meta.env.VITE_GOOGLE_CLIENT_ID?.trim();
  const isGoogleConfigured = Boolean(googleClientId);

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
        setUser({
          name: profile.name ?? "",
          email: profile.email ?? "",
          picture: profile.picture ?? "",
        });
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

  const value = useMemo(
    () => ({
      user,
      setUser,
      isGoogleAuthLoading,
      googleAuthError,
      isGoogleConfigured,
      startGoogleLogin,
    }),
    [user, isGoogleAuthLoading, googleAuthError, isGoogleConfigured, startGoogleLogin]
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
