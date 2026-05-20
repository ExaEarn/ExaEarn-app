import { Navigate } from "react-router-dom";
import { useAdminAuth } from "../context/AdminAuthContext";

export function AdminProtectedRoute({ children }) {
  const { isAuthenticated, loading } = useAdminAuth();

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-cosmic-950">
        <div className="text-white text-center">
          <div className="inline-block mb-4">
            <div className="animate-spin h-12 w-12 border-4 border-violet-400 border-t-auric-300 rounded-full" />
          </div>
          <p>Loading...</p>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/admin/login" replace />;
  }

  return children;
}
