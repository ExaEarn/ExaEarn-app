import { useCallback, useEffect, useMemo, useState } from "react";
import Decimal from "decimal.js";
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
  X,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";

const tokenTones = {
  XRP: "from-sky-400 to-blue-500",
  USDT: "from-emerald-400 to-green-500",
  USDC: "from-cyan-300 to-blue-500",
  BTC: "from-amber-300 to-orange-500",
  ETH: "from-indigo-300 to-violet-500",
  SOL: "from-fuchsia-400 to-emerald-400",
  EXA: "from-amber-200 to-yellow-600",
};

const fallbackAssets = ["USDT", "USDC", "BTC", "ETH", "SOL", "XRP", "EXA"].map((symbol) => ({
  symbol,
  name: symbol,
  tone: tokenTones[symbol] || "from-slate-300 to-slate-600",
}));

const fallbackPaymentOptions = ["All Methods", "Bank Transfer", "Opay", "Airtel Money", "PalmPay"];
const fallbackFiatOptions = ["NGN", "USD", "GHS", "KES", "ZAR"];

const defaultAdForm = {
  type: "sell",
  asset: "USDT",
  fiat_currency: "NGN",
  price: "",
  min_limit: "",
  max_limit: "",
  available_amount: "",
  payment_methods: [],
  payment_time_limit_minutes: 15,
  terms_of_trade: "",
};

const defaultPaymentMethodForm = {
  method_type: "Bank Transfer",
  fiat_currency: "NGN",
  display_name: "",
  bank_name: "",
  account_name: "",
  account_number: "",
  payment_note: "",
  is_default: false,
};

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

function extractFieldErrors(error) {
  const fieldBag = error?.payload?.errors ?? error?.errors ?? {};
  if (!fieldBag || typeof fieldBag !== "object") return {};
  return Object.entries(fieldBag).reduce((carry, [field, messages]) => {
    carry[field] = Array.isArray(messages) ? String(messages[0] ?? "") : String(messages ?? "");
    return carry;
  }, {});
}

function paymentMethodLabel(method) {
  return method.display_name || [method.bank_name, method.account_name].filter(Boolean).join(" â€¢ ") || method.method_type;
}

function TokenLogo({ symbol, tone, iconUrl }) {
  return (
    <span className={`inline-flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br ${tone} text-[11px] font-bold text-white shadow-[var(--exa-shadow-soft)]`}>
      {iconUrl ? <img src={iconUrl} alt="" className="h-full w-full object-cover" /> : symbol.slice(0, 2)}
    </span>
  );
}

function ProductNav({ onOpenConvert, onOpenFiatGateway }) {
  const items = [
    { label: "Convert", onClick: onOpenConvert },
    { label: "P2P", active: true },
    { label: "Fiat Gateway", onClick: onOpenFiatGateway },
  ];

  return (
    <nav className="flex gap-5 overflow-x-auto border-b border-[var(--exa-border)] px-3 py-2 text-xs font-semibold sm:px-4" aria-label="P2P product navigation">
      {items.map((item) => (
        <button
          key={item.label}
          type="button"
          onClick={item.onClick}
          disabled={!item.onClick && !item.active}
          className={`relative min-w-fit py-1.5 transition ${item.active ? "text-[var(--exa-gold-light)]" : "text-[var(--exa-text-muted)] hover:text-white disabled:opacity-40"}`}
        >
          {item.label}
          {item.active ? <span className="absolute inset-x-0 -bottom-2 h-0.5 rounded-full bg-amber-300" /> : null}
        </button>
      ))}
    </nav>
  );
}

