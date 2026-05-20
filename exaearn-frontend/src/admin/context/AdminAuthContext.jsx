import { createContext, useContext, useState, useEffect } from "react";
import { adminHttp } from "../services/http";

const AdminAuthContext = createContext();

export function useAdminAuth() {
  const context = useContext(AdminAuthContext);
  if (!context) {
    throw new Error("useAdminAuth must be used within AdminAuthProvider");
  }
  return context;
}

export function AdminAuthProvider({ children }) {
  const [admin, setAdmin] = useState(null);
  const [loading, setLoading] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem("exaearn-admin-token");
    const adminData = localStorage.getItem("exaearn-admin-user");

    if (token && adminData) {
      setAdmin(JSON.parse(adminData));
      setIsAuthenticated(true);
    }
    setLoading(false);
  }, []);

  const login = (token, adminData) => {
    localStorage.setItem("exaearn-admin-token", token);
    localStorage.setItem("exaearn-admin-user", JSON.stringify(adminData));
    setAdmin(adminData);
    setIsAuthenticated(true);
  };

  const logout = async () => {
    try {
      await adminHttp.post("/logout");
    } catch (error) {
      console.error("Logout error:", error);
    }
    localStorage.removeItem("exaearn-admin-token");
    localStorage.removeItem("exaearn-admin-user");
    setAdmin(null);
    setIsAuthenticated(false);
  };

  return (
    <AdminAuthContext.Provider value={{ admin, loading, isAuthenticated, login, logout }}>
      {children}
    </AdminAuthContext.Provider>
  );
}
