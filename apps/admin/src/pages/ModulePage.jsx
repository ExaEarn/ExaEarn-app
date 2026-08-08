import { useEffect, useMemo, useState } from "react";
import { AlertTriangle, CheckCircle2, Download, Eye, Search, ShieldAlert, UserRound, X } from "lucide-react";
import { useForm } from "react-hook-form";
import { DataTable, GlassPanel, GradientButton, OutlineButton, PageShell, Pill } from "../components/AdminPrimitives";
import { fetchModuleData, runModuleAction } from "../services/adminApi";
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

const primaryActions = {
  users: "freeze account",
  wallets: "queue sweep",
  transactions: "flag suspicious",
  trading: "pause pair",
  p2p: "open dispute",
  staking: "pause pool",
  rewards: "run simulation",
  nft: "approve collection",
  agritech: "verify report",
  sports: "verify athlete",
  edtech: "approve course",
  crowdfunding: "approve campaign",
  lottery: "verify winners",
  giftcard: "approve order",
  campaigns: "schedule broadcast",
  kyc: "approve KYC",
  treasury: "approve withdrawal",
  notifications: "send test",
  logs: "mark reviewed",
  security: "escalate alert",
  admins: "reset access",
  roles: "assign permissions",
  permissions: "audit permission",
  settings: "stage change",
  "system-monitor": "run health check",
};

function formatCellValue(value) {
  if (value === null || value === undefined || value === "") return "—";
  if (typeof value === "object") return JSON.stringify(value);
  return value;
}

function downloadCsv(filename, rows) {
  const keys = Array.from(rows.reduce((set, row) => {
    Object.keys(row).forEach((key) => set.add(key));
    return set;
  }, new Set()));
  const escape = (value) => `"${String(formatCellValue(value)).replace(/"/g, '""')}"`;
  const csv = [keys.join(","), ...rows.map((row) => keys.map((key) => escape(row[key])).join(","))].join("\n");
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
}

function buildColumns(rows, moduleKey, { onView, onAction }) {
  const actionName = primaryActions[moduleKey] ?? "mark reviewed";
  const actionColumn = {
    id: "operator_actions",
    header: "Actions",
    cell: ({ row }) => (
      <div className="flex min-w-[180px] flex-wrap gap-2">
        <button
          type="button"
          onClick={() => onView(row.original)}
          className="inline-flex items-center gap-1 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-violet-50 hover:border-auric-300/40"
        >
          <Eye className="h-3.5 w-3.5" />
          View
        </button>
        <button
          type="button"
          onClick={() => onAction(actionName, row.original)}
          className="inline-flex items-center gap-1 rounded-xl border border-auric-300/35 bg-auric-300/10 px-3 py-2 text-xs font-semibold text-auric-100 hover:bg-auric-300/15"
        >
          <ShieldAlert className="h-3.5 w-3.5" />
          {actionName}
        </button>
      </div>
    ),
  };

  if (moduleKey === "users") {
    return [
      { header: "ID", accessorKey: "id" },
      { header: "Email", accessorKey: "email" },
      { header: "Username", accessorKey: "username" },
      { header: "Profile", accessorKey: "profile_display_type", cell: ({ row }) => <ProfileCell user={row.original} /> },
      { header: "Balance", accessorKey: "balance" },
      { header: "Status", accessorKey: "status", cell: ({ getValue }) => renderStatus(getValue()) },
      { header: "KYC", accessorKey: "kyc" },
      { header: "Created", accessorKey: "created_at" },
      actionColumn,
    ];
  }

  return [
    ...Object.keys(rows[0] ?? {}).map((key) => ({
      header: key.replace(/_/g, " "),
      accessorKey: key,
      cell: ({ getValue }) => renderStatus(getValue()),
    })),
    actionColumn,
  ];
}

function ProfileCell({ user }) {
  const status = user.profile_image_status || "none";
  const display = user.profile_display_type || (user.avatar_id ? "avatar" : "initials");

  return (
    <div className="flex min-w-[150px] items-center gap-2">
      <span className="grid h-8 w-8 place-items-center rounded-full border border-auric-300/30 bg-auric-300/10 text-auric-100">
        <UserRound className="h-4 w-4" />
      </span>
      <span className="min-w-0">
        <span className="block text-xs font-semibold capitalize text-white">{display.replace(/_/g, " ")}</span>
        <span className="block truncate text-[11px] text-violet-100/55">{status.replace(/_/g, " ")}</span>
      </span>
    </div>
  );
}

