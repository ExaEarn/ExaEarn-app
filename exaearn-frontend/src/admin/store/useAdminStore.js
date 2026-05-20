import { create } from "zustand";
import { fetchAdminBootstrap } from "../services/adminApi";

export const useAdminStore = create((set, get) => ({
  loading: true,
  hydrated: false,
  admin: null,
  role: "super_admin",
  stats: [],
  charts: {},
  serverStatus: [],
  notifications: [
    { id: 1, title: "Treasury sweep ready for approval", level: "warning", time: "2m ago" },
    { id: 2, title: "KYC review queue has 18 pending cases", level: "info", time: "6m ago" },
  ],
  permissionsByRole: {},
  heartbeat: {
    queueDepth: 18,
    websocketClients: 234,
    securityAlerts: 3,
    blockchainLatency: 201,
    feedStatus: "stable",
  },
  async hydrate() {
    if (get().hydrated) return;
    const payload = await fetchAdminBootstrap();
    set({
      loading: false,
      hydrated: true,
      admin: payload.admin,
      role: payload.admin.role,
      stats: payload.stats,
      charts: payload.charts,
      serverStatus: payload.serverStatus,
      permissionsByRole: payload.permissionsByRole,
    });
  },
  setRole(role) {
    set({ role });
  },
  setNotifications(notifications) {
    set({ notifications });
  },
  pushNotification(notification) {
    set((state) => ({ notifications: [notification, ...state.notifications].slice(0, 6) }));
  },
  updateHeartbeat(patch) {
    set((state) => ({ heartbeat: { ...state.heartbeat, ...patch } }));
  },
}));
