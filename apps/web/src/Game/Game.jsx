import { memo, useCallback, useEffect, useMemo, useRef, useState } from "react";
import { ArrowLeft, History, Plane, RefreshCw, ShieldCheck, Wallet } from "lucide-react";
import { useAuth } from "../context/AuthContext";
import { onEvent } from "../services/webSocketService";

const toNumber = (value) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
};

const cleanInput = (value) => String(value ?? "").replace(/[^0-9.]/g, "");
const formatMoney = (value, asset = "USDT") => `${toNumber(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 8 })} ${asset}`;
const formatMultiplier = (value) => `${toNumber(value).toFixed(2)}Ã—`;
const formatTime = (value) => {
  if (!value) return "--";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
};

function computeMultiplier(round, nowMs) {
  if (!round?.starts_at || !round?.growth_rate) return 1;
  const startMs = new Date(round.starts_at).getTime();
  const crashMs = new Date(round.crashes_at).getTime();
  const crash = toNumber(round.crash_multiplier || round.current_multiplier || 1);
  if (round.status === "completed" || nowMs >= crashMs) return crash;
  if (nowMs <= startMs) return 1;
  const elapsedSeconds = Math.max(0, (nowMs - startMs) / 1000);
  return Math.min(Math.exp(toNumber(round.growth_rate) * elapsedSeconds), crash || 1);
}

const stageLabel = (multiplier) => multiplier >= 20 ? "Orbit" : multiplier >= 5 ? "Stratosphere" : multiplier >= 2 ? "Cruising" : "Takeoff";
const roundHeadline = (round) => !round ? "Preparing runway..." : round.status === "betting" ? "Boarding is open" : round.status === "running" ? "Round active" : `Flight completed at ${formatMultiplier(round.crash_multiplier || round.current_multiplier || 1)}`;
const emptyPanel = () => ({ amount: "10", autoCollect: "2.00" });
const displayEntryStatus = (status, roundStatus) => status === "cashed_out" ? "Collected" : status === "lost" ? "Round Ended" : status === "cancelled" ? "Cancelled" : status === "placed" && roundStatus === "running" ? "Active" : status === "placed" ? "Waiting" : status;

