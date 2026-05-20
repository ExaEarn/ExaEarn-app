import { useEffect, useMemo, useState } from "react";
import { Search, ShieldAlert } from "lucide-react";
import { useForm } from "react-hook-form";
import { DataTable, GlassPanel, GradientButton, OutlineButton, PageShell, Pill } from "../components/AdminPrimitives";
import { fetchModuleData } from "../services/adminApi";
import { usePermission } from "../hooks/usePermission";

const titles = {
  users: "Users Management",
  wallets: "Wallet Management",
  transactions: "Transaction Oversight",
  trading: "Trading Pair Management",
  p2p: "P2P Control Room",
  staking: "Staking Pools",
  rewards: "Reward Engine",
  nft: "NFT Operations",
  agritech: "AgriTech Projects",
  sports: "Sports Talent Pool",
  edtech: "EdTech Control",
  crowdfunding: "Crowdfunding Control",
  lottery: "Lottery Operations",
  giftcard: "Giftcard Desk",
  campaigns: "Campaigns",
  kyc: "KYC Review",
  treasury: "Treasury Control",
  notifications: "Notifications",
  logs: "Audit Logs",
  security: "Security Operations",
  admins: "Admins Management",
  roles: "Roles",
  permissions: "Permissions",
  settings: "Settings Engine",
  "system-monitor": "System Monitor",
};

function renderStatus(value) {
  if (typeof value !== "string") return value;
  if (["active", "approved", "healthy", "secured", "online", "enabled", "completed"].includes(value)) {
    return <Pill tone="success">{value}</Pill>;
  }
  if (["frozen", "review", "warning", "paused", "degraded", "pending", "disabled", "funding", "scheduled", "live"].includes(value)) {
    return <Pill tone="warning">{value}</Pill>;
  }
  return value;
}

function buildColumns(rows, moduleKey) {
  if (moduleKey === "users") {
    return [
      { header: "ID", accessorKey: "id" },
      { header: "Email", accessorKey: "email" },
      { header: "Username", accessorKey: "username" },
      { header: "Balance", accessorKey: "balance" },
      { header: "Status", accessorKey: "status", cell: ({ getValue }) => renderStatus(getValue()) },
      { header: "KYC", accessorKey: "kyc" },
      { header: "Created", accessorKey: "created_at" },
    ];
  }

  return Object.keys(rows[0] ?? {}).map((key) => ({
    header: key.replace(/_/g, " "),
    accessorKey: key,
    cell: ({ getValue }) => renderStatus(getValue()),
  }));
}

export function ModulePage({ moduleKey, pathKey }) {
  const [data, setData] = useState({ rows: [] });
  const [loading, setLoading] = useState(true);
  const permission = usePermission();
  const { register, watch } = useForm({
    defaultValues: {
      search: "",
      status: "all",
    },
  });

  useEffect(() => {
    let active = true;

    setLoading(true);
    fetchModuleData(pathKey).then((payload) => {
      if (!active) return;
      setData(payload);
      setLoading(false);
    });

    return () => {
      active = false;
    };
  }, [pathKey]);

  const search = watch("search");
  const status = watch("status");

  const filteredRows = useMemo(() => {
    return data.rows.filter((row) => {
      const values = Object.values(row).join(" ").toLowerCase();
      const matchesSearch = values.includes(search.toLowerCase());
      const matchesStatus = status === "all" || String(row.status ?? "").toLowerCase() === status.toLowerCase();
      return matchesSearch && matchesStatus;
    });
  }, [data.rows, search, status]);

  const columns = useMemo(() => buildColumns(filteredRows, moduleKey), [filteredRows, moduleKey]);

  return (
    <PageShell
      eyebrow="Independent module"
      title={titles[moduleKey] ?? "Module"}
      description={data.headline}
      actions={
        <>
          <OutlineButton>Export</OutlineButton>
          <GradientButton>{moduleKey === "users" ? "Create action" : "Open controls"}</GradientButton>
        </>
      }
    >
      <div className="grid gap-6 xl:grid-cols-[1fr_320px]">
        <GlassPanel>
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div className="relative w-full max-w-md">
              <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-violet-100/35" />
              <input
                type="text"
                placeholder={`Search ${titles[moduleKey] ?? "records"}`}
                className="w-full rounded-2xl border border-white/10 bg-white/5 py-3 pl-11 pr-4 text-sm text-white outline-none placeholder:text-violet-100/30"
                {...register("search")}
              />
            </div>
            <div className="flex flex-wrap gap-3">
              <select
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none"
                {...register("status")}
              >
                <option value="all" className="bg-cosmic-900">All statuses</option>
                <option value="active" className="bg-cosmic-900">Active</option>
                <option value="pending" className="bg-cosmic-900">Pending</option>
                <option value="review" className="bg-cosmic-900">Review</option>
                <option value="frozen" className="bg-cosmic-900">Frozen</option>
              </select>
              <Pill>{loading ? "syncing" : `${filteredRows.length} records`}</Pill>
              {moduleKey === "users" && !permission.can("wallet.adjust") ? <Pill tone="warning">Balance adjust hidden for this role</Pill> : null}
            </div>
          </div>
          <div className="mt-6">
            <DataTable columns={columns} rows={filteredRows} />
          </div>
        </GlassPanel>

        <div className="space-y-6">
          <GlassPanel>
            <h3 className="font-['Sora'] text-lg font-semibold text-white">Module actions</h3>
            <div className="mt-4 flex flex-wrap gap-2">
              {(data.actions ?? ["view", "create", "approve", "disable", "monitor"]).map((action) => (
                <OutlineButton key={action} className="capitalize">
                  {action}
                </OutlineButton>
              ))}
            </div>
          </GlassPanel>

          <GlassPanel>
            <div className="flex items-center gap-3">
              <ShieldAlert className="h-4 w-4 text-auric-300" />
              <h3 className="font-['Sora'] text-lg font-semibold text-white">Guard rails</h3>
            </div>
            <ul className="mt-4 space-y-3 text-sm text-violet-100/70">
              <li>All operator actions should flow through audited admin API endpoints.</li>
              <li>Balance changes must route through the transaction engine, never direct wallet mutations.</li>
              <li>Critical treasury and security operations should require elevated permissions and confirmation.</li>
            </ul>
          </GlassPanel>

          {data.stats ? (
            <GlassPanel>
              <h3 className="font-['Sora'] text-lg font-semibold text-white">Quick metrics</h3>
              <div className="mt-4 space-y-3">
                {data.stats.map((item) => (
                  <div key={item.label} className="flex items-center justify-between rounded-2xl border border-white/8 px-4 py-3">
                    <span className="text-sm text-violet-100/70">{item.label}</span>
                    <span className="font-semibold text-white">{item.value}</span>
                  </div>
                ))}
              </div>
            </GlassPanel>
          ) : null}
        </div>
      </div>
    </PageShell>
  );
}
