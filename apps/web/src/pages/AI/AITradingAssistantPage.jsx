import {
  ArrowLeft,
  Bot,
  Pause,
  Play,
  ShieldCheck,
  Square,
  TrendingDown,
  TrendingUp,
  Wallet,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useAuth } from "../../context/AuthContext";
import {
  createExaAiAllocation,
  createExaAiSession,
  createExaAiSubscription,
  getCurrentExaAiSession,
  getExaAiAllocations,
  getExaAiActiveAllocation,
  getExaAiOverview,
  getExaAiPerformance,
  getExaAiPlans,
  getExaAiPositions,
  getExaAiStrategies,
  getExaAiSubscription,
  getExaAiTrades,
  getUnifiedTradingBalances,
  pauseExaAiSession,
  resumeExaAiSession,
  stopExaAiSession,
} from "../../services/exaAiApi";

const TABS = ["Overview", "Start Trading", "Strategies", "Performance", "Positions", "Trade History", "Plans"];

function fmtMoney(value, asset = "USDT") {
  const numeric = Number(value ?? 0);
  if (!Number.isFinite(numeric)) return "--";
  return `${numeric.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${asset}`;
}

function fmtPct(value) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) return "--";
  return `${numeric.toFixed(2)}%`;
}

function fmtSigned(value, suffix = " USDT") {
  const numeric = Number(value ?? 0);
  if (!Number.isFinite(numeric)) return "--";
  const sign = numeric > 0 ? "+" : numeric < 0 ? "-" : "";
  return `${sign}${Math.abs(numeric).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${suffix}`;
}

function Badge({ tone = "neutral", children }) {
  const classes = {
    success: "border-emerald-400/30 bg-emerald-500/10 text-emerald-200",
    warning: "border-amber-400/30 bg-amber-500/10 text-amber-200",
    danger: "border-red-400/30 bg-red-500/10 text-red-200",
    neutral: "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-secondary)]",
    brand: "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]",
  };
  return <span className={`inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold ${classes[tone]}`}>{children}</span>;
}

function StatCard({ label, value, hint, positive, negative, icon: Icon }) {
  return (
    <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-xs uppercase tracking-[0.18em] text-[var(--exa-text-muted)]">{label}</p>
          <p className={`mt-2 text-xl font-semibold ${positive ? "text-emerald-300" : negative ? "text-red-300" : "text-[var(--exa-text-primary)]"}`}>{value}</p>
          {hint ? <p className="mt-1 text-xs text-[var(--exa-text-muted)]">{hint}</p> : null}
        </div>
        {Icon ? <Icon className="h-5 w-5 text-[var(--exa-gold)]" /> : null}
      </div>
    </div>
  );
}