const FlightStageScene = memo(function FlightStageScene({ multiplier, roundStatus, stage }) {
  const progress = roundStatus === "betting" ? 0.04 : roundStatus === "running" ? Math.min(1, Math.log(Math.max(1, toNumber(multiplier))) / Math.log(25)) : 0;
  const hasCrashed = roundStatus === "completed";
  const craftX = 112 + progress * 468;
  const craftY = roundStatus === "running" ? 214 - Math.min(148, Math.log(Math.max(1, multiplier)) * 43) : 214;
  const craftScale = 1.56 + progress * 0.98;
  const craftRotate = roundStatus === "running" ? -9 - progress * 15 : -4;
  const trailWidth = 6 + progress * 12;
  const glow = 0.25 + progress * 0.42;
  const stars = stage === "Orbit" ? 1 : stage === "Stratosphere" ? 0.72 : 0.18;
  const cloudOpacity = stage === "Takeoff" ? 0.34 : stage === "Cruising" ? 0.22 : 0.06;

  return (
    <div className="mt-4 h-80 w-full max-w-6xl sm:h-96">
      <svg viewBox="0 0 720 320" className="h-full w-full overflow-visible">
        <defs>
          <linearGradient id="aviation-sky" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stopColor="#11224d" /><stop offset="48%" stopColor="#0a1735" /><stop offset="100%" stopColor="#060b17" /></linearGradient>
          <linearGradient id="aviation-trail" x1="0" x2="1" y1="0.5" y2="0.5"><stop offset="0%" stopColor="rgba(83,126,255,0.05)" /><stop offset="40%" stopColor="rgba(110,168,255,0.48)" /><stop offset="100%" stopColor="rgba(248,200,74,1)" /></linearGradient>
          <linearGradient id="aviation-hull" x1="0" x2="1" y1="0.2" y2="0.8"><stop offset="0%" stopColor="#f8fbff" /><stop offset="45%" stopColor="#d9e4ff" /><stop offset="100%" stopColor="#6f8fff" /></linearGradient>
          <linearGradient id="aviation-accent" x1="0" x2="1" y1="0" y2="1"><stop offset="0%" stopColor="#6ea8ff" /><stop offset="100%" stopColor="#f8c84a" /></linearGradient>
          <radialGradient id="aviation-engine" cx="50%" cy="50%" r="50%"><stop offset="0%" stopColor="#fff7d1" /><stop offset="52%" stopColor="#ffc764" /><stop offset="100%" stopColor="rgba(255,199,100,0)" /></radialGradient>
          <filter id="aviation-glow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="11" result="blur" /><feMerge><feMergeNode in="blur" /><feMergeNode in="SourceGraphic" /></feMerge></filter>
        </defs>
        <rect x="0" y="0" width="720" height="320" rx="32" fill="url(#aviation-sky)" />
        <ellipse cx="120" cy="264" rx="164" ry="28" fill="rgba(71,101,189,0.16)" />
        <ellipse cx="370" cy="284" rx="310" ry="50" fill="rgba(7,13,28,0.94)" />
        <path d="M58 248 C 162 244, 248 226, 334 186 S 504 102, 666 42" fill="none" stroke="url(#aviation-trail)" strokeWidth={trailWidth} strokeLinecap="round" opacity="0.98" />
        <path d="M58 248 C 162 244, 248 226, 334 186 S 504 102, 666 42" fill="none" stroke="rgba(255,255,255,0.12)" strokeWidth="1.5" strokeDasharray="10 10" opacity="0.4" />
        <g opacity={cloudOpacity}><ellipse cx="152" cy="188" rx="54" ry="15" fill="#dae7ff" /><ellipse cx="190" cy="178" rx="42" ry="11" fill="#dae7ff" /><ellipse cx="522" cy="130" rx="62" ry="17" fill="#d4e4ff" /><ellipse cx="568" cy="118" rx="38" ry="10" fill="#d4e4ff" /></g>
        <g opacity={stars}><circle cx="138" cy="54" r="1.8" fill="#d6e5ff" /><circle cx="222" cy="68" r="1.4" fill="#f8e9a8" /><circle cx="338" cy="34" r="1.8" fill="#c8ddff" /><circle cx="462" cy="58" r="1.5" fill="#e8f1ff" /><circle cx="574" cy="38" r="1.7" fill="#f5e6a2" /><circle cx="634" cy="82" r="1.2" fill="#d6e5ff" /></g>
        {hasCrashed ? <g opacity="0.96"><circle cx={craftX + 28} cy={craftY + 4} r="30" fill="rgba(248,200,74,0.26)" /><circle cx={craftX + 28} cy={craftY + 4} r="15" fill="rgba(255,255,255,0.34)" /><path d={`M ${craftX - 10} ${craftY + 10} L ${craftX + 16} ${craftY - 22} L ${craftX + 34} ${craftY + 4} L ${craftX + 60} ${craftY - 14} L ${craftX + 40} ${craftY + 24}`} fill="none" stroke="rgba(248,200,74,0.95)" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round" /><path d={`M ${craftX + 2} ${craftY + 30} L ${craftX + 28} ${craftY + 8} L ${craftX + 54} ${craftY + 32}`} fill="none" stroke="rgba(110,168,255,0.72)" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" /></g> : null}
        <g transform={`translate(${craftX} ${craftY}) rotate(${craftRotate}) scale(${craftScale})`} style={{ transformOrigin: `${craftX}px ${craftY}px` }} opacity={hasCrashed ? 0.42 : 1}>
          <ellipse cx="-70" cy="16" rx="48" ry="22" fill={`rgba(110,168,255,${glow})`} filter="url(#aviation-glow)" />
          <ellipse cx="-98" cy="18" rx="36" ry="16" fill="url(#aviation-engine)" />
          <path d="M-104 18 C -146 12, -172 10, -208 16" stroke="rgba(111,143,255,0.35)" strokeWidth="12" strokeLinecap="round" />
          <path d="M-104 18 C -146 12, -172 10, -208 16" stroke="rgba(255,204,102,0.78)" strokeWidth="5.6" strokeLinecap="round" />
          <path d="M-96 0 C -74 -16, -18 -22, 70 -12 C 112 -8, 136 0, 154 12 C 138 26, 112 34, 66 38 C -6 44, -66 36, -96 16 Z" fill="url(#aviation-hull)" stroke="rgba(255,255,255,0.48)" strokeWidth="1.5" />
          <path d="M-14 -14 C 10 -24, 42 -24, 86 -12" stroke="url(#aviation-accent)" strokeWidth="5.5" strokeLinecap="round" opacity="0.95" />
          <path d="M18 16 C 50 12, 88 14, 124 22" stroke="rgba(10,19,43,0.35)" strokeWidth="4" strokeLinecap="round" opacity="0.55" />
          <path d="M10 6 L -52 50 L 22 28 L 90 34 Z" fill="rgba(111,143,255,0.92)" stroke="rgba(255,255,255,0.35)" strokeWidth="1.3" />
          <path d="M0 -4 L -56 -36 L 14 -14 L 86 -14 Z" fill="rgba(248,200,74,0.88)" stroke="rgba(255,255,255,0.3)" strokeWidth="1.3" />
          <path d="M-78 -4 L -118 -42 L -62 -12 Z" fill="rgba(74,109,220,0.95)" stroke="rgba(255,255,255,0.22)" strokeWidth="1.1" />
          <path d="M-74 12 L -126 28 L -60 28 Z" fill="rgba(248,200,74,0.82)" stroke="rgba(255,255,255,0.18)" strokeWidth="1" />
          <ellipse cx="102" cy="4" rx="28" ry="9" fill="rgba(7,16,31,0.7)" />
          <path d="M90 -2 C 102 -14, 120 -14, 136 -6 C 124 2, 112 6, 90 7 Z" fill="#0d214f" stroke="rgba(162,194,255,0.55)" strokeWidth="1" />
          <circle cx="2" cy="6" r="13" fill="#08152b" stroke="rgba(248,200,74,0.85)" strokeWidth="2.2" />
          <text x="2" y="10" textAnchor="middle" fontSize="8" fontWeight="700" fill="#f8c84a">EE</text>
          <text x="22" y="11" fontSize="13" fontWeight="800" fill="#f7fbff" style={{ letterSpacing: "0.12em" }}>EXAEARN</text>
          <path d="M22 16 H 96" stroke="rgba(248,200,74,0.95)" strokeWidth="2.2" strokeLinecap="round" />
        </g>
      </svg>
    </div>
  );
});

