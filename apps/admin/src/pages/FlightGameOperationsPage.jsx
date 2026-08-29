import { useEffect, useMemo, useState } from "react";
import { AlertTriangle, Gamepad2, PauseCircle, RotateCcw, ShieldCheck } from "lucide-react";
import { GlassPanel, GradientButton, OutlineButton, PageShell, Pill } from "../components/AdminPrimitives";
import { adminHttp } from "../services/http";

function valueOf(value, fallback = "0") {
  if (value === null || value === undefined || value === "") return fallback;
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
}

function statusTone(value) {
  const normalized = String(value ?? "").toUpperCase();
  if (["READY", "PASS", "SETTLED", "OPEN", "RUNNING", "DISABLED"].includes(normalized)) return "success";
  if (["MANUAL_REVIEW", "FAILED", "CRITICAL", "HIGH"].includes(normalized)) return "danger";
  return "warning";
}

export function FlightGameOperationsPage() {
  const [summary, setSummary] = useState(null);
  const [reconciliation, setReconciliation] = useState(null);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState("");

  const load = async () => {
    setLoading(true);
    setMessage("");
    try {
      const [summaryResponse, reconciliationResponse] = await Promise.all([
        adminHttp.get("/v1/games/flight/summary"),
        adminHttp.get("/v1/games/flight/reconciliation"),
      ]);
      setSummary(summaryResponse.data?.data ?? {});
      setReconciliation(reconciliationResponse.data?.data ?? {});
    } catch (error) {
      setMessage(error?.response?.data?.message || error?.message || "Unable to load EXA Flight operations.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  const control = async (action) => {
    setBusy(action);
    setMessage("");
    try {
      await adminHttp.post("/v1/games/flight/control", {
        action,
        round_uuid: summary?.active_round?.round_uuid,
        reason: `Admin ${action.toLowerCase().replaceAll("_", " ")}`,
      });
      await load();
    } catch (error) {
      setMessage(error?.response?.data?.message || error?.message || "EXA Flight control action failed.");
    } finally {
      setBusy("");
    }
  };

  const settings = summary?.settings ?? {};
  const product = summary?.product_control ?? {};
  const round = summary?.active_round ?? null;
  const totals = summary?.totals ?? {};
  const findings = reconciliation?.findings ?? [];
  const exposureRows = summary?.exposure_by_asset ?? [];

  const metrics = useMemo(() => [
    { label: "Game mode", value: valueOf(settings.game_mode, "sandbox") },
    { label: "Classification", value: valueOf(product.product_classification ?? settings.product_classification, "REGULATED_GAMBLING") },
    { label: "Real money", value: valueOf(product.real_money_mode, "DISABLED") },
    { label: "Entries today", value: valueOf(totals.entries_today) },
    { label: "Demo entries", value: valueOf(totals.demo_entries_today) },
    { label: "Real entries", value: valueOf(totals.real_entries_today) },
    { label: "Pending settlements", value: valueOf(totals.pending_settlements) },
    { label: "Reconciliation", value: findings.length ? `${findings.length} findings` : "PASS" },
  ], [findings.length, product, settings, totals]);

  return (
    <PageShell
      eyebrow="Games / EXA Flight"
      title="EXA Flight Operations"
      description="Monitor demo mode, real-money gates, round state, treasury exposure, responsible-gaming controls, fairness and reconciliation from the existing admin workspace."
      actions={
        <>
          <OutlineButton onClick={load} disabled={loading}>
            <RotateCcw className="mr-2 inline h-4 w-4" />
            Refresh
          </OutlineButton>
          <GradientButton onClick={() => control("DISABLE_REAL_MONEY")} disabled={Boolean(busy)}>
            <ShieldCheck className="mr-2 inline h-4 w-4" />
            Disable real money
          </GradientButton>
        </>
      }
    >
      {message ? (
        <div className="rounded-2xl border border-rose-400/25 bg-rose-400/10 p-4 text-sm text-rose-100">{message}</div>
      ) : null}

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {metrics.map((item) => (
          <GlassPanel key={item.label} className="min-h-[118px]">
            <p className="text-xs uppercase tracking-[0.22em] text-violet-100/45">{item.label}</p>
            <div className="mt-5 flex items-center justify-between gap-3">
              <span className="text-xl font-semibold text-white">{item.value}</span>
              <Pill tone={statusTone(item.value)}>{String(item.value).split(" ")[0]}</Pill>
            </div>
          </GlassPanel>
        ))}
      </div>

      <div className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <GlassPanel>
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className="text-xs uppercase tracking-[0.24em] text-auric-300/70">Current round</p>
              <h2 className="mt-2 font-['Sora'] text-2xl font-semibold text-white">
                {round ? `Round #${round.round_number}` : "No active round"}
              </h2>
              <p className="mt-2 text-sm text-violet-100/60">State and timing are server-authoritative. Admin controls cannot edit outcomes or seeds.</p>
            </div>
            <Gamepad2 className="h-8 w-8 text-auric-300" />
          </div>

          {round ? (
            <div className="mt-6 grid gap-3 md:grid-cols-2">
              {[
                ["Round state", round.round_state],
                ["Legacy status", round.status],
                ["Mode", round.mode],
                ["Asset", round.asset],
                ["Players", round.players],
                ["Total stake", round.total_stake],
                ["Server seed hash", round.server_seed_hash],
                ["Fairness version", round.fairness_version],
                ["Open", round.betting_opens_at],
                ["Locked", round.locked_at],
                ["Start", round.starts_at],
                ["End", round.ended_at ?? round.crashes_at],
              ].map(([label, value]) => (
                <div key={label} className="rounded-2xl border border-white/8 bg-white/[0.035] p-4">
                  <p className="text-xs uppercase tracking-[0.2em] text-violet-100/40">{label}</p>
                  <p className="mt-2 break-words text-sm font-semibold text-violet-50">{valueOf(value, "—")}</p>
                </div>
              ))}
            </div>
          ) : null}

          <div className="mt-6 flex flex-wrap gap-3">
            <OutlineButton onClick={() => control("PAUSE_NEW_ENTRIES")} disabled={Boolean(busy)}>
              <PauseCircle className="mr-2 inline h-4 w-4" />
              Pause new entries
            </OutlineButton>
            <OutlineButton onClick={() => control("RESUME_DEMO_MODE")} disabled={Boolean(busy)}>
              Resume demo mode
            </OutlineButton>
            {round ? (
              <OutlineButton onClick={() => control("CANCEL_ROUND")} disabled={Boolean(busy)}>
                Cancel pre-running round
              </OutlineButton>
            ) : null}
          </div>
        </GlassPanel>

        <div className="space-y-6">
          <GlassPanel>
            <h3 className="font-['Sora'] text-lg font-semibold text-white">Treasury risk</h3>
            <div className="mt-4 space-y-3">
              {exposureRows.length ? exposureRows.map((row) => (
                <div key={row.asset} className="rounded-2xl border border-white/8 bg-white/[0.035] p-4">
                  <div className="flex items-center justify-between">
                    <span className="font-semibold text-white">{row.asset}</span>
                    <Pill>{valueOf(row.liability)}</Pill>
                  </div>
                  <p className="mt-2 text-xs text-violet-100/60">Stakes {row.stakes} · Payouts {row.payouts}</p>
                </div>
              )) : <p className="text-sm text-violet-100/60">No exposure rows yet.</p>}
            </div>
          </GlassPanel>

          <GlassPanel>
            <div className="flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-amber-200" />
              <h3 className="font-['Sora'] text-lg font-semibold text-white">Reconciliation</h3>
            </div>
            <div className="mt-4 space-y-3">
              {findings.length ? findings.map((finding, index) => (
                <div key={`${finding.code}-${index}`} className="rounded-2xl border border-amber-300/20 bg-amber-300/10 p-4 text-sm text-amber-100">
                  <p className="font-semibold">{finding.code}</p>
                  <p className="mt-1 text-amber-100/75">{finding.severity} · bet {finding.bet_uuid}</p>
                </div>
              )) : <Pill tone="success">No reconciliation findings</Pill>}
            </div>
          </GlassPanel>

          <GlassPanel>
            <h3 className="font-['Sora'] text-lg font-semibold text-white">Responsible gaming</h3>
            <p className="mt-3 text-sm text-violet-100/65">
              Self-exclusion and limit enforcement are server-side. This page intentionally has no casual override action.
            </p>
          </GlassPanel>
        </div>
      </div>
    </PageShell>
  );
}
