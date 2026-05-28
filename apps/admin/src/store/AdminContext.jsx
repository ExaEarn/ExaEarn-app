import { createContext, useContext, useEffect, useMemo, useState } from "react";
import { fetchAdminBootstrap } from "../services/adminApi";

const AdminContext = createContext(null);

export function AdminProvider({ children }) {
  const [loading, setLoading] = useState(true);
  const [admin, setAdmin] = useState(null);
  const [role, setRole] = useState("super_admin");
  const [stats, setStats] = useState([]);
  const [charts, setCharts] = useState({});
  const [serverStatus, setServerStatus] = useState([]);
  const [permissionsByRole, setPermissionsByRole] = useState({});
  const [notifications, setNotifications] = useState([
    { id: 1, title: "Treasury sweep ready for approval", level: "warning", time: "2m ago" },
    { id: 2, title: "KYC review queue has 18 pending cases", level: "info", time: "6m ago" },
  ]);

  useEffect(() => {
    let active = true;

    fetchAdminBootstrap().then((payload) => {
      if (!active) return;
      setAdmin(payload.admin);
      setRole(payload.admin.role);
      setStats(payload.stats);
      setCharts(payload.charts);
      setServerStatus(payload.serverStatus);
      setPermissionsByRole(payload.permissionsByRole);
      setLoading(false);
    });

    return () => {
      active = false;
    };
  }, []);

  const value = useMemo(
    () => ({
      loading,
      admin,
      role,
      setRole,
      stats,
      charts,
      serverStatus,
      notifications,
      setNotifications,
      permissionsByRole,
    }),
    [admin, charts, loading, notifications, permissionsByRole, role, serverStatus, stats],
  );

  return <AdminContext.Provider value={value}>{children}</AdminContext.Provider>;
}

export function useAdmin() {
  const context = useContext(AdminContext);

  if (!context) {
    throw new Error("useAdmin must be used inside AdminProvider");
  }

  return context;
}
