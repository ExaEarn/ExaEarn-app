import { useCallback, useEffect, useMemo, useState } from "react";
import { AlertTriangle, Check, CircleStop, Fingerprint, RefreshCw, Shield, ShieldAlert, Smartphone } from "lucide-react";
import { usePermission } from "../hooks/usePermission";
import { GlassPanel, GradientButton, OutlineButton, PageShell, Pill } from "../components/AdminPrimitives";
import { adminHttp } from "../services/http";
import { useAdminStore } from "../store/useAdminStore";

const API = "/exapay/offline";
const TABS = ["Overview", "Risk policy", "Devices", "Fraud cases", "Approvals", "Audit"];
const POLICY_FIELDS = [
  ["max_amount_minor", "Per-transfer cap"], ["max_outstanding_per_user_minor", "Per-user liability cap"],
  ["max_devices_per_user", "Devices per user"], ["instrument_lifetime_seconds", "Instrument lifetime (seconds)"],
  ["max_hops", "Maximum chain depth"], ["max_transfers_per_journal", "Transfers per journal"],
  ["max_cumulative_spending_minor", "Device cumulative spend"], ["max_device_exposure_minor", "Per-device exposure"],
  ["max_incoming_exposure_per_user_minor", "Incoming user exposure"], ["max_transactions_per_device", "Transactions per device"],
  ["max_journal_bytes", "Maximum journal bytes"],
];

