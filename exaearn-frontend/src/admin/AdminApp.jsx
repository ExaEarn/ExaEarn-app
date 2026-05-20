import { useEffect } from "react";
import { BrowserRouter } from "react-router-dom";
import "./admin.css";
import { AdminRoutes } from "./routes/AdminRoutes";
import { useAdminStore } from "./store/useAdminStore";
import { AdminAuthProvider } from "./context/AdminAuthContext";

function AdminBootstrap() {
  const hydrate = useAdminStore((state) => state.hydrate);

  useEffect(() => {
    hydrate();
  }, [hydrate]);

  return <AdminRoutes />;
}

export default function AdminApp({ basename = "/admin" }) {
  return (
    <AdminAuthProvider>
      <BrowserRouter basename={basename}>
        <AdminBootstrap />
      </BrowserRouter>
    </AdminAuthProvider>
  );
}
