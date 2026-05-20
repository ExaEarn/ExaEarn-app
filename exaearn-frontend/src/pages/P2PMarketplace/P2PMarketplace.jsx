import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  BadgeCheck,
  ChevronDown,
  CircleAlert,
  Loader2,
  MessageSquare,
  Plus,
  RefreshCw,
  ShieldCheck,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";

const assets = [
  { symbol: "XRP", tone: "from-sky-400 to-blue-500" },
  { symbol: "USDT", tone: "from-emerald-400 to-green-500" },
  { symbol: "BTC", tone: "from-amber-300 to-orange-500" },
  { symbol: "ETH", tone: "from-indigo-300 to-violet-500" },
];

const paymentOptions = ["All Methods", "Bank Transfer", "Opay", "Airtel Money", "PalmPay"];
const fiatOptions = ["NGN", "USD", "GHS", "KES", "ZAR"];

function unwrapList(payload) {
  const data = payload?.data;
  if (Array.isArray(data)) return data;
  if (Array.isArray(data?.data)) return data.data;
  return [];
}

function formatNumber(value, max = 8) {
  const number = Number(value);
  if (!Number.isFinite(number)) return String(value ?? "--");
  return number.toLocaleString(undefined, { maximumFractionDigits: max });
}

function TokenLogo({ symbol, tone }) {
  return (
    <span className={`inline-flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br ${tone} text-[11px] font-bold text-white shadow-[0_0_18px_rgba(168,85,247,0.35)]`}>
      {symbol.slice(0, 2)}
    </span>
  );
}