function P2PMarketplace({ onBack, onOpenConvert, onOpenFiatGateway, initialTradeSide = "buy" }) {
  const { request, user } = useAuth();
  const [tab, setTab] = useState("market");
  const [tradeSide, setTradeSide] = useState(initialTradeSide === "sell" ? "sell" : "buy");
  const [selectedAsset, setSelectedAsset] = useState("USDT");
  const [fiatCurrency, setFiatCurrency] = useState("NGN");
  const [paymentMethod, setPaymentMethod] = useState("All Methods");
  const [amount, setAmount] = useState("");
  const [ads, setAds] = useState([]);
  const [myAds, setMyAds] = useState([]);
  const [trades, setTrades] = useState([]);
  const [savedPaymentMethods, setSavedPaymentMethods] = useState([]);
  const [selectedAd, setSelectedAd] = useState(null);
  const [tradeAmount, setTradeAmount] = useState("");
  const [tradePaymentMethod, setTradePaymentMethod] = useState("Bank Transfer");
  const [notice, setNotice] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [createAdOpen, setCreateAdOpen] = useState(false);
  const [paymentMethodOpen, setPaymentMethodOpen] = useState(false);
  const [adForm, setAdForm] = useState(defaultAdForm);

  useEffect(() => {
    setTradeSide(initialTradeSide === "sell" ? "sell" : "buy");
    setTab("market");
  }, [initialTradeSide]);
  const [createAdErrors, setCreateAdErrors] = useState({});
  const [createAdSaving, setCreateAdSaving] = useState(false);
  const [paymentMethodForm, setPaymentMethodForm] = useState(defaultPaymentMethodForm);
  const [paymentMethodErrors, setPaymentMethodErrors] = useState({});
  const [paymentMethodSaving, setPaymentMethodSaving] = useState(false);
  const [activeTrade, setActiveTrade] = useState(null);
  const [tradeMessages, setTradeMessages] = useState([]);
  const [chatMessage, setChatMessage] = useState("");
  const [paymentProofFile, setPaymentProofFile] = useState(null);
  const [paymentProofUploading, setPaymentProofUploading] = useState(false);
  const [paymentConfirmOpen, setPaymentConfirmOpen] = useState(false);
  const [releaseConfirmOpen, setReleaseConfirmOpen] = useState(false);
  const [p2pMeta, setP2pMeta] = useState({ assets: [], fiat_currencies: [], payment_method_types: [] });

  const availableAssets = useMemo(() => {
    const source = Array.isArray(p2pMeta.assets) && p2pMeta.assets.length ? p2pMeta.assets : fallbackAssets;
    return source.map((asset) => {
      const symbol = String(asset.symbol || asset).toUpperCase();
      return {
        symbol,
        name: asset.name || symbol,
        icon_url: asset.icon_url || asset.icon || null,
        tone: tokenTones[symbol] || "from-slate-300 to-slate-600",
      };
    });
  }, [p2pMeta.assets]);

  const fiatOptions = useMemo(() => {
    const source = Array.isArray(p2pMeta.fiat_currencies) && p2pMeta.fiat_currencies.length ? p2pMeta.fiat_currencies : fallbackFiatOptions;
    return source.map((currency) => String(currency).toUpperCase());
  }, [p2pMeta.fiat_currencies]);

  const paymentOptions = useMemo(() => {
    const source = Array.isArray(p2pMeta.payment_method_types) && p2pMeta.payment_method_types.length ? p2pMeta.payment_method_types : fallbackPaymentOptions.filter((item) => item !== "All Methods");
    return ["All Methods", ...source.filter(Boolean)];
  }, [p2pMeta.payment_method_types]);

  const copyText = useCallback(async (value, label = "Details") => {
    const text = String(value || "").trim();
    if (!text) return;

    try {
      await navigator.clipboard.writeText(text);
      setNotice(`${label} copied.`);
    } catch {
      setError("Unable to copy details right now.");
    }
  }, []);

  const selectedAssetMeta = availableAssets.find((asset) => asset.symbol === selectedAsset) ?? availableAssets[0];

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

  const walletBalanceMap = useMemo(() => {
    const source = user?.wallets || user?.balances || [];
    if (!Array.isArray(source)) return {};

    return source.reduce((carry, item) => {
      const symbol = String(item?.currency || item?.asset || item?.symbol || "").toUpperCase();
      if (!symbol) return carry;
      carry[symbol] = String(item?.available_balance ?? item?.available ?? item?.balance ?? "0");
      return carry;
    }, {});
  }, [user]);

  const loadP2P = useCallback(async (mode = "default") => {
    setLoading(mode !== "silent");
    setError("");

    try {
      const calls = [
        request("/api/p2p/meta", { method: "GET" }),
        request(`/api/p2p/ads?${query}`, { method: "GET" }),
        request("/api/p2p/ads/mine", { method: "GET" }),
        request("/api/p2p/trades/mine", { method: "GET" }),
      ];

      if (user) {
        calls.push(request("/api/p2p/payment-methods", { method: "GET" }));
      }

      const [metaPayload, adsPayload, myAdsPayload, tradesPayload, paymentMethodsPayload] = await Promise.all(calls);
      setP2pMeta(metaPayload?.data || {});
      setAds(unwrapList(adsPayload));
      setMyAds(unwrapList(myAdsPayload));
      setTrades(unwrapList(tradesPayload));
      setSavedPaymentMethods(user ? unwrapList(paymentMethodsPayload) : []);
    } catch (loadError) {
      setError(loadError.message || "Unable to load P2P marketplace.");
    } finally {
      setLoading(false);
    }
  }, [query, request, user]);

  useEffect(() => {
    loadP2P();
  }, [loadP2P]);

  useEffect(() => {
    if (availableAssets.length && !availableAssets.some((asset) => asset.symbol === selectedAsset)) {
      setSelectedAsset(availableAssets[0].symbol);
    }
  }, [availableAssets, selectedAsset]);

  useEffect(() => {
    if (fiatOptions.length && !fiatOptions.includes(fiatCurrency)) {
      setFiatCurrency(fiatOptions[0]);
    }
  }, [fiatCurrency, fiatOptions]);

  const eligibleCreateAdPaymentMethods = useMemo(() => {
    const desiredFiat = String(adForm.fiat_currency || "").toUpperCase();
    const grouped = new Map();

    savedPaymentMethods.forEach((method) => {
      if (String(method?.status || "active").toLowerCase() !== "active") return;
      if (String(method?.fiat_currency || "").toUpperCase() !== desiredFiat) return;
      const key = String(method.method_type || "").trim();
      if (!key || grouped.has(key)) return;
      grouped.set(key, method);
    });

    return Array.from(grouped.values());
  }, [adForm.fiat_currency, savedPaymentMethods]);

  const createAdAssetBalance = useMemo(() => walletBalanceMap[String(adForm.asset || "").toUpperCase()] ?? "0", [adForm.asset, walletBalanceMap]);

  useEffect(() => {
    setAdForm((current) => ({
      ...current,
      payment_methods: current.payment_methods.filter((selected) =>
        eligibleCreateAdPaymentMethods.some((method) => method.method_type === selected),
      ),
    }));
  }, [eligibleCreateAdPaymentMethods]);

  const openTradeModal = (ad) => {
    setSelectedAd(ad);
    setTradeAmount("");
    setTradePaymentMethod(ad.payment_methods?.[0] || "Bank Transfer");
    setError("");
    setNotice("");
  };

  const openCreateAdModal = () => {
    setCreateAdErrors({});
    setError("");
    setNotice("");
    setCreateAdOpen(true);
  };

  const openPaymentMethodEditor = () => {
    setPaymentMethodErrors({});
    setPaymentMethodForm((current) => ({
      ...defaultPaymentMethodForm,
      fiat_currency: adForm.fiat_currency || current.fiat_currency || "NGN",
      method_type: current.method_type || "Bank Transfer",
    }));
    setPaymentMethodOpen(true);
  };
  const openTradeRoom = async (trade) => {
    if (!trade?.trade_uuid) return;
    setError("");
    try {
      const [tradePayload, messagesPayload] = await Promise.all([
        request(`/api/p2p/trades/${trade.trade_uuid}`, { method: "GET" }),
        request(`/api/p2p/trades/${trade.trade_uuid}/messages`, { method: "GET" }),
      ]);
      setActiveTrade(tradePayload?.data || trade);
      setTradeMessages(unwrapList(messagesPayload));
      setTab("trades");
    } catch (roomError) {
      setError(roomError.message || "Unable to open P2P order room.");
    }
  };

  const refreshTradeRoom = async () => {
    if (!activeTrade?.trade_uuid) return;
    await openTradeRoom(activeTrade);
  };

  const uploadPaymentProof = async () => {
    if (!activeTrade?.trade_uuid || !paymentProofFile) return;
    setPaymentProofUploading(true);
    setError("");
    try {
      const body = new FormData();
      body.append("proof", paymentProofFile);
      const payload = await request(`/api/p2p/trades/${activeTrade.trade_uuid}/payment-proof`, {
        method: "POST",
        body,
        timeoutMs: 30000,
      });
      setActiveTrade(payload?.data || activeTrade);
      setPaymentProofFile(null);
      await refreshTradeRoom();
      setNotice("Payment proof uploaded. The seller must still verify real receipt before release.");
    } catch (proofError) {
      setError(proofError.message || "Unable to upload payment proof.");
    } finally {
      setPaymentProofUploading(false);
    }
  };

  const confirmPaymentSent = async () => {
    if (!activeTrade) return;
    await tradeAction(activeTrade, "payment-sent");
    setPaymentConfirmOpen(false);
    await refreshTradeRoom();
  };

  const confirmRelease = async () => {
    if (!activeTrade) return;
    await tradeAction(activeTrade, "release");
    setReleaseConfirmOpen(false);
    await refreshTradeRoom();
  };

  const sendTradeMessage = async () => {
    if (!activeTrade?.trade_uuid || !chatMessage.trim()) return;
    setBusy(true);
    setError("");
    try {
      await request(`/api/p2p/trades/${activeTrade.trade_uuid}/messages`, {
        method: "POST",
        body: JSON.stringify({ message: chatMessage.trim() }),
      });
      setChatMessage("");
      await refreshTradeRoom();
    } catch (messageError) {
      setError(messageError.message || "Unable to send message.");
    } finally {
      setBusy(false);
    }
  };


  const setAdField = (field, value) => {
    setAdForm((current) => ({ ...current, [field]: value }));
    setCreateAdErrors((current) => ({ ...current, [field]: undefined }));
  };

  const setPaymentMethodField = (field, value) => {
    setPaymentMethodForm((current) => ({ ...current, [field]: value }));
    setPaymentMethodErrors((current) => ({ ...current, [field]: undefined }));
  };

  const validatePaymentMethodForm = () => {
    const nextErrors = {};
    if (!paymentMethodForm.method_type) nextErrors.method_type = "Select a payment method.";
    if (!paymentMethodForm.fiat_currency) nextErrors.fiat_currency = "Select a fiat currency.";
    if (!paymentMethodForm.display_name.trim()) nextErrors.display_name = "Enter a display name.";
    if (!paymentMethodForm.bank_name.trim()) nextErrors.bank_name = "Enter the bank name.";
    if (!paymentMethodForm.account_name.trim()) nextErrors.account_name = "Enter the account name.";
    if (!paymentMethodForm.account_number.trim()) nextErrors.account_number = "Enter the account number.";
    return nextErrors;
  };

  const savePaymentMethod = async () => {
    const nextErrors = validatePaymentMethodForm();
    setPaymentMethodErrors(nextErrors);

    if (Object.keys(nextErrors).length) {
      return;
    }

    setPaymentMethodSaving(true);
    setError("");
    setNotice("");

    try {
      await request("/api/p2p/payment-methods", {
        method: "POST",
        body: JSON.stringify({
          ...paymentMethodForm,
          fiat_currency: paymentMethodForm.fiat_currency.toUpperCase(),
        }),
      });

      const paymentMethodsPayload = await request("/api/p2p/payment-methods", { method: "GET" });
      setSavedPaymentMethods(unwrapList(paymentMethodsPayload));
      setPaymentMethodOpen(false);
      setNotice("Payment method saved successfully.");
    } catch (saveError) {
      setPaymentMethodErrors((current) => ({ ...current, ...extractFieldErrors(saveError) }));
      setError(saveError.message || "Unable to save payment method.");
    } finally {
      setPaymentMethodSaving(false);
    }
  };

  const validateCreateAdForm = () => {
    const nextErrors = {};

    if (!adForm.type) nextErrors.type = "Select Buy or Sell.";
    if (!adForm.asset) nextErrors.asset = "Select an asset.";
    if (!adForm.fiat_currency) nextErrors.fiat_currency = "Select a fiat currency.";
    if (!adForm.payment_time_limit_minutes) nextErrors.payment_time_limit_minutes = "Select a payment window.";

    const numericFields = [
      ["price", "Enter a valid price."],
      ["available_amount", "Enter the available amount."],
      ["min_limit", "Enter the minimum limit."],
      ["max_limit", "Enter the maximum limit."],
    ];

    numericFields.forEach(([field, message]) => {
      try {
        const value = new Decimal(String(adForm[field] || "0"));
        if (!value.isFinite() || value.lte(0)) nextErrors[field] = message;
      } catch {
        nextErrors[field] = message;
      }
    });

    try {
      const min = new Decimal(String(adForm.min_limit || "0"));
      const max = new Decimal(String(adForm.max_limit || "0"));
      if (max.lt(min)) nextErrors.max_limit = "Maximum limit must be greater than or equal to the minimum limit.";
    } catch {
      // handled above
    }

    if (!adForm.payment_methods.length) {
      nextErrors.payment_methods = `Select at least one payment method for ${adForm.fiat_currency}.`;
    }

    if (adForm.type === "sell") {
      try {
        const available = new Decimal(String(createAdAssetBalance || "0"));
        const requested = new Decimal(String(adForm.available_amount || "0"));
        if (requested.gt(available)) {
          nextErrors.available_amount = `You can only publish up to ${available.toFixed()} ${adForm.asset} from your available balance.`;
        }
      } catch {
        nextErrors.available_amount = "Unable to verify the available balance for this asset.";
      }
    }

    return nextErrors;
  };

  const submitTrade = async () => {
    if (!selectedAd || !tradeAmount) return;
    setBusy(true);
    setError("");
    setNotice("");
    try {
      const payload = await request(`/api/p2p/ads/${selectedAd.id}/trades`, {
        method: "POST",
        body: JSON.stringify({
          fiat_amount: tradeAmount,
          payment_method: tradePaymentMethod,
        }),
      });
      setSelectedAd(null);
      setNotice("Trade opened. Escrow is locked and the payment timer has started.");
      setTab("trades");
      await loadP2P("silent");
    } catch (tradeError) {
      setError(tradeError.message || "Unable to open trade.");
    } finally {
      setBusy(false);
    }
  };

  const createAd = async () => {
    const nextErrors = validateCreateAdForm();
    setCreateAdErrors(nextErrors);

    if (Object.keys(nextErrors).length) {
      return;
    }

    setCreateAdSaving(true);
    setError("");
    setNotice("");

    try {
      await request("/api/p2p/ads", {
        method: "POST",
        body: JSON.stringify({
          ...adForm,
          fiat_currency: String(adForm.fiat_currency).toUpperCase(),
        }),
      });

      setNotice("P2P ad created successfully.");
      setCreateAdOpen(false);
      setTab("myAds");
      setAdForm((current) => ({
        ...defaultAdForm,
        asset: current.asset,
        fiat_currency: current.fiat_currency,
        type: current.type,
      }));
      await loadP2P("silent");
    } catch (adError) {
      setCreateAdErrors((current) => ({ ...current, ...extractFieldErrors(adError) }));
      setError(adError.message || "Unable to create P2P ad.");
    } finally {
      setCreateAdSaving(false);
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
      await loadP2P("silent");
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
    <main className="relative min-h-screen overflow-hidden bg-[var(--exa-bg-primary)] px-2 py-3 text-[var(--exa-text-primary)] sm:px-4 sm:py-5">
      <div className="pointer-events-none absolute -left-24 top-24 h-56 w-56 rounded-full bg-[var(--exa-gold-surface)] blur-3xl" />
      <div className="pointer-events-none absolute right-0 top-1/3 h-64 w-64 rounded-full bg-amber-400/10 blur-3xl" />

      <section className="mx-auto flex min-h-[calc(100dvh-4rem)] w-full max-w-7xl flex-col overflow-hidden rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] shadow-[var(--exa-shadow-panel)] backdrop-blur-xl">
        <header className="border-b border-[var(--exa-border)] bg-[var(--exa-surface-elevated)]">
          <ProductNav onOpenConvert={onOpenConvert} onOpenFiatGateway={onOpenFiatGateway} />
          <div className="flex flex-wrap items-center justify-between gap-3 px-3 py-3 sm:px-4">
            <div className="flex min-w-0 items-center gap-3">
              {onBack ? (
                <button type="button" onClick={onBack} className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-secondary)] transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]" aria-label="Back">
                  <ArrowLeft className="h-4 w-4" />
                </button>
              ) : null}
              <div className="min-w-0">
                <div className="flex items-center gap-2">
                  <h1 className="truncate font-['Sora'] text-lg font-semibold text-white sm:text-xl">P2P</h1>
                  <span className="inline-flex items-center gap-1 rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-2 py-0.5 text-[11px] font-semibold text-[var(--exa-gold-light)]">
                    <ShieldCheck className="h-3.5 w-3.5" /> Escrow
                  </span>
                </div>
                <p className="truncate text-xs text-[var(--exa-text-muted)]">Buy and sell crypto with verified counterparties.</p>
              </div>
            </div>
            <div className="flex shrink-0 items-center gap-2">
              <button type="button" onClick={() => loadP2P()} disabled={loading} className="inline-flex h-9 items-center gap-2 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 text-xs font-semibold text-[var(--exa-text-secondary)] transition hover:border-[var(--exa-border-active)] disabled:opacity-60">
                {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                Refresh
              </button>
              <button type="button" onClick={openCreateAdModal} className="inline-flex h-9 items-center gap-2 rounded-lg exa-button-primary px-3 text-xs font-bold text-black">
                <Plus className="h-4 w-4" /> Post Ad
              </button>
            </div>
          </div>

          <div className="flex gap-1 overflow-x-auto border-t border-[var(--exa-border)] px-3 py-2 sm:px-4">
            {[
              ["market", "Marketplace"],
              ["trades", "My Orders"],
              ["myAds", "My Ads"],
            ].map(([key, label]) => (
              <button key={key} type="button" onClick={() => setTab(key)} className={`min-w-fit rounded-lg px-3 py-2 text-xs font-semibold transition ${tab === key ? "bg-[var(--exa-gold)] text-[var(--exa-gold-contrast)]" : "text-[var(--exa-text-secondary)] hover:bg-[var(--exa-surface-hover)] hover:text-white"}`}>
                {label}
              </button>
            ))}
          </div>
        </header>

        {notice ? <div className="mt-4 rounded-xl border border-emerald-300/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{notice}</div> : null}
        {error ? <div className="mt-4 rounded-xl border border-rose-300/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">{error}</div> : null}

        {tab === "market" ? (
          <section className="mt-5 space-y-5">
            <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
              <div className="inline-flex rounded-full border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-1">
                <button type="button" onClick={() => setTradeSide("buy")} className={`rounded-full px-5 py-2 text-sm font-semibold transition ${tradeSide === "buy" ? "exa-button-primary text-black" : "text-[var(--exa-text-secondary)] hover:text-white"}`}>
                  Buy
                </button>
                <button type="button" onClick={() => setTradeSide("sell")} className={`rounded-full px-5 py-2 text-sm font-semibold transition ${tradeSide === "sell" ? "bg-gradient-to-r from-[var(--exa-surface-hover)] to-[var(--exa-surface-elevated)] text-white" : "text-[var(--exa-text-secondary)] hover:text-white"}`}>
                  Sell
                </button>
              </div>

              <div className="mt-4 flex gap-3 overflow-x-auto pb-1">
                {availableAssets.map((asset) => (
                  <button key={asset.symbol} type="button" onClick={() => setSelectedAsset(asset.symbol)} className={`inline-flex min-w-fit items-center gap-2 rounded-xl border px-3 py-2 transition ${selectedAsset === asset.symbol ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-hover)] hover:border-[var(--exa-border-active)]"}`}>
                    <TokenLogo symbol={asset.symbol} tone={asset.tone} iconUrl={asset.icon_url} />
                    <span className="text-sm font-semibold text-[var(--exa-text-primary)]">{asset.symbol}</span>
                  </button>
                ))}
              </div>
            </div>

            <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
              <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <SelectField label="Fiat Currency" value={fiatCurrency} onChange={setFiatCurrency} options={fiatOptions} />
                <SelectField label="Payment Method" value={paymentMethod} onChange={setPaymentMethod} options={paymentOptions} />
                <TextField label="Fiat Amount" value={amount} onChange={setAmount} placeholder="e.g. 30000" />
                <div className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2.5">
                  <span className="block text-xs text-[var(--exa-text-muted)]">Current Side</span>
                  <strong className="text-sm text-[var(--exa-text-primary)]">{tradeSide === "buy" ? "Buying from sell ads" : "Selling into buy ads"}</strong>
                </div>
              </div>
            </div>

            <section className="space-y-3">
              {loading ? <LoadingPanel /> : null}
              {!loading && filteredAds.length ? filteredAds.map((ad) => (
                <AdCard key={ad.id} ad={ad} selectedAssetMeta={selectedAssetMeta} actionLabel={tradeSide === "buy" ? "Buy" : "Sell"} onOpen={() => openTradeModal(ad)} />
              )) : null}
              {!loading && !filteredAds.length ? <EmptyPanel title="No live ads found" body="Try another asset, payment method, or amount." /> : null}
            </section>
          </section>
        ) : null}

        {tab === "trades" ? (
          <section className="mt-5 space-y-3">
            {trades.length ? trades.map((trade) => (
              <TradeCard key={trade.id} trade={trade} userId={user?.id} busy={busy} onAction={tradeAction} onOpen={() => openTradeRoom(trade)} />
            )) : <EmptyPanel title="No P2P trades yet" body="Open a marketplace trade and it will appear here." />}
          </section>
        ) : null}

        {tab === "myAds" ? (
          <section className="mt-5 space-y-3">
            {myAds.length ? myAds.map((ad) => (
              <AdCard key={ad.id} ad={ad} selectedAssetMeta={availableAssets.find((asset) => asset.symbol === ad.asset) ?? selectedAssetMeta} actionLabel="View" />
            )) : <EmptyPanel title="No ads published yet" body="Create your first buy or sell advert." />}
          </section>
        ) : null}
      </section>

      {activeTrade ? (
        <BottomSheet title={`P2P Order ${activeTrade.trade_uuid || ''}`} onClose={() => setActiveTrade(null)}>
          <OrderRoom
            trade={activeTrade}
            userId={user?.id}
            messages={tradeMessages}
            chatMessage={chatMessage}
            onChatChange={setChatMessage}
            onSendMessage={sendTradeMessage}
            onCopy={copyText}
            proofFile={paymentProofFile}
            onProofFile={setPaymentProofFile}
            onUploadProof={uploadPaymentProof}
            proofUploading={paymentProofUploading}
            busy={busy}
            onConfirmPaid={() => setPaymentConfirmOpen(true)}
            onConfirmRelease={() => setReleaseConfirmOpen(true)}
            onCancel={() => tradeAction(activeTrade, "cancel")}
            onAppeal={() => tradeAction(activeTrade, "disputes", { reason: "User opened a P2P order appeal from the order room.", evidence: [] })}
          />
        </BottomSheet>
      ) : null}

      {paymentConfirmOpen && activeTrade ? (
        <BottomSheet title="Confirm Payment" onClose={() => setPaymentConfirmOpen(false)} footer={<div className="grid grid-cols-2 gap-3"><button type="button" onClick={() => setPaymentConfirmOpen(false)} className="h-11 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-sm font-semibold text-[var(--exa-text-secondary)]">Cancel</button><button type="button" onClick={confirmPaymentSent} disabled={busy} className="h-11 rounded-xl exa-button-primary text-sm font-bold text-black disabled:opacity-60">Confirm Payment</button></div>}>
          <p className="text-sm leading-6 text-[var(--exa-text-secondary)]">You are confirming that you have transferred {formatNumber(activeTrade.fiat_amount, 2)} {activeTrade.fiat_currency} to the seller's payment account shown inside this order.</p>
          <div className="mt-4 rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] p-4 text-sm text-[var(--exa-gold-light)]">Do not click Confirm Payment until you have actually completed the transfer. False payment claims may restrict your account.</div>
        </BottomSheet>
      ) : null}

      {releaseConfirmOpen && activeTrade ? (
        <BottomSheet title="Release Crypto" onClose={() => setReleaseConfirmOpen(false)} footer={<div className="grid grid-cols-2 gap-3"><button type="button" onClick={() => setReleaseConfirmOpen(false)} className="h-11 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-sm font-semibold text-[var(--exa-text-secondary)]">Cancel</button><button type="button" onClick={confirmRelease} disabled={busy} className="h-11 rounded-xl exa-button-primary text-sm font-bold text-black disabled:opacity-60">Release Crypto</button></div>}>
          <p className="text-sm leading-6 text-[var(--exa-text-secondary)]">Only release {formatNumber(activeTrade.crypto_amount)} {activeTrade.asset} after you have personally confirmed the fiat payment arrived in your real payment account.</p>
          <div className="mt-4 rounded-2xl border border-rose-300/25 bg-rose-500/10 p-4 text-sm text-rose-100">Screenshots, SMS messages, and chat claims are not enough. Check your bank or payment provider directly.</div>
        </BottomSheet>
      ) : null}
      {selectedAd ? (
        <BottomSheet title="Open P2P Trade" onClose={() => setSelectedAd(null)} footer={<div className="grid grid-cols-2 gap-3"><button type="button" onClick={() => setSelectedAd(null)} className="h-11 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-sm font-semibold text-[var(--exa-text-secondary)]">Cancel</button><button type="button" onClick={submitTrade} disabled={busy || !tradeAmount} className="h-11 rounded-xl exa-button-primary text-sm font-bold text-black disabled:opacity-60">{busy ? "Opening..." : "Open Trade"}</button></div>}>
          <p className="text-sm text-[var(--exa-text-secondary)]">{selectedAd.asset} at {formatNumber(selectedAd.price, 4)} {selectedAd.fiat_currency}</p>
          <div className="mt-4 space-y-3">
            <TextField label={`Amount (${selectedAd.fiat_currency})`} value={tradeAmount} onChange={setTradeAmount} placeholder={`${selectedAd.min_limit} - ${selectedAd.max_limit}`} />
            <SelectField label="Payment Method" value={tradePaymentMethod} onChange={setTradePaymentMethod} options={selectedAd.payment_methods ?? ["Bank Transfer"]} />
          </div>
        </BottomSheet>
      ) : null}

      {createAdOpen ? (
        <BottomSheet title="Create P2P Ad" onClose={() => !createAdSaving && setCreateAdOpen(false)} footer={<div className="grid grid-cols-2 gap-3"><button type="button" onClick={() => setCreateAdOpen(false)} className="h-11 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-sm font-semibold text-[var(--exa-text-secondary)]">Cancel</button><button type="button" onClick={createAd} disabled={createAdSaving} className="h-11 rounded-xl exa-button-primary text-sm font-bold text-black disabled:opacity-60">{createAdSaving ? "Creating..." : "Create Ad"}</button></div>}>
          <div className="grid gap-3 md:grid-cols-2">
            <SelectField label="Side" value={adForm.type} onChange={(value) => setAdField("type", value)} options={["sell", "buy"]} error={createAdErrors.type} />
            <SelectField label="Asset" value={adForm.asset} onChange={(value) => setAdField("asset", value)} options={availableAssets.map((asset) => asset.symbol)} error={createAdErrors.asset} />
            <SelectField label="Fiat Currency" value={adForm.fiat_currency} onChange={(value) => setAdField("fiat_currency", value)} options={fiatOptions} error={createAdErrors.fiat_currency} />
            <SelectField label="Payment Window" value={String(adForm.payment_time_limit_minutes)} onChange={(value) => setAdField("payment_time_limit_minutes", Number(value))} options={["15", "30", "45", "60"]} error={createAdErrors.payment_time_limit_minutes} />
            <TextField label="Price" value={adForm.price} onChange={(value) => setAdField("price", value)} placeholder="1500" inputMode="decimal" error={createAdErrors.price} />
            <TextField label="Available Amount" value={adForm.available_amount} onChange={(value) => setAdField("available_amount", value)} placeholder="250" inputMode="decimal" error={createAdErrors.available_amount} />
            <TextField label="Minimum Limit" value={adForm.min_limit} onChange={(value) => setAdField("min_limit", value)} placeholder="10000" inputMode="decimal" error={createAdErrors.min_limit} />
            <TextField label="Maximum Limit" value={adForm.max_limit} onChange={(value) => setAdField("max_limit", value)} placeholder="100000" inputMode="decimal" error={createAdErrors.max_limit} />
          </div>

          <div className="mt-4 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-3">
            <span className="block text-xs text-[var(--exa-text-muted)]">Available {adForm.asset} Balance</span>
            <strong className="text-sm text-white">{formatNumber(createAdAssetBalance)} {adForm.asset}</strong>
            {adForm.type === "sell" ? <p className="mt-1 text-xs text-[var(--exa-text-muted)]">Sell ads are validated against your live available balance before they are published.</p> : null}
          </div>

          <div className="mt-4 space-y-2">
            <div className="flex items-center justify-between gap-3">
              <span className="text-xs font-medium text-[var(--exa-text-muted)]">Payment Methods</span>
              <button type="button" onClick={openPaymentMethodEditor} className="inline-flex items-center gap-2 rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-1 text-xs font-semibold text-[var(--exa-text-secondary)]"><Plus className="h-3.5 w-3.5" />Add Payment Method</button>
            </div>

            {eligibleCreateAdPaymentMethods.length ? (
              <div className="space-y-2">
                {eligibleCreateAdPaymentMethods.map((method) => {
                  const selected = adForm.payment_methods.includes(method.method_type);
                  return (
                    <button key={`${method.method_type}-${method.id}`} type="button" onClick={() => {
                      const exists = adForm.payment_methods.includes(method.method_type);
                      setAdField("payment_methods", exists ? adForm.payment_methods.filter((item) => item !== method.method_type) : [...adForm.payment_methods, method.method_type]);
                    }} className={`w-full rounded-2xl border px-4 py-3 text-left transition ${selected ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] hover:border-[var(--exa-border-active)]"}`}>
                      <div className="flex items-center justify-between gap-3">
                        <div>
                          <p className="text-sm font-semibold text-white">{paymentMethodLabel(method)}</p>
                          <p className="mt-1 text-xs text-[var(--exa-text-muted)]">{method.method_type} - {method.fiat_currency} - {method.account_name || method.masked_account_number}</p>
                        </div>
                        <span className={`rounded-full px-2 py-1 text-[11px] font-semibold ${selected ? "bg-[var(--exa-gold)] text-[var(--exa-gold-contrast)]" : "bg-[var(--exa-surface-hover)] text-[var(--exa-text-secondary)]"}`}>{selected ? "Selected" : "Select"}</span>
                      </div>
                    </button>
                  );
                })}
              </div>
            ) : (
              <div className="rounded-2xl border border-dashed border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-4">
                <p className="text-sm font-medium text-white">No payment method available for {adForm.fiat_currency}.</p>
                <p className="mt-1 text-xs text-[var(--exa-text-muted)]">Add a receiving method and we will bring it straight back into this ad flow without clearing your form.</p>
              </div>
            )}
            {createAdErrors.payment_methods ? <p className="text-xs text-rose-300">{createAdErrors.payment_methods}</p> : null}
          </div>

          <TextAreaField label="Terms / Instructions" value={adForm.terms_of_trade} onChange={(value) => setAdField("terms_of_trade", value)} placeholder="Optional payment instructions for counterparties." />
        </BottomSheet>
      ) : null}

      {paymentMethodOpen ? (
        <BottomSheet title="Add Payment Method" onClose={() => !paymentMethodSaving && setPaymentMethodOpen(false)} footer={<div className="grid grid-cols-2 gap-3"><button type="button" onClick={() => setPaymentMethodOpen(false)} className="h-11 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-sm font-semibold text-[var(--exa-text-secondary)]">Cancel</button><button type="button" onClick={savePaymentMethod} disabled={paymentMethodSaving} className="h-11 rounded-xl exa-button-primary text-sm font-bold text-black disabled:opacity-60">{paymentMethodSaving ? "Saving..." : "Save Payment Method"}</button></div>}>
          <div className="grid gap-3 md:grid-cols-2">
            <SelectField label="Method" value={paymentMethodForm.method_type} onChange={(value) => setPaymentMethodField("method_type", value)} options={paymentOptions.filter((item) => item !== "All Methods")} error={paymentMethodErrors.method_type} />
            <SelectField label="Fiat Currency" value={paymentMethodForm.fiat_currency} onChange={(value) => setPaymentMethodField("fiat_currency", value)} options={fiatOptions} error={paymentMethodErrors.fiat_currency} />
            <TextField label="Display Name" value={paymentMethodForm.display_name} onChange={(value) => setPaymentMethodField("display_name", value)} placeholder="GTBank Main Account" error={paymentMethodErrors.display_name} />
            <TextField label="Bank Name" value={paymentMethodForm.bank_name} onChange={(value) => setPaymentMethodField("bank_name", value)} placeholder="GTBank" error={paymentMethodErrors.bank_name} />
            <TextField label="Account Name" value={paymentMethodForm.account_name} onChange={(value) => setPaymentMethodField("account_name", value)} placeholder="John Doe" error={paymentMethodErrors.account_name} />
            <TextField label="Account Number" value={paymentMethodForm.account_number} onChange={(value) => setPaymentMethodField("account_number", value)} placeholder="0123456789" inputMode="numeric" error={paymentMethodErrors.account_number} />
          </div>
          <TextAreaField label="Payment Note (Optional)" value={paymentMethodForm.payment_note} onChange={(value) => setPaymentMethodField("payment_note", value)} placeholder="Optional instructions for counterparties." error={paymentMethodErrors.payment_note} />
          <label className="mt-4 flex items-center gap-3 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-3 text-sm text-[var(--exa-text-secondary)]">
            <input type="checkbox" checked={paymentMethodForm.is_default} onChange={(event) => setPaymentMethodField("is_default", event.target.checked)} className="h-4 w-4 rounded border-[var(--exa-border)] bg-transparent text-amber-300 focus:ring-[var(--exa-gold)]" />
            Use as default receiving account
          </label>
        </BottomSheet>
      ) : null}
    </main>
  );
}

