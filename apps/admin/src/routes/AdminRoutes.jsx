import { Navigate, Route, Routes } from "react-router-dom";
import { AdminLayout } from "../layout/AdminLayout";
import { AdminLoginPage } from "../pages/AdminLoginPage";
import { AdminProtectedRoute } from "../components/AdminProtectedRoute";
import { routeRegistry } from "../modules/moduleRegistry";

export function AdminRoutes() {
  const entries = Object.entries(routeRegistry);

  return (
    <Routes>
      <Route path="/login" element={<AdminLoginPage />} />
      <Route
        path="/"
        element={
          <AdminProtectedRoute>
            <AdminLayout />
          </AdminProtectedRoute>
        }
      >
        {entries.map(([path, config]) => {
          const Page = config.element;
          const nestedPath = path.replace("/admin/", "").replace("/admin", "");
          const element = <Page moduleKey={config.key} pathKey={config.key === "system-monitor" ? "/admin/system" : `/admin/${config.key}`} />;

          if (!nestedPath) {
            return <Route key={path} index element={element} />;
          }

          return <Route key={path} path={nestedPath} element={element} />;
        })}
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Route>
    </Routes>
  );
}