function P2PMarketplace({ onBack }) {
  const { request, user } = useAuth();
  const [tab, setTab] = useState("market");
  const [tradeSide, setTradeSide] = useState("buy");
  const [selectedAsset, setSelectedAsset] = useState("USDT");
  const [fiatCurrency, setFiatCurrency] = useState("NGN");
  const [paymentMethod, setPaymentMethod] = useState("All Methods");
  const [amount, setAmount] = useState("");
  const [ads, setAds] = useState([]);
  const [myAds, setMyAds] = useState([]);
  const [trades, setTrades] = useState([]);
  const [selectedAd, setSelectedAd] = useState(null);
  const [tradeAmount, setTradeAmount] = useState("");
  const [tradePaymentMethod, setTradePaymentMethod] = useState("Bank Transfer");
  const [notice, setNotice] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [adForm, setAdForm] = useState({
    type: "sell",
    asset: "USDT",
    fiat_currency: "NGN",
    price: "",
    min_limit: "",
    max_limit: "",
    available_amount: "",
    payment_methods: ["Bank Transfer"],
    payment_time_limit_minutes: 15,
    terms_of_trade: "",
  });

  const selectedAssetMeta = assets.find((asset) => asset.symbol === selectedAsset) ?? assets[1];

  const query = useMemo(() => {
    const params = new URLSearchParams({
      type: tradeSide === "buy" ? "sell" : "buy",
      asset: selectedAsset,
      fiat_currency: fiatCurrency.toUpperCase(),
      per_page: "30",
    });
    if (paymentMethod !== "All Methods") params.set("payment_method", paymentMethod);
    return params.toString();
  }, [fiatCurrency, paymentMethod, selectedAsset, tradeSide]);

  const loadP2P = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [adsPayload, myAdsPayload, tradesPayload] = await Promise.all([
        request(`/api/p2p/ads?${query}`, { method: "GET" }),
        request("/api/p2p/ads/mine", { method: "GET" }),
        request("/api/p2p/trades/mine", { method: "GET" }),
      ]);
      setAds(unwrapList(adsPayload));
      setMyAds(unwrapList(myAdsPayload));
      setTrades(unwrapList(tradesPayload));
    } catch (loadError) {
      setError(loadError.message || "Unable to load P2P marketplace.");
    } finally {
      setLoading(false);
    }
  }, [query, request]);

  useEffect(() => {
    loadP2P();
  }, [loadP2P]);

  const openTradeModal = (ad) => {
    setSelectedAd(ad);
    setTradeAmount("");
    setTradePaymentMethod(ad.payment_methods?.[0] || "Bank Transfer");
    setError("");
    setNotice("");
  };

  const submitTrade = async () => {
    if (!selectedAd || !tradeAmount) return;
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await request(`/api/p2p/ads/${selectedAd.id}/trades`, {
        method: "POST",
        body: JSON.stringify({
          fiat_amount: tradeAmount,
          payment_method: tradePaymentMethod,
        }),
      });
      setSelectedAd(null);
      setNotice("Trade opened. Escrow is locked and the payment timer has started.");
      setTab("trades");
      await loadP2P();
    } catch (tradeError) {
      setError(tradeError.message || "Unable to open trade.");
    } finally {
      setBusy(false);
    }
  };

  const createAd = async (event) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await request("/api/p2p/ads", {
        method: "POST",
        body: JSON.stringify(adForm),
      });
      setNotice("P2P ad created and published.");
      setTab("myAds");
      setAdForm((current) => ({
        ...current,
        price: "",
        min_limit: "",
        max_limit: "",
        available_amount: "",
        terms_of_trade: "",
      }));
      await loadP2P();
    } catch (adError) {
      setError(adError.message || "Unable to create P2P ad.");
    } finally {
      setBusy(false);
    }
  };

  const tradeAction = async (trade, action, payload = {}) => {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await request(`/api/p2p/trades/${trade.trade_uuid}/${action}`, {
        method: "POST",
        body: JSON.stringify(payload),
      });
      setNotice("Trade updated.");
      await loadP2P();
    } catch (actionError) {
      setError(actionError.message || "Unable to update trade.");
    } finally {
      setBusy(false);
    }
  };

  const filteredAds = useMemo(() => {
    if (!amount) return ads;
    const numericAmount = Number(amount);
    if (!Number.isFinite(numericAmount)) return ads;
    return ads.filter((ad) => numericAmount >= Number(ad.min_limit) && numericAmount <= Number(ad.max_limit));
  }, [ads, amount]);

  return (
    <main className="relative min-h-screen overflow-hidden bg-gradient-to-br from-[#050509] via-[#13071f] to-[#1a0d2f] px-4 py-8 text-violet-50 sm:px-6 sm:py-10">
      <div className="pointer-events-none absolute -left-24 top-24 h-56 w-56 rounded-full bg-purple-500/20 blur-3xl" />
      <div className="pointer-events-none absolute right-0 top-1/3 h-64 w-64 rounded-full bg-amber-400/10 blur-3xl" />

      <section className="mx-auto w-full max-w-7xl rounded-3xl border border-violet-300/15 bg-[#100a1e]/65 p-4 shadow-[0_20px_80px_rgba(0,0,0,0.55)] backdrop-blur-xl sm:p-6 lg:p-8">
        <header className="rounded-2xl border border-violet-300/15 bg-[#120b22]/80 p-4 shadow-[0_12px_35px_rgba(0,0,0,0.45)] sm:p-6">
          <div className="mb-5 flex flex-wrap items-start justify-between gap-4">
            <div>
              {onBack ? (
                <button type="button" onClick={onBack} className="mb-3 inline-flex items-center gap-2 rounded-xl border border-violet-300/25 bg-violet-950/35 px-3 py-2 text-xs font-semibold text-violet-100 transition hover:border-amber-300/60 hover:text-amber-200">
                  <ArrowLeft className="h-4 w-4" />
                  Back
                </button>
              ) : null}
              <h1 className="font-['Sora'] text-3xl font-semibold tracking-tight text-white sm:text-4xl">P2P Marketplace</h1>
              <p className="mt-1 text-sm text-violet-100/75">Live escrow-protected buy and sell orders</p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <button type="button" onClick={loadP2P} disabled={loading} className="inline-flex items-center gap-2 rounded-full border border-violet-300/25 bg-violet-400/10 px-3 py-1.5 text-xs font-semibold text-violet-100 transition hover:border-amber-300/50">
                {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                Refresh
              </button>
              <span className="inline-flex items-center gap-2 rounded-full border border-amber-300/40 bg-amber-300/10 px-3 py-1.5 text-xs font-semibold text-amber-200">
                <ShieldCheck className="h-4 w-4" />
                Escrow Protected
              </span>
            </div>
          </div>

          <div className="grid gap-2 rounded-2xl border border-violet-300/20 bg-[#1b112d]/70 p-1.5 sm:grid-cols-4">
            {[
              ["market", "Marketplace"],
              ["trades", "My Trades"],
              ["myAds", "My Ads"],
              ["create", "Create Ad"],
            ].map(([key, label]) => (
              <button key={key} type="button" onClick={() => setTab(key)} className={`rounded-xl px-4 py-2.5 text-sm font-semibold transition ${tab === key ? "bg-gradient-to-r from-amber-300 via-amber-400 to-yellow-500 text-black" : "text-violet-100/80 hover:bg-violet-400/10 hover:text-white"}`}>
                {label}
              </button>
            ))}
          </div>
        </header>

        {notice ? <div className="mt-4 rounded-xl border border-emerald-300/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{notice}</div> : null}
        {error ? <div className="mt-4 rounded-xl border border-rose-300/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">{error}</div> : null}

        {tab === "market" ? (
          <section className="mt-5 space-y-5">
            <div className="rounded-2xl border border-violet-300/15 bg-[#120c22]/70 p-4">
              <div className="inline-flex rounded-full border border-violet-300/20 bg-[#0f0a1b] p-1">
                <button type="button" onClick={() => setTradeSide("buy")} className={`rounded-full px-5 py-2 text-sm font-semibold transition ${tradeSide === "buy" ? "bg-gradient-to-r from-amber-300 to-yellow-500 text-black" : "text-violet-100/80 hover:text-white"}`}>
                  Buy
                </button>
                <button type="button" onClick={() => setTradeSide("sell")} className={`rounded-full px-5 py-2 text-sm font-semibold transition ${tradeSide === "sell" ? "bg-gradient-to-r from-purple-500 to-violet-600 text-white" : "text-violet-100/80 hover:text-white"}`}>
                  Sell
                </button>
              </div>

              <div className="mt-4 flex gap-3 overflow-x-auto pb-1">
                {assets.map((asset) => (
                  <button key={asset.symbol} type="button" onClick={() => setSelectedAsset(asset.symbol)} className={`inline-flex min-w-fit items-center gap-2 rounded-xl border px-3 py-2 transition ${selectedAsset === asset.symbol ? "border-amber-300/70 bg-amber-300/12" : "border-violet-300/20 bg-violet-950/35 hover:border-violet-200/45"}`}>
                    <TokenLogo symbol={asset.symbol} tone={asset.tone} />
                    <span className="text-sm font-semibold text-violet-50">{asset.symbol}</span>
                  </button>
                ))}
              </div>
            </div>

            <div className="rounded-2xl border border-violet-300/15 bg-[#120c22]/70 p-4">
              <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <SelectField label="Fiat Currency" value={fiatCurrency} onChange={setFiatCurrency} options={fiatOptions} />
                <SelectField label="Payment Method" value={paymentMethod} onChange={setPaymentMethod} options={paymentOptions} />
                <TextField label="Fiat Amount" value={amount} onChange={setAmount} placeholder="e.g. 30000" />
                <div className="rounded-xl border border-violet-300/15 bg-[#100a1c] px-3 py-2.5">
                  <span className="block text-xs text-violet-100/55">Current Side</span>
                  <strong className="text-sm text-violet-50">{tradeSide === "buy" ? "Buying from sell ads" : "Selling into buy ads"}</strong>
                </div>
              </div>
            </div>

            <section className="space-y-3">
              {loading ? <LoadingPanel /> : null}
              {!loading && filteredAds.length ? (
                filteredAds.map((ad) => (
                  <AdCard key={ad.id} ad={ad} selectedAssetMeta={selectedAssetMeta} actionLabel={tradeSide === "buy" ? "Buy" : "Sell"} onOpen={() => openTradeModal(ad)} />
                ))
              ) : null}
              {!loading && !filteredAds.length ? <EmptyPanel title="No live ads found" body="Try another asset, payment method, or amount." /> : null}
            </section>
          </section>
        ) : null}

        {tab === "trades" ? (
          <section className="mt-5 space-y-3">
            {trades.length ? trades.map((trade) => (
              <TradeCard key={trade.id} trade={trade} userId={user?.id} busy={busy} onAction={tradeAction} />
            )) : <EmptyPanel title="No P2P trades yet" body="Open a marketplace trade and it will appear here." />}
          </section>
        ) : null}

        {tab === "myAds" ? (
          <section className="mt-5 space-y-3">
            {myAds.length ? myAds.map((ad) => (
              <AdCard key={ad.id} ad={ad} selectedAssetMeta={assets.find((asset) => asset.symbol === ad.asset) ?? selectedAssetMeta} actionLabel="View" />
            )) : <EmptyPanel title="No ads published yet" body="Create your first buy or sell advert." />}
          </section>
        ) : null}

        {tab === "create" ? (
          <CreateAdForm form={adForm} setForm={setAdForm} busy={busy} onSubmit={createAd} />
        ) : null}
      </section>

      {selectedAd ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4 backdrop-blur-sm">
          <section className="w-full max-w-md rounded-2xl border border-violet-300/20 bg-[#120c22] p-5 shadow-[0_20px_80px_rgba(0,0,0,0.65)]">
            <h2 className="font-['Sora'] text-xl font-semibold text-white">Open P2P Trade</h2>
            <p className="mt-1 text-sm text-violet-100/70">
              {selectedAd.asset} at {formatNumber(selectedAd.price, 4)} {selectedAd.fiat_currency}
            </p>
            <div className="mt-4 space-y-3">
              <TextField label={`Amount (${selectedAd.fiat_currency})`} value={tradeAmount} onChange={setTradeAmount} placeholder={`${selectedAd.min_limit} - ${selectedAd.max_limit}`} />
              <SelectField label="Payment Method" value={tradePaymentMethod} onChange={setTradePaymentMethod} options={selectedAd.payment_methods ?? ["Bank Transfer"]} />
            </div>
            <div className="mt-5 grid grid-cols-2 gap-3">
              <button type="button" onClick={() => setSelectedAd(null)} className="h-11 rounded-xl border border-violet-300/25 bg-violet-950/35 text-sm font-semibold text-violet-100">
                Cancel
              </button>
              <button type="button" onClick={submitTrade} disabled={busy || !tradeAmount} className="h-11 rounded-xl bg-gradient-to-r from-amber-300 to-yellow-500 text-sm font-bold text-black disabled:opacity-60">
                {busy ? "Opening..." : "Open Trade"}
              </button>
            </div>
          </section>
        </div>
      ) : null}
    </main>
  );
}