function OrderRoom({ trade, userId, messages, chatMessage, onChatChange, onSendMessage, onCopy, proofFile, onProofFile, onUploadProof, proofUploading, busy, onConfirmPaid, onConfirmRelease, onCancel, onAppeal }) {
  const isBuyer = Number(trade.buyer?.id) === Number(userId);
  const isSeller = Number(trade.seller?.id) === Number(userId);
  const paymentDetails = trade.metadata?.seller_payment_details || {};
  const paymentProof = trade.metadata?.payment_proof || null;
  const canMarkPaid = isBuyer && trade.status === "pending";
  const canRelease = isSeller && trade.status === "payment_sent";
  const canCancel = trade.status === "pending";
  const canAppeal = ["pending", "payment_sent", "disputed"].includes(trade.status);

  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Metric label="Order Type" value={`${isBuyer ? "Buy" : "Sell"} ${trade.asset}`} />
        <Metric label="Status" value={String(trade.status || "--").replaceAll("_", " ")} tone="gold" />
        <Metric label="Crypto Amount" value={`${formatNumber(trade.crypto_amount)} ${trade.asset}`} />
        <Metric label="Fiat Amount" value={`${formatNumber(trade.fiat_amount, 2)} ${trade.fiat_currency}`} />
      </div>

      <section className="grid gap-4 lg:grid-cols-[1.05fr_0.95fr]">
        <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
          <h3 className="text-sm font-semibold text-white">{isBuyer ? "Pay the Seller" : "Payment Verification"}</h3>
          <p className="mt-1 text-xs text-[var(--exa-text-muted)]">Order {trade.trade_uuid}</p>

          {isBuyer ? (
            <div className="mt-4 space-y-3">
              <DetailRow label="Amount to Pay" value={`${formatNumber(trade.fiat_amount, 2)} ${trade.fiat_currency}`} onCopy={() => onCopy(trade.fiat_amount)} />
              <DetailRow label="Payment Method" value={trade.payment_method} />
              <DetailRow label="Bank Name" value={paymentDetails.bank_name || paymentDetails.display_name || "Seller details pending"} />
              <DetailRow label="Account Number" value={paymentDetails.account_number || paymentDetails.masked_account_number || "--"} onCopy={() => onCopy(paymentDetails.account_number)} />
              <DetailRow label="Account Holder" value={paymentDetails.account_name || "--"} onCopy={() => onCopy(paymentDetails.account_name)} />
              {paymentDetails.payment_note ? <DetailRow label="Instructions" value={paymentDetails.payment_note} /> : null}
              <div className="rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] p-3 text-xs leading-5 text-[var(--exa-gold-light)]">Only send payment to the account shown inside this order. Never rely on payment instructions sent outside this order.</div>
            </div>
          ) : (
            <div className="mt-4 space-y-3">
              <DetailRow label="Amount Expected" value={`${formatNumber(trade.fiat_amount, 2)} ${trade.fiat_currency}`} />
              <DetailRow label="Buyer" value={trade.buyer?.name || "Buyer"} />
              <DetailRow label="Payment Method" value={trade.payment_method} />
              <div className="rounded-2xl border border-rose-300/25 bg-rose-500/10 p-3 text-xs leading-5 text-rose-100">Check your bank or payment account directly before releasing crypto. Do not release because of screenshots, SMS notifications or buyer claims.</div>
            </div>
          )}
        </div>

        <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
          <h3 className="text-sm font-semibold text-white">Payment Proof</h3>
          <p className="mt-1 text-xs text-[var(--exa-text-muted)]">Supporting evidence only. The seller must still verify receipt.</p>
          {paymentProof ? (
            <a href={paymentProof.download_url} target="_blank" rel="noreferrer" className="mt-3 block rounded-2xl border border-emerald-300/25 bg-emerald-400/10 p-3 text-sm text-emerald-100">
              {paymentProof.file_name || "Payment proof"}
              <span className="mt-1 block text-xs text-emerald-100/70">Uploaded {paymentProof.uploaded_at ? new Date(paymentProof.uploaded_at).toLocaleString() : "recently"}</span>
            </a>
          ) : <div className="mt-3 rounded-2xl border border-dashed border-[var(--exa-border)] p-4 text-sm text-[var(--exa-text-muted)]">No payment proof uploaded yet.</div>}
          {isBuyer && ["pending", "payment_sent", "disputed"].includes(trade.status) ? (
            <div className="mt-3 space-y-2">
              <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" onChange={(event) => onProofFile(event.target.files?.[0] || null)} className="block w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 py-2 text-xs text-[var(--exa-text-secondary)]" />
              <button type="button" onClick={onUploadProof} disabled={!proofFile || proofUploading} className="h-10 w-full rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-sm font-semibold text-[var(--exa-gold-light)] disabled:opacity-60">{proofUploading ? "Uploading..." : "Upload Payment Proof"}</button>
            </div>
          ) : null}
        </div>
      </section>

      <section className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
        <h3 className="text-sm font-semibold text-white">Order Timeline</h3>
        <div className="mt-3 grid gap-2 text-xs text-[var(--exa-text-secondary)] sm:grid-cols-4">
          <Metric label="Created" value={trade.created_at ? new Date(trade.created_at).toLocaleString() : "--"} />
          <Metric label="Payment Deadline" value={trade.payment_deadline ? new Date(trade.payment_deadline).toLocaleString() : "--"} />
          <Metric label="Marked Paid" value={trade.payment_sent_at ? new Date(trade.payment_sent_at).toLocaleString() : "--"} />
          <Metric label="Completed" value={trade.completed_at ? new Date(trade.completed_at).toLocaleString() : "--"} />
        </div>
      </section>

      <section className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
        <div className="flex items-center justify-between gap-3">
          <h3 className="text-sm font-semibold text-white">P2P Chat</h3>
          <span className="text-xs text-[var(--exa-text-muted)]">Keep all communication inside ExaEarn.</span>
        </div>
        <div className="mt-3 max-h-52 space-y-2 overflow-y-auto rounded-2xl border border-[var(--exa-border)] bg-black/20 p-3">
          {messages.length ? messages.map((message) => (
            <div key={message.id} className="rounded-xl bg-[var(--exa-surface-hover)] px-3 py-2 text-sm text-[var(--exa-text-secondary)]">
              <span className="block text-[11px] text-[var(--exa-text-disabled)]">{message.sender?.name || "System"}</span>
              {message.message || "Attachment"}
            </div>
          )) : <p className="text-sm text-[var(--exa-text-muted)]">No messages yet.</p>}
        </div>
        <div className="mt-3 flex gap-2">
          <input value={chatMessage} onChange={(event) => onChatChange(event.target.value)} placeholder="Type a message" className="min-w-0 flex-1 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 py-2 text-sm text-white outline-none focus:border-[var(--exa-border-active)]" />
          <button type="button" onClick={onSendMessage} disabled={busy || !chatMessage.trim()} className="rounded-xl bg-amber-300 px-4 text-sm font-bold text-black disabled:opacity-60">Send</button>
        </div>
      </section>

      <div className="sticky bottom-0 z-10 grid gap-2 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-2 backdrop-blur sm:grid-cols-4">
        {canMarkPaid ? <button type="button" onClick={onConfirmPaid} className="h-11 rounded-xl bg-amber-300 text-sm font-bold text-black">I Have Paid</button> : null}
        {canRelease ? <button type="button" onClick={onConfirmRelease} className="h-11 rounded-xl bg-emerald-400 text-sm font-bold text-black">Payment Received - Release Crypto</button> : null}
        {canCancel ? <button type="button" onClick={onCancel} disabled={busy} className="h-11 rounded-xl border border-[var(--exa-border)] text-sm font-semibold text-[var(--exa-text-secondary)] disabled:opacity-60">Cancel Order</button> : null}
        {canAppeal ? <button type="button" onClick={onAppeal} disabled={busy} className="h-11 rounded-xl border border-rose-300/35 text-sm font-semibold text-rose-100 disabled:opacity-60">Appeal</button> : null}
      </div>
    </div>
  );
}

