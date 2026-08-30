import { useCallback, useEffect, useMemo, useState } from "react";
import { CheckCircle2, RefreshCw, Search, ShieldAlert, XCircle } from "lucide-react";
import { GlassPanel, GradientButton, OutlineButton, PageShell, Pill } from "../components/AdminPrimitives";
import { adminHttp } from "../services/http";

const STATUSES = ["", "submitted", "under_review", "action_required", "partially_approved", "approved", "suspended", "rejected", "revoked"];

function tone(status) {
  if (["approved", "active"].includes(status)) return "success";
  if (["rejected", "revoked", "suspended"].includes(status)) return "danger";
  return "warning";
}

function label(value) {
  return String(value || "unknown").replaceAll("_", " ");
}

export function DeveloperProductionAccessPage() {
  const [rows, setRows] = useState([]);
  const [selected, setSelected] = useState(null);
  const [conflict, setConflict] = useState(null);
  const [status, setStatus] = useState("");
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [note, setNote] = useState("");

  const loadDetail = useCallback(async (uuid) => {
    const response = await adminHttp.get(`/v1/developer-production/requests/${uuid}`);
    setSelected(response.data?.data ?? null);
    setConflict(response.data?.reviewer_conflict ?? null);
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setMessage("");
    try {
      const response = await adminHttp.get("/v1/developer-production/requests", { params: { status: status || undefined, search: search || undefined, per_page: 50 } });
      const nextRows = response.data?.data?.data ?? [];
      setRows(nextRows);
      if (selected?.request_uuid) await loadDetail(selected.request_uuid);
    } catch (error) {
      setMessage(error?.response?.data?.message || "Production Access queue could not load.");
    } finally {
      setLoading(false);
    }
  }, [loadDetail, search, selected?.request_uuid, status]);

  useEffect(() => { void load(); }, [status]);

  const decide = async (action, capabilities = undefined) => {
    if (!selected) return;
    setBusy(true);
    setMessage("");
    try {
      await adminHttp.post(`/v1/developer-production/requests/${selected.request_uuid}/decision`, {
        action,
        capabilities,
        internal_note: note || undefined,
        public_message: action === "action_required" ? note : undefined,
        expected_version: selected.version,
        idempotency_key: `${action}-${selected.request_uuid}-${crypto.randomUUID()}`,
      });
      setNote("");
      await loadDetail(selected.request_uuid);
      const response = await adminHttp.get("/v1/developer-production/requests", { params: { status: status || undefined, search: search || undefined, per_page: 50 } });
      setRows(response.data?.data?.data ?? []);
    } catch (error) {
      setMessage(error?.response?.data?.message || "The review action could not be completed.");
    } finally {
      setBusy(false);
    }
  };

  const stats = useMemo(() => ({
    queue: rows.filter((row) => ["submitted", "under_review", "partially_approved", "action_required"].includes(row.status)).length,
    second: rows.reduce((count, row) => count + (row.capabilities || []).filter((capability) => capability.status === "pending_second_review").length, 0),
    approved: rows.filter((row) => row.status === "approved").length,
  }), [rows]);

  return (
    <PageShell eyebrow="Developer Platform" title="Production Access" description="Review production eligibility and capabilities with canonical reviewer identity, four-eyes approval and an immutable decision trail." actions={<OutlineButton onClick={load} disabled={loading}><RefreshCw className="mr-2 inline h-4 w-4" />Refresh</OutlineButton>}>
      {message ? <div role="alert" className="rounded-lg border border-rose-400/25 bg-rose-400/10 p-4 text-sm text-rose-100">{message}</div> : null}

      <div className="grid gap-4 sm:grid-cols-3">
        {[['Review queue', stats.queue], ['Second approvals', stats.second], ['Approved', stats.approved]].map(([name, value]) => <GlassPanel key={name} className="min-h-[104px]"><p className="text-xs uppercase tracking-[0.2em] text-violet-100/45">{name}</p><p className="mt-3 text-2xl font-semibold text-white">{value}</p></GlassPanel>)}
      </div>

      <div className="grid gap-5 xl:grid-cols-[minmax(340px,0.78fr)_minmax(520px,1.22fr)]">
        <GlassPanel className="min-w-0">
          <div className="flex flex-col gap-3 sm:flex-row">
            <label className="relative flex-1"><span className="sr-only">Search requests</span><Search className="absolute left-3 top-3 h-4 w-4 text-violet-100/40" /><input value={search} onChange={(event) => setSearch(event.target.value)} onKeyDown={(event) => event.key === "Enter" && load()} placeholder="Request or project" className="w-full rounded-lg border border-white/10 bg-white/5 py-2.5 pl-9 pr-3 text-sm text-white outline-none focus:border-auric-300/60" /></label>
            <select aria-label="Filter by status" value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-lg border border-white/10 bg-cosmic-950 px-3 py-2.5 text-sm text-white"><option value="">All statuses</option>{STATUSES.slice(1).map((item) => <option key={item} value={item}>{label(item)}</option>)}</select>
          </div>
          <div className="mt-4 space-y-2">
            {loading ? <p className="py-8 text-center text-sm text-violet-100/55">Loading review queue...</p> : null}
            {!loading && !rows.length ? <p className="py-8 text-center text-sm text-violet-100/55">No matching Production Access requests.</p> : null}
            {rows.map((row) => <button key={row.request_uuid} type="button" onClick={() => loadDetail(row.request_uuid)} className={`w-full rounded-lg border p-4 text-left transition ${selected?.request_uuid === row.request_uuid ? "border-auric-300/55 bg-auric-300/10" : "border-white/8 bg-white/[0.03] hover:border-white/20"}`}><div className="flex items-center justify-between gap-3"><strong className="truncate text-sm text-white">{row.project?.name || row.request_uuid}</strong><Pill tone={tone(row.status)}>{label(row.status)}</Pill></div><p className="mt-2 truncate text-xs text-violet-100/50">{row.request_uuid}</p><p className="mt-2 text-xs text-violet-100/65">{label(row.applicant_type)} · {row.jurisdiction || "Jurisdiction unavailable"} · {(row.capabilities || []).length} capabilities</p></button>)}
          </div>
        </GlassPanel>

        <GlassPanel className="min-w-0">
          {!selected ? <div className="flex min-h-[360px] items-center justify-center text-center text-sm text-violet-100/55">Select a request to review its evidence and capabilities.</div> : <>
            <div className="flex flex-wrap items-start justify-between gap-4"><div><p className="text-xs uppercase tracking-[0.2em] text-auric-300/70">{selected.request_uuid}</p><h2 className="mt-2 text-xl font-semibold text-white">{selected.project?.name || "Production project"}</h2><p className="mt-1 text-sm text-violet-100/60">Version {selected.version} · submitted {selected.submitted_at ? new Date(selected.submitted_at).toLocaleString() : "unknown"}</p></div><Pill tone={tone(selected.status)}>{label(selected.status)}</Pill></div>
            {conflict ? <div className="mt-4 flex gap-3 rounded-lg border border-rose-400/25 bg-rose-400/10 p-4 text-sm text-rose-100"><ShieldAlert className="h-5 w-5 shrink-0" /><span>Review is blocked: {label(conflict)}.</span></div> : null}
            <div className="mt-5 space-y-3">{selected.capabilities?.map((capability) => <div key={capability.id} className="rounded-lg border border-white/8 bg-white/[0.03] p-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><p className="font-medium text-white">{capability.capability}</p><p className="mt-1 text-xs text-violet-100/50">Approvals {capability.approval_count || 0}/{capability.required_approvals || 1} · {label(capability.reason_code)}</p></div><Pill tone={tone(capability.status)}>{label(capability.status)}</Pill></div><div className="mt-3 flex flex-wrap gap-2"><GradientButton disabled={busy || Boolean(conflict) || ["approved", "rejected", "revoked"].includes(capability.status)} onClick={() => decide("partial_approve", { [capability.capability]: "approved" })}><CheckCircle2 className="mr-2 inline h-4 w-4" />{capability.status === "pending_second_review" ? "Second approval" : "Approve"}</GradientButton><OutlineButton disabled={busy || Boolean(conflict)} onClick={() => decide("partial_approve", { [capability.capability]: "restricted" })}>Restrict</OutlineButton><OutlineButton disabled={busy || Boolean(conflict)} onClick={() => decide("partial_approve", { [capability.capability]: "rejected" })}><XCircle className="mr-2 inline h-4 w-4" />Reject</OutlineButton></div></div>)}</div>
            <label className="mt-5 block"><span className="text-xs uppercase tracking-[0.18em] text-violet-100/45">Review note</span><textarea value={note} onChange={(event) => setNote(event.target.value)} rows={3} className="mt-2 w-full rounded-lg border border-white/10 bg-white/5 p-3 text-sm text-white outline-none focus:border-auric-300/60" placeholder="Required context for action-required decisions; internal evidence for reviews." /></label>
            <div className="mt-4 flex flex-wrap gap-2"><OutlineButton disabled={busy || Boolean(conflict) || !note.trim()} onClick={() => decide("action_required")}>Request information</OutlineButton><OutlineButton disabled={busy} onClick={() => decide("suspend")} className="border-rose-400/30 text-rose-100">Suspend access</OutlineButton><OutlineButton disabled={busy} onClick={() => decide("revoke")} className="border-rose-400/30 text-rose-100">Revoke access</OutlineButton></div>
            <div className="mt-6 border-t border-white/8 pt-5"><h3 className="text-sm font-semibold text-white">Decision timeline</h3><div className="mt-3 space-y-2">{selected.reviews?.map((review) => <div key={review.id} className="flex flex-col justify-between gap-1 rounded-lg bg-white/[0.025] px-3 py-2 text-xs sm:flex-row"><span className="text-violet-50">{label(review.action)} → {label(review.to_status)}</span><span className="text-violet-100/45">{review.created_at ? new Date(review.created_at).toLocaleString() : ""}</span></div>)}</div></div>
          </>}
        </GlassPanel>
      </div>
    </PageShell>
  );
}