function TextField({ label, value, onChange, placeholder, type = "text" }) {
  return (
    <label className="space-y-1">
      <span className="text-xs font-medium text-violet-100/65">{label}</span>
      <input type={type} value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} className="w-full rounded-xl border border-violet-300/20 bg-[#100a1c] px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/70" />
    </label>
  );
}

function SelectField({ label, value, onChange, options }) {
  return (
    <label className="space-y-1">
      <span className="text-xs font-medium text-violet-100/65">{label}</span>
      <div className="relative">
        <select value={value} onChange={(event) => onChange(event.target.value)} className="w-full appearance-none rounded-xl border border-violet-300/20 bg-[#100a1c] px-3 py-2.5 pr-10 text-sm text-white outline-none transition focus:border-amber-300/70">
          {options.map((option) => <option key={option} value={option}>{option}</option>)}
        </select>
        <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-violet-200/80" />
      </div>
    </label>
  );
}

function AdCard({ ad, selectedAssetMeta, actionLabel, onOpen }) {
  return (
    <article className="rounded-2xl border border-violet-300/15 bg-[#120c20]/85 p-4 shadow-[0_10px_28px_rgba(0,0,0,0.35)] transition duration-300 hover:-translate-y-1 hover:border-amber-300/40 sm:p-5">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div className="space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <TokenLogo symbol={ad.asset} tone={selectedAssetMeta.tone} />
            <span className="text-sm font-semibold text-white">{ad.merchant?.name ?? "Merchant"}</span>
            {ad.merchant?.email_verified ? (
              <span className="inline-flex items-center gap-1 rounded-full border border-emerald-300/35 bg-emerald-400/10 px-2 py-0.5 text-[11px] font-semibold text-emerald-200">
                <BadgeCheck className="h-3.5 w-3.5" />
                Verified
              </span>
            ) : null}
            <span className="rounded-full border border-violet-300/20 bg-violet-500/10 px-2 py-0.5 text-[11px] uppercase text-violet-100/75">{ad.type}</span>
          </div>
          <div className="grid gap-2 text-sm text-violet-100/85 sm:grid-cols-3">
            <p><span className="block text-xs text-violet-100/55">Price</span><span className="font-semibold text-amber-200">{formatNumber(ad.price, 4)} {ad.fiat_currency}</span></p>
            <p><span className="block text-xs text-violet-100/55">Available</span><span className="font-medium text-violet-50">{formatNumber(ad.available_amount)} {ad.asset}</span></p>
            <p><span className="block text-xs text-violet-100/55">Limits</span><span className="font-medium text-violet-50">{formatNumber(ad.min_limit, 2)} - {formatNumber(ad.max_limit, 2)} {ad.fiat_currency}</span></p>
          </div>
          <div className="flex flex-wrap gap-2">
            {(ad.payment_methods ?? []).map((method) => (
              <span key={method} className="rounded-full border border-violet-300/25 bg-violet-400/10 px-2.5 py-1 text-xs text-violet-100/85">{method}</span>
            ))}
          </div>
        </div>
        {onOpen ? (
          <button type="button" onClick={onOpen} className="h-11 rounded-xl bg-gradient-to-r from-amber-300 to-yellow-500 px-6 text-sm font-bold text-black transition hover:scale-[1.02] active:scale-[0.99]">
            {actionLabel}
          </button>
        ) : null}
      </div>
    </article>
  );
}

