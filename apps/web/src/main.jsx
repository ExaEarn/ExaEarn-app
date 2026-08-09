import React, { lazy, StrictMode, Suspense } from "react";
import { createRoot } from "react-dom/client";
import { GoogleOAuthProvider } from "@react-oauth/google";
import "./styles/index.css";
import { AuthProvider } from "./context/AuthContext.jsx";
import { ThemeProvider } from "./context/ThemeContext.jsx";
import { LanguageProvider } from "./context/LanguageContext.jsx";

const App = lazy(() => import("./App.jsx"));

const googleClientId = import.meta.env.VITE_GOOGLE_CLIENT_ID?.trim() || "placeholder-client-id";

function AppFallback() {
  return (
    <div className="min-h-screen w-full bg-gradient-to-br from-black via-[#140a24] to-[#220c3d] text-white">
      <div className="flex min-h-screen items-center justify-center px-4 text-center">
        <p className="rounded-2xl border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-violet-50 shadow-[0_0_35px_rgba(168,85,247,0.2)]">
          Loading ExaEarn...
        </p>
      </div>
    </div>
  );
}

createRoot(document.getElementById("root")).render(
  <StrictMode>
    <GoogleOAuthProvider clientId={googleClientId}>
      <ThemeProvider>
        <AuthProvider>
          <LanguageProvider>
            <Suspense fallback={<AppFallback />}>
              <App />
            </Suspense>
          </LanguageProvider>
        </AuthProvider>
      </ThemeProvider>
    </GoogleOAuthProvider>
  </StrictMode>,
)

