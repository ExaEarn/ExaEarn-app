import { useEffect } from "react";
import { BrowserRouter } from "react-router-dom";
import "./styles/admin.css";
import { AdminRoutes } from "./routes/AdminRoutes";
import { useAdminStore } from "./store/useAdminStore";
import { AdminAuthProvider } from "./context/AdminAuthContext";
import { ThemeProvider } from "./context/ThemeContext";

function AdminBootstrap() {
  const hydrate = useAdminStore((state) => state.hydrate);

  useEffect(() => {
    hydrate();
  }, [hydrate]);

  return <AdminRoutes />;
}

export default function AdminApp() {
  const basename = import.meta.env.VITE_ADMIN_BASE_PATH || "/";

  return (
    <ThemeProvider>
      <AdminAuthProvider>
        <BrowserRouter basename={basename}>
          <AdminBootstrap />
        </BrowserRouter>
      </AdminAuthProvider>
    </ThemeProvider>
  );
}