function DetailRow({ label, value, onCopy }) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2">
      <span className="text-xs text-[var(--exa-text-muted)]">{label}</span>
      <span className="min-w-0 truncate text-right text-sm font-semibold text-white">{value}</span>
      {onCopy ? <button type="button" onClick={onCopy} className="rounded-lg border border-[var(--exa-border)] px-2 py-1 text-[11px] text-[var(--exa-text-secondary)]">Copy</button> : null}
    </div>
  );
}

function Metric({ label, value, tone }) {
  return <div className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3"><span className="block text-[11px] text-[var(--exa-text-muted)]">{label}</span><strong className={`mt-1 block text-sm capitalize ${tone === "gold" ? "text-[var(--exa-gold-light)]" : "text-white"}`}>{value}</strong></div>;
}
function BottomSheet({ title, children, onClose, footer }) {
  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/70 px-4 py-4 backdrop-blur-sm sm:items-center">
      <section className="flex max-h-[min(92dvh,840px)] w-full max-w-2xl flex-col overflow-hidden rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface)] shadow-[var(--exa-shadow-panel)]">
        <div className="flex items-center justify-between border-b border-[var(--exa-border)] px-5 py-4">
          <h2 className="font-['Sora'] text-xl font-semibold text-white">{title}</h2>
          <button type="button" onClick={onClose} className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-secondary)]"><X className="h-4 w-4" /></button>
        </div>
        <div className="min-h-0 flex-1 overflow-y-auto px-5 py-4">{children}</div>
        {footer ? <div className="sticky bottom-0 border-t border-[var(--exa-border)] bg-[var(--exa-surface)] px-5 py-4">{footer}</div> : null}
      </section>
    </div>
  );
}