function DetailDrawer({ moduleKey, row, onClose, onAction }) {
  if (!row) return null;

  return (
    <div className="fixed inset-0 z-50 flex justify-end bg-cosmic-950/70 backdrop-blur-sm">
      <aside className="h-full w-full max-w-xl overflow-y-auto border-l border-white/10 bg-[rgba(9,8,20,0.96)] p-5 shadow-2xl">
        <div className="flex items-start justify-between gap-4">
          <div>
            <p className="text-xs uppercase tracking-[0.28em] text-auric-300/70">Record detail</p>
            <h2 className="mt-2 font-['Sora'] text-2xl font-semibold text-white">{titles[moduleKey] ?? "Module"} Review</h2>
            <p className="mt-2 text-sm text-violet-100/60">Inspect this record before taking an audited operator action.</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl border border-white/10 p-2 text-violet-100/70 hover:bg-white/5">
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="mt-6 grid gap-3">
          {Object.entries(row).map(([key, value]) => (
            <div key={key} className="rounded-2xl border border-white/8 bg-white/[0.035] p-4">
              <p className="text-xs uppercase tracking-[0.22em] text-violet-100/40">{key.replace(/_/g, " ")}</p>
              <div className="mt-2 text-sm font-medium text-violet-50">{renderStatus(formatCellValue(value))}</div>
            </div>
          ))}
        </div>

        <div className="mt-6 rounded-2xl border border-amber-300/20 bg-amber-300/10 p-4 text-sm text-amber-100">
          <div className="flex items-center gap-2 font-semibold">
            <AlertTriangle className="h-4 w-4" />
            Operator guard rail
          </div>
          <p className="mt-2 text-amber-100/75">
            Any production action from this panel should be routed through an audited admin endpoint with permission checks.
          </p>
        </div>

        <div className="mt-6 flex flex-wrap gap-3">
          <GradientButton onClick={() => onAction(primaryActions[moduleKey] ?? "mark reviewed", row)}>
            Run primary action
          </GradientButton>
          <OutlineButton onClick={onClose}>Close</OutlineButton>
        </div>
      </aside>
    </div>
  );
}

function ActionModal({ action, row, loading, result, onCancel, onConfirm, note, setNote }) {
  if (!action || !row) return null;

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-cosmic-950/70 p-4 backdrop-blur-sm">
      <div className="w-full max-w-lg rounded-[28px] border border-white/10 bg-[rgba(12,9,25,0.97)] p-6 shadow-2xl">
        <div className="flex items-start justify-between gap-4">
          <div>
            <p className="text-xs uppercase tracking-[0.28em] text-auric-300/70">Confirm action</p>
            <h2 className="mt-2 font-['Sora'] text-2xl font-semibold text-white">{action}</h2>
          </div>
          <button type="button" onClick={onCancel} className="rounded-xl border border-white/10 p-2 text-violet-100/70 hover:bg-white/5">
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="mt-5 rounded-2xl border border-white/8 bg-white/[0.035] p-4">
          <p className="text-xs uppercase tracking-[0.22em] text-violet-100/40">Target record</p>
          <p className="mt-2 text-sm text-violet-50">{Object.entries(row).slice(0, 4).map(([key, value]) => `${key}: ${formatCellValue(value)}`).join(" | ")}</p>
        </div>

        <label className="mt-5 block">
          <span className="text-sm font-medium text-violet-100/75">Operator note</span>
          <textarea
            value={note}
            onChange={(event) => setNote(event.target.value)}
            rows={4}
            placeholder="Add reason, ticket ID, or risk context..."
            className="mt-2 w-full rounded-2xl border border-white/10 bg-white/5 p-3 text-sm text-white outline-none placeholder:text-violet-100/30"
          />
        </label>

        {result ? (
          <div className="mt-5 rounded-2xl border border-emerald-400/20 bg-emerald-400/10 p-4 text-sm text-emerald-100">
            <div className="flex items-center gap-2 font-semibold">
              <CheckCircle2 className="h-4 w-4" />
              {result.status}
            </div>
            <p className="mt-2 text-emerald-100/75">{result.message}</p>
            {result.audit_id ? <p className="mt-2 text-xs text-emerald-100/55">Audit ID: {result.audit_id}</p> : null}
          </div>
        ) : null}

        <div className="mt-6 flex flex-wrap justify-end gap-3">
          <OutlineButton onClick={onCancel}>Cancel</OutlineButton>
          <GradientButton onClick={onConfirm} disabled={loading}>
            {loading ? "Processing..." : "Confirm action"}
          </GradientButton>
        </div>
      </div>
    </div>
  );
}

