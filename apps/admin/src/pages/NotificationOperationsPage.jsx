import { useEffect, useState } from "react";
import { AlertTriangle, Eye, RefreshCw, Send, ShieldCheck } from "lucide-react";
import { GlassPanel, PageShell, Pill } from "../components/AdminPrimitives";
import { adminHttp } from "../services/http";

const tabs = ["Overview", "Deliveries", "Templates", "Events", "Providers", "Failed/DLQ", "Broadcasts"];

export function NotificationOperationsPage() {
  const [tab, setTab] = useState("Overview");
  const [overview, setOverview] = useState(null);
  const [rows, setRows] = useState([]);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [preview, setPreview] = useState(null);

  const load = async () => {
    setLoading(true);
    setError("");
    try {
      if (tab === "Overview") {
        const { data } = await adminHttp.get("/notifications/operations/overview");
        setOverview(data.data);
        setRows([]);
      } else if (tab === "Deliveries") {
        const { data } = await adminHttp.get("/notifications/operations/deliveries");
        setRows(data.data?.data || []);
      } else if (tab === "Templates") {
        const { data } = await adminHttp.get("/notifications/operations/templates");
        setRows(data.data?.data || []);
      } else if (tab === "Events") {
        const { data } = await adminHttp.get("/notifications/operations/events");
        setRows(data.data?.data || []);
      } else if (tab === "Providers") {
        const { data } = await adminHttp.get("/notifications/operations/providers");
        setRows(data.data || []);
      } else if (tab === "Failed/DLQ") {
        const { data } = await adminHttp.get("/notifications/operations/dlq");
        setRows(data.data?.data || []);
      } else {
        setRows([]);
      }
    } catch (exception) {
      setError(exception.response?.data?.message || "Notification operations are unavailable.");
      setRows([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, [tab]);

  const previewTemplate = async (eventKey) => {
    const { data } = await adminHttp.post("/notifications/operations/templates/preview", {
      event_key: eventKey,
      channel: "in_app",
      locale: "en",
      variables: sampleVariables(eventKey),
    });
    setPreview(data.data);
  };

  const retryDelivery = async (row) => {
    await adminHttp.post(`/notifications/operations/deliveries/${row.id}/retry`, { reason: "Operator controlled notification delivery retry." });
    await load();
  };

  return (
    <PageShell eyebrow="Communications" title="Notification Operations" description="Operate user notifications, templates, provider health, delivery logs and safe broadcast workflows.">
      {error ? <div className="mb-4 rounded-xl border border-red-400/20 bg-red-400/10 p-3 text-sm text-red-100">{error}</div> : null}
      <div className="mb-5 flex gap-2 overflow-x-auto">
        {tabs.map((item) => <button key={item} type="button" onClick={() => setTab(item)} className={`shrink-0 rounded-xl border px-3 py-2 text-xs font-semibold ${tab === item ? "border-auric-300/50 bg-auric-300/15 text-auric-100" : "border-white/10 bg-white/5 text-violet-100/65"}`}>{item}</button>)}
      </div>

      {tab === "Overview" ? <Overview overview={overview} loading={loading} /> : null}
      {tab === "Broadcasts" ? <BroadcastSafety /> : null}
      {tab !== "Overview" && tab !== "Broadcasts" ? <Rows tab={tab} rows={rows} loading={loading} onPreview={previewTemplate} onRetry={retryDelivery} /> : null}

      {preview ? (
        <div className="fixed inset-0 z-50 grid place-items-center bg-cosmic-950/70 p-4">
          <GlassPanel className="w-full max-w-lg">
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-xs uppercase tracking-[0.22em] text-auric-200">Template preview</p>
                <h2 className="mt-2 text-xl font-semibold text-white">{preview.title}</h2>
              </div>
              <button type="button" onClick={() => setPreview(null)} className="text-violet-100/70">Close</button>
            </div>
            <p className="mt-4 text-sm leading-6 text-violet-100/70">{preview.body}</p>
            <p className="mt-4 text-xs text-violet-100/45">{preview.event_key} - {preview.channel} - {preview.locale} - v{preview.template_version}</p>
          </GlassPanel>
        </div>
      ) : null}
    </PageShell>
  );
}

function Overview({ overview, loading }) {
  if (loading || !overview) return <GlassPanel><p className="text-sm text-violet-100/60">Loading operations...</p></GlassPanel>;
  return (
    <div className="grid gap-4 lg:grid-cols-4">
      <Metric label="Active notifications" value={overview.notifications?.active} />
      <Metric label="Failed deliveries" value={overview.deliveries?.failed} tone={overview.deliveries?.failed ? "warning" : "success"} />
      <Metric label="Templates" value={overview.templates} />
      <Metric label="Registered events" value={overview.events} />
      <GlassPanel className="lg:col-span-4"><h2 className="font-semibold text-white">Provider health</h2><div className="mt-3 grid gap-2 md:grid-cols-3">{(overview.providers || []).map((row) => <Provider key={row.provider} row={row} />)}</div></GlassPanel>
    </div>
  );
}

function Rows({ tab, rows, loading, onPreview, onRetry }) {
  if (loading) return <GlassPanel><p className="text-sm text-violet-100/60">Loading {tab.toLowerCase()}...</p></GlassPanel>;
  return <GlassPanel><div className="grid gap-3">{rows.length ? rows.map((row) => <Record key={row.id || row.event_key || row.provider} tab={tab} row={row} onPreview={onPreview} onRetry={onRetry} />) : <p className="text-sm text-violet-100/60">No records found.</p>}</div></GlassPanel>;
}

function Record({ tab, row, onPreview, onRetry }) {
  const title = row.event_key || row.template_key || row.provider || row.event || row.title || `Record ${row.id}`;
  return (
    <article className="rounded-xl border border-white/10 bg-white/[.035] p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex flex-wrap gap-2"><Pill>{row.product || row.channel || tab}</Pill>{row.status ? <Pill tone={["HEALTHY", "ACTIVE", "DELIVERED", "SENT"].includes(row.status) ? "success" : "warning"}>{row.status}</Pill> : null}</div>
          <h3 className="mt-2 text-sm font-semibold text-white">{title}</h3>
          <p className="mt-1 text-xs text-violet-100/55">{row.safe_error || row.body || row.title || row.category || row.recipient || "Operational notification record."}</p>
        </div>
        <div className="flex gap-2">
          {(tab === "Templates" || tab === "Events") && (row.event_key || row.template_key) ? <button type="button" onClick={() => onPreview(row.event_key || row.template_key)} className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-white/10 px-3 text-xs text-violet-50"><Eye className="h-4 w-4" />Preview</button> : null}
          {tab === "Failed/DLQ" ? <button type="button" onClick={() => onRetry(row)} className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-auric-300/35 bg-auric-300/10 px-3 text-xs text-auric-100"><RefreshCw className="h-4 w-4" />Retry</button> : null}
        </div>
      </div>
    </article>
  );
}

function Provider({ row }) {
  return <div className="rounded-xl border border-white/10 bg-white/[.035] p-3"><div className="flex items-center justify-between"><strong className="text-sm text-white">{row.provider}</strong><Pill tone={row.status === "HEALTHY" ? "success" : "warning"}>{row.metadata?.configuration || row.status}</Pill></div><p className="mt-2 text-xs text-violet-100/55">{row.channel} - success {row.success_count || 0} - failure {row.failure_count || 0}</p></div>;
}

function Metric({ label, value, tone = "neutral" }) {
  return <GlassPanel><p className="text-xs uppercase tracking-[0.2em] text-violet-100/45">{label}</p><strong className={`mt-2 block text-2xl ${tone === "warning" ? "text-amber-100" : tone === "success" ? "text-emerald-100" : "text-white"}`}>{Number(value || 0).toLocaleString()}</strong></GlassPanel>;
}

function BroadcastSafety() {
  return <GlassPanel><div className="flex gap-3"><ShieldCheck className="h-5 w-5 text-emerald-200" /><div><h2 className="font-semibold text-white">Broadcast safety</h2><p className="mt-2 text-sm leading-6 text-violet-100/65">Manual broadcast must use draft, audience, channel, preview, approval and audited send workflow. Marketing consent is mandatory for marketing broadcasts, and operators cannot impersonate financial or security system events.</p><div className="mt-4 rounded-xl border border-amber-300/20 bg-amber-300/10 p-3 text-sm text-amber-100"><AlertTriangle className="mr-2 inline h-4 w-4" />Large campaigns and critical templates require maker-checker before production activation.</div><button type="button" disabled className="mt-4 inline-flex min-h-10 items-center gap-2 rounded-lg border border-white/10 px-3 text-xs text-violet-100/50"><Send className="h-4 w-4" />Broadcast workflow pending approval setup</button></div></div></GlassPanel>;
}

function sampleVariables(eventKey) {
  if (eventKey.includes("funding.completed")) return { amount: "50.00", currency: "USD" };
  if (eventKey.includes("deposit")) return { asset: "USDT", amount: "25.00" };
  if (eventKey.includes("convert")) return { from_asset: "USDT", to_asset: "BTC", amount: "100.00" };
  if (eventKey.includes("exaai.subscription")) return { plan: "Pro", ends_at: "2026-12-31" };
  return {};
}
