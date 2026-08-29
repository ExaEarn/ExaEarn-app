import { useEffect, useState } from "react";
import { AlertTriangle, Banknote, CheckCircle2, FileText, Landmark, MessageSquare, RefreshCcw, Settings2, ShieldCheck, WalletCards } from "lucide-react";
import { adminHttp } from "../services/http";

const tabs = ["Overview", "Campaigns", "Escrow", "Milestones", "Comments", "Documents", "Operations", "Refunds", "Reconciliation", "Policy"];

export function CrowdfundingOperationsPage() {
  const [active, setActive] = useState("Overview");
  const [overview, setOverview] = useState(null);
  const [campaigns, setCampaigns] = useState([]);
  const [records, setRecords] = useState(null);
  const [reconciliation, setReconciliation] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const load = async () => {
    setLoading(true);
    setError("");
    try {
      const [summary, campaignRows, recordRows, recon] = await Promise.all([
        adminHttp("/api/admin/crowdfunding/overview"),
        adminHttp("/api/admin/crowdfunding/campaigns"),
        adminHttp("/api/admin/crowdfunding/records"),
        adminHttp("/api/admin/crowdfunding/reconciliation"),
      ]);
      setOverview(summary?.data?.data ?? summary?.data ?? summary);
      setCampaigns(campaignRows?.data?.data?.data ?? campaignRows?.data?.data ?? []);
      setRecords(recordRows?.data?.data ?? recordRows?.data ?? recordRows);
      setReconciliation(recon?.data?.data ?? recon?.data ?? recon);
    } catch (err) {
      setError(err?.response?.data?.message || err?.message || "Crowdfunding operations could not load.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  return (
    <main className="min-h-screen bg-[#070b14] p-5 text-white">
      <div className="mx-auto max-w-7xl">
        <header className="flex flex-col gap-4 border-b border-white/10 pb-5 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[#d4af37]">Ecosystem Operations</p>
            <h1 className="mt-2 text-3xl font-semibold text-[#f8f1de]">Crowdfunding Center</h1>
            <p className="mt-2 max-w-3xl text-sm text-[#aab4c4]">
              Campaign review, pledge escrow, milestone release, refund and reconciliation controls backed by the canonical ledger.
            </p>
          </div>
          <button onClick={load} className="inline-flex items-center gap-2 rounded-xl border border-white/15 px-4 py-2 text-sm text-[#e6eaf2]">
            <RefreshCcw className="h-4 w-4" /> Refresh
          </button>
        </header>

        {error ? <div className="mt-4 rounded-xl border border-red-400/30 bg-red-500/10 p-3 text-sm text-red-100">{error}</div> : null}
        {loading ? <p className="mt-4 text-sm text-[#aab4c4]">Loading crowdfunding operations...</p> : null}

        <nav className="mt-5 flex gap-2 overflow-x-auto">
          {tabs.map((tab) => (
            <button key={tab} onClick={() => setActive(tab)} className={`rounded-full px-4 py-2 text-sm ${active === tab ? "bg-[#d4af37] text-[#111827]" : "border border-white/10 text-[#c8d0dd]"}`}>
              {tab}
            </button>
          ))}
        </nav>

        {active === "Overview" ? <Overview data={overview} reconciliation={reconciliation} /> : null}
        {active === "Campaigns" ? <CampaignTable rows={campaigns} onReload={load} /> : null}
        {active === "Escrow" ? <RecordList title="Pledge escrow" rows={records?.pledges || []} fields={["id", "campaign_id", "backer_id", "amount", "asset", "status"]} /> : null}
        {active === "Milestones" ? <RecordList title="Milestones and payouts" rows={records?.payouts || []} fields={["id", "campaign_id", "milestone_id", "amount", "asset", "status"]} /> : null}
        {active === "Comments" ? <Comments rows={records?.comments || []} onReload={load} /> : null}
        {active === "Documents" ? <Documents rows={records?.documents || []} onReload={load} /> : null}
        {active === "Operations" ? <Operations data={overview?.operations} records={records} onReload={load} /> : null}
        {active === "Refunds" ? <RecordList title="Refund batches" rows={records?.refund_batches || []} fields={["id", "campaign_id", "reason", "processed_items", "status"]} /> : null}
        {active === "Reconciliation" ? <Reconciliation data={reconciliation} incidents={records?.incidents || []} /> : null}
        {active === "Policy" ? <Policy /> : null}
      </div>
    </main>
  );
}

function Comments({ rows, onReload }) {
  const moderate = async (row, status) => {
    await adminHttp(`/api/admin/crowdfunding/comments/${row.id}/moderate`, {
      method: "POST",
      data: { status, reason: `Admin ${status.toLowerCase()} from crowdfunding center` },
    });
    await onReload();
  };

  return (
    <section className="mt-5 rounded-2xl border border-white/10 bg-[#101827] p-4">
      <div className="flex items-center gap-2">
        <MessageSquare className="h-5 w-5 text-[#d4af37]" />
        <h2 className="text-lg font-semibold text-[#f8f1de]">Comments and questions</h2>
      </div>
      <div className="mt-3 grid gap-3">
        {rows.map((row) => (
          <article key={row.id} className="rounded-xl border border-white/10 bg-white/[0.03] p-3 text-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="font-semibold text-[#f8f1de]">{row.type || "COMMENT"} · {row.status}</p>
                <p className="mt-1 text-[#aab4c4]">{row.body}</p>
                <p className="mt-2 text-xs text-[#8792a5]">Campaign {row.campaign_id} · User {row.user?.email || row.user_id}</p>
              </div>
              <div className="flex gap-2">
                {["ACTIVE", "HIDDEN", "REMOVED"].map((status) => (
                  <button key={status} onClick={() => moderate(row, status)} className="rounded-lg border border-white/10 px-2 py-1 text-xs text-[#d7ddea]">{status}</button>
                ))}
              </div>
            </div>
          </article>
        ))}
        {!rows.length ? <p className="py-6 text-center text-sm text-[#8792a5]">No comments or reports yet.</p> : null}
      </div>
    </section>
  );
}

function Documents({ rows, onReload }) {
  const review = async (row, status) => {
    await adminHttp(`/api/admin/crowdfunding/documents/${row.id}/review`, {
      method: "POST",
      data: { status, reason: `Admin ${status.toLowerCase()} from crowdfunding center` },
    });
    await onReload();
  };

  return (
    <section className="mt-5 rounded-2xl border border-white/10 bg-[#101827] p-4">
      <div className="flex items-center gap-2">
        <FileText className="h-5 w-5 text-[#d4af37]" />
        <h2 className="text-lg font-semibold text-[#f8f1de]">Documents and media</h2>
      </div>
      <div className="mt-3 grid gap-3">
        {rows.map((row) => (
          <article key={row.id} className="grid gap-3 rounded-xl border border-white/10 bg-white/[0.03] p-3 text-sm lg:grid-cols-[1fr_auto]">
            <div className="grid gap-2 md:grid-cols-3">
              <p className="text-[#aab4c4]"><span className="text-[#8792a5]">file: </span>{row.safe_filename}</p>
              <p className="text-[#aab4c4]"><span className="text-[#8792a5]">type: </span>{row.document_type}</p>
              <p className="text-[#aab4c4]"><span className="text-[#8792a5]">status: </span>{row.status}</p>
              <p className="text-[#aab4c4]"><span className="text-[#8792a5]">visibility: </span>{row.visibility}</p>
              <p className="text-[#aab4c4]"><span className="text-[#8792a5]">campaign: </span>{row.campaign_id}</p>
              <p className="text-[#aab4c4]"><span className="text-[#8792a5]">owner: </span>{row.owner?.email || row.owner_id}</p>
            </div>
            <div className="flex flex-wrap gap-2">
              {["APPROVED", "REJECTED", "REPLACEMENT_REQUIRED"].map((status) => (
                <button key={status} onClick={() => review(row, status)} className="rounded-lg border border-white/10 px-2 py-1 text-xs text-[#d7ddea]">{status}</button>
              ))}
            </div>
          </article>
        ))}
        {!rows.length ? <p className="py-6 text-center text-sm text-[#8792a5]">No campaign documents yet.</p> : null}
      </div>
    </section>
  );
}

function Operations({ data, records, onReload }) {
  const [assignment, setAssignment] = useState({ entity_type: "CAMPAIGN", entity_id: "", assignee_admin_id: "", reason: "" });
  const settings = data?.settings || {};
  const toggle = async (key, enabled) => {
    await adminHttp("/api/admin/crowdfunding/operations", { method: "PUT", data: { key, value: { enabled: !enabled } } });
    await onReload();
  };
  const assign = async (event) => {
    event.preventDefault();
    await adminHttp("/api/admin/crowdfunding/assignments", { method: "POST", data: assignment });
    setAssignment({ entity_type: "CAMPAIGN", entity_id: "", assignee_admin_id: "", reason: "" });
    await onReload();
  };

  return (
    <section className="mt-5 grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
      <article className="rounded-2xl border border-white/10 bg-[#101827] p-4">
        <div className="flex items-center gap-2">
          <Settings2 className="h-5 w-5 text-[#d4af37]" />
          <h2 className="text-lg font-semibold text-[#f8f1de]">Operations feature flags</h2>
        </div>
        <div className="mt-3 grid gap-3 md:grid-cols-2">
          {Object.entries(settings).map(([key, value]) => {
            const enabled = Boolean(value?.enabled);
            return (
              <button key={key} onClick={() => toggle(key, enabled)} className="rounded-xl border border-white/10 bg-white/[0.03] p-3 text-left text-sm">
                <span className="block font-semibold text-[#f8f1de]">{key.replaceAll("_", " ")}</span>
                <span className={enabled ? "text-emerald-300" : "text-amber-200"}>{enabled ? "Enabled" : "Disabled"}</span>
              </button>
            );
          })}
        </div>
      </article>
      <article className="rounded-2xl border border-white/10 bg-[#101827] p-4">
        <h2 className="text-lg font-semibold text-[#f8f1de]">Review assignment</h2>
        <p className="mt-1 text-sm text-[#aab4c4]">Assign campaign, document or milestone review without changing financial state.</p>
        <form onSubmit={assign} className="mt-3 grid gap-2">
          <select value={assignment.entity_type} onChange={(event) => setAssignment((current) => ({ ...current, entity_type: event.target.value }))} className="rounded-xl border border-white/10 bg-[#070b14] px-3 py-2 text-sm text-[#e6eaf2]">
            <option>CAMPAIGN</option>
            <option>DOCUMENT</option>
            <option>MILESTONE</option>
          </select>
          <input value={assignment.entity_id} onChange={(event) => setAssignment((current) => ({ ...current, entity_id: event.target.value }))} placeholder="Entity ID" className="rounded-xl border border-white/10 bg-[#070b14] px-3 py-2 text-sm text-[#e6eaf2]" />
          <input value={assignment.assignee_admin_id} onChange={(event) => setAssignment((current) => ({ ...current, assignee_admin_id: event.target.value }))} placeholder="Assignee admin ID" className="rounded-xl border border-white/10 bg-[#070b14] px-3 py-2 text-sm text-[#e6eaf2]" />
          <input value={assignment.reason} onChange={(event) => setAssignment((current) => ({ ...current, reason: event.target.value }))} placeholder="Assignment reason" className="rounded-xl border border-white/10 bg-[#070b14] px-3 py-2 text-sm text-[#e6eaf2]" />
          <button className="rounded-xl bg-[#d4af37] px-3 py-2 text-sm font-semibold text-[#111827]">Assign review</button>
        </form>
        <p className="mt-3 text-xs text-[#8792a5]">Backlog: {data?.review_backlog ?? 0} campaigns · {data?.milestones_awaiting_review ?? 0} milestones · {(records?.documents || []).filter((row) => row.status === "PENDING_REVIEW").length} documents.</p>
      </article>
    </section>
  );
}

function Overview({ data, reconciliation }) {
  const cards = [
    ["Campaigns", Object.values(data?.campaigns || {}).reduce((sum, value) => sum + Number(value || 0), 0), Landmark],
    ["Pledges", data?.pledges ?? 0, WalletCards],
    ["Escrow Assets", Object.keys(data?.escrowed || {}).length, Banknote],
    ["Reconciliation", reconciliation?.status || "UNKNOWN", reconciliation?.status === "PASS" ? CheckCircle2 : AlertTriangle],
  ];
  return (
    <section className="mt-5 grid gap-3 md:grid-cols-4">
      {cards.map(([label, value, Icon]) => (
        <article key={label} className="rounded-2xl border border-white/10 bg-[#101827] p-4">
          <Icon className="h-5 w-5 text-[#d4af37]" />
          <p className="mt-3 text-xs uppercase text-[#8792a5]">{label}</p>
          <p className="mt-1 text-2xl font-semibold text-[#f8f1de]">{value}</p>
        </article>
      ))}
      <article className="rounded-2xl border border-white/10 bg-[#101827] p-4 md:col-span-4">
        <p className="font-semibold text-[#f8f1de]">Source-of-truth policy</p>
        <p className="mt-1 text-sm text-[#aab4c4]">
          Pledges are reserved and moved into crowdfunding escrow through the canonical ledger. Milestone payouts and refunds are ledger-backed and reviewable.
        </p>
      </article>
    </section>
  );
}

function CampaignTable({ rows, onReload }) {
  const review = async (campaign, action) => {
    await adminHttp(`/api/admin/crowdfunding/campaigns/${campaign.id}/review`, {
      method: "POST",
      data: { action, reason: `Admin ${action.toLowerCase()} from crowdfunding center` },
    });
    await onReload();
  };

  return (
    <section className="mt-5 overflow-hidden rounded-2xl border border-white/10 bg-[#101827]">
      <table className="w-full min-w-[900px] text-left text-sm">
        <thead className="bg-white/5 text-xs uppercase text-[#8792a5]">
          <tr><th className="p-3">Campaign</th><th>Creator</th><th>Class</th><th>Raised</th><th>Goal</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.id} className="border-t border-white/10">
              <td className="p-3 font-semibold text-[#f8f1de]">{row.title}</td>
              <td className="p-3 text-[#c8d0dd]">{row.creator?.user?.email || row.creator_id}</td>
              <td className="p-3 text-[#c8d0dd]">{row.classification}</td>
              <td className="p-3 text-[#c8d0dd]">{row.raised_amount} {row.asset}</td>
              <td className="p-3 text-[#c8d0dd]">{row.funding_goal} {row.asset}</td>
              <td className="p-3 text-[#d4af37]">{row.status}</td>
              <td className="p-3">
                <div className="flex gap-2">
                  {["APPROVE", "LIVE", "SUSPEND"].map((action) => (
                    <button key={action} onClick={() => review(row, action)} className="rounded-lg border border-white/10 px-2 py-1 text-xs text-[#d7ddea]">{action}</button>
                  ))}
                </div>
              </td>
            </tr>
          ))}
          {!rows.length ? <tr><td colSpan="7" className="p-5 text-center text-[#8792a5]">No crowdfunding campaigns yet.</td></tr> : null}
        </tbody>
      </table>
    </section>
  );
}

