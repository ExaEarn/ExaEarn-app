
import { useEffect, useMemo, useState } from "react";
import Decimal from "decimal.js";
import {
  ArrowLeft,
  ArrowUpDown,
  ChevronDown,
  Search,
  Settings2,
  Sparkles,
  Star,
  X,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";

const PRODUCT_TABS = ["Convert", "Spot", "Futures", "Options", "TradFi"];
const PERCENTAGES = [25, 50, 75, 100];
const FAVORITES_KEY = "exaearn_convert_favorites";
const RECENT_KEY = "exaearn_convert_recent";
const CONVERT_PRESET_KEY = "exaearn_convert_preset";

const CRYPTO_REGISTRY = {
  BTC: { name: "Bitcoin" },
  ETH: { name: "Ethereum" },
  USDT: { name: "Tether" },
  USDC: { name: "USD Coin" },
  BNB: { name: "BNB" },
  TRX: { name: "Tron" },
  SOL: { name: "Solana" },
  XRP: { name: "XRP" },
  EXA: { name: "ExaToken" },
  TON: { name: "Toncoin" },
  MATIC: { name: "Polygon" },
};

const FIAT_REGISTRY = {
  NGN: { name: "Nigerian Naira", symbol: "?" },
  USD: { name: "US Dollar", symbol: "$" },
  EUR: { name: "Euro", symbol: "€" },
  GBP: { name: "British Pound", symbol: "£" },
  CAD: { name: "Canadian Dollar", symbol: "CA$" },
  AUD: { name: "Australian Dollar", symbol: "A$" },
  CHF: { name: "Swiss Franc", symbol: "CHF" },
  JPY: { name: "Japanese Yen", symbol: "¥" },
  CNY: { name: "Chinese Yuan", symbol: "¥" },
  ZAR: { name: "South African Rand", symbol: "R" },
  KES: { name: "Kenyan Shilling", symbol: "KSh" },
  GHS: { name: "Ghanaian Cedi", symbol: "GH?" },
  UGX: { name: "Ugandan Shilling", symbol: "USh" },
  TZS: { name: "Tanzanian Shilling", symbol: "TSh" },
  RWF: { name: "Rwandan Franc", symbol: "FRw" },
  AED: { name: "UAE Dirham", symbol: "?.?" },
  SAR: { name: "Saudi Riyal", symbol: "?" },
  INR: { name: "Indian Rupee", symbol: "?" },
  SGD: { name: "Singapore Dollar", symbol: "S$" },
  HKD: { name: "Hong Kong Dollar", symbol: "HK$" },
  NZD: { name: "New Zealand Dollar", symbol: "NZ$" },
  SEK: { name: "Swedish Krona", symbol: "kr" },
  NOK: { name: "Norwegian Krone", symbol: "kr" },
  DKK: { name: "Danish Krone", symbol: "kr" },
  PLN: { name: "Polish Zloty", symbol: "zl" },
  TRY: { name: "Turkish Lira", symbol: "?" },
  BRL: { name: "Brazilian Real", symbol: "R$" },
  MXN: { name: "Mexican Peso", symbol: "MX$" },
};

const decimal = (value) => {
  try {
    return new Decimal(value || 0);
  } catch {
    return new Decimal(0);
  }
};

const readList = (key) => {
  try {
    const raw = localStorage.getItem(key);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
};

const persistList = (key, value) => {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {
    // ignore
  }
};

const formatAmount = (value, maximumFractionDigits = 8) => {
  const amount = Number(value);
  if (!Number.isFinite(amount)) return "0";
  return amount.toLocaleString(undefined, {
    minimumFractionDigits: amount > 0 && amount < 1 ? Math.min(maximumFractionDigits, 2) : 0,
    maximumFractionDigits,
  });
};

const formatCurrencyValue = (code, value) => {
  const amount = Number(value);
  const registry = FIAT_REGISTRY[code] || { symbol: code };
  if (!Number.isFinite(amount)) return `${registry.symbol}0.00`;
  return `${registry.symbol}${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const mapError = (message) => {
  const text = String(message || "");
  const lower = text.toLowerCase();
  if (lower.includes("insufficient balance")) return "Insufficient available balance for this conversion.";
  if (lower.includes("quote expired")) return "Your quote expired. Refresh the rate and try again.";
  if (lower.includes("quote not found")) return "The quote is no longer available. Please request a new quote.";
  if (lower.includes("unsupported") || lower.includes("unavailable")) return "This conversion pair is currently unavailable.";
  if (lower.includes("unauthenticated") || lower.includes("authentication required")) return "Please sign in to continue with Convert.";
  if (lower.includes("unable to reach the api")) return "Convert is temporarily unavailable because the backend cannot be reached.";
  return text || "The request could not be completed safely. Please try again later.";
};

const mergeBalances = (assets, balances) => {
  const balanceMap = new Map((balances || []).map((item) => [String(item.currency || "").toUpperCase(), item]));
  return assets.map((asset) => ({
    ...asset,
    available_balance: String(balanceMap.get(asset.code)?.balance ?? asset.available_balance ?? "0"),
    locked_balance: String(balanceMap.get(asset.code)?.locked ?? asset.locked_balance ?? "0"),
    total_balance: String(balanceMap.get(asset.code)?.total ?? asset.total_balance ?? "0"),
  }));
};

const getAssetLabel = (asset) => {
  if (!asset) return "Select an asset";
  if (asset.type === "fiat") return FIAT_REGISTRY[asset.code]?.name || asset.code;
  return CRYPTO_REGISTRY[asset.code]?.name || asset.code;
};

const getAssetBadge = (asset) => {
  if (!asset) return "--";
  if (asset.type === "fiat") return asset.code;
  return asset.code.slice(0, 3);
};

const formatBalanceDisplay = (asset) => {
  if (!asset) return "--";
  if (asset.type === "fiat") return formatCurrencyValue(asset.code, asset.available_balance || 0);
  return `${formatAmount(asset.available_balance || 0, 8)} ${asset.code}`;
};

function Swap({ onBack, onOpenTrade, onOpenFutures, onOpenOptions, onOpenTradFi }) {
  const { request, user } = useAuth();
  const [metaLoading, setMetaLoading] = useState(true);
  const [metaError, setMetaError] = useState("");
  const [assets, setAssets] = useState([]);
  const [history, setHistory] = useState([]);
  const [fromCode, setFromCode] = useState("");
  const [toCode, setToCode] = useState("");
  const [amount, setAmount] = useState("");
  const [selectorRole, setSelectorRole] = useState("");
  const [selectorSearch, setSelectorSearch] = useState("");
  const [detailsOpen, setDetailsOpen] = useState(false);
  const [reviewOpen, setReviewOpen] = useState(false);
  const [quote, setQuote] = useState(null);
  const [quoteLoading, setQuoteLoading] = useState(false);
  const [quoteError, setQuoteError] = useState("");
  const [expiresIn, setExpiresIn] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [submitResult, setSubmitResult] = useState(null);
  const [favorites, setFavorites] = useState(() => readList(FAVORITES_KEY));
  const [recentCodes, setRecentCodes] = useState(() => readList(RECENT_KEY));
  const fromAsset = useMemo(() => assets.find((item) => item.code === fromCode) || null, [assets, fromCode]);
  const toAsset = useMemo(() => assets.find((item) => item.code === toCode) || null, [assets, toCode]);
  const fromType = fromAsset?.type === "fiat" ? "fiat" : "crypto";
  const toType = toAsset?.type === "fiat" ? "fiat" : "crypto";
  const amountDecimal = decimal(amount);
  const balanceDecimal = decimal(fromAsset?.available_balance);
  const insufficientBalance = amountDecimal.gt(balanceDecimal);
  const sameAsset = fromCode && toCode && fromCode === toCode;
  const canQuote = Boolean(user && fromAsset && toAsset && amountDecimal.gt(0) && !sameAsset);

  const supportedFiat = useMemo(() => assets.filter((item) => item.type === "fiat"), [assets]);
  const supportedCrypto = useMemo(() => assets.filter((item) => item.type !== "fiat"), [assets]);

  const selectorAssets = useMemo(() => {
    const source = selectorRole === "to" ? (toType === "fiat" ? supportedFiat : supportedCrypto) : (fromType === "fiat" ? supportedFiat : supportedCrypto);
    const term = selectorSearch.trim().toLowerCase();
    return source.filter((item) => {
      const label = getAssetLabel(item).toLowerCase();
      return !term || item.code.toLowerCase().includes(term) || label.includes(term);
    });
  }, [selectorRole, selectorSearch, fromType, toType, supportedFiat, supportedCrypto]);

  const referenceFiat = useMemo(() => {
    const supportedCodes = new Set(supportedFiat.map((item) => item.code));
    return Object.entries(FIAT_REGISTRY)
      .filter(([code]) => !supportedCodes.has(code))
      .map(([code, item]) => ({ code, ...item }));
  }, [supportedFiat]);

  const persistRecent = (codes) => {
    const next = Array.from(new Set(codes.filter(Boolean))).slice(0, 8);
    persistList(RECENT_KEY, next);
    return next;
  };

  const refreshMeta = async () => {
    if (!user) return;
    setMetaError("");
    setMetaLoading(true);
    try {
      const [metaPayload, balancesPayload, historyPayload] = await Promise.all([
        request("/api/swap/meta", { method: "GET" }),
        request("/api/wallet/balances", { method: "GET" }),
        request("/api/swap/history?per_page=10", { method: "GET" }).catch(() => ({ data: { data: [] } })),
      ]);
      const merged = mergeBalances(Array.isArray(metaPayload?.data?.assets) ? metaPayload.data.assets : [], Array.isArray(balancesPayload?.data) ? balancesPayload.data : []);
      setAssets(merged);
      setHistory(Array.isArray(historyPayload?.data?.data) ? historyPayload.data.data : []);
    } catch (error) {
      setMetaError(mapError(error?.message));
    } finally {
      setMetaLoading(false);
    }
  };

  useEffect(() => {
    if (!user) {
      setMetaLoading(false);
      setMetaError("Authentication required");
      return;
    }

    let cancelled = false;
    const load = async () => {
      setMetaLoading(true);
      try {
        const [metaPayload, balancesPayload, historyPayload] = await Promise.all([
          request("/api/swap/meta", { method: "GET" }),
          request("/api/wallet/balances", { method: "GET" }),
          request("/api/swap/history?per_page=10", { method: "GET" }).catch(() => ({ data: { data: [] } })),
        ]);

        if (cancelled) return;
        const baseAssets = Array.isArray(metaPayload?.data?.assets) ? metaPayload.data.assets : [];
        const balances = Array.isArray(balancesPayload?.data) ? balancesPayload.data : [];
        const merged = mergeBalances(baseAssets, balances);
        setAssets(merged);
        setHistory(Array.isArray(historyPayload?.data?.data) ? historyPayload.data.data : Array.isArray(metaPayload?.data?.recent_swaps) ? metaPayload.data.recent_swaps : []);

        const defaultFrom = metaPayload?.data?.defaults?.from_currency;
        const defaultTo = metaPayload?.data?.defaults?.to_currency;
        const availableCodes = merged.map((item) => item.code);
        const recentAvailable = recentCodes.filter((code) => availableCodes.includes(code));
        let preset = null;
        try {
          const rawPreset = localStorage.getItem(CONVERT_PRESET_KEY);
          preset = rawPreset ? JSON.parse(rawPreset) : null;
        } catch {
          preset = null;
        }

        const presetFrom = preset?.fromCode;
        const presetTo = preset?.toCode;
        const hasPresetFrom = presetFrom && availableCodes.includes(presetFrom);
        const hasPresetTo = presetTo && availableCodes.includes(presetTo);
        const nextFrom = hasPresetFrom ? presetFrom : recentAvailable[0] || (availableCodes.includes(defaultFrom) ? defaultFrom : merged[0]?.code) || "";
        const nextToCandidate = hasPresetTo ? presetTo : recentAvailable[1] || (availableCodes.includes(defaultTo) ? defaultTo : merged.find((item) => item.code !== nextFrom)?.code) || "";
        setFromCode((prev) => prev || nextFrom);
        setToCode((prev) => prev || (nextToCandidate === nextFrom ? merged.find((item) => item.code !== nextFrom)?.code || "" : nextToCandidate));
        if (hasPresetFrom || hasPresetTo) {
          try { localStorage.removeItem(CONVERT_PRESET_KEY); } catch { /* ignore local preset cleanup errors */ }
        }
        setMetaError("");
      } catch (error) {
        if (!cancelled) setMetaError(mapError(error?.message));
      } finally {
        if (!cancelled) setMetaLoading(false);
      }
    };

    load();
    return () => { cancelled = true; };
  }, [request, user]);

  useEffect(() => {
    if (!canQuote) {
      setQuote(null);
      setQuoteError(sameAsset ? "Choose two different assets." : "");
      setExpiresIn(0);
      return;
    }
    if (insufficientBalance) {
      setQuote(null);
      setQuoteError("Insufficient available balance for this conversion.");
      setExpiresIn(0);
      return;
    }

    let cancelled = false;
    const timer = window.setTimeout(async () => {
      setQuoteLoading(true);
      try {
        const payload = await request("/api/swap/quote", {
          method: "POST",
          body: JSON.stringify({ from_currency: fromCode, to_currency: toCode, amount: amountDecimal.toString() }),
        });
        if (cancelled) return;
        setQuote(payload?.data || null);
        setExpiresIn(Number(payload?.data?.expires_in || 0));
        setQuoteError("");
      } catch (error) {
        if (!cancelled) {
          setQuote(null);
          setExpiresIn(0);
          setQuoteError(mapError(error?.message));
        }
      } finally {
        if (!cancelled) setQuoteLoading(false);
      }
    }, 350);

    return () => {
      cancelled = true;
      window.clearTimeout(timer);
    };
  }, [request, canQuote, fromCode, toCode, amount, insufficientBalance, sameAsset]);

  useEffect(() => {
    if (!quote?.quote_id) return;
    const timer = window.setInterval(() => setExpiresIn((current) => (current > 0 ? current - 1 : 0)), 1000);
    return () => window.clearInterval(timer);
  }, [quote?.quote_id]);

  const receiveDisplay = useMemo(() => {
    if (!quote?.receive_amount) return toType === "fiat" ? formatCurrencyValue(toCode || "USD", 0) : `0 ${toCode || ""}`.trim();
    if (toType === "fiat") return formatCurrencyValue(toCode, quote.receive_amount);
    return `${formatAmount(quote.receive_amount, 8)} ${toCode}`;
  }, [quote, toType, toCode]);

  const feeDisplay = useMemo(() => {
    if (!quote?.fee) return fromType === "fiat" ? formatCurrencyValue(fromCode || "USD", 0) : `0 ${fromCode || ""}`.trim();
    if (fromType === "fiat") return formatCurrencyValue(fromCode, quote.fee);
    return `${formatAmount(quote.fee, 8)} ${fromCode}`;
  }, [quote, fromType, fromCode]);

  const rateDisplay = useMemo(() => {
    if (!quote?.rate || !fromCode || !toCode) return "--";
    if (toType === "fiat") return `1 ${fromCode} ˜ ${formatCurrencyValue(toCode, quote.rate)}`;
    return `1 ${fromCode} ˜ ${formatAmount(quote.rate, 8)} ${toCode}`;
  }, [quote, fromCode, toCode, toType]);

  const disabledReason = useMemo(() => {
    if (!user) return "Sign in to use Convert.";
    if (!fromAsset || !toAsset) return "Loading supported assets...";
    if (sameAsset) return "Choose two different assets.";
    if (!amount) return "Enter an amount to continue.";
    if (!amountDecimal.gt(0)) return "Enter an amount greater than zero.";
    if (insufficientBalance) return "Insufficient available balance.";
    if (quoteLoading) return "Fetching best rate...";
    if (quoteError) return quoteError;
    if (!quote?.quote_id) return "Quote unavailable right now.";
    if (expiresIn <= 0) return "Quote expired. Refresh the rate.";
    return "";
  }, [user, fromAsset, toAsset, sameAsset, amount, amountDecimal, insufficientBalance, quoteLoading, quoteError, quote, expiresIn]);

  const handleSelectCode = (code) => {
    if (selectorRole === "from") {
      setFromCode(code);
      setRecentCodes((prev) => persistRecent([code, toCode, ...prev]));
    } else if (selectorRole === "to") {
      setToCode(code);
      setRecentCodes((prev) => persistRecent([fromCode, code, ...prev]));
    }
    setSelectorRole("");
    setSelectorSearch("");
  };

  const handleTypeChange = (role, type) => {
    const source = type === "fiat" ? supportedFiat : supportedCrypto;
    const fallback = source.find((item) => item.code !== (role === "from" ? toCode : fromCode)) || source[0];
    if (!fallback) return;
    if (role === "from") setFromCode(fallback.code);
    if (role === "to") setToCode(fallback.code);
  };

  const handleReverse = () => {
    if (!fromCode || !toCode) return;
    setFromCode(toCode);
    setToCode(fromCode);
  };

  const handlePercent = (percent) => {
    if (!fromAsset) return;
    const decimals = Number(fromAsset.decimals ?? (fromType === "fiat" ? 2 : 8));
    const value = decimal(fromAsset.available_balance).mul(percent).div(100);
    setAmount(value.toDecimalPlaces(Math.min(decimals, fromType === "fiat" ? 2 : 8), Decimal.ROUND_DOWN).toString());
  };

  const toggleFavorite = (code) => {
    const next = favorites.includes(code) ? favorites.filter((item) => item !== code) : [code, ...favorites].slice(0, 12);
    setFavorites(next);
    persistList(FAVORITES_KEY, next);
  };

  const handleExecute = async () => {
    if (disabledReason || !quote?.quote_id) return;
    setSubmitting(true);
    try {
      const payload = await request("/api/swap/execute", {
        method: "POST",
        body: JSON.stringify({ quote_id: quote.quote_id }),
        headers: { "X-Idempotency-Key": `swap-${Date.now()}-${Math.random().toString(36).slice(2, 8)}` },
      });
      setSubmitResult(payload?.data || null);
      setReviewOpen(false);
      setAmount("");
      setQuote(null);
      setExpiresIn(0);
      await refreshMeta();
    } catch (error) {
      setQuoteError(mapError(error?.message));
    } finally {
      setSubmitting(false);
    }
  };
  return (
    <main className="min-h-[100dvh] overflow-x-hidden bg-[#04070d] px-3 pb-[calc(env(safe-area-inset-bottom)+88px)] pt-[calc(env(safe-area-inset-top)+10px)] text-white sm:px-4 lg:px-6 lg:pb-8">
      <div className="mx-auto flex w-full max-w-[1180px] flex-col gap-4">
        <ProductNav onBack={onBack} onOpenTrade={onOpenTrade} onOpenFutures={onOpenFutures} onOpenOptions={onOpenOptions} onOpenTradFi={onOpenTradFi} />
        <div className="grid gap-4 lg:grid-cols-[minmax(0,560px)_minmax(280px,1fr)] lg:items-start lg:justify-center">
          <section className="rounded-3xl border border-white/10 bg-[#0a1019] p-3 shadow-[0_24px_80px_rgba(0,0,0,0.35)] sm:p-4">
            <div className="flex items-center justify-between gap-3 border-b border-white/8 pb-3">
              <div>
                <div className="text-[11px] uppercase tracking-[0.22em] text-slate-500">Convert</div>
                <h1 className="mt-1 text-xl font-semibold tracking-tight text-white">Swap assets instantly</h1>
              </div>
              <button type="button" onClick={() => setDetailsOpen(true)} className="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.03] text-slate-300">
                <Settings2 className="h-4 w-4" />
              </button>
            </div>

            {!user ? <InlineMessage tone="warning" text="Authentication required. Sign in to request live conversion quotes." /> : null}
            {metaError && !metaLoading ? <InlineMessage tone="danger" text={metaError} /> : null}

            <div className="mt-3 space-y-3">
              <CompactPanel label="From" type={fromType} onTypeChange={(type) => handleTypeChange("from", type)} asset={fromAsset} onOpenSelector={() => setSelectorRole("from")} amount={amount} onAmountChange={setAmount} onMax={() => setAmount(fromAsset?.available_balance || "")} percentages={PERCENTAGES} onPercent={handlePercent} balanceLabel={fromType === "fiat" ? "Available fiat balance" : "Available balance"} quoteSide="from" />

              <div className="flex justify-center">
                <button type="button" onClick={handleReverse} className="inline-flex h-11 w-11 items-center justify-center rounded-full border border-amber-300/40 bg-amber-300/10 text-amber-200 transition hover:rotate-180">
                  <ArrowUpDown className="h-4 w-4" />
                </button>
              </div>

              <CompactPanel label="To" type={toType} onTypeChange={(type) => handleTypeChange("to", type)} asset={toAsset} onOpenSelector={() => setSelectorRole("to")} quoteSide="to" estimatedReceive={receiveDisplay} rateLine={rateDisplay} quoteLoading={quoteLoading} expiresIn={expiresIn} />

              <div className="rounded-2xl border border-white/8 bg-[#070d16] p-3">
                <SummaryRow label="Rate" value={rateDisplay} />
                <SummaryRow label="Fee" value={feeDisplay} />
                <SummaryRow label="You receive" value={receiveDisplay} emphasis />
                <button type="button" onClick={() => setDetailsOpen(true)} className="mt-3 text-xs font-medium text-amber-300 transition hover:text-amber-200">Rate & fee details &gt;</button>
              </div>

              {quoteError && amount ? <InlineMessage tone="danger" text={quoteError} /> : null}

              <button type="button" onClick={() => setReviewOpen(true)} disabled={Boolean(disabledReason)} className="w-full rounded-2xl bg-[#f0c96b] px-4 py-3.5 text-sm font-semibold text-[#1d1707] transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-45">
                {quoteLoading ? "Fetching best rate..." : "Review Conversion"}
              </button>

              {disabledReason ? <p className="text-center text-xs text-slate-500">{disabledReason}</p> : null}
            </div>
          </section>

          <aside className="space-y-4">
            <section className="hidden rounded-3xl border border-white/8 bg-[#0a1019] p-4 lg:block">
              <div className="flex items-center justify-between gap-3"><div><div className="text-[11px] uppercase tracking-[0.18em] text-slate-500">Recent</div><h2 className="mt-1 text-lg font-semibold">Recent conversions</h2></div><Sparkles className="h-4 w-4 text-amber-300" /></div>
              <div className="mt-3 space-y-2">{history.length ? history.slice(0, 6).map((item) => <HistoryRow key={item.swap_id} item={item} />) : <EmptyCard title="No recent conversions" copy="Your latest convert activity will appear here." />}</div>
            </section>
            <section className="rounded-3xl border border-white/8 bg-[#0a1019] p-4">
              <div className="flex items-center justify-between gap-3"><div><div className="text-[11px] uppercase tracking-[0.18em] text-slate-500">Supported</div><h2 className="mt-1 text-lg font-semibold">Active convert assets</h2></div><button type="button" onClick={refreshMeta} className="text-xs font-medium text-slate-400 hover:text-white">Refresh</button></div>
              <div className="mt-3 flex flex-wrap gap-2">{assets.slice(0, 12).map((item) => <span key={item.code} className="rounded-full border border-white/8 bg-white/[0.03] px-3 py-1 text-xs text-slate-300">{item.code}</span>)}</div>
            </section>
          </aside>
        </div>
      </div>

      {selectorRole ? <SelectorSheet title={selectorRole === "from" ? "Select source asset" : "Select destination asset"} assets={selectorAssets} favorites={favorites} recentCodes={recentCodes} search={selectorSearch} onSearch={setSelectorSearch} onClose={() => setSelectorRole("")} onSelect={handleSelectCode} onToggleFavorite={toggleFavorite} referenceFiat={(selectorRole === "from" ? fromType : toType) === "fiat" ? referenceFiat : []} /> : null}

      {detailsOpen ? <Sheet title="Rate & fee details" onClose={() => setDetailsOpen(false)}><div className="space-y-3 text-sm"><DetailRow label="Indicative rate" value={rateDisplay} /><DetailRow label="ExaEarn fee" value={feeDisplay} /><DetailRow label="Route" value={quote?.route || "Best available route"} /><DetailRow label="Quote validity" value={expiresIn > 0 ? `${expiresIn}s remaining` : "Awaiting fresh quote"} /><DetailRow label="Settlement" value={toType === "fiat" ? `${toCode} wallet settlement` : `${toCode} wallet credit`} /><div className="rounded-2xl border border-white/8 bg-white/[0.03] p-3 text-xs text-slate-400">Rates are variable and refresh automatically. Unsupported currencies may still appear in the global registry as coming soon, but only backend-enabled currencies can execute.</div></div></Sheet> : null}

      {reviewOpen ? <Sheet title="Review conversion" onClose={() => setReviewOpen(false)}><div className="space-y-3"><div className="rounded-2xl border border-white/8 bg-white/[0.03] p-3"><DetailRow label="Convert" value={`${amount || "0"} ${fromCode || ""}`.trim()} /><DetailRow label="Receive" value={receiveDisplay} /><DetailRow label="Rate" value={rateDisplay} /><DetailRow label="Fees" value={feeDisplay} /><DetailRow label="Destination" value={`${toCode} wallet`} /><DetailRow label="Quote" value={expiresIn > 0 ? `Rate locked for ${expiresIn}s` : "Refresh required"} /></div><div className="rounded-2xl border border-amber-300/20 bg-amber-300/10 p-3 text-xs text-amber-100">The final conversion uses the backend-confirmed quote only, and expired quotes cannot execute.</div><button type="button" onClick={handleExecute} disabled={Boolean(disabledReason) || submitting} className="w-full rounded-2xl bg-[#f0c96b] px-4 py-3 text-sm font-semibold text-[#1d1707] disabled:cursor-not-allowed disabled:opacity-45">{submitting ? "Submitting..." : "Confirm Conversion"}</button></div></Sheet> : null}

      {submitResult ? <Sheet title="Conversion submitted" onClose={() => setSubmitResult(null)}><div className="space-y-3"><div className="rounded-2xl border border-white/8 bg-white/[0.03] p-3 text-sm"><DetailRow label="Reference" value={submitResult.swap_id || "--"} /><DetailRow label="Status" value={String(submitResult.status || "queued").toUpperCase()} /><DetailRow label="From" value={`${submitResult.amount_sent} ${submitResult.from_currency}`} /><DetailRow label="To" value={`${submitResult.amount_received} ${submitResult.to_currency}`} /></div><p className="text-xs text-slate-400">Your conversion was queued through the existing ExaEarn swap engine. Balances and recent conversions refresh automatically after submission.</p></div></Sheet> : null}
    </main>
  );
}

function ProductNav({ onBack, onOpenTrade, onOpenFutures, onOpenOptions, onOpenTradFi }) {
  return <div className="flex items-center gap-3 overflow-x-auto border-b border-white/8 pb-2"><button type="button" onClick={onBack} className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/8 bg-white/[0.03] text-slate-300"><ArrowLeft className="h-4 w-4" /></button>{PRODUCT_TABS.map((tab) => { const active = tab === "Convert"; const handler = tab === "Spot" ? onOpenTrade : tab === "Futures" ? onOpenFutures : tab === "Options" ? onOpenOptions : tab === "TradFi" ? onOpenTradFi : undefined; return <button key={tab} type="button" onClick={handler} className={`relative shrink-0 whitespace-nowrap pb-2 text-sm ${active ? "text-white" : "text-slate-500"}`}>{tab}{active ? <span className="absolute bottom-0 left-0 h-0.5 w-full rounded-full bg-amber-400" /> : null}</button>; })}</div>;
}

function CompactPanel({ label, type, onTypeChange, asset, onOpenSelector, amount, onAmountChange, onMax, percentages, onPercent, balanceLabel, estimatedReceive, rateLine, quoteLoading, expiresIn, quoteSide }) {
  return <div className="rounded-2xl border border-white/8 bg-[#070d16] p-3"><div className="flex items-center justify-between gap-3"><div className="text-[11px] uppercase tracking-[0.18em] text-slate-500">{label}</div><div className="inline-flex rounded-xl border border-white/8 bg-white/[0.03] p-1 text-xs">{["crypto", "fiat"].map((item) => <button key={item} type="button" onClick={() => onTypeChange(item)} className={`rounded-lg px-2.5 py-1 ${type === item ? "bg-white text-slate-950" : "text-slate-400"}`}>{item === "crypto" ? "Crypto" : "Fiat"}</button>)}</div></div><button type="button" onClick={onOpenSelector} className="mt-3 flex w-full items-center justify-between rounded-2xl border border-white/8 bg-white/[0.03] px-3 py-3 text-left"><div className="min-w-0"><div className="text-sm font-semibold text-white">{asset?.code || "Select"}</div><div className="truncate text-xs text-slate-500">{getAssetLabel(asset)}</div></div><ChevronDown className="h-4 w-4 text-slate-500" /></button>{quoteSide === "from" ? <><div className="mt-3 flex items-center gap-2 rounded-2xl border border-white/8 bg-[#04070d] px-3 py-3"><input value={amount} onChange={(event) => onAmountChange(event.target.value)} inputMode="decimal" placeholder="0.00" className="h-8 w-full bg-transparent text-2xl font-semibold text-white outline-none placeholder:text-slate-600" /><button type="button" onClick={onMax} className="rounded-lg border border-amber-300/30 bg-amber-300/10 px-2.5 py-1 text-xs font-semibold text-amber-200">MAX</button></div><div className="mt-2 flex items-center justify-between gap-3 text-xs text-slate-500"><span>{balanceLabel}: <span className="text-slate-300">{asset ? formatBalanceDisplay(asset) : "--"}</span></span><div className="flex gap-1">{percentages.map((percent) => <button key={percent} type="button" onClick={() => onPercent(percent)} className="rounded-lg bg-white/[0.04] px-2 py-1 text-[11px] text-slate-300">{percent === 100 ? "MAX" : `${percent}%`}</button>)}</div></div></> : <div className="mt-3 rounded-2xl border border-white/8 bg-[#04070d] px-3 py-3"><div className="text-xs text-slate-500">Estimated receive</div><div className="mt-1 text-2xl font-semibold text-white">{estimatedReceive}</div><div className="mt-2 flex items-center justify-between gap-3 text-xs text-slate-500"><span>{quoteLoading ? "Fetching best rate..." : rateLine}</span><span>{expiresIn > 0 ? `Rate expires in ${expiresIn}s` : "Indicative rate"}</span></div></div>}</div>;
}
function SelectorSheet({ title, assets, favorites, recentCodes, search, onSearch, onClose, onSelect, onToggleFavorite, referenceFiat }) {
  const recent = assets.filter((item) => recentCodes.includes(item.code));
  const favoriteAssets = assets.filter((item) => favorites.includes(item.code));
  return <div className="fixed inset-0 z-50 flex items-end bg-black/70 p-0 lg:items-center lg:justify-center lg:p-6" onClick={onClose}><div className="w-full max-h-[88dvh] rounded-t-[24px] border border-white/10 bg-[#050b14] p-4 lg:max-w-[720px] lg:rounded-[28px]" onClick={(event) => event.stopPropagation()}><div className="flex items-center justify-between gap-3"><div><div className="text-xs uppercase tracking-[0.18em] text-slate-500">Select</div><div className="text-lg font-semibold text-white">{title}</div></div><button type="button" onClick={onClose} className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/[0.04] text-slate-300"><X className="h-4 w-4" /></button></div><div className="mt-3 rounded-2xl bg-white/[0.04] px-3 py-2"><div className="flex items-center gap-2"><Search className="h-4 w-4 text-slate-500" /><input value={search} onChange={(event) => onSearch(event.target.value)} placeholder="Search asset or currency" className="w-full bg-transparent text-sm text-white outline-none placeholder:text-slate-500" /></div></div>{favoriteAssets.length ? <Section title="Favorites">{favoriteAssets.map((item) => <SelectorRow key={item.code} item={item} favorite onSelect={onSelect} onToggleFavorite={onToggleFavorite} />)}</Section> : null}{recent.length ? <Section title="Recently used">{recent.map((item) => <SelectorRow key={item.code} item={item} favorite={favorites.includes(item.code)} onSelect={onSelect} onToggleFavorite={onToggleFavorite} />)}</Section> : null}<Section title="Available now">{assets.map((item) => <SelectorRow key={item.code} item={item} favorite={favorites.includes(item.code)} onSelect={onSelect} onToggleFavorite={onToggleFavorite} />)}</Section>{referenceFiat.length ? <Section title="Global currencies coming soon">{referenceFiat.map((item) => <button key={item.code} type="button" disabled className="flex w-full items-center justify-between rounded-2xl border border-white/8 bg-white/[0.02] px-3 py-3 text-left opacity-60"><div><div className="text-sm font-semibold text-white">{item.code}</div><div className="text-xs text-slate-500">{item.name}</div></div><div className="text-[11px] font-medium text-slate-400">Coming soon</div></button>)}</Section> : null}</div></div>;
}

function SelectorRow({ item, favorite, onSelect, onToggleFavorite }) {
  return <button type="button" onClick={() => onSelect(item.code)} className="flex w-full items-center justify-between rounded-2xl border border-white/8 bg-white/[0.02] px-3 py-3 text-left hover:bg-white/[0.04]"><div className="flex min-w-0 items-center gap-3"><div className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-[#0d1522] text-xs font-semibold text-amber-200">{getAssetBadge(item)}</div><div className="min-w-0"><div className="truncate text-sm font-semibold text-white">{item.code}</div><div className="truncate text-xs text-slate-500">{getAssetLabel(item)}</div></div></div><div className="flex items-center gap-3"><div className="text-right text-xs text-slate-400">{formatBalanceDisplay(item)}</div><button type="button" onClick={(event) => { event.stopPropagation(); onToggleFavorite(item.code); }} className={`inline-flex h-8 w-8 items-center justify-center rounded-xl ${favorite ? "text-amber-300" : "text-slate-500"}`}><Star className={`h-4 w-4 ${favorite ? "fill-current" : ""}`} /></button></div></button>;
}

function Section({ title, children }) {
  return <div className="mt-4"><div className="mb-2 text-[11px] uppercase tracking-[0.18em] text-slate-500">{title}</div><div className="space-y-2">{children}</div></div>;
}

function Sheet({ title, children, onClose }) {
  return <div className="fixed inset-0 z-50 flex items-end bg-black/70 p-0 lg:items-center lg:justify-center lg:p-6" onClick={onClose}><div className="w-full max-h-[88dvh] overflow-y-auto rounded-t-[24px] border border-white/10 bg-[#050b14] p-4 lg:max-w-[520px] lg:rounded-[28px]" onClick={(event) => event.stopPropagation()}><div className="flex items-center justify-between gap-3"><div className="text-lg font-semibold text-white">{title}</div><button type="button" onClick={onClose} className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/[0.04] text-slate-300"><X className="h-4 w-4" /></button></div><div className="mt-4">{children}</div></div></div>;
}

function SummaryRow({ label, value, emphasis = false }) {
  return <div className="mt-2 flex items-center justify-between gap-3 first:mt-0"><span className="text-sm text-slate-400">{label}</span><span className={`text-right font-mono ${emphasis ? "text-base font-semibold text-white" : "text-sm text-white"}`}>{value}</span></div>;
}

function DetailRow({ label, value }) {
  return <div className="flex items-center justify-between gap-4 border-b border-white/8 pb-3 last:border-none last:pb-0"><span className="text-sm text-slate-400">{label}</span><span className="text-right text-sm font-medium text-white">{value}</span></div>;
}

function HistoryRow({ item }) {
  return <div className="rounded-2xl border border-white/8 bg-white/[0.03] p-3"><div className="flex items-center justify-between gap-3"><div><div className="text-sm font-semibold text-white">{item.from_currency} ? {item.to_currency}</div><div className="text-xs text-slate-500">{new Date(item.created_at).toLocaleString()}</div></div><div className="rounded-full border border-white/8 px-2 py-1 text-[11px] uppercase tracking-wide text-slate-300">{item.status}</div></div><div className="mt-2 flex items-center justify-between gap-3 text-sm"><span className="text-slate-400">{item.amount_sent} {item.from_currency}</span><span className="font-medium text-white">{item.amount_received} {item.to_currency}</span></div></div>;
}

function EmptyCard({ title, copy }) {
  return <div className="rounded-2xl border border-dashed border-white/10 bg-white/[0.02] px-4 py-6 text-center"><div className="text-sm font-semibold text-white">{title}</div><div className="mt-1 text-xs text-slate-400">{copy}</div></div>;
}

function InlineMessage({ tone, text }) {
  const className = tone === "danger" ? "border-rose-500/20 bg-rose-500/10 text-rose-200" : "border-amber-300/20 bg-amber-300/10 text-amber-100";
  return <div className={`mt-3 rounded-2xl border px-3 py-2 text-xs ${className}`}>{text}</div>;
}

export default Swap;