function SectionCard({ title, subtitle, action, children }) {
  return (
    <section className="rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 md:p-5">
      <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-base font-semibold text-[var(--exa-text-primary)]">{title}</h3>
          {subtitle ? <p className="mt-1 text-sm text-[var(--exa-text-muted)]">{subtitle}</p> : null}
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}

export default function AITradingAssistantPage({ onBack }) {
  const { apiBaseUrl, token } = useAuth();
  const [activeTab, setActiveTab] = useState("Overview");
  const [status, setStatus] = useState({ type: "", text: "" });
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [overview, setOverview] = useState(null);
  const [plans, setPlans] = useState([]);
  const [subscription, setSubscription] = useState(null);
  const [strategies, setStrategies] = useState([]);
  const [balances, setBalances] = useState([]);
  const [session, setSession] = useState(null);
  const [positions, setPositions] = useState([]);
  const [trades, setTrades] = useState([]);
  const [performance, setPerformance] = useState(null);
  const [performancePeriod, setPerformancePeriod] = useState("30d");
  const [selectedPlan, setSelectedPlan] = useState("pro");
  const [billingCycle, setBillingCycle] = useState("monthly");
  const [allocationForm, setAllocationForm] = useState({ asset: "USDT", amount: "1000" });
  const [allocations, setAllocations] = useState([]);
  const [allocation, setAllocation] = useState(null);
  const [sessionForm, setSessionForm] = useState({ strategy_id: "", duration: "30d", max_daily_loss: "50", max_drawdown_percent: "8" });

  const loadAll = async (period = performancePeriod) => {
    setLoading(true);
    try {
      const [overviewRes, plansRes, subscriptionRes, strategiesRes, balancesRes, allocationsRes, activeAllocationRes, sessionRes, positionsRes, tradesRes, performanceRes] = await Promise.all([
        getExaAiOverview({ apiBaseUrl, token }),
        getExaAiPlans({ apiBaseUrl, token }),
        getExaAiSubscription({ apiBaseUrl, token }),
        getExaAiStrategies({ apiBaseUrl, token }),
        getUnifiedTradingBalances({ apiBaseUrl, token }).catch(() => ({ data: [] })),
        getExaAiAllocations({ apiBaseUrl, token }).catch(() => ({ data: [] })),
        getExaAiActiveAllocation({ apiBaseUrl, token }).catch(() => ({ data: null })),
        getCurrentExaAiSession({ apiBaseUrl, token }),
        getExaAiPositions({ apiBaseUrl, token }),
        getExaAiTrades({ apiBaseUrl, token }),
        getExaAiPerformance({ apiBaseUrl, token, period }),
      ]);

      const allocationRows = allocationsRes?.data || [];
      const currentAllocation = activeAllocationRes?.data || allocationRows.find((item) => item?.status === "active") || null;

      setOverview(overviewRes?.data || null);
      setPlans(plansRes?.data || []);
      setSubscription(subscriptionRes?.data || null);
      setStrategies(strategiesRes?.data || []);
      setBalances(balancesRes?.data || []);
      setAllocations(allocationRows);
      setAllocation(currentAllocation);
      setSession(sessionRes?.data || null);
      setPositions(positionsRes?.data?.data || positionsRes?.data || []);
      setTrades(tradesRes?.data?.data || tradesRes?.data || []);
      setPerformance(performanceRes?.data || null);

      if (currentAllocation?.asset || currentAllocation?.amount) {
        setAllocationForm((current) => ({
          ...current,
          asset: currentAllocation?.asset || current.asset,
          amount: currentAllocation?.amount ? String(currentAllocation.amount) : current.amount,
        }));
      }

      const firstStrategy = (strategiesRes?.data || [])[0];
      if (firstStrategy && !sessionForm.strategy_id) {
        setSessionForm((current) => ({ ...current, strategy_id: String(firstStrategy.id) }));
      }
    } catch (error) {
      setStatus({ type: "error", text: error.message || "Unable to load ExaAI." });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadAll();
  }, [apiBaseUrl, token]);

  useEffect(() => {
    if (!loading) {
      getExaAiPerformance({ apiBaseUrl, token, period: performancePeriod })
        .then((response) => setPerformance(response?.data || null))
        .catch(() => {});
    }
  }, [performancePeriod]);

  const unifiedAssetOptions = useMemo(() => (Array.isArray(balances) ? balances : []).filter((item) => Number(item?.transferable ?? 0) > 0), [balances]);
  const selectedBalance = useMemo(
    () => unifiedAssetOptions.find((item) => item.asset === allocationForm.asset) || unifiedAssetOptions[0] || null,
    [unifiedAssetOptions, allocationForm.asset]
  );

  const sessionStatusTone = session?.status === "active" ? "success" : session?.status === "paused" ? "warning" : "neutral";
  const overviewStatus = overview?.status || {};
  const overviewPerformance = overview?.performance || {};
  const overviewCapital = overview?.capital || {};

  const handleSubscribe = async () => {
    setSubmitting(true);
    setStatus({ type: "", text: "" });
    try {
      await createExaAiSubscription({ apiBaseUrl, token, body: { plan_code: selectedPlan, billing_cycle: billingCycle } });
      setStatus({ type: "success", text: "ExaAI subscription activated successfully." });
      await loadAll();
      setActiveTab("Start Trading");
    } catch (error) {
      setStatus({ type: "error", text: error.message });
    } finally {
      setSubmitting(false);
    }
  };

  const handleAllocate = async () => {
    setSubmitting(true);
    setStatus({ type: "", text: "" });
    try {
      const response = await createExaAiAllocation({ apiBaseUrl, token, body: allocationForm });
      setAllocation(response?.data || null);
      setStatus({ type: "success", text: "ExaAI capital allocated successfully." });
      await loadAll();
    } catch (error) {
      setStatus({ type: "error", text: error.message });
    } finally {
      setSubmitting(false);
    }
  };  const handleActivate = async () => {
    setSubmitting(true);
    setStatus({ type: "", text: "" });
    try {
      const response = await createExaAiSession({
        apiBaseUrl,
        token,
        body: {
          allocation_id: allocation?.id,
          strategy_id: Number(sessionForm.strategy_id),
          duration: sessionForm.duration,
          max_daily_loss: sessionForm.max_daily_loss,
          max_drawdown_percent: sessionForm.max_drawdown_percent,
          eligible_markets: ["BTC/USDT", "ETH/USDT", "SOL/USDT"],
        },
      });
      setSession(response?.data || null);
      setStatus({ type: "success", text: "ExaAI is now active." });
      await loadAll();
      setActiveTab("Overview");
    } catch (error) {
      setStatus({ type: "error", text: error.message });
    } finally {
      setSubmitting(false);
    }
  };

  const handleSessionAction = async (action) => {
    if (!session?.id) return;
    setSubmitting(true);
    setStatus({ type: "", text: "" });
    try {
      if (action === "pause") await pauseExaAiSession({ apiBaseUrl, token, id: session.id });
      if (action === "resume") await resumeExaAiSession({ apiBaseUrl, token, id: session.id });
      if (action === "stop") await stopExaAiSession({ apiBaseUrl, token, id: session.id });
      setStatus({ type: "success", text: `ExaAI ${action}d successfully.` });
      await loadAll();
    } catch (error) {
      setStatus({ type: "error", text: error.message });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <main className="min-h-screen bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
      <header className="sticky top-0 z-20 border-b border-[var(--exa-border)] bg-[var(--exa-bg-primary)] backdrop-blur">
        <div className="mx-auto flex max-w-7xl items-center gap-3 px-4 py-4 md:px-6">
          <button type="button" onClick={onBack} className="rounded-full border border-[var(--exa-border)] p-2 text-[var(--exa-text-secondary)] transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-text-primary)]">
            <ArrowLeft className="h-4 w-4" />
          </button>
          <div>
            <h1 className="text-lg font-semibold tracking-tight text-[var(--exa-text-primary)]">ExaAI</h1>
            <p className="text-xs text-[var(--exa-text-muted)]">Automated trading powered by intelligent strategy and disciplined risk controls.</p>
          </div>
          <div className="ml-auto flex items-center gap-2">
            <Badge tone={sessionStatusTone}>{overviewStatus.session_status || "stopped"}</Badge>
            <Badge tone="brand">{overviewStatus.current_plan || "Plan required"}</Badge>
          </div>
        </div>
      </header>

      <div className="mx-auto max-w-7xl px-4 py-5 md:px-6 md:py-6">
        {status.text ? (
          <div className={`mb-4 rounded-2xl border px-4 py-3 text-sm ${status.type === "error" ? "border-red-400/30 bg-red-500/10 text-red-200" : "border-emerald-400/30 bg-emerald-500/10 text-emerald-200"}`}>
            {status.text}
          </div>
        ) : null}

        <section className="grid gap-4 lg:grid-cols-[1.4fr_0.9fr]">
          <div className="rounded-[28px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-5 md:p-6">
            <div className="flex flex-wrap items-center justify-between gap-4">
              <div>
                <Badge tone="brand">ExaEarn AI Trading Module</Badge>
                <h2 className="mt-3 text-2xl font-semibold tracking-tight text-[var(--exa-text-primary)] md:text-3xl">Native automation for your ExaEarn trading account</h2>
                <p className="mt-2 max-w-2xl text-sm leading-6 text-[var(--exa-text-secondary)]">Activate a governed ExaAI session, allocate only the capital you approve, and keep visibility into strategy, exposure, session controls, and performance.</p>
              </div>
              <div className="flex flex-wrap gap-2">
                <button type="button" onClick={() => setActiveTab("Start Trading")} className="rounded-xl bg-[var(--exa-gold)] px-4 py-2.5 text-sm font-semibold text-[var(--exa-gold-contrast)] transition hover:brightness-105">Start ExaAI</button>
                <button type="button" onClick={() => setActiveTab("Performance")} className="rounded-xl border border-[var(--exa-border)] px-4 py-2.5 text-sm font-semibold text-[var(--exa-text-primary)] transition hover:border-[var(--exa-border-active)]">View Performance</button>
              </div>
            </div>
            <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <StatCard label="Allocated Capital" value={fmtMoney(overviewCapital.allocated_capital)} hint="Capital explicitly assigned to ExaAI" icon={Wallet} />
              <StatCard label="Total P/L" value={fmtSigned(overviewPerformance.total_pnl)} hint="Realized and unrealized combined" positive={Number(overviewPerformance.total_pnl) > 0} negative={Number(overviewPerformance.total_pnl) < 0} icon={Number(overviewPerformance.total_pnl) >= 0 ? TrendingUp : TrendingDown} />
              <StatCard label="Win Rate" value={overviewPerformance.not_enough_history ? "Not enough history" : fmtPct(overviewPerformance.win_rate)} hint={`${overviewPerformance.completed_trades || 0} completed trades`} icon={ShieldCheck} />
              <StatCard label="Open Positions" value={String(overviewPerformance.open_positions || 0)} hint={overviewStatus.current_strategy || "No strategy active"} icon={Bot} />
            </div>
          </div>

          <SectionCard title="Live Session" subtitle="Pause or stop automation without leaving the module." action={<Badge tone={sessionStatusTone}>{session?.status || "inactive"}</Badge>}>
            <div className="space-y-3 text-sm text-[var(--exa-text-secondary)]">
              <div className="flex items-center justify-between rounded-2xl bg-[var(--exa-surface-elevated)] px-4 py-3"><span>Strategy</span><span className="font-medium text-[var(--exa-text-primary)]">{session?.strategy?.name || overviewStatus.current_strategy || "Not configured"}</span></div>
              <div className="flex items-center justify-between rounded-2xl bg-[var(--exa-surface-elevated)] px-4 py-3"><span>Risk Level</span><span className="font-medium text-[var(--exa-text-primary)] capitalize">{session?.risk_level || overviewStatus.risk_level || "--"}</span></div>
              <div className="flex items-center justify-between rounded-2xl bg-[var(--exa-surface-elevated)] px-4 py-3"><span>Available ExaAI Capital</span><span className="font-medium text-[var(--exa-text-primary)]">{fmtMoney(overviewCapital.available_exaai_capital)}</span></div>
            </div>
            <div className="mt-4 grid grid-cols-3 gap-2">
              <button type="button" disabled={!session?.id || submitting} onClick={() => handleSessionAction("pause")} className="inline-flex items-center justify-center gap-2 rounded-xl border border-[var(--exa-border)] px-3 py-2 text-sm font-semibold text-[var(--exa-text-primary)] disabled:opacity-50"><Pause className="h-4 w-4" />Pause</button>
              <button type="button" disabled={!session?.id || submitting} onClick={() => handleSessionAction("resume")} className="inline-flex items-center justify-center gap-2 rounded-xl border border-[var(--exa-border)] px-3 py-2 text-sm font-semibold text-[var(--exa-text-primary)] disabled:opacity-50"><Play className="h-4 w-4" />Resume</button>
              <button type="button" disabled={!session?.id || submitting} onClick={() => handleSessionAction("stop")} className="inline-flex items-center justify-center gap-2 rounded-xl bg-red-500/90 px-3 py-2 text-sm font-semibold text-[var(--exa-text-primary)] disabled:opacity-50"><Square className="h-4 w-4" />Stop</button>
            </div>
          </SectionCard>
        </section>

        <div className="mt-6 flex gap-2 overflow-x-auto pb-2">
          {TABS.map((tab) => (
            <button key={tab} type="button" onClick={() => setActiveTab(tab)} className={`whitespace-nowrap rounded-full border px-4 py-2 text-sm font-medium transition ${activeTab === tab ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)] hover:text-[var(--exa-text-primary)]"}`}>
              {tab}
            </button>
          ))}
        </div>

        <div className="mt-6 space-y-6">
          {activeTab === "Overview" ? (
            <div className="grid gap-4 lg:grid-cols-3">
              <SectionCard title="Performance Snapshot" subtitle="Derived from real ExaAI order and session records.">
                <div className="space-y-3 text-sm text-[var(--exa-text-secondary)]">
                  <div className="flex justify-between rounded-2xl bg-[var(--exa-surface-elevated)] px-4 py-3"><span>Realized P/L</span><span className="font-medium text-[var(--exa-text-primary)]">{fmtSigned(overviewPerformance.realized_pnl)}</span></div>
                  <div className="flex justify-between rounded-2xl bg-[var(--exa-surface-elevated)] px-4 py-3"><span>Unrealized P/L</span><span className="font-medium text-[var(--exa-text-primary)]">{fmtSigned(overviewPerformance.unrealized_pnl)}</span></div>
                  <div className="flex justify-between rounded-2xl bg-[var(--exa-surface-elevated)] px-4 py-3"><span>Today's P/L</span><span className="font-medium text-[var(--exa-text-primary)]">{fmtSigned(overviewPerformance.today_pnl)}</span></div>
                  <div className="flex justify-between rounded-2xl bg-[var(--exa-surface-elevated)] px-4 py-3"><span>Max Drawdown</span><span className="font-medium text-[var(--exa-text-primary)]">{fmtPct(overviewPerformance.max_drawdown_percent)}</span></div>
                </div>
              </SectionCard>
              <SectionCard title="Current Entitlements" subtitle="The active plan governs capacity, analytics, and strategy access.">
                <div className="space-y-3 text-sm text-[var(--exa-text-secondary)]">
                  <div className="flex justify-between rounded-2xl bg-[var(--exa-surface-elevated)] px-4 py-3"><span>Plan</span><span className="font-medium text-[var(--exa-text-primary)]">{subscription?.plan?.name || "No active plan"}</span></div>
                  <div className="flex justify-between rounded-2xl bg-[var(--exa-surface-elevated)] px-4 py-3"><span>Execution Tier</span><span className="font-medium text-[var(--exa-text-primary)]">{subscription?.plan?.execution_tier || "--"}</span></div>
                  <div className="flex justify-between rounded-2xl bg-[var(--exa-surface-elevated)] px-4 py-3"><span>Capital Limit</span><span className="font-medium text-[var(--exa-text-primary)]">{fmtMoney(subscription?.plan?.capital_limit)}</span></div>
                  <div className="flex justify-between rounded-2xl bg-[var(--exa-surface-elevated)] px-4 py-3"><span>Renewal</span><span className="font-medium text-[var(--exa-text-primary)]">{subscription?.renewal_at ? new Date(subscription.renewal_at).toLocaleDateString() : "--"}</span></div>
                </div>
              </SectionCard>
              <SectionCard title="Why ExaAI stops" subtitle="Automation fails closed when controls are breached or infrastructure is unhealthy.">
                <ul className="space-y-2 text-sm leading-6 text-[var(--exa-text-secondary)]">
                  <li>Maximum daily loss or drawdown reached</li>
                  <li>Stale market data or suspended markets</li>
                  <li>Plan or eligibility becomes invalid</li>
                  <li>Unified trading capital becomes unavailable</li>
                  <li>Manual pause, stop, or emergency control</li>
                </ul>
              </SectionCard>
            </div>
          ) : null}
          {activeTab === "Start Trading" ? (
            <div className="grid gap-4 xl:grid-cols-[1.05fr_1fr]">
              <SectionCard title="1. Choose a plan" subtitle="Pick the ExaAI plan that matches your trading permissions and analytics needs.">
                <div className="grid gap-3 lg:grid-cols-3">
                  {plans.map((plan) => {
                    const active = selectedPlan === plan.code;
                    return (
                      <button key={plan.id} type="button" onClick={() => setSelectedPlan(plan.code)} className={`rounded-2xl border p-4 text-left transition ${active ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] hover:border-[var(--exa-border-active)]"}`}>
                        <div className="flex items-center justify-between gap-2"><div><p className="text-base font-semibold text-[var(--exa-text-primary)]">{plan.name}</p><p className="mt-1 text-xs uppercase tracking-[0.18em] text-[var(--exa-text-muted)]">{plan.code}</p></div>{plan.code === "pro" ? <Badge tone="brand">Most Popular</Badge> : null}</div>
                        <p className="mt-3 text-2xl font-semibold text-[var(--exa-text-primary)]">{fmtMoney(billingCycle === "annual" && plan.annual_price ? plan.annual_price : plan.price)}</p>
                        <p className="mt-1 text-sm text-[var(--exa-text-muted)]">{plan.description}</p>
                        <div className="mt-4 space-y-2 text-sm text-[var(--exa-text-secondary)]">
                          <div className="flex justify-between"><span>Capital Limit</span><span>{fmtMoney(plan.capital_limit)}</span></div>
                          <div className="flex justify-between"><span>Max Positions</span><span>{plan.max_open_positions}</span></div>
                          <div className="flex justify-between"><span>Analytics</span><span className="capitalize">{plan.analytics_level}</span></div>
                        </div>
                      </button>
                    );
                  })}
                </div>
                <div className="mt-4 flex flex-wrap items-center gap-3">
                  <div className="inline-flex rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-1">{[{ key: "monthly", label: "Monthly" }, { key: "annual", label: "Annual" }].map((option) => <button key={option.key} type="button" onClick={() => setBillingCycle(option.key)} className={`rounded-lg px-4 py-2 text-sm font-medium ${billingCycle === option.key ? "bg-[var(--exa-gold)] text-[var(--exa-gold-contrast)]" : "text-[var(--exa-text-secondary)]"}`}>{option.label}</button>)}</div>
                  <button type="button" disabled={submitting} onClick={handleSubscribe} className="rounded-xl bg-[var(--exa-gold)] px-4 py-2.5 text-sm font-semibold text-[var(--exa-gold-contrast)] disabled:opacity-60">{submitting ? "Activating..." : "Activate Plan"}</button>
                </div>
              </SectionCard>

              <SectionCard title="2. Allocate capital and activate" subtitle="ExaAI only trades capital you explicitly assign from Unified Trading.">
                <div className="grid gap-4 md:grid-cols-2">
                  <label className="space-y-2 text-sm text-[var(--exa-text-secondary)]"><span>Asset</span><select value={allocationForm.asset} onChange={(event) => setAllocationForm((current) => ({ ...current, asset: event.target.value }))} className="w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-3 text-[var(--exa-text-primary)] outline-none focus:border-[var(--exa-border-active)]">{(unifiedAssetOptions.length ? unifiedAssetOptions : [{ asset: "USDT" }]).map((item) => <option key={item.asset} value={item.asset}>{item.asset}</option>)}</select></label>
                  <label className="space-y-2 text-sm text-[var(--exa-text-secondary)]"><span>Allocation Amount</span><input value={allocationForm.amount} onChange={(event) => setAllocationForm((current) => ({ ...current, amount: event.target.value }))} className="w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-3 text-[var(--exa-text-primary)] outline-none focus:border-[var(--exa-border-active)]" inputMode="decimal" /></label>
                  <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 text-sm text-[var(--exa-text-secondary)] md:col-span-2">
                    <div className="flex items-center justify-between"><span>Unified Trading Transferable</span><span className="font-medium text-[var(--exa-text-primary)]">{selectedBalance ? fmtMoney(selectedBalance.transferable, selectedBalance.asset) : "--"}</span></div>
                    <div className="mt-2 flex items-center justify-between"><span>Spot Available</span><span className="font-medium text-[var(--exa-text-primary)]">{selectedBalance ? fmtMoney(selectedBalance.spot_available, selectedBalance.asset) : "--"}</span></div>
                    <div className="mt-2 flex items-center justify-between"><span>Futures Available</span><span className="font-medium text-[var(--exa-text-primary)]">{selectedBalance ? fmtMoney(selectedBalance.futures_available, selectedBalance.asset) : "--"}</span></div>
                  </div>
                </div>
                <div className="mt-4 flex flex-wrap gap-2"><button type="button" disabled={submitting || !subscription} onClick={handleAllocate} className="rounded-xl border border-[var(--exa-border)] px-4 py-2.5 text-sm font-semibold text-[var(--exa-text-primary)] disabled:opacity-50">{submitting ? "Saving..." : "Save Allocation"}</button><Badge tone={allocation ? "success" : "neutral"}>{allocation ? `Allocation ${allocation.reference}` : "No allocation saved yet"}</Badge>{allocations.length > 1 ? <Badge tone="brand">{allocations.length} saved allocations</Badge> : null}</div>
                <div className="mt-5 grid gap-4 md:grid-cols-2">
                  <label className="space-y-2 text-sm text-[var(--exa-text-secondary)]"><span>Strategy</span><select value={sessionForm.strategy_id} onChange={(event) => setSessionForm((current) => ({ ...current, strategy_id: event.target.value }))} className="w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-3 text-[var(--exa-text-primary)] outline-none focus:border-[var(--exa-border-active)]"><option value="">Select strategy</option>{strategies.map((strategy) => <option key={strategy.id} value={strategy.id}>{strategy.name}</option>)}</select></label>
                  <label className="space-y-2 text-sm text-[var(--exa-text-secondary)]"><span>Duration</span><select value={sessionForm.duration} onChange={(event) => setSessionForm((current) => ({ ...current, duration: event.target.value }))} className="w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-3 text-[var(--exa-text-primary)] outline-none focus:border-[var(--exa-border-active)]"><option value="24h">24 Hours</option><option value="7d">7 Days</option><option value="30d">30 Days</option><option value="90d">90 Days</option><option value="manual">Until Manually Stopped</option></select></label>
                  <label className="space-y-2 text-sm text-[var(--exa-text-secondary)]"><span>Maximum Daily Loss</span><input value={sessionForm.max_daily_loss} onChange={(event) => setSessionForm((current) => ({ ...current, max_daily_loss: event.target.value }))} className="w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-3 text-[var(--exa-text-primary)] outline-none focus:border-[var(--exa-border-active)]" inputMode="decimal" /></label>
                  <label className="space-y-2 text-sm text-[var(--exa-text-secondary)]"><span>Maximum Drawdown %</span><input value={sessionForm.max_drawdown_percent} onChange={(event) => setSessionForm((current) => ({ ...current, max_drawdown_percent: event.target.value }))} className="w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-3 text-[var(--exa-text-primary)] outline-none focus:border-[var(--exa-border-active)]" inputMode="decimal" /></label>
                </div>
                <div className="mt-4 rounded-2xl border border-amber-400/20 bg-amber-500/10 p-4 text-sm leading-6 text-amber-100">Trading involves risk. Automated trading can generate losses. Historical performance does not guarantee future performance, and ExaAI remains subject to ExaEarn eligibility, market availability, and server-side risk controls.</div>
                <button type="button" disabled={submitting || !subscription || !allocation || !sessionForm.strategy_id} onClick={handleActivate} className="mt-4 w-full rounded-2xl bg-[var(--exa-gold)] px-4 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] disabled:opacity-50">{submitting ? "Activating ExaAI..." : "Activate ExaAI"}</button>
              </SectionCard>
            </div>
          ) : null}

          {activeTab === "Strategies" ? <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{strategies.map((strategy) => <SectionCard key={strategy.id} title={strategy.name} subtitle={strategy.description} action={<Badge tone={strategy.risk_level === "aggressive" ? "danger" : strategy.risk_level === "balanced" ? "warning" : "success"}>{strategy.risk_level}</Badge>}><div className="space-y-2 text-sm text-[var(--exa-text-secondary)]"><div className="flex justify-between"><span>Spot</span><span>{strategy.supports_spot ? "Enabled" : "No"}</span></div><div className="flex justify-between"><span>Futures</span><span>{strategy.supports_futures ? "Enabled" : "No"}</span></div><div className="flex justify-between"><span>Plan Access</span><span>{(strategy.allowed_plan_codes || []).join(", ") || "--"}</span></div></div></SectionCard>)}</div> : null}

          {activeTab === "Performance" ? <div className="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]"><SectionCard title="Performance Metrics" subtitle="Calculated from recorded ExaAI orders only." action={<div className="inline-flex rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-1">{["24h", "7d", "30d", "90d", "all"].map((period) => <button key={period} type="button" onClick={() => setPerformancePeriod(period)} className={`rounded-lg px-3 py-1.5 text-xs font-medium ${performancePeriod === period ? "bg-[var(--exa-gold)] text-[var(--exa-gold-contrast)]" : "text-[var(--exa-text-secondary)]"}`}>{period.toUpperCase()}</button>)}</div>}><div className="grid gap-3 md:grid-cols-2"><StatCard label="Net P/L" value={fmtSigned(performance?.net_pnl)} positive={Number(performance?.net_pnl) > 0} negative={Number(performance?.net_pnl) < 0} /><StatCard label="Trading Fees" value={fmtMoney(performance?.trading_fees)} /><StatCard label="Win Rate" value={fmtPct(performance?.win_rate)} /><StatCard label="Profit Factor" value={performance?.profit_factor ?? "--"} /></div></SectionCard><SectionCard title="Equity Curve" subtitle="A simple view of cumulative ExaAI realized performance over time."><div className="space-y-3">{(performance?.equity_curve || []).length ? performance.equity_curve.map((point, index) => <div key={`${point.time}-${index}`} className="flex items-center justify-between rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-3 text-sm text-[var(--exa-text-secondary)]"><span>{point.time ? new Date(point.time).toLocaleString() : "--"}</span><span className="font-medium text-[var(--exa-text-primary)]">{fmtMoney(point.equity)}</span></div>) : <p className="text-sm text-[var(--exa-text-muted)]">Not enough trading history to plot an equity curve yet.</p>}</div></SectionCard></div> : null}

          {activeTab === "Positions" ? <SectionCard title="ExaAI Positions" subtitle="Open and pending ExaAI-linked positions only."><div className="overflow-hidden rounded-2xl border border-[var(--exa-border)]"><div className="grid grid-cols-6 gap-3 bg-[var(--exa-surface-elevated)] px-4 py-3 text-xs uppercase tracking-[0.16em] text-[var(--exa-text-muted)]"><span>Pair</span><span>Side</span><span>Status</span><span>Qty</span><span>Unrealized P/L</span><span>Opened</span></div>{(positions || []).length ? positions.map((position) => <div key={position.id} className="grid grid-cols-6 gap-3 border-t border-[var(--exa-border)] px-4 py-3 text-sm text-[var(--exa-text-secondary)]"><span>{position.pair}</span><span className="capitalize">{position.side}</span><span className="capitalize">{position.status}</span><span>{position.quantity}</span><span>{fmtSigned(position.unrealized_pnl)}</span><span>{position.opened_at ? new Date(position.opened_at).toLocaleString() : "--"}</span></div>) : <div className="px-4 py-6 text-sm text-[var(--exa-text-muted)]">No ExaAI positions are open right now.</div>}</div></SectionCard> : null}

          {activeTab === "Trade History" ? <SectionCard title="ExaAI Trade History" subtitle="Every ExaAI-generated order remains auditable and attributable to its session."><div className="space-y-3">{(trades || []).length ? trades.map((trade) => <div key={trade.id} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><p className="text-sm font-semibold text-[var(--exa-text-primary)]">{trade.pair}</p><p className="mt-1 text-xs text-[var(--exa-text-muted)]">{trade.market_type} / {trade.order_type} / {trade.status}</p></div><Badge tone={Number(trade.realized_pnl) >= 0 ? "success" : "danger"}>{fmtSigned(trade.realized_pnl)}</Badge></div><div className="mt-3 grid gap-2 text-sm text-[var(--exa-text-secondary)] md:grid-cols-4"><div><span className="text-[var(--exa-text-muted)]">Side</span><p className="mt-1 capitalize text-[var(--exa-text-primary)]">{trade.side}</p></div><div><span className="text-[var(--exa-text-muted)]">Quantity</span><p className="mt-1 text-[var(--exa-text-primary)]">{trade.quantity}</p></div><div><span className="text-[var(--exa-text-muted)]">Entry</span><p className="mt-1 text-[var(--exa-text-primary)]">{trade.entry_price || "--"}</p></div><div><span className="text-[var(--exa-text-muted)]">Exit</span><p className="mt-1 text-[var(--exa-text-primary)]">{trade.exit_price || "--"}</p></div></div></div>) : <p className="text-sm text-[var(--exa-text-muted)]">No ExaAI trades have been recorded yet.</p>}</div></SectionCard> : null}

          {activeTab === "Plans" ? <div className="grid gap-4 lg:grid-cols-3">{plans.map((plan) => <SectionCard key={plan.id} title={plan.name} subtitle={plan.description} action={<Badge tone={subscription?.plan_id === plan.id ? "brand" : "neutral"}>{subscription?.plan_id === plan.id ? "Current Plan" : plan.code}</Badge>}><div className="space-y-3 text-sm text-[var(--exa-text-secondary)]"><div className="flex justify-between"><span>Monthly</span><span className="font-medium text-[var(--exa-text-primary)]">{fmtMoney(plan.price)}</span></div><div className="flex justify-between"><span>Annual</span><span className="font-medium text-[var(--exa-text-primary)]">{plan.annual_price ? fmtMoney(plan.annual_price) : "--"}</span></div><div className="flex justify-between"><span>Execution Tier</span><span className="font-medium text-[var(--exa-text-primary)] capitalize">{plan.execution_tier}</span></div><div className="flex justify-between"><span>Strategy Access</span><span className="font-medium text-[var(--exa-text-primary)]">{(plan.strategy_access || []).join(", ")}</span></div></div></SectionCard>)}</div> : null}
        </div>

        {loading ? <div className="mt-6 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-5 text-sm text-[var(--exa-text-muted)]">Loading ExaAI...</div> : null}
      </div>
    </main>
  );
}