function RecordList({ title, rows, fields }) {
  return (
    <section className="mt-5 rounded-2xl border border-white/10 bg-[#101827] p-4">
      <h2 className="text-lg font-semibold text-[#f8f1de]">{title}</h2>
      <div className="mt-3 grid gap-3">
        {rows.map((row) => (
          <article key={`${title}-${row.id}`} className="grid gap-2 rounded-xl border border-white/10 bg-white/[0.03] p-3 text-sm md:grid-cols-3">
            {fields.map((field) => <p key={field} className="text-[#aab4c4]"><span className="text-[#8792a5]">{field}: </span>{String(row[field] ?? "-")}</p>)}
          </article>
        ))}
        {!rows.length ? <p className="py-6 text-center text-sm text-[#8792a5]">No records yet.</p> : null}
      </div>
    </section>
  );
}

function Reconciliation({ data, incidents }) {
  return (
    <section className="mt-5 grid gap-4 lg:grid-cols-[1fr_1fr]">
      <article className="rounded-2xl border border-white/10 bg-[#101827] p-5">
        <ShieldCheck className="h-5 w-5 text-[#d4af37]" />
        <h2 className="mt-3 text-lg font-semibold text-[#f8f1de]">Reconciliation</h2>
        <p className="mt-2 text-sm text-[#aab4c4]">Status: {data?.status || "UNKNOWN"}</p>
        <p className="mt-1 text-sm text-[#aab4c4]">Findings: {data?.findings?.length ?? 0}</p>
      </article>
      <RecordList title="Open incidents" rows={incidents} fields={["id", "campaign_id", "incident_type", "severity", "status"]} />
    </section>
  );
}

function Policy() {
  return (
    <section className="mt-5 rounded-2xl border border-white/10 bg-[#101827] p-5">
      <FileText className="h-5 w-5 text-[#d4af37]" />
      <h2 className="mt-3 text-lg font-semibold text-[#f8f1de]">Product classification policy</h2>
      <p className="mt-2 text-sm text-[#aab4c4]">
        Non-investment project support and reward campaigns may be software-enabled after admin review. Equity, debt, yield, revenue-share or token-sale campaigns remain disabled until external legal/product approval.
      </p>
    </section>
  );
}