function TradeCard({ trade, userId, busy, onAction }) {
  const isBuyer = Number(trade.buyer?.id) === Number(userId);
  const isSeller = Number(trade.seller?.id) === Number(userId);
  const canMarkPaid = isBuyer && trade.status === "pending";
  const canRelease = isSeller && trade.status === "payment_sent";
  const canCancel = trade.status === "pending";
  const canDispute = ["pending", "payment_sent"].includes(trade.status);

  return (
    <article className="rounded-2xl border border-violet-300/15 bg-[#120c20]/85 p-4 shadow-[0_10px_28px_rgba(0,0,0,0.35)] sm:p-5">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <strong className="text-white">{formatNumber(trade.crypto_amount)} {trade.asset}</strong>
            <span className="rounded-full border border-violet-300/20 bg-violet-500/10 px-2 py-0.5 text-xs uppercase text-violet-100/75">{trade.status}</span>
            <span className="text-xs text-violet-100/55">{trade.trade_uuid}</span>
          </div>
          <p className="mt-2 text-sm text-violet-100/75">
            {formatNumber(trade.fiat_amount, 2)} {trade.fiat_currency} via {trade.payment_method}
          </p>
          <p className="mt-1 text-xs text-violet-100/55">
            Buyer: {trade.buyer?.name ?? "--"} | Seller: {trade.seller?.name ?? "--"}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {canMarkPaid ? <ActionButton disabled={busy} onClick={() => onAction(trade, "payment-sent")}>Payment Sent</ActionButton> : null}
          {canRelease ? <ActionButton disabled={busy} onClick={() => onAction(trade, "release")}>Release</ActionButton> : null}
          {canCancel ? <ActionButton disabled={busy} muted onClick={() => onAction(trade, "cancel")}>Cancel</ActionButton> : null}
          {canDispute ? <ActionButton disabled={busy} muted onClick={() => onAction(trade, "disputes", { reason: "User opened a marketplace dispute.", evidence: [] })}>Dispute</ActionButton> : null}
        </div>
      </div>
    </article>
  );
}