function TextField({ label, value, onChange, placeholder, type = "text", inputMode, error }) {
  return (
    <label className="space-y-1.5">
      <span className="text-xs font-medium text-[var(--exa-text-muted)]">{label}</span>
      <input type={type} inputMode={inputMode} value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} className={`w-full rounded-xl border bg-[var(--exa-surface-elevated)] px-3 py-2.5 text-sm text-white outline-none transition ${error ? "border-rose-300/60" : "border-[var(--exa-border)] focus:border-[var(--exa-border-active)]"}`} />
      {error ? <span className="text-xs text-rose-300">{error}</span> : null}
    </label>
  );
}

function TextAreaField({ label, value, onChange, placeholder, error }) {
  return (
    <label className="mt-4 block space-y-1.5">
      <span className="text-xs font-medium text-[var(--exa-text-muted)]">{label}</span>
      <textarea value={value} onChange={(event) => onChange(event.target.value)} rows={3} placeholder={placeholder} className={`w-full rounded-xl border bg-[var(--exa-surface-elevated)] px-3 py-2.5 text-sm text-white outline-none transition ${error ? "border-rose-300/60" : "border-[var(--exa-border)] focus:border-[var(--exa-border-active)]"}`} />
      {error ? <span className="text-xs text-rose-300">{error}</span> : null}
    </label>
  );
}

