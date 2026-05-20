import { lazy, StrictMode, Suspense } from "react";
import { createRoot } from "react-dom/client";
import { GoogleOAuthProvider } from "@react-oauth/google";
import "./index.css";
import { AuthProvider } from "./context/AuthContext.jsx";
import { ThemeProvider } from "./context/ThemeContext.jsx";

const App = lazy(() => import("./App.jsx"));
const AdminApp = lazy(() => import("./admin/AdminApp.jsx"));

const googleClientId = import.meta.env.VITE_GOOGLE_CLIENT_ID?.trim() || "placeholder-client-id";
const basePath = import.meta.env.BASE_URL.replace(/\/$/, "");
const adminPath = `${basePath}/admin`;
const isAdminRoute = window.location.pathname.startsWith(adminPath) || window.location.pathname.startsWith("/admin");

createRoot(document.getElementById("root")).render(
  <StrictMode>
    <GoogleOAuthProvider clientId={googleClientId}>
      <ThemeProvider>
        <AuthProvider>
          <Suspense fallback={null}>
            {isAdminRoute ? <AdminApp basename={adminPath} /> : <App />}
          </Suspense>
        </AuthProvider>
      </ThemeProvider>
    </GoogleOAuthProvider>
  </StrictMode>,
)