function ActionButton({ children, onClick, disabled, muted = false }) {
  return (
    <button type="button" onClick={onClick} disabled={disabled} className={`inline-flex h-10 items-center gap-2 rounded-xl px-4 text-sm font-semibold transition disabled:opacity-60 ${muted ? "border border-violet-300/25 bg-violet-950/35 text-violet-100 hover:border-amber-300/50" : "bg-gradient-to-r from-amber-300 to-yellow-500 text-black"}`}>
      {children === "Dispute" ? <MessageSquare className="h-4 w-4" /> : null}
      {children}
    </button>
  );
}

function CreateAdForm({ form, setForm, busy, onSubmit }) {
  const setField = (field, value) => setForm((current) => ({ ...current, [field]: value }));
  const togglePayment = (method) => {
    setForm((current) => {
      const exists = current.payment_methods.includes(method);
      const next = exists ? current.payment_methods.filter((item) => item !== method) : [...current.payment_methods, method];
      return { ...current, payment_methods: next.length ? next : [method] };
    });
  };

  return (
    <form onSubmit={onSubmit} className="mt-5 rounded-2xl border border-violet-300/15 bg-[#120c22]/70 p-4 sm:p-5">
      <div className="mb-4 flex items-center gap-2">
        <Plus className="h-5 w-5 text-amber-200" />
        <h2 className="font-['Sora'] text-xl font-semibold text-white">Create P2P Ad</h2>
      </div>
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <SelectField label="Ad Type" value={form.type} onChange={(value) => setField("type", value)} options={["sell", "buy"]} />
        <SelectField label="Asset" value={form.asset} onChange={(value) => setField("asset", value)} options={assets.map((asset) => asset.symbol)} />
        <SelectField label="Fiat" value={form.fiat_currency} onChange={(value) => setField("fiat_currency", value)} options={fiatOptions} />
        <TextField label="Price" value={form.price} onChange={(value) => setField("price", value)} placeholder="1500" />
        <TextField label="Min Limit" value={form.min_limit} onChange={(value) => setField("min_limit", value)} placeholder="10000" />
        <TextField label="Max Limit" value={form.max_limit} onChange={(value) => setField("max_limit", value)} placeholder="100000" />
        <TextField label="Available Amount" value={form.available_amount} onChange={(value) => setField("available_amount", value)} placeholder="250" />
        <SelectField label="Payment Window" value={String(form.payment_time_limit_minutes)} onChange={(value) => setField("payment_time_limit_minutes", Number(value))} options={["15", "30", "45", "60"]} />
      </div>
      <div className="mt-4">
        <span className="text-xs font-medium text-violet-100/65">Payment Methods</span>
        <div className="mt-2 flex flex-wrap gap-2">
          {paymentOptions.filter((method) => method !== "All Methods").map((method) => (
            <button key={method} type="button" onClick={() => togglePayment(method)} className={`rounded-full border px-3 py-1.5 text-sm transition ${form.payment_methods.includes(method) ? "border-amber-300/70 bg-amber-300/15 text-amber-100" : "border-violet-300/25 bg-violet-400/10 text-violet-100/80"}`}>
              {method}
            </button>
          ))}
        </div>
      </div>
      <label className="mt-4 block space-y-1">
        <span className="text-xs font-medium text-violet-100/65">Terms</span>
        <textarea value={form.terms_of_trade} onChange={(event) => setField("terms_of_trade", event.target.value)} rows={3} className="w-full rounded-xl border border-violet-300/20 bg-[#100a1c] px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/70" />
      </label>
      <button type="submit" disabled={busy} className="mt-5 h-12 w-full rounded-xl bg-gradient-to-r from-amber-300 via-amber-400 to-yellow-500 text-base font-bold text-black shadow-[0_0_26px_rgba(245,158,11,0.35)] transition hover:brightness-105 disabled:opacity-60">
        {busy ? "Publishing..." : "Publish Ad"}
      </button>
    </form>
  );
}

function EmptyPanel({ title, body }) {
  return (
    <div className="rounded-2xl border border-violet-300/15 bg-[#120c20]/85 p-10 text-center">
      <CircleAlert className="mx-auto h-10 w-10 text-violet-200/70" />
      <p className="mt-3 text-base font-semibold text-violet-50">{title}</p>
      <p className="mt-1 text-sm text-violet-100/65">{body}</p>
    </div>
  );
}

function LoadingPanel() {
  return (
    <div className="rounded-2xl border border-violet-300/15 bg-[#120c20]/85 p-10 text-center">
      <Loader2 className="mx-auto h-10 w-10 animate-spin text-amber-200" />
      <p className="mt-3 text-sm font-semibold text-violet-50">Loading P2P market...</p>
    </div>
  );
}

export default P2PMarketplace;