function SelectField({ label, value, onChange, options, error }) {
  return (
    <label className="space-y-1.5">
      <span className="text-xs font-medium text-[var(--exa-text-muted)]">{label}</span>
      <div className="relative">
        <select value={value} onChange={(event) => onChange(event.target.value)} className={`w-full appearance-none rounded-xl border bg-[var(--exa-surface-elevated)] px-3 py-2.5 pr-10 text-sm text-white outline-none transition ${error ? "border-rose-300/60" : "border-[var(--exa-border)] focus:border-[var(--exa-border-active)]"}`}>
          {options.map((option) => <option key={option} value={option}>{option}</option>)}
        </select>
        <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--exa-text-secondary)]" />
      </div>
      {error ? <span className="text-xs text-rose-300">{error}</span> : null}
    </label>
  );
}

function AdCard({ ad, selectedAssetMeta = {}, actionLabel, onOpen }) {
  return (
    <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-soft)] transition duration-300 hover:-translate-y-1 hover:border-[var(--exa-border-active)] sm:p-5">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div className="space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <TokenLogo symbol={ad.asset} tone={selectedAssetMeta.tone} iconUrl={selectedAssetMeta.icon_url} />
            <span className="text-sm font-semibold text-white">{ad.merchant?.name ?? "Merchant"}</span>
            {ad.merchant?.email_verified ? <span className="inline-flex items-center gap-1 rounded-full border border-emerald-300/35 bg-emerald-400/10 px-2 py-0.5 text-[11px] font-semibold text-emerald-200"><BadgeCheck className="h-3.5 w-3.5" />Verified</span> : null}
            <span className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-2 py-0.5 text-[11px] uppercase text-[var(--exa-text-secondary)]">{ad.type}</span>
          </div>
          <div className="grid gap-2 text-sm text-[var(--exa-text-secondary)]/85 sm:grid-cols-3">
            <p><span className="block text-xs text-[var(--exa-text-muted)]">Price</span><span className="font-semibold text-[var(--exa-gold-light)]">{formatNumber(ad.price, 4)} {ad.fiat_currency}</span></p>
            <p><span className="block text-xs text-[var(--exa-text-muted)]">Available</span><span className="font-medium text-[var(--exa-text-primary)]">{formatNumber(ad.available_amount)} {ad.asset}</span></p>
            <p><span className="block text-xs text-[var(--exa-text-muted)]">Limits</span><span className="font-medium text-[var(--exa-text-primary)]">{formatNumber(ad.min_limit, 2)} - {formatNumber(ad.max_limit, 2)} {ad.fiat_currency}</span></p>
          </div>
          <div className="flex flex-wrap gap-2">{(ad.payment_methods ?? []).map((method) => <span key={method} className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-2.5 py-1 text-xs text-[var(--exa-text-secondary)]/85">{method}</span>)}</div>
        </div>
        {onOpen ? <button type="button" onClick={onOpen} className="h-11 rounded-xl exa-button-primary px-6 text-sm font-bold text-black transition hover:scale-[1.02] active:scale-[0.99]">{actionLabel}</button> : null}
      </div>
    </article>
  );
}

