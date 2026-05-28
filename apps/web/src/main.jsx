import { lazy, StrictMode, Suspense } from "react";
import { createRoot } from "react-dom/client";
import { GoogleOAuthProvider } from "@react-oauth/google";
import "./styles/index.css";
import { AuthProvider } from "./context/AuthContext.jsx";
import { ThemeProvider } from "./context/ThemeContext.jsx";

const App = lazy(() => import("./App.jsx"));

const googleClientId = import.meta.env.VITE_GOOGLE_CLIENT_ID?.trim() || "placeholder-client-id";

createRoot(document.getElementById("root")).render(
  <StrictMode>
    <GoogleOAuthProvider clientId={googleClientId}>
      <ThemeProvider>
        <AuthProvider>
          <Suspense fallback={null}>
            <App />
          </Suspense>
        </AuthProvider>
      </ThemeProvider>
    </GoogleOAuthProvider>
  </StrictMode>,
)
