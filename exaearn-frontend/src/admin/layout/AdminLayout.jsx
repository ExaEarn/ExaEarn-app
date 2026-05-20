import { useState } from "react";
import { NavLink, Outlet, useLocation, useNavigate } from "react-router-dom";
import { Bell, ChevronLeft, Menu, MoonStar, Search, SunMedium, LogOut } from "lucide-react";
import { adminMenu } from "../routes/menu";
import { usePermission } from "../hooks/usePermission";
import { GlassPanel, OutlineButton, Pill } from "../components/AdminPrimitives";
import { adminTheme } from "../theme/adminTheme";
import { useTheme } from "../../context/ThemeContext";
import { useAdminStore } from "../store/useAdminStore";
import { useAdminAuth } from "../context/AdminAuthContext";

function SidebarItem({ item, collapsed }) {
  const Icon = item.icon;

  return (
    <NavLink
      to={item.key === "dashboard" ? "/dashboard" : `/${item.key}`}
      className={({ isActive }) =>
        `flex w-full items-center gap-3 rounded-2xl border px-4 py-3 text-left transition ${
          isActive
            ? "border-auric-300/30 bg-gradient-to-r from-auric-300/15 to-violet-400/10 text-white shadow-[0_0_22px_rgba(234,185,95,0.12)]"
            : "border-transparent bg-transparent text-violet-100/65 hover:border-white/8 hover:bg-white/[0.04]"
        }`
      }
    >
      <Icon className="h-4 w-4 shrink-0" />
      {collapsed ? null : <span className="text-sm font-medium">{item.label}</span>}
    </NavLink>
  );
}