function TradeCard({ trade, userId, busy, onAction, onOpen }) {
  const isBuyer = Number(trade.buyer?.id) === Number(userId);
  const isSeller = Number(trade.seller?.id) === Number(userId);
  const canMarkPaid = isBuyer && trade.status === "pending";
  const canRelease = isSeller && trade.status === "payment_sent";
  const canCancel = trade.status === "pending";
  const canDispute = ["pending", "payment_sent"].includes(trade.status);

  return (
    <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-soft)] sm:p-5">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <strong className="text-white">{formatNumber(trade.crypto_amount)} {trade.asset}</strong>
            <span className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-2 py-0.5 text-xs uppercase text-[var(--exa-text-secondary)]">{trade.status}</span>
            <span className="text-xs text-[var(--exa-text-muted)]">{trade.trade_uuid}</span>
          </div>
          <p className="mt-2 text-sm text-[var(--exa-text-secondary)]">{formatNumber(trade.fiat_amount, 2)} {trade.fiat_currency} via {trade.payment_method}</p>
          <p className="mt-1 text-xs text-[var(--exa-text-muted)]">Buyer: {trade.buyer?.name ?? "--"} | Seller: {trade.seller?.name ?? "--"}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <ActionButton disabled={busy} muted onClick={onOpen}>Details</ActionButton>
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
  return <button type="button" onClick={onClick} disabled={disabled} className={`inline-flex h-10 items-center gap-2 rounded-xl px-4 text-sm font-semibold transition disabled:opacity-60 ${muted ? "border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)]" : "exa-button-primary text-black"}`}>{children === "Dispute" ? <MessageSquare className="h-4 w-4" /> : null}{children}</button>;
}

function EmptyPanel({ title, body }) {
  return <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-10 text-center"><CircleAlert className="mx-auto h-10 w-10 text-[var(--exa-text-muted)]" /><p className="mt-3 text-base font-semibold text-[var(--exa-text-primary)]">{title}</p><p className="mt-1 text-sm text-[var(--exa-text-muted)]">{body}</p></div>;
}

function LoadingPanel() {
  return <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-10 text-center"><Loader2 className="mx-auto h-10 w-10 animate-spin text-[var(--exa-gold-light)]" /><p className="mt-3 text-sm font-semibold text-[var(--exa-text-primary)]">Loading P2P market...</p></div>;
}

export default P2PMarketplace;