function Game({ onBack }) {
  const { request, user, authReady } = useAuth();
  const [state, setState] = useState(null);
  const [balances, setBalances] = useState([]);
  const [selectedAsset, setSelectedAsset] = useState("USDT");
  const [panels, setPanels] = useState([emptyPanel(), emptyPanel()]);
  const [submitting, setSubmitting] = useState({});
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [selectedHistory, setSelectedHistory] = useState(null);
  const [serverOffset, setServerOffset] = useState(0);
  const [displayMultiplier, setDisplayMultiplier] = useState(1);
  const rafRef = useRef(null);
  const lastFrameRef = useRef(0);
  const fetchBalances = useCallback(async () => {
    if (!user) {
      setBalances([]);
      return;
    }
    try {
      const payload = await request("/api/wallet/balances", { method: "GET", timeoutMs: 10000 });
      setBalances(Array.isArray(payload?.data) ? payload.data : []);
    } catch {
      // keep current balances when unavailable
    }
  }, [request, user]);

  const fetchState = useCallback(async () => {
    const payload = await request("/api/games/flight/state", { method: "GET", timeoutMs: 10000 });
    const next = payload?.data ?? null;
    setState(next);
    if (next?.server_time) setServerOffset(new Date(next.server_time).getTime() - Date.now());
    if (Array.isArray(next?.settings?.enabled_assets) && next.settings.enabled_assets.length > 0) {
      setSelectedAsset((current) => next.settings.enabled_assets.includes(current) ? current : next.settings.enabled_assets[0]);
    } else if (next?.settings?.default_asset && !selectedAsset) setSelectedAsset(next.settings.default_asset);
  }, [request, selectedAsset]);

  useEffect(() => {
    fetchState().catch((loadError) => setError(loadError.message || "Unable to load Aviation state."));
    fetchBalances();
  }, [fetchBalances, fetchState]);


  useEffect(() => {
    const stateInterval = window.setInterval(() => fetchState().catch(() => undefined), 8000);
    const balanceInterval = user ? window.setInterval(() => fetchBalances(), 30000) : null;
    return () => {
      window.clearInterval(stateInterval);
      if (balanceInterval) window.clearInterval(balanceInterval);
    };
  }, [fetchBalances, fetchState, user]);

  useEffect(() => {
    const unsubscribe = onEvent("game.flight", (payload) => {
      const event = payload?.event;
      const roundPayload = payload?.data?.round;
      const betPayload = payload?.data?.bet;
      if (!event) return;

      setState((current) => {
        if (!current) return current;
        const next = { ...current };
        if (roundPayload) next.round = roundPayload;
        if (event === "game.round.crashed" && roundPayload) {
          const existing = Array.isArray(current.history) ? current.history : [];
          next.history = [roundPayload, ...existing.filter((item) => item.round_uuid !== roundPayload.round_uuid)].slice(0, 20);
          setSelectedHistory((selected) => selected ?? roundPayload);
        }
        if (event === "game.bet.accepted" && betPayload) {
          const liveRows = Array.isArray(current.live_bets) ? current.live_bets : [];
          next.live_bets = [betPayload, ...liveRows.filter((item) => item.bet_uuid !== betPayload.bet_uuid)].slice(0, 20);
        }
        if (event === "game.cashout.accepted" && betPayload) {
          const liveRows = Array.isArray(current.live_bets) ? current.live_bets : [];
          next.live_bets = liveRows.map((item) => (item.bet_uuid === betPayload.bet_uuid ? betPayload : item));
        }
        return next;
      });

      if (["game.round.betting", "game.round.started", "game.round.crashed"].includes(event)) fetchState().catch(() => undefined);
      if (["game.bet.accepted", "game.cashout.accepted"].includes(event)) fetchBalances();
    });

    return unsubscribe;
  }, [fetchBalances, fetchState]);


  useEffect(() => {
    const tick = () => {
      const now = performance.now();
      if (state?.round && now - lastFrameRef.current >= 48) {
        setDisplayMultiplier(computeMultiplier(state.round, Date.now() + serverOffset));
        lastFrameRef.current = now;
      }
      rafRef.current = window.requestAnimationFrame(tick);
    };
    rafRef.current = window.requestAnimationFrame(tick);
    return () => {
      if (rafRef.current) window.cancelAnimationFrame(rafRef.current);
    };
  }, [serverOffset, state]);

  const round = state?.round ?? null;
  const history = state?.history ?? [];
  const myEntries = state?.my_bets ?? [];
  const liveActivity = state?.live_bets ?? [];
  const currentStage = stageLabel(displayMultiplier);
  const enabledAssets = state?.settings?.enabled_assets || [selectedAsset];
  const balanceEntry = balances.find((item) => item.asset === selectedAsset || item.currency === selectedAsset || item.symbol === selectedAsset);
  const assetBalance = toNumber(balanceEntry?.available ?? balanceEntry?.balance ?? 0);
  const historySelection = selectedHistory || history[0] || null;
  const canEnterAmount = assetBalance > 0;

  const myOpenEntries = useMemo(() => {
    const currentRound = state?.round?.round_uuid;
    return myEntries.filter((entry) => entry.round_uuid === currentRound);
  }, [myEntries, state]);

  const handlePanelChange = useCallback((index, field, value) => {
    setPanels((current) => current.map((panel, panelIndex) => panelIndex === index ? { ...panel, [field]: cleanInput(value) } : panel));
  }, []);

  const applyAmountPreset = useCallback((index, percentage) => {
    const computed = assetBalance > 0 ? (assetBalance * percentage) / 100 : 0;
    const formatted = computed > 0 ? computed.toFixed(8).replace(/\.?0+$/, "") : "0";
    handlePanelChange(index, "amount", formatted);
  }, [assetBalance, handlePanelChange]);

  const panelError = useCallback((index) => {
    const panel = panels[index];
    const amount = toNumber(panel.amount);
    if (!authReady) return "Checking session...";
    if (!user) return "Sign in to enter a round.";
    if (!round || round.status !== "betting") return "This round is not accepting new entries right now.";
    if (!amount || amount <= 0) return "Enter a valid entry amount.";
    if (amount > assetBalance) return `Available balance is ${formatMoney(assetBalance, selectedAsset)}.`;
    return "";
  }, [assetBalance, authReady, panels, round, selectedAsset, user]);

  const enterRound = async (index) => {
    const issue = panelError(index);
    if (issue) {
      setError(issue);
      setMessage("");
      return;
    }

    const panel = panels[index];
    setError("");
    setMessage("");
    setSubmitting((current) => ({ ...current, [`entry-${index}`]: true }));

    try {
      const payload = await request("/api/games/flight/bets", {
        method: "POST",
        timeoutMs: 12000,
        body: JSON.stringify({ asset: selectedAsset, stake: panel.amount, panel_slot: index + 1, auto_cashout: panel.autoCollect || null }),
        headers: { "X-Idempotency-Key": `flight-entry-${index + 1}-${Date.now()}` },
      });
      setMessage(`Entry confirmed for ${formatMoney(payload?.data?.stake || panel.amount, selectedAsset)}.`);
      await Promise.all([fetchState(), fetchBalances()]);
    } catch (actionError) {
      setError(actionError.message || "Unable to enter this round.");
    } finally {
      setSubmitting((current) => ({ ...current, [`entry-${index}`]: false }));
    }
  };

  const collectReward = async (betUuid) => {
    if (!user) {
      setError("Sign in to collect a round reward.");
      return;
    }
    setError("");
    setMessage("");
    setSubmitting((current) => ({ ...current, [betUuid]: true }));

    try {
      const payload = await request(`/api/games/flight/bets/${betUuid}/cashout`, { method: "POST", timeoutMs: 12000 });
      setMessage(`Reward collected at ${formatMultiplier(payload?.data?.cashout_multiplier || 1)}.`);
      await Promise.all([fetchState(), fetchBalances()]);
    } catch (actionError) {
      setError(actionError.message || "Unable to collect this reward.");
    } finally {
      setSubmitting((current) => ({ ...current, [betUuid]: false }));
    }
  };

  return (
    <main className="min-h-screen bg-[var(--exa-bg-primary)] px-4 py-5 text-[var(--exa-text-primary)] sm:px-6 lg:px-8">
      <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-4 py-3">
          <div className="flex items-center gap-3">
            {onBack ? <button type="button" onClick={onBack} className="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-primary)]"><ArrowLeft className="h-4 w-4" /></button> : null}
            <div><p className="text-xs uppercase tracking-[0.28em] text-[var(--exa-text-muted)]">ExaEarn Games</p><h1 className="text-xl font-semibold sm:text-2xl">EXAEARN Aviation</h1></div>
          </div>
          <div className="flex flex-wrap items-center gap-2 text-xs text-[#a7b2d6]">
            <span className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-1.5">Play</span>
            <span className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-1.5">My Entries</span>
            <span className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-1.5">Round History</span>
            <span className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-1.5">Game Rules</span>
            <button type="button" onClick={() => fetchState().catch((loadError) => setError(loadError.message || "Unable to refresh the round."))} className="inline-flex items-center gap-2 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-sm text-[var(--exa-text-secondary)]"><RefreshCw className="h-4 w-4" /> Refresh</button>
          </div>
        </div>
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_390px]">
          <section className="overflow-hidden rounded-[28px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 sm:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div><p className="text-xs uppercase tracking-[0.28em] text-[var(--exa-text-muted)]">Game Balance</p><div className="mt-1 flex items-center gap-2 text-lg font-semibold text-[#f4f7ff]"><Wallet className="h-4 w-4 text-[var(--exa-gold)]" />{formatMoney(assetBalance, selectedAsset)}</div></div>
              <div className="flex items-center gap-2 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2"><select value={selectedAsset} onChange={(event) => setSelectedAsset(event.target.value)} className="bg-transparent text-sm font-medium outline-none">{enabledAssets.map((asset) => <option key={asset} value={asset} className="bg-[var(--exa-surface-elevated)] text-[var(--exa-text-primary)]">{asset}</option>)}</select></div>
            </div>

            <div className="mt-4 flex gap-2 overflow-x-auto pb-1">{history.slice(0, 10).map((item) => <button key={item.round_uuid} type="button" onClick={() => setSelectedHistory(item)} className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-1 text-xs text-[var(--exa-text-secondary)]">{formatMultiplier(item.crash_multiplier || 1)}</button>)}</div>

            <div className="relative mt-4 overflow-hidden rounded-[30px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-5 sm:px-6 sm:py-8">
              <div className="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-[#0c1430] to-transparent" />
              <div className="absolute inset-0 opacity-70" style={{ backgroundImage: "radial-gradient(circle at 20% 20%, rgba(53,105,255,0.25), transparent 25%), radial-gradient(circle at 80% 10%, rgba(96,255,210,0.12), transparent 20%)" }} />
              <div className="relative z-10 flex flex-col items-center text-center">
                <div className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-1 text-xs uppercase tracking-[0.28em] text-[var(--exa-gold-light)]">{currentStage}</div>
                <div className="mt-5 text-5xl font-semibold tracking-tight sm:text-7xl">{formatMultiplier(displayMultiplier)}</div>
                <p className="mt-3 text-sm text-[#9aa7cf]">{roundHeadline(round)}</p>
                <div className="mt-5 flex flex-wrap items-center justify-center gap-4 text-xs text-[#b3bddf]"><span>Round #{round?.round_number ?? "--"}</span><span>Total Entries {round?.players ?? 0}</span><span>Total Entry Value {formatMoney(round?.total_stake || 0, round?.asset || selectedAsset)}</span></div>
                <FlightStageScene multiplier={displayMultiplier} roundStatus={round?.status} stage={currentStage} />
                <div className="inline-flex items-center gap-2 rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-2 text-sm text-[var(--exa-text-secondary)]"><Plane className="h-4 w-4 text-[var(--exa-gold)]" />EXAEARN server-authoritative round timing</div>
              </div>
            </div>
          </section>

          <aside className="space-y-4">
            {[0, 1].map((index) => {
              const panel = panels[index];
              const liveEntry = myOpenEntries.find((entry) => entry.panel_slot === index + 1);
              const isRunning = round?.status === "running";
              const issue = panelError(index);
              const entryAmount = toNumber(panel.amount);
              const previewMultiplier = toNumber(panel.autoCollect || displayMultiplier || 1);
              const previewReward = liveEntry && liveEntry.status === "placed" && isRunning ? toNumber(liveEntry.stake) * displayMultiplier : entryAmount > 0 ? entryAmount * Math.max(1, previewMultiplier) : 0;
              const cta = liveEntry ? liveEntry.status === "placed" && isRunning ? `Collect ${formatMoney(toNumber(liveEntry.stake) * displayMultiplier, liveEntry.asset)}` : liveEntry.status === "cashed_out" ? "Reward Collected" : liveEntry.status === "lost" ? "Round Ended" : "Entry Confirmed" : !authReady ? "Checking session..." : !user ? "Sign in to enter" : round?.status === "running" ? "Round in flight" : round?.status !== "betting" ? "Next round soon" : "Enter Round";

              return (
                <div key={index} className="rounded-[28px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                  <div className="flex items-center justify-between"><div><p className="text-xs uppercase tracking-[0.24em] text-[var(--exa-text-muted)]">Entry {index + 1}</p><h3 className="mt-1 text-lg font-semibold">{selectedAsset}</h3></div><div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-1 text-xs text-[var(--exa-text-muted)]">Auto Collect {panel.autoCollect || "--"}Ã—</div></div>
                  <div className="mt-4 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3">
                    <div className="flex items-center justify-between text-xs text-[var(--exa-text-muted)]"><span>Entry Amount</span><span>Available {formatMoney(assetBalance, selectedAsset)}</span></div>
                    <div className="mt-2 flex items-center gap-2"><input value={panel.amount} onChange={(event) => handlePanelChange(index, "amount", event.target.value)} className="w-full bg-transparent text-2xl font-semibold outline-none" /><span className="text-sm text-[#9fb4ff]">{selectedAsset}</span></div>
                    <div className="mt-3 flex flex-wrap gap-2">{[1, 5, 10, 50].map((amount) => <button key={amount} type="button" onClick={() => handlePanelChange(index, "amount", String(toNumber(panel.amount || 0) + amount))} className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-1 text-xs text-[var(--exa-text-secondary)]">+{amount}</button>)}</div>
                  </div>
                  <div className="mt-3 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3">
                    <div className="flex items-center justify-between text-xs text-[var(--exa-text-muted)]"><span>Auto Collect</span><span>Server-side execution</span></div>
                    <div className="mt-2 flex items-center gap-2"><input value={panel.autoCollect} inputMode="decimal" onChange={(event) => handlePanelChange(index, "autoCollect", event.target.value)} className="w-full bg-transparent text-xl font-semibold outline-none" /><span className="text-sm text-[#9fb4ff]">Ã—</span></div>
                  </div>
                  <div className="mt-3 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3 text-sm text-[var(--exa-text-secondary)]">
                    <div className="flex items-center justify-between"><span className="text-[var(--exa-text-muted)]">Potential Reward</span><span>{formatMoney(previewReward, selectedAsset)}</span></div>
                    <div className="mt-2 flex items-center justify-between"><span className="text-[var(--exa-text-muted)]">Current Multiplier</span><span>{formatMultiplier(displayMultiplier)}</span></div>
                    {liveEntry ? <div className="mt-2 flex items-center justify-between"><span className="text-[var(--exa-text-muted)]">Entry Status</span><span>{displayEntryStatus(liveEntry.status, round?.status)}</span></div> : null}
                  </div>
                  {issue && !liveEntry ? <p className="mt-3 text-xs text-amber-200">{issue}</p> : null}
                  <button type="button" onClick={() => (liveEntry && liveEntry.status === "placed" && isRunning ? collectReward(liveEntry.bet_uuid) : enterRound(index))} disabled={Boolean(submitting[`entry-${index}`] || (liveEntry && submitting[liveEntry.bet_uuid]) || (!liveEntry && issue))} className={`mt-4 w-full rounded-2xl px-4 py-3 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 ${liveEntry && liveEntry.status === "placed" && isRunning ? "bg-[#19c37d] text-[#04110b]" : "bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] text-[var(--exa-gold-contrast)]"}`}>
                    {submitting[`entry-${index}`] || (liveEntry && submitting[liveEntry.bet_uuid]) ? (liveEntry ? "Collecting..." : "Entering...") : cta}
                  </button>
                </div>
              );
            })}
          </aside>
        </div>

        {(message || error) ? <div className={`rounded-2xl border px-4 py-3 text-sm ${error ? "border-red-500/30 bg-red-500/10 text-red-200" : "border-emerald-500/30 bg-emerald-500/10 text-emerald-200"}`}>{error || message}</div> : null}
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
          <section className="rounded-[28px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
            <div className="flex items-center justify-between"><h2 className="text-lg font-semibold">Live Round Activity</h2><span className="text-xs text-[var(--exa-text-muted)]">Realtime feed</span></div>
            <div className="mt-4 overflow-hidden rounded-2xl border border-[var(--exa-border)]">
              <div className="grid grid-cols-4 bg-[var(--exa-surface-elevated)] px-3 py-2 text-xs uppercase tracking-[0.18em] text-[var(--exa-text-muted)]"><span>Player</span><span>Entry</span><span>Collect</span><span>Result</span></div>
              <div className="divide-y divide-white/5">{liveActivity.map((entry) => <div key={entry.bet_uuid} className="grid grid-cols-4 px-3 py-3 text-sm text-[var(--exa-text-secondary)]"><span>{entry.player}</span><span>{formatMoney(entry.stake, entry.asset)}</span><span>{entry.cashout_multiplier ? formatMultiplier(entry.cashout_multiplier) : "--"}</span><span>{displayEntryStatus(entry.status, round?.status)}</span></div>)}</div>
            </div>

            <div className="mt-4">
              <h3 className="text-base font-semibold">My Entries</h3>
              <div className="mt-3 grid gap-2">{myEntries.map((entry) => <div key={entry.bet_uuid} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-3 text-sm"><div className="flex items-center justify-between"><span>Round #{entry.round_number}</span><span className="text-[var(--exa-text-muted)]">{displayEntryStatus(entry.status, round?.status)}</span></div><div className="mt-2 grid gap-1 text-[var(--exa-text-secondary)] sm:grid-cols-2"><span>Entry Amount: {formatMoney(entry.stake, entry.asset)}</span><span>Collect Multiplier: {entry.cashout_multiplier ? formatMultiplier(entry.cashout_multiplier) : "--"}</span><span>Reward: {formatMoney(entry.payout, entry.asset)}</span><span>Time: {formatTime(entry.settled_at || entry.placed_at)}</span></div></div>)}</div>
            </div>
          </section>

          <section className="space-y-4 rounded-[28px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
            <div className="flex items-center gap-2"><ShieldCheck className="h-5 w-5 text-[#8fe3b8]" /><h2 className="text-lg font-semibold">Fairness Verification</h2></div>
            <p className="text-sm text-[#9aa7cf]">Each round publishes a server-seed hash before takeoff and reveals the seed after completion so completed rounds can be verified independently.</p>
            <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3 text-sm text-[var(--exa-text-secondary)]"><div className="flex items-center justify-between"><span>Round</span><span>#{historySelection?.round_number ?? round?.round_number ?? "--"}</span></div><div className="mt-2 flex items-center justify-between"><span>Hash</span><span className="max-w-[180px] truncate">{historySelection?.server_seed_hash || round?.server_seed_hash || "--"}</span></div><div className="mt-2 flex items-center justify-between"><span>Multiplier</span><span>{historySelection?.crash_multiplier ? formatMultiplier(historySelection.crash_multiplier) : "Pending"}</span></div><div className="mt-2 flex items-center justify-between"><span>Completed</span><span>{formatTime(historySelection?.settled_at)}</span></div></div>
            <button
              type="button"
              onClick={async () => {
                try {
                  if (!historySelection?.round_uuid && !round?.round_uuid) return;
                  const target = historySelection?.round_uuid || round?.round_uuid;
                  const payload = await request(`/api/games/flight/rounds/${target}/fairness`, { method: "GET", timeoutMs: 10000 });
                  setSelectedHistory({ ...(historySelection || round), ...payload.data });
                } catch (verificationError) {
                  setError(verificationError.message || "Unable to verify the selected round.");
                }
              }}
              className="inline-flex items-center gap-2 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-sm text-[var(--exa-text-secondary)]"
            >
              <History className="h-4 w-4" /> Verify selected round
            </button>
          </section>
        </div>
      </div>
    </main>
  );
}

export default Game;