export function AdminLayout() {
  const admin = useAdminStore((state) => state.admin);
  const notifications = useAdminStore((state) => state.notifications);
  const role = useAdminStore((state) => state.role);
  const setRole = useAdminStore((state) => state.setRole);
  const loading = useAdminStore((state) => state.loading);
  const { theme, setTheme } = useTheme();
  const { logout } = useAdminAuth();
  const navigate = useNavigate();
  const permission = usePermission();
  const location = useLocation();
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const visibleMenu = adminMenu.filter((item) => permission.canAny(item.permissions));

  const handleLogout = async () => {
    await logout();
    navigate("/admin/login");
  };

  return (
    <div className="admin-grid min-h-screen bg-cosmic-950 text-white" style={{ backgroundImage: adminTheme.gradients.page }}>
      <div className="pointer-events-none fixed inset-0 opacity-80">
        <div className="absolute -left-24 top-0 h-72 w-72 rounded-full bg-violet-500/20 blur-3xl" />
        <div className="absolute right-0 top-32 h-80 w-80 rounded-full bg-auric-400/15 blur-3xl" />
        <div className="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-sky-400/10 blur-3xl" />
      </div>

      <div className="relative flex min-h-screen">
        <aside
          className={`fixed inset-y-0 left-0 z-30 flex w-[290px] flex-col border-r border-white/8 bg-[rgba(8,7,18,0.92)] p-4 backdrop-blur-xl transition-transform lg:translate-x-0 ${
            mobileOpen ? "translate-x-0" : "-translate-x-full"
          } ${sidebarCollapsed ? "lg:w-[96px]" : "lg:w-[290px]"}`}
        >
          <div className="flex items-center justify-between gap-3 px-2 pb-6 pt-2">
            {sidebarCollapsed ? (
              <div className="grid h-11 w-11 place-items-center rounded-2xl bg-gradient-to-br from-auric-300 to-violet-400 font-['Sora'] text-sm font-semibold text-cosmic-950">
                EXA
              </div>
            ) : (
              <div>
                <p className="text-xs uppercase tracking-[0.32em] text-auric-300/75">ExaEarn</p>
                <h1 className="mt-2 font-['Sora'] text-xl font-semibold text-white">Admin Command</h1>
              </div>
            )}
            <button type="button" onClick={() => setSidebarCollapsed((value) => !value)} className="hidden rounded-xl border border-white/10 p-2 text-violet-100/70 lg:block">
              <ChevronLeft className={`h-4 w-4 transition ${sidebarCollapsed ? "rotate-180" : ""}`} />
            </button>
          </div>

          <div className="space-y-2 overflow-y-auto pr-1">
            {visibleMenu.map((item) => (
              <div key={item.key} onClick={() => setMobileOpen(false)}>
                <SidebarItem item={item} collapsed={sidebarCollapsed} />
              </div>
            ))}
          </div>

          <GlassPanel className="mt-4">
            <p className="text-xs uppercase tracking-[0.22em] text-violet-100/40">Role Preview</p>
            <select
              value={role}
              onChange={(event) => setRole(event.target.value)}
              className="mt-3 w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none"
            >
              {Object.entries(adminTheme.roles).map(([key, label]) => (
                <option key={key} value={key} className="bg-cosmic-900">
                  {label}
                </option>
              ))}
            </select>
            <p className="mt-3 text-xs text-violet-100/50">
              Route: {location.pathname} {loading ? "syncing..." : ""}
            </p>
            {!permission.can("treasury.manage") ? <p className="mt-2 text-xs text-violet-100/50">Treasury hidden for this role.</p> : null}
          </GlassPanel>
        </aside>

        <div className={sidebarCollapsed ? "flex-1 lg:pl-[96px]" : "flex-1 lg:pl-[290px]"}>
          <header className="sticky top-0 z-20 border-b border-white/8 bg-[rgba(9,8,20,0.72)] px-4 py-4 backdrop-blur-xl sm:px-6 lg:px-8">
            <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
              <div className="flex items-center gap-3">
                <button type="button" onClick={() => setMobileOpen(true)} className="rounded-xl border border-white/10 p-2 text-violet-100/70 lg:hidden">
                  <Menu className="h-4 w-4" />
                </button>
                <div className="relative w-full max-w-xl">
                  <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-violet-100/35" />
                  <input
                    type="text"
                    placeholder="Search users, wallets, withdrawals, campaigns..."
                    className="w-full rounded-2xl border border-white/10 bg-white/5 py-3 pl-11 pr-4 text-sm text-white outline-none placeholder:text-violet-100/30"
                  />
                </div>
              </div>

              <div className="flex flex-wrap items-center gap-3">
                <Pill tone="success">Sanctum Ready</Pill>
                <OutlineButton onClick={() => setTheme(theme === "dark" ? "light" : "dark")}>
                  {theme === "dark" ? <SunMedium className="h-4 w-4" /> : <MoonStar className="h-4 w-4" />}
                </OutlineButton>
                <button type="button" className="relative rounded-2xl border border-white/10 bg-white/5 p-3 text-violet-100/75">
                  <Bell className="h-4 w-4" />
                  <span className="absolute -right-1 -top-1 grid h-5 w-5 place-items-center rounded-full bg-auric-400 text-[10px] font-semibold text-cosmic-950">
                    {notifications.length}
                  </span>
                </button>
                <div className="rounded-[22px] border border-white/10 bg-white/5 px-4 py-2">
                  <p className="text-sm font-medium text-white">{admin?.name ?? "Admin"}</p>
                  <p className="text-xs text-violet-100/50">{admin?.email ?? "loading@exaearn.com"}</p>
                </div>
                <button
                  type="button"
                  onClick={handleLogout}
                  className="rounded-2xl border border-red-500/30 bg-red-500/10 p-3 text-red-400 hover:bg-red-500/20 transition"
                  title="Logout"
                >
                  <LogOut className="h-4 w-4" />
                </button>
              </div>
            </div>
          </header>

          <main className="px-4 py-6 sm:px-6 lg:px-8">
            <Outlet />
          </main>
        </div>
      </div>
    </div>
  );
}
