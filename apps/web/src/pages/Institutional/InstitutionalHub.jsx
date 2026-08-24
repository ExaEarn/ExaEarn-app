import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, BadgeCheck, Building2, CandlestickChart, HandCoins, KeyRound, Landmark, Loader2, Network, ShieldCheck, UsersRound, WalletCards } from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import { acceptOtcQuote, applyForInstitutional, applyForMarketMakerProgram, createInstitutionalSubaccount, createInstitutionalTransfer, createMarketMakerBot, createMarketMakerBotStrategy, fetchInstitutionalApplications, fetchInstitutionalOverview, fetchMarketMakerBotStrategies, fetchMarketMakerBots, fetchMarketMakerOverview, fetchOtcRfqs, requestOtcQuote, runMarketMakerBotShadow, startMarketMakerBot } from "../../services/institutionalApi";

const defaultApplication = {
  legal_company_name: "",
  business_type: "asset_manager",
  incorporation_country: "",
  website: "",
  expected_monthly_spot_volume: "250000",
  intended_products: ["SPOT", "CONVERT"],
  contact_person: "",
  business_email: "",
};

function rows(value) {
  if (Array.isArray(value)) return value;
  if (Array.isArray(value?.data?.data)) return value.data.data;
  if (Array.isArray(value?.data)) return value.data;
  return [];
}