function unwrap(response) { return response?.data?.data ?? response?.data ?? null; }
function rowsOf(response) { const value = unwrap(response); return Array.isArray(value) ? value : value?.data ?? []; }
function makeIdempotencyKey(prefix) {
  if (!globalThis.crypto?.randomUUID) throw new Error("A secure browser context is required for an idempotency key.");
  return `${prefix}:${globalThis.crypto.randomUUID()}`;
}
function amount(value) {
  if (value === null || value === undefined || value === "") return "—";
  const minor = Number(value);
  if (!Number.isSafeInteger(minor)) return "Invalid amount";
  return `SIMUSDT ${(minor / 100).toFixed(2)} (${minor.toLocaleString()} minor units)`;
}
function title(value) { return String(value ?? "unknown").replaceAll("_", " ").toLowerCase().replace(/\b\w/g, (letter) => letter.toUpperCase()); }
function statusTone(status) {
  if (["APPROVED", "VERIFIED", "REDEEMED", "RESOLVED", "active"].includes(status)) return "success";
  if (["COMPROMISED", "CONFLICT_DETECTED", "OPEN", "REJECTED", "SUSPENDED", "INVESTIGATING"].includes(status)) return "danger";
  return "warning";
}
function ErrorBox({ children }) { return <div role="alert" className="rounded-xl border border-rose-400/25 bg-rose-400/10 p-3 text-sm text-rose-100">{children}</div>; }
function SectionTitle({ children, right }) { return <div className="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 className="text-lg font-semibold text-white">{children}</h2>{right}</div>; }
function Empty({ children = "No records returned by the API." }) { return <p className="py-8 text-center text-sm text-violet-100/55">{children}</p>; }

export function ExaPayOfflineOperationsPage() {
  const permission = usePermission();
  const currentAdmin = useAdminStore((state) => state.admin);
  const canManage = permission.can("exapay.offline.manage");
  const [tab, setTab] = useState("Overview");
  const [overview, setOverview] = useState(null);
  const [exposure, setExposure] = useState(null);
  const [policy, setPolicy] = useState(null);
  const [devices, setDevices] = useState([]);
  const [fraud, setFraud] = useState([]);
  const [keys, setKeys] = useState([]);
  const [approvals, setApprovals] = useState([]);
  const [auditEvents, setAuditEvents] = useState([]);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [loading, setLoading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [reason, setReason] = useState("");

  const load = useCallback(async () => {
    setLoading(true); setError("");
    const tasks = [
      ["overview", () => adminHttp.get(`${API}/overview`)],
      ["exposure", () => adminHttp.get(`${API}/exposure`)],
      ["policy", () => adminHttp.get(`${API}/risk-policy`)],
      ["devices", () => adminHttp.get(`${API}/devices`, { params: { per_page: 50 } })],
      ["fraud", () => adminHttp.get(`${API}/fraud-cases`, { params: { per_page: 50 } })],
      ["keys", () => adminHttp.get(`${API}/issuer-keys`)],
      ["approvals", () => adminHttp.get(`${API}/policy-approvals`, { params: { per_page: 50 } })],
      ["audit", () => adminHttp.get(`${API}/audit-events`, { params: { per_page: 100 } })],
    ];
    const results = await Promise.allSettled(tasks.map(([, request]) => request()));
    const failures = [];
    results.forEach((result, index) => {
      const [key] = tasks[index];
      if (result.status === "rejected") { failures.push(result.reason?.response?.data?.message || `${key} could not load`); return; }
      const value = unwrap(result.value);
      if (key === "overview") setOverview(value);
      if (key === "exposure") setExposure(value);
      if (key === "policy") setPolicy(value);
      if (key === "devices") setDevices(rowsOf(result.value));
      if (key === "fraud") setFraud(rowsOf(result.value));
      if (key === "keys") setKeys(Array.isArray(value) ? value : []);
      if (key === "approvals") setApprovals(rowsOf(result.value));
      if (key === "audit") setAuditEvents(rowsOf(result.value));
    });
    if (failures.length) setError([...new Set(failures)].join("; "));
    setLoading(false);
  }, []);

  useEffect(() => { void load(); }, [load]);

  const pendingApprovals = useMemo(() => approvals.filter((item) => item.status === "PENDING"), [approvals]);
  const setPolicyField = (name, value) => setPolicy((current) => ({ ...current, [name]: value }));

  const savePolicy = async () => {
    if (!policy || !canManage) return;
    setBusy(true); setError(""); setNotice("");
    try {
      const payload = Object.fromEntries(POLICY_FIELDS.map(([key]) => [key, Number(policy[key])]));
      Object.assign(payload, {
        enabled: Boolean(policy.enabled), minimum_protocol_version: 1,
        supported_assets: ["SIMUSDT"], allowed_device_classes: ["SIMULATOR_ONLY", "SOFTWARE_ONLY"],
        trust_epoch: Number(policy.trust_epoch || overview?.trust_epoch || 1), require_enrolled_device: true,
      });
      await adminHttp.put(`${API}/risk-policy`, payload, { headers: { "Idempotency-Key": makeIdempotencyKey("policy") } });
      setNotice("Policy change submitted for a distinct administrator’s approval. It is not active yet.");
      await load();
    } catch (err) { setError(err?.response?.data?.message || err.message || "Policy proposal failed."); }
    finally { setBusy(false); }
  };

  const suspendAll = async () => {
    if (!canManage || reason.trim().length < 8) return;
    setBusy(true); setError(""); setNotice("");
    try {
      await adminHttp.post(`${API}/emergency-suspend`, { reason, suspend_redemption: true }, { headers: { "Idempotency-Key": makeIdempotencyKey("suspend") } });
      setNotice("Simulation issuance and redemption are suspended. Existing liabilities were not released.");
      setReason(""); await load();
    } catch (err) { setError(err?.response?.data?.message || err.message || "Emergency suspension failed."); }
    finally { setBusy(false); }
  };

  const proposeResume = async () => {
    if (!canManage || reason.trim().length < 12) return;
    setBusy(true); setError(""); setNotice("");
    try {
      await adminHttp.post(`${API}/emergency-resume`, { reason }, { headers: { "Idempotency-Key": makeIdempotencyKey("resume") } });
      setNotice("Resume request is pending a distinct administrator. It will not resume until approved.");
      setReason(""); await load();
    } catch (err) { setError(err?.response?.data?.message || err.message || "Resume proposal failed."); }
    finally { setBusy(false); }
  };

  const approve = async (approvalUuid) => {
    if (!canManage || !window.confirm("Review the displayed proposal and approve it as the second administrator?")) return;
    setBusy(true); setError(""); setNotice("");
    try {
      await adminHttp.post(`${API}/risk-policy/approvals/${approvalUuid}/approve`, {}, { headers: { "Idempotency-Key": makeIdempotencyKey("approve") } });
      setNotice("Approval recorded. The server rechecked distinct-admin and policy constraints."); await load();
    } catch (err) { setError(err?.response?.data?.message || err.message || "Approval failed."); }
    finally { setBusy(false); }
  };

  const deviceAction = async (device, action) => {
    if (!canManage) return;
    const why = window.prompt(`Reason to ${action} device ${device.device_uuid}?`);
    if (!why || why.trim().length < 8) return;
    setBusy(true); setError(""); setNotice("");
    try {
      await adminHttp.post(`${API}/devices/${device.id}/${action}`, { reason: why }, { headers: { "Idempotency-Key": makeIdempotencyKey(`device-${action}`) } });
      setNotice(`Device ${title(action)} recorded. Offline liabilities remain outstanding.`); await load();
    } catch (err) { setError(err?.response?.data?.message || err.message || "Device action failed."); }
    finally { setBusy(false); }
  };

  const investigate = async (item, status) => {
    if (!canManage) return;
    const resolution = status === "RESOLVED" ? window.prompt("Resolution code (no sensitive data):") : undefined;
    if (status === "RESOLVED" && !resolution) return;
    setBusy(true); setError(""); setNotice("");
    try {
      await adminHttp.post(`${API}/fraud-cases/${item.id}/investigate`, { status, ...(resolution ? { resolution_code: resolution } : {}) }, { headers: { "Idempotency-Key": makeIdempotencyKey("fraud") } });
      setNotice("Fraud case status recorded in the audit trail."); await load();
    } catch (err) { setError(err?.response?.data?.message || err.message || "Fraud case update failed."); }
    finally { setBusy(false); }
  };

  return (
    <PageShell eyebrow="ExaPay · simulation controls" title="Offline payment safety" description="SIMUSDT-only policy, device trust, liabilities and fraud review. This console cannot access real funds or live settlement." actions={<OutlineButton onClick={load} disabled={loading}><RefreshCw className={`mr-2 inline h-4 w-4 ${loading ? "animate-spin" : ""}`} />Refresh from API</OutlineButton>}>
      {error ? <ErrorBox>{error}</ErrorBox> : null}
      {notice ? <div role="status" className="rounded-xl border border-emerald-400/25 bg-emerald-400/10 p-3 text-sm text-emerald-100">{notice}</div> : null}
      {!permission.can("exapay.offline.view") ? <ErrorBox>Your administrator role lacks `exapay.offline.view`.</ErrorBox> : null}
      <div className="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Offline safety sections">{TABS.map((item) => <button key={item} type="button" role="tab" aria-selected={tab === item} onClick={() => setTab(item)} className={`shrink-0 rounded-xl border px-4 py-2 text-sm ${tab === item ? "border-auric-300/40 bg-auric-300/10 text-white" : "border-white/10 bg-white/[0.03] text-violet-100/65"}`}>{item}</button>)}</div>

      {tab === "Overview" ? <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{[
          ["Outstanding liability", amount(overview?.outstanding_liability_minor)], ["Reserve", amount(overview?.reserved_minor)],
          ["Issued · redeemed · extinguished", `${amount(overview?.issued_minor)} · ${amount(overview?.redeemed_minor)} · ${amount(overview?.extinguished_minor)}`],
          ["Active / suspended devices", `${overview?.active_devices ?? "—"} / ${overview?.suspended_devices ?? "—"}`],
          ["Open fraud cases", overview?.fraud_cases_open ?? "—"], ["Pending / conflicts", `${overview?.claims_pending ?? "—"} / ${overview?.claims_in_conflict ?? "—"}`],
          ["Liability utilization", exposure?.liability_utilization_basis_points == null ? "—" : `${(exposure.liability_utilization_basis_points / 100).toFixed(2)}%`],
          ["Simulation controls", overview ? `${overview.issuance_suspended ? "Issuance suspended" : "Issuance enabled"} · ${overview.redemption_suspended ? "redemption suspended" : "redemption enabled"}` : "—"],
        ].map(([label, value]) => <GlassPanel key={label}><p className="text-xs uppercase tracking-[0.18em] text-violet-100/45">{label}</p><p className="mt-3 break-words text-lg font-semibold text-white">{String(value)}</p></GlassPanel>)}</div>
        <GlassPanel><SectionTitle right={<Pill tone={exposure?.alert || !overview?.accounting_conserved ? "danger" : "success"}>{exposure?.alert || !overview?.accounting_conserved ? "Accounting alert" : "Simulation accounting balanced"}</Pill>}>Liability and reserve reconciliation</SectionTitle><div className="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">{[["Ledger reserve", amount(exposure?.ledger_reserve_balance_minor)], ["Authoritative reserve", amount(exposure?.authoritative_reserve_minor)], ["Outstanding liability", amount(exposure?.outstanding_liability_minor)], ["Estimated device outputs", amount(exposure?.estimated_unspent_device_outputs_minor)]].map(([label, value]) => <div key={label} className="rounded-xl border border-white/8 bg-white/[0.03] p-4"><p className="text-xs text-violet-100/50">{label}</p><p className="mt-2 text-white">{value}</p></div>)}</div><p className="mt-4 text-xs text-violet-100/50">Device and instrument amounts are estimates. PostgreSQL ledger and liability rows remain authoritative. Merchant exposure is not supported.</p></GlassPanel>
        <GlassPanel><SectionTitle>Emergency controls</SectionTitle>{!canManage ? <p className="text-sm text-violet-100/55">Read-only role. Ask an authorized ExaPay offline administrator to apply an emergency control.</p> : <><label className="block text-sm text-violet-100/70">Incident reason (audited)<textarea value={reason} onChange={(event) => setReason(event.target.value)} maxLength={240} rows={2} className="mt-2 w-full rounded-xl border border-white/10 bg-white/5 p-3 text-white outline-none focus:border-rose-300/50" placeholder="Describe the simulation incident; avoid personal or credential data." /></label><div className="mt-3 flex flex-wrap gap-2"><OutlineButton className="border-rose-400/30 text-rose-100" disabled={busy || reason.trim().length < 8} onClick={suspendAll}><CircleStop className="mr-2 inline h-4 w-4" />Suspend issuance and redemption</OutlineButton><OutlineButton disabled={busy || reason.trim().length < 12} onClick={proposeResume}><Shield className="mr-2 inline h-4 w-4" />Propose dual-admin resume</OutlineButton></div><p className="mt-3 text-xs text-violet-100/45">Resume is never immediate. It requires a second administrator, a fresh signed policy and an active simulation signing key.</p></>}</GlassPanel>
      </div> : null}

      {tab === "Risk policy" ? <GlassPanel><SectionTitle right={policy ? <Pill tone={policy.enabled ? "warning" : "default"}>{policy.enabled ? "Enabled in simulation" : "Disabled"} · v{policy.policy_version}</Pill> : null}>Versioned signed policy</SectionTitle>{!policy ? <Empty>Policy is unavailable from the backend.</Empty> : <><label className="flex items-center gap-3 rounded-xl border border-white/8 bg-white/[0.03] p-4 text-sm text-white"><input type="checkbox" checked={Boolean(policy.enabled)} disabled={!canManage || busy} onChange={(event) => setPolicyField("enabled", event.target.checked)} />Allow SIMUSDT simulation issuance (requires approved signed policy)</label><div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{POLICY_FIELDS.map(([key, label]) => <label key={key} className="text-xs text-violet-100/60">{label}{key.includes("minor") ? <span className="ml-2 text-violet-100/35">minor units</span> : null}<input inputMode="numeric" type="number" min="1" step="1" value={policy[key] ?? ""} disabled={!canManage || busy} onChange={(event) => setPolicyField(key, event.target.value)} className="mt-2 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-white outline-none focus:border-auric-300/50 disabled:opacity-50" /></label>)}</div><div className="mt-4 rounded-xl border border-white/8 bg-white/[0.03] p-4 text-sm text-violet-100/65">Asset: SIMUSDT only · Device classes: simulator/software only · Enrolled devices required · Protocol v1 · trust epoch {policy.trust_epoch}</div>{canManage ? <GradientButton className="mt-4" disabled={busy} onClick={savePolicy}>Propose signed policy change</GradientButton> : null}<p className="mt-3 text-xs text-violet-100/45">Changes remain pending until a distinct administrator approves. The server signs the canonical policy; browser state is not authoritative.</p></>}</GlassPanel> : null}

      {tab === "Devices" ? <GlassPanel><SectionTitle right={<Pill>{devices.length} loaded</Pill>}>Device trust registry</SectionTitle>{devices.length ? <div className="space-y-3">{devices.map((device) => <div key={device.id} className="grid gap-3 rounded-xl border border-white/8 bg-white/[0.03] p-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center"><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><Smartphone className="h-4 w-4 text-violet-200" /><code className="text-xs text-white">{device.device_uuid}</code><Pill tone={statusTone(device.trust_class)}>{title(device.trust_class)}</Pill><Pill tone={statusTone(device.status)}>{title(device.status)}</Pill></div><p className="mt-2 text-xs text-violet-100/55">User {device.user_id} · attestation {device.attestation_status} · epoch {device.trust_epoch} · spent {amount(device.cumulative_spent_minor)}</p></div>{canManage && device.status !== "suspended" ? <div className="flex gap-2"><OutlineButton disabled={busy} onClick={() => deviceAction(device, "suspend")}><ShieldAlert className="mr-1 inline h-4 w-4" />Suspend</OutlineButton>{device.trust_class !== "COMPROMISED" ? <OutlineButton className="border-rose-400/25 text-rose-100" disabled={busy} onClick={() => deviceAction(device, "compromise")}><AlertTriangle className="mr-1 inline h-4 w-4" />Compromise</OutlineButton> : null}</div> : null}</div>)}</div> : <Empty />}</GlassPanel> : null}

      {tab === "Fraud cases" ? <GlassPanel><SectionTitle right={<Pill tone={fraud.some((item) => item.severity === "CRITICAL") ? "danger" : "default"}>{fraud.length} loaded</Pill>}>Fraud investigations</SectionTitle>{fraud.length ? <div className="space-y-3">{fraud.map((item) => <div key={item.id} className="rounded-xl border border-white/8 bg-white/[0.03] p-4"><div className="flex flex-wrap items-center gap-2"><strong className="text-sm text-white">{title(item.reason_code)}</strong><Pill tone={item.severity === "CRITICAL" ? "danger" : "warning"}>{item.severity}</Pill><Pill tone={statusTone(item.status)}>{title(item.status)}</Pill></div><p className="mt-2 text-xs text-violet-100/55">Case {item.case_uuid} · user {item.user_id ?? "unknown"} · device {item.device_id ?? "unknown"} · {item.detected_at || "time unavailable"}</p><p className="mt-2 break-all text-xs text-violet-100/45">{JSON.stringify(item.evidence ?? {})}</p>{canManage && item.status !== "RESOLVED" ? <div className="mt-3 flex gap-2"><OutlineButton disabled={busy} onClick={() => investigate(item, "INVESTIGATING")}>Mark investigating</OutlineButton><OutlineButton disabled={busy} onClick={() => investigate(item, "RESOLVED")}>Resolve with code</OutlineButton></div> : null}</div>)}</div> : <Empty />}</GlassPanel> : null}

      {tab === "Approvals" ? <div className="space-y-4"><GlassPanel><SectionTitle right={<Pill tone={pendingApprovals.length ? "warning" : "success"}>{pendingApprovals.length} pending</Pill>}>Two-person approval queue</SectionTitle>{pendingApprovals.length ? <div className="space-y-3">{pendingApprovals.map((item) => <div key={item.approval_uuid} className="rounded-xl border border-white/8 bg-white/[0.03] p-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><strong className="text-white">{title(item.change_type)}</strong><p className="mt-1 text-xs text-violet-100/45">Requested by administrator {item.requested_by_admin_id} · expires {item.expires_at}</p></div><Pill tone="warning">{item.status}</Pill></div><pre className="mt-3 max-h-64 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-black/25 p-3 text-xs text-violet-100/70">{JSON.stringify(item.proposed_change, null, 2)}</pre><p className="mt-2 break-all text-[11px] text-violet-100/35">Digest {item.change_digest}</p>{canManage ? <OutlineButton className="mt-3" disabled={busy || Number(item.requested_by_admin_id) === Number(currentAdmin?.id)} onClick={() => approve(item.approval_uuid)}><Check className="mr-2 inline h-4 w-4" />Approve as second administrator</OutlineButton> : null}</div>)}</div> : <Empty>No pending policy or emergency-resume approvals.</Empty>}</GlassPanel><GlassPanel><SectionTitle>Issuer keys</SectionTitle>{keys.length ? keys.map((key) => <div key={key.key_id} className="mb-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white/[0.03] p-3 text-xs"><span className="break-all text-violet-100/70"><Fingerprint className="mr-2 inline h-4 w-4" />{key.key_id} · {key.fingerprint}</span><Pill tone={statusTone(key.status)}>{key.status}</Pill></div>) : <Empty>No issuer keys reported.</Empty>}<p className="mt-3 text-xs text-violet-100/45">Key rotation is managed by the configured signing provider; this console does not display private key material.</p></GlassPanel></div> : null}

      {tab === "Audit" ? <GlassPanel><SectionTitle right={<Pill>{auditEvents.length} loaded</Pill>}>Security audit events</SectionTitle>{auditEvents.length ? <div className="space-y-2">{auditEvents.map((event) => <div key={event.id} className="grid gap-1 rounded-lg border border-white/8 bg-white/[0.03] p-3 text-xs sm:grid-cols-[190px_minmax(0,1fr)_180px]"><span className="text-violet-100/45">{event.created_at}</span><span className="break-all text-white">{event.action}</span><span className="text-violet-100/55">Admin {event.admin_id ?? "unknown"}</span><pre className="overflow-auto whitespace-pre-wrap break-words text-[11px] text-violet-100/45 sm:col-span-3">{JSON.stringify(event.context ?? {}, null, 2)}</pre></div>)}</div> : <Empty />}</GlassPanel> : null}

      <div className="sr-only" aria-live="polite">{loading ? "Refreshing offline safety data" : "Offline safety data refreshed"}{busy ? "; operation in progress" : ""}</div>
    </PageShell>
  );
}