export function ModulePage({ moduleKey, pathKey }) {
  const [data, setData] = useState({ rows: [] });
  const [loading, setLoading] = useState(true);
  const [selectedRow, setSelectedRow] = useState(null);
  const [pendingAction, setPendingAction] = useState(null);
  const [actionRow, setActionRow] = useState(null);
  const [actionNote, setActionNote] = useState("");
  const [actionLoading, setActionLoading] = useState(false);
  const [actionResult, setActionResult] = useState(null);
  const [activityLog, setActivityLog] = useState([]);
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

  const openAction = (action, row = filteredRows[0] ?? {}) => {
    setPendingAction(action);
    setActionRow(row);
    setActionNote("");
    setActionResult(null);
  };

  const confirmAction = async () => {
    if (!pendingAction || !actionRow) return;
    setActionLoading(true);
    const result = await runModuleAction(pathKey, pendingAction, actionRow, actionNote);
    setActionResult(result);
    setActionLoading(false);
    setActivityLog((items) => [
      {
        id: Date.now(),
        action: pendingAction,
        status: result.status,
        target: Object.values(actionRow).slice(0, 2).join(" / "),
      },
      ...items,
    ].slice(0, 5));
  };

  const closeAction = () => {
    setPendingAction(null);
    setActionRow(null);
    setActionNote("");
    setActionResult(null);
  };

  const columns = useMemo(
    () => buildColumns(filteredRows, moduleKey, { onView: setSelectedRow, onAction: openAction }),
    [filteredRows, moduleKey],
  );

  const moduleActions = data.actions?.length ? data.actions : ["view", "mark reviewed"];

  return (
    <PageShell
      eyebrow="Independent module"
      title={titles[moduleKey] ?? "Module"}
      description={data.headline}
      actions={
        <>
          <OutlineButton onClick={() => downloadCsv(`${moduleKey}-export.csv`, filteredRows)}>
            <Download className="mr-2 inline h-4 w-4" />
            Export
          </OutlineButton>
          <GradientButton onClick={() => openAction(primaryActions[moduleKey] ?? moduleActions[0], filteredRows[0] ?? {})}>
            {moduleKey === "users" ? "Create action" : "Open controls"}
          </GradientButton>
        </>
      }
    >
      <div className="flex flex-wrap gap-3">
        <Pill tone={data.source === "api" ? "success" : "warning"}>
          {data.source === "api" ? "Live API data" : "Mock fallback data"}
        </Pill>
        <Pill>{permission.role} role</Pill>
        <Pill>{filteredRows.length} visible records</Pill>
      </div>

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
            {loading ? (
              <div className="grid gap-3">
                {Array.from({ length: 4 }, (_, index) => (
                  <div key={index} className="h-16 animate-pulse rounded-2xl bg-white/[0.04]" />
                ))}
              </div>
            ) : filteredRows.length ? (
              <DataTable columns={columns} rows={filteredRows} />
            ) : (
              <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-8 text-center">
                <p className="font-['Sora'] text-lg font-semibold text-white">No records found</p>
                <p className="mt-2 text-sm text-violet-100/60">Try clearing the search or status filter.</p>
              </div>
            )}
          </div>
        </GlassPanel>

        <div className="space-y-6">
          <GlassPanel>
            <h3 className="font-['Sora'] text-lg font-semibold text-white">Module actions</h3>
            <div className="mt-4 flex flex-wrap gap-2">
              {moduleActions.map((action) => (
                <OutlineButton key={action} className="capitalize" onClick={() => openAction(action, filteredRows[0] ?? {})}>
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

          <GlassPanel>
            <h3 className="font-['Sora'] text-lg font-semibold text-white">Recent operator actions</h3>
            <div className="mt-4 space-y-3">
              {activityLog.length ? activityLog.map((item) => (
                <div key={item.id} className="rounded-2xl border border-white/8 bg-white/[0.03] p-3">
                  <p className="text-sm font-semibold capitalize text-white">{item.action}</p>
                  <p className="mt-1 text-xs text-violet-100/50">{item.status} - {item.target || "module level"}</p>
                </div>
              )) : (
                <p className="text-sm text-violet-100/55">No actions in this session yet.</p>
              )}
            </div>
          </GlassPanel>
        </div>
      </div>
      <DetailDrawer moduleKey={moduleKey} row={selectedRow} onClose={() => setSelectedRow(null)} onAction={openAction} />
      <ActionModal
        action={pendingAction}
        row={actionRow}
        loading={actionLoading}
        result={actionResult}
        onCancel={closeAction}
        onConfirm={confirmAction}
        note={actionNote}
        setNote={setActionNote}
      />
    </PageShell>
  );
}