function idempotencyKey(prefix) {
  return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export default function InstitutionalHub({ onBack }) {
  const { request, user } = useAuth();
  const [overview, setOverview] = useState(null);
  const [marketMakerOverview, setMarketMakerOverview] = useState(null);
  const [applications, setApplications] = useState([]);
  const [form, setForm] = useState(defaultApplication);
  const [subaccountForm, setSubaccountForm] = useState({ name: "", type: "SPOT", description: "" });
  const [transferForm, setTransferForm] = useState({ source_subaccount_uuid: "", destination_subaccount_uuid: "", asset: "USDT", amount: "" });
  const [marketMakerForm, setMarketMakerForm] = useState({ subaccount_id: "", requested_markets: "BTCUSDT", requested_products: ["SPOT"] });
  const [otcForm, setOtcForm] = useState({ subaccount_id: "", symbol: "BTCUSDT", side: "BUY", base_amount: "", execution_preference: "BEST_PRICE" });
  const [botForm, setBotForm] = useState({ market_maker_id: "", strategy_id: "", name: "Primary BTC Bot", market_symbol: "BTC/USDT", quote_size: "0.1", levels: "1", base_spread_bps: "20" });
  const [botStrategyForm, setBotStrategyForm] = useState({ market_maker_id: "", name: "Two-Sided Market Making", supported_markets: "BTC/USDT" });
  const [otcRfqs, setOtcRfqs] = useState([]);
  const [bots, setBots] = useState([]);
  const [botStrategies, setBotStrategies] = useState([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const data = overview?.data || overview || {};
  const institution = data.institution || null;
  const subaccounts = useMemo(() => rows(data.subaccounts), [data.subaccounts]);
  const balances = useMemo(() => rows(data.summary?.balances), [data.summary]);
  const marketMakerSubaccounts = useMemo(() => subaccounts.filter((item) => item.type === "MARKET_MAKER"), [subaccounts]);
  const tradingSubaccounts = useMemo(() => subaccounts.filter((item) => ["SPOT", "TREASURY", "API_TRADING", "GENERAL"].includes(item.type)), [subaccounts]);

  const refresh = async () => {
    setLoading(true);
    setError("");
    try {
      const [overviewPayload, applicationPayload, marketMakerPayload, otcPayload, botsPayload, strategiesPayload] = await Promise.allSettled([
        fetchInstitutionalOverview(request),
        fetchInstitutionalApplications(request),
        fetchMarketMakerOverview(request),
        fetchOtcRfqs(request),
        fetchMarketMakerBots(request),
        fetchMarketMakerBotStrategies(request),
      ]);
      if (overviewPayload.status === "fulfilled") setOverview(overviewPayload.value);
      if (applicationPayload.status === "fulfilled") setApplications(rows(applicationPayload.value));
      if (marketMakerPayload.status === "fulfilled") setMarketMakerOverview(marketMakerPayload.value);
      if (otcPayload.status === "fulfilled") setOtcRfqs(rows(otcPayload.value));
      if (botsPayload.status === "fulfilled") setBots(rows(botsPayload.value));
      if (strategiesPayload.status === "fulfilled") setBotStrategies(rows(strategiesPayload.value));
    } catch (err) {
      setError(err?.message || "Institutional workspace is unavailable.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    refresh();
  }, []);

  useEffect(() => {
    if (subaccounts.length < 1) return;
    setTransferForm((current) => ({
      ...current,
      source_subaccount_uuid: current.source_subaccount_uuid || subaccounts[0]?.subaccount_uuid || "",
      destination_subaccount_uuid: current.destination_subaccount_uuid || subaccounts[1]?.subaccount_uuid || subaccounts[0]?.subaccount_uuid || "",
    }));
    setMarketMakerForm((current) => ({
      ...current,
      subaccount_id: current.subaccount_id || subaccounts.find((item) => item.type === "MARKET_MAKER")?.id || "",
    }));
    setOtcForm((current) => ({
      ...current,
      subaccount_id: current.subaccount_id || tradingSubaccounts[0]?.id || subaccounts[0]?.id || "",
    }));
  }, [subaccounts, tradingSubaccounts]);

  const marketMakerProfiles = useMemo(
    () => rows(marketMakerOverview?.data?.profiles || marketMakerOverview?.profiles),
    [marketMakerOverview],
  );

  useEffect(() => {
    setBotStrategyForm((current) => ({
      ...current,
      market_maker_id: current.market_maker_id || marketMakerProfiles[0]?.id || "",
    }));
    setBotForm((current) => ({
      ...current,
      market_maker_id: current.market_maker_id || marketMakerProfiles[0]?.id || "",
      strategy_id: current.strategy_id || botStrategies[0]?.id || "",
    }));
  }, [marketMakerProfiles, botStrategies]);

  const submitApplication = async (event) => {
    event.preventDefault();
    setBusy("application");
    setError("");
    setMessage("");
    try {
      await applyForInstitutional(request, {
        ...form,
        business_email: form.business_email || user?.email || "",
      });
      setMessage("Institutional application submitted for KYB and risk review.");
      setForm(defaultApplication);
      await refresh();
    } catch (err) {
      setError(err?.message || "Unable to submit institutional application.");
    } finally {
      setBusy("");
    }
  };

  const submitSubaccount = async (event) => {
    event.preventDefault();
    setBusy("subaccount");
    setError("");
    try {
      await createInstitutionalSubaccount(request, subaccountForm);
      setMessage("Subaccount created with institutional RBAC controls.");
      setSubaccountForm({ name: "", type: "SPOT", description: "" });
      await refresh();
    } catch (err) {
      setError(err?.message || "Unable to create subaccount.");
    } finally {
      setBusy("");
    }
  };

  const submitTransfer = async (event) => {
    event.preventDefault();
    setBusy("transfer");
    setError("");
    try {
      await createInstitutionalTransfer(request, {
        ...transferForm,
        idempotency_key: idempotencyKey("institutional-transfer"),
      });
      setMessage("Transfer submitted through canonical ledger settlement.");
      setTransferForm((current) => ({ ...current, amount: "" }));
      await refresh();
    } catch (err) {
      setError(err?.message || "Unable to create transfer.");
    } finally {
      setBusy("");
    }
  };

  const submitMarketMaker = async (event) => {
    event.preventDefault();
    setBusy("market-maker");
    setError("");
    setMessage("");
    try {
      await applyForMarketMakerProgram(request, {
        subaccount_id: Number(marketMakerForm.subaccount_id),
        requested_markets: marketMakerForm.requested_markets.split(",").map((item) => item.trim().toUpperCase()).filter(Boolean),
        requested_products: marketMakerForm.requested_products,
        technical_profile: { websocket: true, mass_cancel: true },
        risk_profile: { dedicated_subaccount: true },
        commercial_terms: { program: "standard_market_maker" },
        idempotency_key: idempotencyKey("market-maker-apply"),
      });
      setMessage("Market maker program application submitted for technical, risk and commercial review.");
      await refresh();
    } catch (err) {
      setError(err?.message || "Unable to submit market maker application.");
    } finally {
      setBusy("");
    }
  };

  const submitOtcRfq = async (event) => {
    event.preventDefault();
    setBusy("otc-rfq");
    setError("");
    setMessage("");
    try {
      await requestOtcQuote(request, {
        ...otcForm,
        subaccount_id: Number(otcForm.subaccount_id),
        symbol: otcForm.symbol.replace(/[^a-z0-9]/gi, "").toUpperCase(),
        side: otcForm.side.toUpperCase(),
        idempotency_key: idempotencyKey("otc-rfq"),
      });
      setMessage("OTC RFQ submitted to eligible liquidity providers.");
      setOtcForm((current) => ({ ...current, base_amount: "" }));
      await refresh();
    } catch (err) {
      setError(err?.message || "Unable to request OTC quote.");
    } finally {
      setBusy("");
    }
  };

  const acceptBestOtcQuote = async (rfq) => {
    setBusy(`otc-accept-${rfq.rfq_uuid}`);
    setError("");
    setMessage("");
    try {
      await acceptOtcQuote(request, rfq.rfq_uuid, { idempotency_key: idempotencyKey("otc-accept") });
      setMessage("OTC quote accepted and submitted through protected settlement.");
      await refresh();
    } catch (err) {
      setError(err?.message || "Unable to accept OTC quote.");
    } finally {
      setBusy("");
    }
  };

  const submitBotStrategy = async (event) => {
    event.preventDefault();
    setBusy("bot-strategy");
    setError("");
    setMessage("");
    try {
      await createMarketMakerBotStrategy(request, {
        market_maker_id: Number(botStrategyForm.market_maker_id),
        name: botStrategyForm.name,
        strategy_type: "TWO_SIDED_MARKET_MAKING",
        supported_markets: botStrategyForm.supported_markets.split(",").map((item) => item.trim().toUpperCase()).filter(Boolean),
        version: "1.0.0",
        configuration: { quote_mode: "two_sided", source: "institutional_console" },
      });
      setMessage("Market-maker bot strategy created as a reviewed version.");
      await refresh();
    } catch (err) {
      setError(err?.message || "Unable to create bot strategy.");
    } finally {
      setBusy("");
    }
  };

  const submitBot = async (event) => {
    event.preventDefault();
    setBusy("bot-create");
    setError("");
    setMessage("");
    try {
      await createMarketMakerBot(request, {
        market_maker_id: Number(botForm.market_maker_id),
        strategy_id: Number(botForm.strategy_id),
        name: botForm.name,
        market_symbol: botForm.market_symbol.toUpperCase(),
        product_type: "SPOT",
        configuration: {
          quote_size: botForm.quote_size,
          levels: Number(botForm.levels),
          base_spread_bps: Number(botForm.base_spread_bps),
          quote_ttl_seconds: 30,
        },
        risk_limits: {
          max_market_data_age_seconds: 60,
          max_spread_bps: 100,
        },
      });
      setMessage("Market-maker bot created. Admin approval is required before live quoting.");
      await refresh();
    } catch (err) {
      setError(err?.message || "Unable to create market-maker bot.");
    } finally {
      setBusy("");
    }
  };

  const runBotShadow = async (bot) => {
    setBusy(`bot-shadow-${bot.bot_uuid}`);
    setError("");
    setMessage("");
    try {
      await runMarketMakerBotShadow(request, bot.bot_uuid, { idempotency_key: idempotencyKey("mm-bot-shadow") });
      setMessage("Shadow quote cycle recorded without touching the live book.");
      await refresh();
    } catch (err) {
      setError(err?.message || "Unable to run shadow quote cycle.");
    } finally {
      setBusy("");
    }
  };

  const startBot = async (bot) => {
    setBusy(`bot-start-${bot.bot_uuid}`);
    setError("");
    setMessage("");
    try {
      await startMarketMakerBot(request, bot.bot_uuid, { reason: "Start approved market-maker bot from institutional console." });
      setMessage("Market-maker bot moved to active mode.");
      await refresh();
    } catch (err) {
      setError(err?.message || "Unable to start market-maker bot.");
    } finally {
      setBusy("");
    }
  };

  return (
    <div className="min-h-screen bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
      <div className="mx-auto w-full max-w-6xl px-4 pb-24 pt-5 sm:px-6 lg:px-8">
        <header className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <button type="button" onClick={onBack} className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-2 text-[var(--exa-text-secondary)] hover:text-[var(--exa-gold-light)]" aria-label="Back">
              <ArrowLeft className="h-5 w-5" />
            </button>
            <div>
              <p className="text-xs uppercase tracking-[0.22em] text-[var(--exa-gold-muted)]">Account</p>
              <h1 className="font-['Sora'] text-2xl font-semibold sm:text-3xl">Institutional & VIP</h1>
            </div>
          </div>
          <button type="button" onClick={refresh} className="rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-4 py-2 text-sm font-semibold text-[var(--exa-gold-light)]">
            Refresh
          </button>
        </header>

        {message ? <div className="mb-4 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{message}</div> : null}
        {error ? <div className="mb-4 rounded-2xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">{error}</div> : null}

        <section className="grid gap-4 lg:grid-cols-[1.35fr_0.65fr]">
          <div className="rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-5 shadow-[var(--exa-shadow-panel)]">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-[var(--exa-text-muted)]">Master Account</p>
                <h2 className="mt-2 font-['Sora'] text-xl font-semibold">{institution?.legal_name || "No active institutional account yet"}</h2>
                <p className="mt-2 max-w-2xl text-sm text-[var(--exa-text-secondary)]">
                  Run treasury, trading desks, API segregation, fee tiers and consolidated reporting without creating parallel wallets.
                </p>
              </div>
              <StatusPill value={institution?.status || "APPLICATION REQUIRED"} />
            </div>
            <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <Metric icon={Building2} label="VIP Tier" value={institution?.vip_tier || "STANDARD"} />
              <Metric icon={ShieldCheck} label="Compliance" value={institution?.compliance_status || "Pending"} />
              <Metric icon={UsersRound} label="Subaccounts" value={String(subaccounts.length)} />
              <Metric icon={KeyRound} label="API Profile" value={institution ? "Scoped" : "Retail"} />
            </div>
          </div>

          <Panel title="Applications" icon={BadgeCheck}>
            <div className="space-y-3">
              {applications.length ? applications.slice(0, 3).map((item) => (
                <div key={item.application_uuid || item.id} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-3">
                  <div className="flex items-center justify-between gap-2">
                    <strong className="text-sm">{item.legal_company_name}</strong>
                    <StatusPill value={item.state} compact />
                  </div>
                  <p className="mt-1 text-xs text-[var(--exa-text-muted)]">{item.business_type} - {item.incorporation_country || "jurisdiction pending"}</p>
                </div>
              )) : <p className="rounded-2xl border border-dashed border-[var(--exa-border)] p-4 text-sm text-[var(--exa-text-muted)]">Submit an application to unlock institutional controls.</p>}
            </div>
          </Panel>
        </section>

        <section className="mt-4 grid gap-4 lg:grid-cols-3">
          <Panel title="Subaccounts" icon={Network} className="lg:col-span-2">
            <div className="grid gap-3 md:grid-cols-2">
              {subaccounts.map((item) => (
                <div key={item.subaccount_uuid} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <h3 className="font-['Sora'] text-sm font-semibold">{item.name}</h3>
                      <p className="text-xs text-[var(--exa-text-muted)]">{item.type} - {item.status}</p>
                    </div>
                    <WalletCards className="h-5 w-5 text-[var(--exa-gold-light)]" />
                  </div>
                </div>
              ))}
            </div>
            <form onSubmit={submitSubaccount} className="mt-4 grid gap-3 sm:grid-cols-[1fr_150px_auto]">
              <Input label="Desk name" value={subaccountForm.name} onChange={(value) => setSubaccountForm((current) => ({ ...current, name: value }))} required />
                <Select label="Type" value={subaccountForm.type} onChange={(value) => setSubaccountForm((current) => ({ ...current, type: value }))} options={["SPOT", "FUTURES", "MARGIN", "TREASURY", "MARKET_MAKER", "API_TRADING", "GENERAL"]} />
              <button disabled={!institution || busy === "subaccount"} className="self-end rounded-xl bg-[var(--exa-gold)] px-4 py-3 text-sm font-semibold text-black disabled:cursor-not-allowed disabled:opacity-50">
                {busy === "subaccount" ? <Loader2 className="h-4 w-4 animate-spin" /> : "Create"}
              </button>
            </form>
          </Panel>

          <Panel title="Ledger Balances" icon={Landmark}>
            <div className="space-y-2">
              {balances.length ? balances.slice(0, 6).map((item, index) => (
                <div key={`${item.subaccount_id}-${item.asset}-${index}`} className="flex items-center justify-between rounded-xl bg-[var(--exa-surface)] px-3 py-2 text-sm">
                  <span className="text-[var(--exa-text-secondary)]">{item.asset}</span>
                  <strong className="font-mono">{item.available || item.total || "0"}</strong>
                </div>
              )) : <p className="text-sm text-[var(--exa-text-muted)]">Balances appear after canonical ledger activity.</p>}
            </div>
          </Panel>
        </section>

        <section className="mt-4">
          <Panel title="Market Making" icon={CandlestickChart}>
            <div className="grid gap-4 lg:grid-cols-[1fr_0.9fr]">
              <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                <p className="text-sm text-[var(--exa-text-secondary)]">
                  Apply with a dedicated market-maker subaccount. Capital readiness is checked from the institutional subaccount ledger, while live orders still use the normal ExaEarn OMS, risk and settlement path.
                </p>
                <div className="mt-4 grid gap-3 sm:grid-cols-3">
                  <Metric icon={ShieldCheck} label="Profiles" value={String(rows(marketMakerOverview?.data?.profiles || marketMakerOverview?.profiles).length)} />
                  <Metric icon={Network} label="Subaccounts" value={String(marketMakerSubaccounts.length)} />
                  <Metric icon={KeyRound} label="API" value="Scoped" />
                </div>
              </div>
              <form onSubmit={submitMarketMaker} className="grid gap-3 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                <Select label="Market-maker subaccount" value={marketMakerForm.subaccount_id} onChange={(value) => setMarketMakerForm((current) => ({ ...current, subaccount_id: value }))} options={marketMakerSubaccounts.map((item) => ({ value: item.id, label: `${item.name} (${item.type})` }))} />
                <Input label="Markets" value={marketMakerForm.requested_markets} onChange={(value) => setMarketMakerForm((current) => ({ ...current, requested_markets: value }))} />
                <button disabled={!institution || !marketMakerForm.subaccount_id || busy === "market-maker"} className="rounded-xl bg-[var(--exa-gold)] px-4 py-3 text-sm font-semibold text-black disabled:cursor-not-allowed disabled:opacity-50">
                  {busy === "market-maker" ? "Submitting..." : "Apply for Market Maker Program"}
                </button>
              </form>
            </div>
          </Panel>
        </section>

        <section className="mt-4">
          <Panel title="Automated Market-Maker Bots" icon={CandlestickChart}>
            <div className="grid gap-4 xl:grid-cols-[0.85fr_1.15fr]">
              <div className="grid gap-4">
                <form onSubmit={submitBotStrategy} className="grid gap-3 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                  <div>
                    <h3 className="font-['Sora'] text-sm font-semibold">Strategy version</h3>
                    <p className="mt-1 text-xs text-[var(--exa-text-muted)]">Create a reviewed strategy definition before attaching a bot.</p>
                  </div>
                  <Select label="Market-maker profile" value={botStrategyForm.market_maker_id} onChange={(value) => setBotStrategyForm((current) => ({ ...current, market_maker_id: value }))} options={marketMakerProfiles.map((item) => ({ value: item.id, label: `${item.market_maker_code || "MM"} - ${item.status}` }))} />
                  <Input label="Strategy name" value={botStrategyForm.name} onChange={(value) => setBotStrategyForm((current) => ({ ...current, name: value }))} />
                  <Input label="Supported markets" value={botStrategyForm.supported_markets} onChange={(value) => setBotStrategyForm((current) => ({ ...current, supported_markets: value }))} />
                  <button disabled={!institution || !botStrategyForm.market_maker_id || busy === "bot-strategy"} className="rounded-xl bg-[var(--exa-gold)] px-4 py-3 text-sm font-semibold text-black disabled:cursor-not-allowed disabled:opacity-50">
                    {busy === "bot-strategy" ? "Creating..." : "Create Strategy"}
                  </button>
                </form>

                <form onSubmit={submitBot} className="grid gap-3 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                  <div>
                    <h3 className="font-['Sora'] text-sm font-semibold">Bot configuration</h3>
                    <p className="mt-1 text-xs text-[var(--exa-text-muted)]">Live cycles submit normal post-only spot orders through ExaEarn OMS.</p>
                  </div>
                  <Select label="Strategy" value={botForm.strategy_id} onChange={(value) => setBotForm((current) => ({ ...current, strategy_id: value }))} options={botStrategies.map((item) => ({ value: item.id, label: `${item.name} (${item.status})` }))} />
                  <Input label="Bot name" value={botForm.name} onChange={(value) => setBotForm((current) => ({ ...current, name: value }))} />
                  <div className="grid gap-3 sm:grid-cols-2">
                    <Input label="Market" value={botForm.market_symbol} onChange={(value) => setBotForm((current) => ({ ...current, market_symbol: value.toUpperCase() }))} />
                    <Input label="Quote size" value={botForm.quote_size} onChange={(value) => setBotForm((current) => ({ ...current, quote_size: value }))} inputMode="decimal" />
                  </div>
                  <div className="grid gap-3 sm:grid-cols-2">
                    <Input label="Levels" value={botForm.levels} onChange={(value) => setBotForm((current) => ({ ...current, levels: value }))} inputMode="numeric" />
                    <Input label="Base spread bps" value={botForm.base_spread_bps} onChange={(value) => setBotForm((current) => ({ ...current, base_spread_bps: value }))} inputMode="numeric" />
                  </div>
                  <button disabled={!institution || !botForm.strategy_id || busy === "bot-create"} className="rounded-xl bg-[var(--exa-gold)] px-4 py-3 text-sm font-semibold text-black disabled:cursor-not-allowed disabled:opacity-50">
                    {busy === "bot-create" ? "Creating..." : "Create Bot"}
                  </button>
                </form>
              </div>

              <div className="space-y-3">
                {bots.length ? bots.slice(0, 6).map((bot) => (
                  <div key={bot.bot_uuid} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <h3 className="font-['Sora'] text-sm font-semibold">{bot.name}</h3>
                        <p className="mt-1 text-xs text-[var(--exa-text-muted)]">{bot.market_symbol} - {bot.product_type} - {bot.strategy?.name || "strategy attached"}</p>
                      </div>
                      <StatusPill value={bot.status} compact />
                    </div>
                    <div className="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                      <MiniStat label="Safety" value={bot.safety_state || "NORMAL"} />
                      <MiniStat label="Cycles" value={String(rows(bot.quote_cycles).length)} />
                      <MiniStat label="Mode" value={bot.status === "ACTIVE" ? "LIVE" : "CONTROLLED"} />
                    </div>
                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                      <button type="button" disabled={busy === `bot-shadow-${bot.bot_uuid}`} onClick={() => runBotShadow(bot)} className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-2.5 text-sm font-semibold text-[var(--exa-text-primary)] disabled:cursor-not-allowed disabled:opacity-50">
                        {busy === `bot-shadow-${bot.bot_uuid}` ? "Running..." : "Run Shadow"}
                      </button>
                      <button type="button" disabled={bot.status !== "APPROVED" || busy === `bot-start-${bot.bot_uuid}`} onClick={() => startBot(bot)} className="rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-4 py-2.5 text-sm font-semibold text-[var(--exa-gold-light)] disabled:cursor-not-allowed disabled:opacity-50">
                        {busy === `bot-start-${bot.bot_uuid}` ? "Starting..." : "Start Bot"}
                      </button>
                    </div>
                  </div>
                )) : <p className="rounded-2xl border border-dashed border-[var(--exa-border)] p-4 text-sm text-[var(--exa-text-muted)]">Approved market makers can create shadow-tested bots here before admin-controlled live activation.</p>}
              </div>
            </div>
          </Panel>
        </section>

        <section className="mt-4">
          <Panel title="OTC / RFQ Block Trading" icon={HandCoins}>
            <div className="grid gap-4 lg:grid-cols-[0.9fr_1.1fr]">
              <form onSubmit={submitOtcRfq} className="grid gap-3 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                <Select label="Trading subaccount" value={otcForm.subaccount_id} onChange={(value) => setOtcForm((current) => ({ ...current, subaccount_id: value }))} options={tradingSubaccounts.map((item) => ({ value: item.id, label: `${item.name} (${item.type})` }))} />
                <div className="grid gap-3 sm:grid-cols-[1fr_130px]">
                  <Input label="Symbol" value={otcForm.symbol} onChange={(value) => setOtcForm((current) => ({ ...current, symbol: value.toUpperCase() }))} />
                  <Select label="Side" value={otcForm.side} onChange={(value) => setOtcForm((current) => ({ ...current, side: value }))} options={["BUY", "SELL"]} />
                </div>
                <Input label="Base amount" value={otcForm.base_amount} onChange={(value) => setOtcForm((current) => ({ ...current, base_amount: value }))} inputMode="decimal" required />
                <button disabled={!institution || !otcForm.subaccount_id || !otcForm.base_amount || busy === "otc-rfq"} className="rounded-xl bg-[var(--exa-gold)] px-4 py-3 text-sm font-semibold text-black disabled:cursor-not-allowed disabled:opacity-50">
                  {busy === "otc-rfq" ? "Requesting..." : "Request Firm Quote"}
                </button>
              </form>
              <div className="space-y-3">
                {otcRfqs.length ? otcRfqs.slice(0, 5).map((rfq) => {
                  const quotes = rows(rfq.quotes);
                  const bestQuote = quotes.find((quote) => quote.status === "VALID") || quotes[0];
                  const canAccept = rfq.status === "QUOTED" && bestQuote;
                  return (
                    <div key={rfq.rfq_uuid} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                      <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                          <h3 className="font-['Sora'] text-sm font-semibold">{rfq.side} {rfq.base_amount} {rfq.base_asset}</h3>
                          <p className="mt-1 text-xs text-[var(--exa-text-muted)]">{rfq.symbol} - {rfq.execution_preference || "BEST_PRICE"}</p>
                        </div>
                        <StatusPill value={rfq.status} compact />
                      </div>
                      <div className="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                        <MiniStat label="Client price" value={bestQuote?.client_price || "-"} />
                        <MiniStat label="Fee" value={bestQuote?.fee_amount || "-"} />
                        <MiniStat label="Valid until" value={bestQuote?.valid_until ? new Date(bestQuote.valid_until).toLocaleTimeString() : "-"} />
                      </div>
                      <button type="button" disabled={!canAccept || busy === `otc-accept-${rfq.rfq_uuid}`} onClick={() => acceptBestOtcQuote(rfq)} className="mt-3 w-full rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-4 py-2.5 text-sm font-semibold text-[var(--exa-gold-light)] disabled:cursor-not-allowed disabled:opacity-50">
                        {busy === `otc-accept-${rfq.rfq_uuid}` ? "Accepting..." : "Accept Best Quote"}
                      </button>
                    </div>
                  );
                }) : <p className="rounded-2xl border border-dashed border-[var(--exa-border)] p-4 text-sm text-[var(--exa-text-muted)]">RFQs appear here after your institution requests block liquidity.</p>}
              </div>
            </div>
          </Panel>
        </section>

        <section className="mt-4 grid gap-4 lg:grid-cols-2">
          <Panel title="Treasury Transfer" icon={WalletCards}>
            <form onSubmit={submitTransfer} className="grid gap-3">
              <Select label="From" value={transferForm.source_subaccount_uuid} onChange={(value) => setTransferForm((current) => ({ ...current, source_subaccount_uuid: value }))} options={subaccounts.map((item) => ({ value: item.subaccount_uuid, label: item.name }))} />
              <Select label="To" value={transferForm.destination_subaccount_uuid} onChange={(value) => setTransferForm((current) => ({ ...current, destination_subaccount_uuid: value }))} options={subaccounts.map((item) => ({ value: item.subaccount_uuid, label: item.name }))} />
              <div className="grid gap-3 sm:grid-cols-[120px_1fr]">
                <Input label="Asset" value={transferForm.asset} onChange={(value) => setTransferForm((current) => ({ ...current, asset: value.toUpperCase() }))} />
                <Input label="Amount" value={transferForm.amount} onChange={(value) => setTransferForm((current) => ({ ...current, amount: value }))} inputMode="decimal" />
              </div>
              <button disabled={!institution || busy === "transfer"} className="rounded-xl bg-[var(--exa-gold)] px-4 py-3 text-sm font-semibold text-black disabled:cursor-not-allowed disabled:opacity-50">
                {busy === "transfer" ? "Submitting..." : "Submit Transfer"}
              </button>
            </form>
          </Panel>

          <Panel title="Apply for Institutional Access" icon={BadgeCheck}>
            <form onSubmit={submitApplication} className="grid gap-3">
              <Input label="Institution name" value={form.legal_company_name} onChange={(value) => setForm((current) => ({ ...current, legal_company_name: value }))} required />
              <div className="grid gap-3 sm:grid-cols-2">
                <Input label="Jurisdiction" value={form.incorporation_country} onChange={(value) => setForm((current) => ({ ...current, incorporation_country: value }))} required />
                <Input label="Monthly spot volume" value={form.expected_monthly_spot_volume} onChange={(value) => setForm((current) => ({ ...current, expected_monthly_spot_volume: value }))} inputMode="decimal" />
              </div>
              <Input label="Contact name" value={form.contact_person} onChange={(value) => setForm((current) => ({ ...current, contact_person: value }))} required />
              <Input label="Business email" value={form.business_email} onChange={(value) => setForm((current) => ({ ...current, business_email: value }))} type="email" />
              <button disabled={busy === "application"} className="rounded-xl bg-[var(--exa-gold)] px-4 py-3 text-sm font-semibold text-black">
                {busy === "application" ? "Submitting..." : "Submit Application"}
              </button>
            </form>
          </Panel>
        </section>

        {loading ? (
          <div className="fixed inset-x-0 bottom-4 mx-auto flex w-max items-center gap-2 rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface)] px-4 py-2 text-sm text-[var(--exa-text-secondary)] shadow-[var(--exa-shadow-panel)]">
            <Loader2 className="h-4 w-4 animate-spin text-[var(--exa-gold-light)]" /> Syncing institutional workspace
          </div>
        ) : null}
      </div>
    </div>
  );
}

function Panel({ title, icon: Icon, children, className = "" }) {
  return (
    <section className={`rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-5 ${className}`}>
      <div className="mb-4 flex items-center gap-2">
        <Icon className="h-5 w-5 text-[var(--exa-gold-light)]" />
        <h2 className="font-['Sora'] text-base font-semibold">{title}</h2>
      </div>
      {children}
    </section>
  );
}

function Metric({ icon: Icon, label, value }) {
  return (
    <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
      <Icon className="h-5 w-5 text-[var(--exa-gold-light)]" />
      <p className="mt-3 text-xs uppercase tracking-[0.16em] text-[var(--exa-text-muted)]">{label}</p>
      <strong className="mt-1 block text-sm">{value}</strong>
    </div>
  );
}

function MiniStat({ label, value }) {
  return (
    <div className="rounded-xl bg-[var(--exa-surface-elevated)] px-3 py-2">
      <span className="block text-[10px] uppercase tracking-[0.12em] text-[var(--exa-text-muted)]">{label}</span>
      <strong className="mt-1 block truncate font-mono text-xs text-[var(--exa-text-primary)]">{value}</strong>
    </div>
  );
}

function StatusPill({ value, compact = false }) {
  return <span className={`rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] font-semibold uppercase tracking-[0.12em] text-[var(--exa-gold-light)] ${compact ? "px-2 py-1 text-[10px]" : "px-3 py-1.5 text-xs"}`}>{value}</span>;
}

function Input({ label, value, onChange, ...props }) {
  return (
    <label className="block">
      <span className="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[var(--exa-text-muted)]">{label}</span>
      <input value={value} onChange={(event) => onChange(event.target.value)} className="h-11 w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 text-sm text-[var(--exa-text-primary)] outline-none transition focus:border-[var(--exa-border-active)]" {...props} />
    </label>
  );
}

function Select({ label, value, onChange, options }) {
  const normalized = options.map((option) => typeof option === "string" ? { value: option, label: option } : option);
  return (
    <label className="block">
      <span className="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[var(--exa-text-muted)]">{label}</span>
      <select value={value} onChange={(event) => onChange(event.target.value)} className="h-11 w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 text-sm text-[var(--exa-text-primary)] outline-none transition focus:border-[var(--exa-border-active)]">
        {normalized.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
      </select>
    </label>
  );
}
