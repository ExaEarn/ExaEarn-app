import React, { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  ArrowLeft,
  Building2,
  CheckCircle2,
  Copy,
  Landmark,
  LoaderCircle,
  QrCode,
  Search,
  ShieldCheck,
  Users,
  Wallet,
} from "lucide-react";
import { QRCodeSVG } from "qrcode.react";
import ethereumLogo from "../../assets/images/ethereum-eth-logo.png";
import xrpLogo from "../../assets/images/XRP.jpg";
import { useAuth } from "../../context/AuthContext";

const METHOD_ICONS = {
  "deposit-crypto": Wallet,
  "exa-pay": QrCode,
  "deposit-fiat": Landmark,
  p2p: Users,
};

const HISTORY_FILTERS = ["all", "pending", "completed", "failed"];

const STATUS_STYLES = {
  credited: "border-emerald-400/20 bg-emerald-400/10 text-emerald-200",
  confirming: "border-amber-300/20 bg-amber-400/10 text-amber-100",
  waiting: "border-sky-400/20 bg-sky-400/10 text-sky-200",
  failed: "border-rose-400/20 bg-rose-400/10 text-rose-200",
  review: "border-violet-400/20 bg-violet-400/10 text-violet-200",
};

function AddFundsPage({ onBack, onOpenP2P, initialMethod = null, initialView = null }) {
  const { request, authReady, user } = useAuth();
  const [meta, setMeta] = useState(null);
  const [history, setHistory] = useState([]);
  const [selectedMethod, setSelectedMethod] = useState("deposit-crypto");
  const [activeView, setActiveView] = useState("hub");
  const [selectedAsset, setSelectedAsset] = useState(null);
  const [selectedNetwork, setSelectedNetwork] = useState("");
  const [depositDetails, setDepositDetails] = useState(null);
  const [search, setSearch] = useState("");
  const [historyFilter, setHistoryFilter] = useState("all");
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [addressLoading, setAddressLoading] = useState(false);
  const [error, setError] = useState("");
  const [historyError, setHistoryError] = useState("");
  const [copied, setCopied] = useState("");
  const [selectedFiatMethodId, setSelectedFiatMethodId] = useState("");
  const [fiatCurrency, setFiatCurrency] = useState("");
  const [fiatAmount, setFiatAmount] = useState("");
  const [fiatReviewOpen, setFiatReviewOpen] = useState(false);
  const [fiatReviewData, setFiatReviewData] = useState(null);
  const [fiatReviewLoading, setFiatReviewLoading] = useState(false);
  const [fiatReviewError, setFiatReviewError] = useState("");
  const [markPaidLoading, setMarkPaidLoading] = useState(false);
  const unwrap = (payload) => payload?.data ?? payload ?? {};
  useEffect(() => {
    if (!initialMethod && !initialView) return;
    if (initialMethod) setSelectedMethod(initialMethod);
    if (initialView) setActiveView(initialView);
  }, [initialMethod, initialView]);

  const loadMeta = useCallback(async (mode = "full") => {
    mode === "full" ? setLoading(true) : setRefreshing(true);
    setError("");

    try {
      const nextMeta = unwrap(await request("/api/wallet/deposit/meta", { method: "GET" }));
      setMeta(nextMeta);

      const assets = Array.isArray(nextMeta.assets) ? nextMeta.assets : [];
      setSelectedAsset((current) => {
        if (current && assets.some((item) => item.symbol === current.symbol)) {
          return assets.find((item) => item.symbol === current.symbol) || current;
        }
        return assets[0] || null;
      });

      const methods = Array.isArray(nextMeta.funding_methods) ? nextMeta.funding_methods : [];
      setSelectedMethod((current) => (methods.some((item) => item.id === current) ? current : methods[0]?.id || "deposit-crypto"));

      const fiatMethods = Array.isArray(nextMeta.fiat_methods) ? nextMeta.fiat_methods : [];
      setSelectedFiatMethodId((current) => {
        if (current && fiatMethods.some((item) => (item.id || item.name) === current)) {
          return current;
        }
        return fiatMethods[0]?.id || fiatMethods[0]?.name || "";
      });
    } catch (loadError) {
      setError(loadError?.message || "We could not load funding methods right now.");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [request]);

  const loadHistory = useCallback(async (filter = historyFilter) => {
    setHistoryLoading(true);
    setHistoryError("");

    try {
      const query = filter !== "all" ? `?status=${encodeURIComponent(filter)}` : "";
      const nextHistory = unwrap(await request(`/api/wallet/deposit/history${query}`, { method: "GET" }));
      setHistory(Array.isArray(nextHistory.items) ? nextHistory.items : []);
    } catch (loadError) {
      setHistoryError(loadError?.message || "We could not load your deposit activity.");
    } finally {
      setHistoryLoading(false);
    }
  }, [historyFilter, request]);

  useEffect(() => {
    if (authReady) {
      void loadMeta();
    }
  }, [authReady, loadMeta]);

  useEffect(() => {
    if (authReady) {
      void loadHistory(historyFilter);
    }
  }, [authReady, historyFilter, loadHistory]);

  useEffect(() => {
    const networks = Array.isArray(selectedAsset?.networks) ? selectedAsset.networks : [];
    setSelectedNetwork((current) => (networks.some((item) => item.id === current) ? current : networks[0]?.id || ""));
    setDepositDetails(null);
  }, [selectedAsset]);

  useEffect(() => {
    setDepositDetails(null);
  }, [selectedNetwork]);

  const assets = Array.isArray(meta?.assets) ? meta.assets : [];
  const methods = Array.isArray(meta?.funding_methods) ? meta.funding_methods : [];
  const currentMethod = methods.find((item) => item.id === selectedMethod) || methods[0] || null;
  const fiatMethods = Array.isArray(meta?.fiat_methods) ? meta.fiat_methods : [];

  const filteredAssets = useMemo(() => {
    const needle = search.trim().toLowerCase();
    if (!needle) return assets;
    return assets.filter((item) => String(item.symbol || "").toLowerCase().includes(needle) || String(item.name || "").toLowerCase().includes(needle));
  }, [assets, search]);

  const selectedNetworkDetails = useMemo(
    () => (selectedAsset?.networks || []).find((item) => item.id === selectedNetwork) || selectedAsset?.networks?.[0] || null,
    [selectedAsset, selectedNetwork]
  );

  const selectedFiatMethod = useMemo(
    () => fiatMethods.find((item) => (item.id || item.name) === selectedFiatMethodId) || fiatMethods[0] || null,
    [fiatMethods, selectedFiatMethodId]
  );

  const fiatCurrencies = useMemo(() => {
    const values = selectedFiatMethod?.currencies;
    return Array.isArray(values) ? values : [];
  }, [selectedFiatMethod]);

  useEffect(() => {
    if (!fiatCurrencies.length) {
      setFiatCurrency("");
      return;
    }

    setFiatCurrency((current) => (fiatCurrencies.includes(current) ? current : fiatCurrencies[0] || ""));
  }, [fiatCurrencies]);

  const canReviewFiat = Boolean(selectedFiatMethod && fiatCurrency && Number(fiatAmount) > 0);
  const qrValue = depositDetails?.address || meta?.receive?.share_link || meta?.receive?.deep_link || "exaearn";
  const receiveUid = meta?.receive?.exaearn_id || user?.unique_user_id || user?.uid || user?.id || "--";
  const receiveUsername = meta?.receive?.username || user?.username || user?.name || "--";
  const receiveEmail = user?.email || meta?.receive?.email || "--";
  const receiveLink = meta?.receive?.share_link || meta?.receive?.deep_link || "";

  const copyValue = async (key, value) => {
    if (!value) return;

    try {
      await navigator.clipboard.writeText(value);
      setCopied(key);
      window.setTimeout(() => setCopied(""), 1600);
    } catch {
      setCopied("");
    }
  };

  const reviewFiatInstructions = async () => {
    if (!selectedFiatMethod || !fiatCurrency || !Number(fiatAmount)) return;

    setActiveView("deposit-fiat-review");
    setFiatReviewData(null);
    setFiatReviewLoading(true);
    setFiatReviewError("");

    try {
      const nextReview = unwrap(
        await request("/api/wallet/deposit/fiat-instructions", {
          method: "POST",
          body: JSON.stringify({
            method_id: selectedFiatMethod.id || selectedFiatMethod.name,
            currency: fiatCurrency,
            amount: fiatAmount,
          }),
        })
      );
      setFiatReviewData(nextReview);
      setFiatReviewOpen(false);
      void loadHistory(historyFilter);
    } catch (loadError) {
      setFiatReviewError(loadError?.message || "We could not prepare fiat funding instructions right now.");
    } finally {
      setFiatReviewLoading(false);
    }
  };

  const markFiatPaymentSent = async () => {
    if (!fiatReviewData?.reference) return;

    setMarkPaidLoading(true);
    setFiatReviewError("");

    try {
      await request(`/api/wallet/deposit/fiat-intents/${encodeURIComponent(fiatReviewData.reference)}/mark-paid`, {
        method: "POST",
      });
      await loadHistory(historyFilter);
      setFiatReviewOpen(false);
    } catch (submitError) {
      setFiatReviewError(submitError?.message || "We could not mark this fiat payment for review yet.");
    } finally {
      setMarkPaidLoading(false);
    }
  };
  const generateAddress = async () => {
    if (!selectedAsset?.symbol || !selectedNetwork) return;

    setAddressLoading(true);
    setError("");

    try {
      const nextDetails = unwrap(
        await request("/api/wallet/deposit/address", {
          method: "POST",
          body: JSON.stringify({ currency: selectedAsset.symbol, network: selectedNetwork }),
        })
      );
      setDepositDetails(nextDetails);
      void loadHistory(historyFilter);
    } catch (loadError) {
      setError(loadError?.message || "We could not prepare your deposit address safely.");
    } finally {
      setAddressLoading(false);
    }
  };

  const openMethod = (methodId) => {
    if (methodId === "p2p") {
      onOpenP2P?.();
      return;
    }

    setSelectedMethod(methodId);

    if (methodId === "deposit-crypto") {
      setActiveView("deposit-crypto-list");
      return;
    }

    if (methodId === "deposit-fiat") {
      setActiveView("deposit-fiat-list");
      return;
    }

    setActiveView(methodId);
  };


  if (!authReady || loading) {
    return <LoadingShell onBack={onBack} />;
  }

  return (
    <main className="min-h-[100dvh] bg-[var(--exa-bg-primary)] px-3 pb-8 pt-3 text-slate-100 sm:px-4 lg:px-6">
      <div className="mx-auto flex w-full max-w-[1380px] flex-col gap-4">
        <header className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] px-4 py-4 shadow-[0_22px_55px_rgba(0,0,0,.35)]">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={activeView === "hub" ? onBack : () => setActiveView("hub")}
                  className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-slate-100 transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
                  aria-label={activeView === "hub" ? "Go back" : "Back to deposit methods"}
                >
                  <ArrowLeft className="h-4 w-4" />
                </button>
                <div>
                  <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Funds</p>
                  <h1 className="text-2xl font-semibold tracking-tight text-white sm:text-3xl">Deposit</h1>
                </div>
              </div>
              <p className="mt-3 max-w-2xl text-sm text-slate-400">
                {activeView === "hub" ? "Choose a deposit method first, then continue into a dedicated funding screen." : currentMethod?.description || "Use the dedicated funding flow below to complete your deposit."}
              </p>
            </div>
          </div>
        </header>

        {error ? <Banner tone="rose">{error}</Banner> : null}

        <section className="space-y-4">
          {activeView === "hub" ? (
            <div className="grid gap-4 xl:grid-cols-[1.08fr_0.92fr]">
              <section className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 sm:p-5">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Choose method</p>
                    <h2 className="mt-1 text-xl font-semibold text-white">How do you want to deposit?</h2>
                    <p className="mt-2 text-sm text-slate-400">Select a method to open its dedicated deposit screen.</p>
                  </div>
                  <span className="rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-2.5 py-1 text-[11px] font-semibold text-[var(--exa-gold-light)]">
                    Exchange flow
                  </span>
                </div>

                <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                  {methods.map((method) => {
                    const Icon = METHOD_ICONS[method.id] || Wallet;
                    return (
                      <button
                        key={method.id}
                        type="button"
                        onClick={() => openMethod(method.id)}
                        className="flex items-start justify-between gap-4 rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-4 text-left transition hover:border-[var(--exa-border-active)] hover:bg-white/[0.05]"
                      >
                        <div className="flex min-w-0 items-start gap-3">
                          <span className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/[0.05] text-[var(--exa-gold-light)]">
                            <Icon className="h-5 w-5" />
                          </span>
                          <div className="min-w-0">
                            <p className="text-sm font-semibold text-white">{method.title}</p>
                            <p className="mt-1 text-xs leading-5 text-slate-400">{method.description}</p>
                          </div>
                        </div>
                        <span className="text-xs font-semibold text-[var(--exa-gold-light)]">Open</span>
                      </button>
                    );
                  })}
                </div>
              </section>

              <div className="space-y-4">
                <section className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Deposit activity</p>
                      <h2 className="mt-1 text-lg font-semibold text-white">Recent deposits</h2>
                    </div>
                  </div>

                  <div className="mt-4 flex flex-wrap gap-2">
                    {HISTORY_FILTERS.map((item) => (
                      <button
                        key={item}
                        type="button"
                        onClick={() => setHistoryFilter(item)}
                        className={`rounded-full border px-3 py-1.5 text-xs font-semibold transition ${
                          historyFilter === item ? "border-[#d1ab55]/60 bg-[#d1ab55]/10 text-[var(--exa-gold-light)]" : "border-white/10 bg-white/[0.03] text-slate-300 hover:border-[var(--exa-border-active)]"
                        }`}
                      >
                        {item === "completed" ? "Credited" : item.charAt(0).toUpperCase() + item.slice(1)}
                      </button>
                    ))}
                  </div>

                  <div className="mt-4 space-y-3">
                    {historyLoading ? (
                      [0, 1, 2].map((item) => <div key={item} className="h-20 animate-pulse rounded-2xl bg-white/[0.04]" />)
                    ) : historyError ? (
                      <Banner tone="rose">{historyError}</Banner>
                    ) : history.length === 0 ? (
                      <EmptyState message="Your deposit history will appear here after ExaEarn detects or credits a deposit." />
                    ) : (
                      history.map((item) => (
                        <div key={item.id || item.reference || item.tx_hash} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                          <div className="flex items-start justify-between gap-3">
                            <div>
                              <div className="flex items-center gap-2">
                                <AssetMark symbol={item.currency} small />
                                <p className="text-sm font-semibold text-white">{formatAmount(item.amount, item.currency)}</p>
                              </div>
                              <p className="mt-2 text-xs text-slate-400">{item.network || "Deposit"} - {formatDate(item.created_at)}</p>
                            </div>
                            <span className={`rounded-full border px-2.5 py-1 text-[11px] font-semibold ${STATUS_STYLES[item.status_key || "review"] || STATUS_STYLES.review}`}>
                              {item.status_label || item.status || "Needs Review"}
                            </span>
                          </div>
                          <div className="mt-3 space-y-2 text-xs text-slate-400">
                            {item.tx_hash ? <Line label="Tx Hash" value={truncateMiddle(item.tx_hash, 9, 8)} /> : null}
                            <Line label="Confirmations" value={String(item.confirmations ?? "--")} />
                            <Line label="Reference" value={item.reference || item.transaction_id || "--"} />
                            {item.source === "fiat_intent" ? <Line label="Net after fee" value={item.net_amount ? `${Number(item.net_amount).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${item.currency}` : "--"} /> : null}
                            {item.source === "fiat_intent" ? <Line label="Expires" value={item.expires_at ? formatDate(item.expires_at) : "--"} /> : null}
                            {item.source === "fiat_intent" ? <Line label="Time remaining" value={item.expires_at ? formatCountdown(item.expires_at) : "--"} /> : null}
                          </div>
                        </div>
                      ))
                    )}
                  </div>
                </section>

                <section className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                  <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Safety</p>
                  <h2 className="mt-1 text-lg font-semibold text-white">Deposit checks</h2>
                  <div className="mt-4 space-y-3 text-sm text-slate-300">
                    <Safety icon={ShieldCheck} title="Match the network" body="Always send funds through the exact network selected above. Wrong-network deposits may be delayed or unrecoverable." />
                    <Safety icon={CheckCircle2} title="Wait for confirmations" body="Deposits remain pending until the required network confirmations are reached and ExaEarn credits your funding wallet." />
                    <Safety icon={AlertTriangle} title="Memo or destination tag" body="Some networks such as XRP require a destination tag or memo in addition to the address. Missing it can trigger manual review." />
                  </div>
                </section>
              </div>
            </div>
          ) : null}

          {activeView === "deposit-crypto-list" ? (
            <section className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 sm:p-5">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  <button
                    type="button"
                    onClick={() => setActiveView("hub")}
                    className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-slate-100 transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
                    aria-label="Back to deposit methods"
                  >
                    <ArrowLeft className="h-4 w-4" />
                  </button>
                  <div>
                    <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Deposit crypto</p>
                    <h2 className="mt-1 text-xl font-semibold text-white">Choose a crypto to open its deposit page</h2>
                  </div>
                </div>
                <span className="rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-2.5 py-1 text-[11px] font-semibold text-[var(--exa-gold-light)]">
                  {assets.length} assets
                </span>
              </div>

              <div className="mt-5 grid gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
                <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                  <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 py-2">
                    <div className="flex items-center gap-2">
                      <Search className="h-4 w-4 text-slate-500" />
                      <input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search crypto"
                        className="h-9 w-full bg-transparent text-sm text-white outline-none placeholder:text-slate-500"
                      />
                    </div>
                  </div>

                  <div className="mt-4 max-h-[62vh] space-y-2 overflow-y-auto pr-1 xl:max-h-[680px]">
                    {filteredAssets.length === 0 ? (
                      <EmptyState message="No supported deposit assets match your search right now." />
                    ) : (
                      filteredAssets.map((item) => (
                        <button
                          key={item.symbol}
                          type="button"
                          onClick={() => {
                            setSelectedAsset(item);
                            setActiveView("deposit-crypto-detail");
                          }}
                          className="flex w-full items-center justify-between rounded-2xl border border-[var(--exa-border-subtle)] bg-[var(--exa-surface)] px-4 py-3 text-left transition hover:border-[var(--exa-border-active)] hover:bg-white/[0.04]"
                        >
                          <div className="flex min-w-0 items-center gap-3">
                            <AssetMark symbol={item.symbol} />
                            <div className="min-w-0">
                              <p className="text-sm font-semibold text-white">{item.symbol}</p>
                              <p className="truncate text-xs text-slate-400">{item.name}</p>
                            </div>
                          </div>
                          <div className="text-right">
                            <p className="text-sm font-semibold text-white">{formatAmount(item.availableBalance || item.balance || "0", item.symbol)}</p>
                            <p className="text-[11px] text-slate-500">Tap to view</p>
                          </div>
                        </button>
                      ))
                    )}
                  </div>
                </div>

                <div className="space-y-4">
                  <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-5">
                    <p className="text-xs uppercase tracking-[0.14em] text-slate-500">How this works</p>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                      <Metric label="Step 1" value="Pick the asset you want to receive" compact />
                      <Metric label="Step 2" value="Open its deposit page and choose the right network" compact />
                      <Metric label="Step 3" value="Generate the address and copy any memo or tag" compact />
                      <Metric label="Step 4" value="Send only the matching asset through that network" compact />
                    </div>
                  </div>

                  <div className="grid gap-3 md:grid-cols-2">
                    <Safety icon={ShieldCheck} title="Always match the network" body="ERC20, BEP20, TRC20, and native networks are not interchangeable. Using the wrong rail can delay or lose funds." />
                    <Safety icon={AlertTriangle} title="Memo or tag matters" body="Assets like XRP can require a destination tag or memo. If one is shown on the asset page, include it exactly." />
                    <Safety icon={CheckCircle2} title="Wait for confirmations" body="Funds remain pending until the required network confirmations are completed and ExaEarn credits your balance." />
                    <Safety icon={Wallet} title="Dedicated deposit pages" body="Each supported coin now has its own deposit details page so users can review the exact address, network, and warnings before sending." />
                  </div>
                </div>
              </div>
            </section>
          ) : null}

          {activeView === "deposit-crypto-detail" ? (
            <section className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 sm:p-5">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  <button
                    type="button"
                    onClick={() => setActiveView("deposit-crypto-list")}
                    className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-slate-100 transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
                    aria-label="Back to crypto list"
                  >
                    <ArrowLeft className="h-4 w-4" />
                  </button>
                  <div>
                    <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Deposit crypto</p>
                    <h2 className="mt-1 text-xl font-semibold text-white">{selectedAsset?.symbol || "Selected asset"} deposit details</h2>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setActiveView("deposit-crypto-list")}
                  className="inline-flex items-center gap-2 rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2 text-sm font-semibold text-white transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
                >
                  Change crypto
                </button>
              </div>


              <div className="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1fr)_420px]">
                <div className="space-y-4">
                  <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                    <div className="flex items-start justify-between gap-3">
                      <div className="flex min-w-0 items-center gap-3">
                        <AssetMark symbol={selectedAsset?.symbol} />
                        <div className="min-w-0">
                          <p className="text-lg font-semibold text-white">{selectedAsset?.symbol || "Select asset"}</p>
                          <p className="text-sm text-slate-400">{selectedAsset?.name || "Choose a supported deposit asset"}</p>
                        </div>
                      </div>
                      <div className="rounded-full border border-white/10 bg-black/20 px-3 py-1.5 text-xs font-semibold text-slate-200">
                        {formatAmount(selectedAsset?.availableBalance || selectedAsset?.balance || "0", selectedAsset?.symbol)}
                      </div>
                    </div>

                    <div className="mt-4">
                      <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Available networks</p>
                      <div className="mt-3 flex flex-wrap gap-2">
                        {(selectedAsset?.networks || []).map((item) => (
                          <button
                            key={item.id}
                            type="button"
                            onClick={() => setSelectedNetwork(item.id)}
                            className={`rounded-full border px-3 py-2 text-sm font-semibold transition ${
                              item.id === selectedNetwork ? "border-[#d1ab55]/60 bg-[#d1ab55]/10 text-[var(--exa-gold-light)]" : "border-white/10 bg-white/[0.03] text-slate-300 hover:border-[var(--exa-border-active)]"
                            }`}
                          >
                            {item.name}
                          </button>
                        ))}
                      </div>
                    </div>

                    {selectedNetworkDetails ? (
                      <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Metric label="Minimum deposit" value={formatAmount(selectedNetworkDetails.minimumDeposit, selectedAsset?.symbol)} compact />
                        <Metric label="Confirmations" value={String(selectedNetworkDetails.depositConfirmations || "--")} compact />
                        <Metric label="Estimated arrival" value={selectedNetworkDetails.estimatedArrival || "--"} compact />
                        <Metric label="Unlocks after" value={String(selectedNetworkDetails.withdrawalUnlockConfirmations || "--")} compact />
                      </div>
                    ) : (
                      <div className="mt-4">
                        <EmptyState message="This asset does not have an active deposit network yet." />
                      </div>
                    )}

                    <button
                      type="button"
                      disabled={!selectedAsset?.symbol || !selectedNetwork || addressLoading}
                      onClick={() => void generateAddress()}
                      className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-2xl exa-button-primary px-4 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-55"
                    >
                      {addressLoading ? <LoaderCircle className="h-4 w-4 animate-spin" /> : null}
                      {depositDetails?.address ? "Refresh deposit address" : "Generate deposit address"}
                    </button>
                  </div>

                  <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                    <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Deposit checklist</p>
                    <div className="mt-3 space-y-2 text-sm text-slate-300">
                      <Line label="Asset" value={selectedAsset?.symbol || "--"} />
                      <Line label="Network" value={selectedNetworkDetails?.name || "--"} />
                      <Line label="Memo needed" value={depositDetails?.memo ? "Yes" : "Only if shown"} />
                      <Line label="Status" value={depositDetails?.status || selectedNetworkDetails?.status || "Active"} />
                    </div>
                  </div>
                </div>

                <div className="rounded-[22px] border border-[var(--exa-border-active)] bg-[linear-gradient(135deg,var(--exa-gold-surface),var(--exa-surface-elevated)_48%,var(--exa-bg-tertiary))] p-4">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Deposit details</p>
                      <p className="mt-1 text-base font-semibold text-white">{selectedAsset?.symbol || "Asset"} on {selectedNetworkDetails?.name || "Network"}</p>
                    </div>
                    <span className="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-200">
                      {depositDetails?.status === "waiting" ? "Ready" : selectedNetworkDetails?.status || "Active"}
                    </span>
                  </div>

                  {depositDetails?.address ? (
                    <>
                      <div className="mt-4 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4">
                        <div className="mx-auto flex h-56 w-56 items-center justify-center rounded-2xl bg-white p-3">
                          <QRCodeSVG value={depositDetails.address} size={192} bgColor="#ffffff" fgColor="#111111" includeMargin />
                        </div>
                      </div>

                      <div className="mt-4 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4">
                        <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Deposit address</p>
                        <p className="mt-2 break-all font-mono text-sm text-[var(--exa-gold-light)]">{depositDetails.address}</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                          <MiniButton onClick={() => void copyValue("address", depositDetails.address)}>
                            {copied === "address" ? "Copied" : "Copy address"}
                          </MiniButton>
                          {depositDetails.memo ? (
                            <MiniButton onClick={() => void copyValue("memo", depositDetails.memo)}>
                              {copied === "memo" ? "Copied tag" : `Copy ${depositDetails.memo_label || "memo"}`}
                            </MiniButton>
                          ) : null}
                        </div>
                      </div>

                      {depositDetails.memo ? (
                        <div className="mt-3 rounded-2xl border border-amber-300/20 bg-amber-400/10 p-4 text-sm text-amber-100">
                          <p className="font-semibold">{depositDetails.memo_label || "Memo / tag"} required</p>
                          <p className="mt-1 break-all font-mono text-[var(--exa-gold-light)]">{depositDetails.memo}</p>
                          <p className="mt-2 text-xs text-amber-100/80">Include this exactly or your deposit may need manual review before crediting.</p>
                        </div>
                      ) : null}

                      <div className="mt-4 space-y-2 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4 text-xs text-slate-300">
                        <Line label="Minimum deposit" value={formatAmount(depositDetails.network?.minimumDeposit, selectedAsset?.symbol)} />
                        <Line label="Deposit confirmations" value={String(depositDetails.network?.depositConfirmations || "--")} />
                        <Line label="Estimated arrival" value={depositDetails.network?.estimatedArrival || "--"} />
                      </div>

                      <div className="mt-4 rounded-2xl border border-amber-300/20 bg-amber-400/10 p-4 text-xs text-amber-100">
                        <p className="font-semibold">Network safety</p>
                        <p className="mt-1 leading-5">{depositDetails.network_warning || `Only send ${selectedAsset?.symbol || "this asset"} through the selected ${selectedNetworkDetails?.name || "network"} rail.`}</p>
                      </div>
                    </>
                  ) : (
                    <EmptyState message="Generate the deposit address to load the QR code, memo requirements, and network-specific instructions for this asset." icon={Wallet} tall />
                  )}
                </div>
              </div>
            </section>
          ) : null}

          {activeView === "exa-pay" ? (
            <section className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 sm:p-5">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  <button
                    type="button"
                    onClick={() => setActiveView("hub")}
                    className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-slate-100 transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
                    aria-label="Back to deposit methods"
                  >
                    <ArrowLeft className="h-4 w-4" />
                  </button>
                  <div>
                    <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Receive via ExaEarn Pay</p>
                    <h2 className="mt-1 text-xl font-semibold text-white">Show your receive QR for internal transfers</h2>
                  </div>
                </div>
                <span className="rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-2.5 py-1 text-[11px] font-semibold text-[var(--exa-gold-light)]">
                  Internal transfer
                </span>
              </div>


              <div className="mt-5 grid gap-4 xl:grid-cols-[0.88fr_1.12fr]">
                <div className="rounded-[22px] border border-[var(--exa-border-active)] bg-[linear-gradient(135deg,var(--exa-gold-surface),var(--exa-surface-elevated)_48%,var(--exa-bg-tertiary))] p-4">
                  <div className="mx-auto flex h-64 w-64 items-center justify-center rounded-2xl bg-white p-3">
                    <QRCodeSVG value={qrValue || "exaearn"} size={220} bgColor="#ffffff" fgColor="#111111" includeMargin />
                  </div>
                  <p className="mt-4 text-center text-sm text-slate-300">Let another ExaEarn user scan this code, or share your UID, email, or username below for direct internal transfer.</p>
                </div>

                <div className="space-y-4">
                  <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                    <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Receive details</p>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                      <IdentityCard label="ExaEarn UID" value={String(receiveUid)} copied={copied === "receive_uid"} onCopy={() => void copyValue("receive_uid", String(receiveUid))} />
                      <IdentityCard label="Username" value={receiveUsername} copied={copied === "receive_username"} onCopy={() => void copyValue("receive_username", receiveUsername)} />
                      <IdentityCard label="Email" value={receiveEmail} copied={copied === "receive_email"} onCopy={() => void copyValue("receive_email", receiveEmail)} />
                      <IdentityCard label="Share link" value={receiveLink || "Not available"} copied={copied === "receive_link"} onCopy={() => void copyValue("receive_link", receiveLink)} />
                    </div>
                  </div>

                  <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                    <p className="text-xs uppercase tracking-[0.14em] text-slate-500">How it works</p>
                    <p className="mt-2 text-sm leading-6 text-slate-300">
                      Let the sender open ExaEarn Pay, scan this QR, or search for you by UID, username, or email. Once they confirm the internal transfer, the funds route directly into your ExaEarn account.
                    </p>
                  </div>

                  <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                    <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Quick checks</p>
                    <div className="mt-3 space-y-2 text-sm text-slate-300">
                      <Line label="Method" value={currentMethod?.title || "ExaEarn Pay"} />
                      <Line label="Transfer speed" value="Instant when approved" />
                      <Line label="Destination" value="Your ExaEarn account" />
                      <Line label="Account lookup" value="UID, username, email, or QR" />
                    </div>
                  </div>
                </div>
              </div>
            </section>
          ) : null}

          {activeView === "deposit-fiat-list" ? (
            <section className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 sm:p-5">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  <button
                    type="button"
                    onClick={() => setActiveView("hub")}
                    className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-slate-100 transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
                    aria-label="Back to deposit methods"
                  >
                    <ArrowLeft className="h-4 w-4" />
                  </button>
                  <div>
                    <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Deposit fiat</p>
                    <h2 className="mt-1 text-xl font-semibold text-white">Choose a funding method to open its deposit page</h2>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={onOpenP2P}
                  className="inline-flex items-center gap-2 rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2 text-sm font-semibold text-white transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
                >
                  <Users className="h-4 w-4" />
                  P2P option
                </button>
              </div>

              <div className="mt-5 grid gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
                <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                  <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Funding methods</p>
                  <div className="mt-3 space-y-3">
                    {fiatMethods.map((item) => {
                      const key = item.id || item.name;
                      return (
                        <button
                          key={key}
                          type="button"
                          onClick={() => {
                            setSelectedFiatMethodId(key);
                            setActiveView("deposit-fiat-detail");
                          }}
                          className="w-full rounded-2xl border border-[var(--exa-border-subtle)] bg-[var(--exa-surface)] px-4 py-4 text-left transition hover:border-[var(--exa-border-active)] hover:bg-white/[0.03]"
                        >
                          <div className="flex items-start gap-3">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-white/[0.05] text-[var(--exa-gold-light)]">
                              <Building2 className="h-4 w-4" />
                            </span>
                            <div className="min-w-0">
                              <p className="text-sm font-semibold text-white">{item.name || item.label || "Fiat method"}</p>
                              <p className="mt-1 text-xs leading-5 text-slate-400">{item.description || "Follow ExaEarn funding instructions for this fiat rail."}</p>
                              <p className="mt-2 text-[11px] font-medium text-slate-500">Tap to continue</p>
                            </div>
                          </div>
                        </button>
                      );
                    })}
                  </div>
                </div>

                <div className="space-y-4">
                  <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-5">
                    <p className="text-xs uppercase tracking-[0.14em] text-slate-500">How fiat funding works</p>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                      <Metric label="Step 1" value="Choose the rail you want to fund through" compact />
                      <Metric label="Step 2" value="Open that method page and pick the settlement currency" compact />
                      <Metric label="Step 3" value="Enter the amount and review ExaEarn instructions" compact />
                      <Metric label="Step 4" value="Send the payment and mark it for review" compact />
                    </div>
                  </div>

                  <div className="grid gap-3 md:grid-cols-2">
                    <Safety icon={Building2} title="Method-specific guidance" body="Bank transfer, card payment, and payment gateway rails can each have different limits, payout references, and processing windows." />
                    <Safety icon={ShieldCheck} title="Use the exact reference" body="When ExaEarn gives you a reference or beneficiary code, copy it exactly so the payment can reconcile safely." />
                    <Safety icon={CheckCircle2} title="Review before sending" body="Your method page will show the settlement currency, amount, timing, and instructions before you move money." />
                    <Safety icon={AlertTriangle} title="Only use supported rails" body="Send fiat only through the ExaEarn method you selected. Unsupported payment paths can delay crediting or require manual review." />
                  </div>
                </div>
              </div>
            </section>
          ) : null}

          {activeView === "deposit-fiat-detail" ? (
            <section className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 sm:p-5">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  <button
                    type="button"
                    onClick={() => setActiveView("deposit-fiat-list")}
                    className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-slate-100 transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
                    aria-label="Back to fiat methods"
                  >
                    <ArrowLeft className="h-4 w-4" />
                  </button>
                  <div>
                    <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Deposit fiat</p>
                    <h2 className="mt-1 text-xl font-semibold text-white">{selectedFiatMethod?.name || selectedFiatMethod?.label || "Selected method"} funding details</h2>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setActiveView("deposit-fiat-list")}
                  className="inline-flex items-center gap-2 rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2 text-sm font-semibold text-white transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
                >
                  Change method
                </button>
              </div>

              <div className="mt-5 grid gap-4 xl:grid-cols-[1.05fr_0.95fr]">
                <div className="space-y-4">
                  <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                    <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Settlement currency</p>
                    <div className="mt-3 flex flex-wrap gap-2">
                      {fiatCurrencies.map((currency) => (
                        <button
                          key={currency}
                          type="button"
                          onClick={() => setFiatCurrency(currency)}
                          className={`rounded-full border px-3 py-2 text-sm font-semibold transition ${
                            fiatCurrency === currency ? "border-[#d1ab55]/60 bg-[#d1ab55]/10 text-[var(--exa-gold-light)]" : "border-white/10 bg-white/[0.03] text-slate-300 hover:border-[var(--exa-border-active)]"
                          }`}
                        >
                          {currency}
                        </button>
                      ))}
                    </div>

                    <label className="mt-4 block">
                      <span className="mb-2 block text-xs uppercase tracking-[0.14em] text-slate-500">Deposit amount</span>
                      <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-4 py-3">
                        <div className="flex items-center justify-between gap-3">
                          <input
                            type="number"
                            inputMode="decimal"
                            min="0"
                            value={fiatAmount}
                            onChange={(event) => setFiatAmount(event.target.value)}
                            placeholder="0.00"
                            className="w-full bg-transparent text-lg font-semibold text-white outline-none placeholder:text-slate-500"
                          />
                          <span className="text-sm font-semibold text-slate-300">{fiatCurrency || "FIAT"}</span>
                        </div>
                      </div>
                    </label>

                    <button
                      type="button"
                      disabled={!canReviewFiat || fiatReviewLoading}
                      onClick={() => void reviewFiatInstructions()}
                      className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-2xl exa-button-primary px-4 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-55"
                    >
                      {fiatReviewLoading ? <LoaderCircle className="h-4 w-4 animate-spin" /> : null}
                      {fiatReviewLoading ? "Preparing instructions" : "Review funding instructions"}
                    </button>
                  </div>

                  <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 text-sm text-slate-300">
                    <p className="font-semibold text-white">What happens next</p>
                    <p className="mt-2 leading-6 text-slate-400">
                      Review the selected funding route, confirm the settlement currency, then follow the fiat payment instructions supplied by ExaEarn before expecting credit into your funding wallet.
                    </p>
                  </div>
                </div>

                <div className="space-y-4">
                  <div className="rounded-[22px] border border-[var(--exa-border-active)] bg-[linear-gradient(135deg,var(--exa-gold-surface),var(--exa-surface-elevated)_48%,var(--exa-bg-tertiary))] p-4">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Fiat funding overview</p>
                        <p className="mt-1 text-base font-semibold text-white">{selectedFiatMethod?.name || selectedFiatMethod?.label || "Select fiat method"}</p>
                      </div>
                      <span className="rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-2.5 py-1 text-[11px] font-semibold text-[var(--exa-gold-light)]">
                        {selectedFiatMethod?.status || "Configured"}
                      </span>
                    </div>

                    <div className="mt-4 space-y-2 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4 text-xs text-slate-300">
                      <Line label="Currency" value={fiatCurrency || "Select currency"} />
                      <Line label="Amount" value={fiatAmount ? `${Number(fiatAmount).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${fiatCurrency}` : "Enter amount"} />
                      <Line label="Method status" value={selectedFiatMethod?.status || "Configured"} />
                      <Line label="Processing" value={selectedFiatMethod?.processing_time || "--"} />
                    </div>
                  </div>

                  <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 text-sm text-slate-300">
                    <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Method details</p>
                    <div className="mt-3 space-y-2 text-sm text-slate-300">
                      <Line label="Method" value={selectedFiatMethod?.name || selectedFiatMethod?.label || "--"} />
                      <Line label="Supported currencies" value={fiatCurrencies.length ? fiatCurrencies.join(", ") : "--"} />
                      <Line label="Processing time" value={selectedFiatMethod?.processing_time || "--"} />
                      <Line label="Status" value={selectedFiatMethod?.status || "Configured"} />
                    </div>
                    <p className="mt-3 text-xs leading-5 text-slate-400">
                      {selectedFiatMethod?.description || "Follow ExaEarn funding instructions for this fiat rail before sending funds."}
                    </p>
                  </div>
                </div>
              </div>
            </section>
          ) : null}
        </section>

        {activeView === "deposit-fiat-review" ? (
          <section className="rounded-[28px] border border-[var(--exa-border)] bg-[var(--exa-surface)] px-4 py-5 shadow-[0_30px_90px_rgba(0,0,0,.28)] sm:px-5 lg:px-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Review</p>
                <h2 className="mt-1 text-xl font-semibold text-white">Fiat funding instructions</h2>
                <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">
                  Confirm this funding route, then continue with the secure payment step or send funds using the exact details below.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setActiveView("deposit-fiat-detail")}
                className="inline-flex items-center gap-2 rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2 text-sm font-semibold text-white transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
              >
                Edit details
              </button>
            </div>

            <div className="mt-5 grid gap-4 xl:grid-cols-[1.02fr_0.98fr]">
              <div className="space-y-4">
                {fiatReviewLoading && !fiatReviewData ? (
                  <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-5 text-sm text-slate-300">
                    <div className="flex items-center gap-3 text-white">
                      <LoaderCircle className="h-5 w-5 animate-spin text-[var(--exa-gold-light)]" />
                      <span className="font-semibold">Preparing your funding details</span>
                    </div>
                    <p className="mt-3 text-sm leading-6 text-slate-400">
                      We are loading the exact card or payment instructions for this amount now.
                    </p>
                  </div>
                ) : null}

                <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 text-sm text-slate-300">
                  <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Funding summary</p>
                  <div className="mt-3 space-y-2">
                    <Line label="Funding method" value={fiatReviewData?.method?.name || selectedFiatMethod?.name || selectedFiatMethod?.label || "--"} />
                    <Line label="Settlement currency" value={fiatReviewData?.currency || fiatCurrency || "--"} />
                    <Line label="Amount" value={fiatReviewData?.amount ? `${Number(fiatReviewData.amount).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${fiatReviewData.currency}` : fiatAmount ? `${Number(fiatAmount).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${fiatCurrency}` : "--"} />
                    <Line label="Fee" value={fiatReviewData?.fee_amount ? `${Number(fiatReviewData.fee_amount).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${fiatReviewData.currency}` : "--"} />
                    <Line label="Net funding after fee" value={fiatReviewData?.net_amount ? `${Number(fiatReviewData.net_amount).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${fiatReviewData.currency}` : "--"} />
                    <Line label="Reference" value={fiatReviewData?.reference || "--"} />
                  </div>
                </div>

                <div className="rounded-[22px] border border-amber-300/20 bg-amber-400/10 p-4 text-sm text-amber-100">
                  <p className="font-semibold">Before you send fiat</p>
                  <p className="mt-2 leading-6 text-amber-100/85">
                    {fiatReviewData?.disclosures?.[0] || "Send funds only through the selected ExaEarn fiat rail and use the exact payment reference shown here."}
                  </p>
                </div>

                {Array.isArray(fiatReviewData?.disclosures) && fiatReviewData.disclosures.length > 1 ? (
                  <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 text-xs text-slate-400">
                    <ul className="space-y-2">
                      {fiatReviewData.disclosures.slice(1).map((item) => (
                        <li key={item}>- {item}</li>
                      ))}
                    </ul>
                  </div>
                ) : null}
              </div>

              <div className="space-y-4">
                <div className="rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4 text-sm text-slate-300">
                  <p className="font-semibold text-white">Instruction details</p>
                  <div className="mt-3 space-y-2">
                    <Line label="Bank / Provider" value={fiatReviewData?.instructions?.bank_name || fiatReviewData?.instructions?.provider || fiatReviewData?.method?.name || "--"} />
                    <Line label="Account name" value={fiatReviewData?.instructions?.account_name || "--"} />
                    <Line label="Account number" value={fiatReviewData?.instructions?.account_number || "--"} />
                    <Line label="Beneficiary code" value={fiatReviewData?.instructions?.beneficiary_code || "--"} />
                    <Line label="Checkout link" value={fiatReviewData?.instructions?.checkout_url ? "Available" : selectedFiatMethod?.id === "card_payment" ? "Waiting for secure checkout link" : "--"} />
                    <Line label="Processing time" value={fiatReviewData?.method?.processing_time || "--"} />
                    <Line label="Expires at" value={fiatReviewData?.expires_at ? formatDate(fiatReviewData.expires_at) : "--"} />
                    <Line label="Time remaining" value={fiatReviewData?.expires_at ? formatCountdown(fiatReviewData.expires_at) : "--"} />
                  </div>
                  <p className="mt-3 text-xs leading-5 text-slate-400">
                    {fiatReviewData?.instructions?.narrative || fiatReviewData?.method?.description || "Follow the funding guidance supplied by ExaEarn for this fiat method before sending money."}
                  </p>
                </div>

                {fiatReviewError ? <Banner tone="rose">{fiatReviewError}</Banner> : null}

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <button
                    type="button"
                    onClick={() => setActiveView("deposit-fiat-detail")}
                    className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-4 py-3 text-sm font-semibold text-slate-200 transition hover:border-[var(--exa-border-active)]"
                  >
                    Back to details
                  </button>
                  {fiatReviewData?.instructions?.checkout_url ? (
                    <button
                      type="button"
                      onClick={() => window.location.assign(fiatReviewData.instructions.checkout_url)}
                      className="rounded-2xl exa-button-primary px-4 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] transition hover:brightness-105"
                    >
                      {fiatReviewData?.instructions?.action_label || "Continue to checkout"}
                    </button>
                  ) : (
                    <button
                      type="button"
                      onClick={() => void markFiatPaymentSent()}
                      disabled={markPaidLoading || fiatReviewLoading || !fiatReviewData?.reference}
                      className="rounded-2xl exa-button-primary px-4 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-55"
                    >
                      {markPaidLoading ? <LoaderCircle className="mx-auto h-4 w-4 animate-spin" /> : fiatReviewLoading ? "Loading details" : selectedFiatMethod?.id === "card_payment" ? "Waiting for checkout link" : "I've sent payment"}
                    </button>
                  )}
                </div>
              </div>
            </div>
          </section>
        ) : null}

        {fiatReviewOpen ? (
          <div className="fixed inset-0 z-40 flex items-end justify-center bg-black/60 px-3 pt-10 backdrop-blur-sm sm:items-center sm:p-6">
            <div className="w-full max-w-lg rounded-t-[28px] border border-white/10 bg-[#0b0f18] p-5 shadow-[0_30px_80px_rgba(0,0,0,.45)] sm:rounded-[28px]">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Review</p>
                  <h3 className="mt-1 text-lg font-semibold text-white">Fiat funding instructions</h3>
                </div>
                <button type="button" onClick={() => setFiatReviewOpen(false)} className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2 text-xs font-semibold text-slate-200">
                  Close
                </button>
              </div>

              <div className="mt-4 space-y-2 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 text-sm text-slate-300">
                <Line label="Funding method" value={fiatReviewData?.method?.name || selectedFiatMethod?.name || selectedFiatMethod?.label || "--"} />
                <Line label="Settlement currency" value={fiatReviewData?.currency || fiatCurrency || "--"} />
                <Line label="Amount" value={fiatReviewData?.amount ? `${Number(fiatReviewData.amount).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${fiatReviewData.currency}` : fiatAmount ? `${Number(fiatAmount).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${fiatCurrency}` : "--"} />
                <Line label="Fee" value={fiatReviewData?.fee_amount ? `${Number(fiatReviewData.fee_amount).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${fiatReviewData.currency}` : "--"} />
                <Line label="Net funding after fee" value={fiatReviewData?.net_amount ? `${Number(fiatReviewData.net_amount).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${fiatReviewData.currency}` : "--"} />
                <Line label="Reference" value={fiatReviewData?.reference || "--"} />
              </div>

              {fiatReviewError ? <Banner tone="rose">{fiatReviewError}</Banner> : null}

              <div className="mt-4 rounded-2xl border border-amber-300/20 bg-amber-400/10 p-4 text-sm text-amber-100">
                <p className="font-semibold">Before you send fiat</p>
                <p className="mt-2 leading-6 text-amber-100/85">
                  {fiatReviewData?.disclosures?.[0] || "Send funds only through the selected ExaEarn fiat rail and use the exact payment reference shown here."}
                </p>
              </div>

              <div className="mt-4 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4 text-sm text-slate-300">
                <p className="font-semibold text-white">Instruction details</p>
                <div className="mt-3 space-y-2 text-sm text-slate-300">
                  <Line label="Bank / Provider" value={fiatReviewData?.instructions?.bank_name || fiatReviewData?.instructions?.provider || fiatReviewData?.method?.name || "--"} />
                  <Line label="Account name" value={fiatReviewData?.instructions?.account_name || "--"} />
                  <Line label="Account number" value={fiatReviewData?.instructions?.account_number || "--"} />
                  <Line label="Beneficiary code" value={fiatReviewData?.instructions?.beneficiary_code || "--"} />
                  <Line label="Checkout link" value={fiatReviewData?.instructions?.checkout_url ? "Available" : "--"} />
                  <Line label="Processing time" value={fiatReviewData?.method?.processing_time || "--"} />
                  <Line label="Expires at" value={fiatReviewData?.expires_at ? formatDate(fiatReviewData.expires_at) : "--"} />
                  <Line label="Time remaining" value={fiatReviewData?.expires_at ? formatCountdown(fiatReviewData.expires_at) : "--"} />
                </div>
                <p className="mt-3 text-xs leading-5 text-slate-400">{fiatReviewData?.instructions?.narrative || fiatReviewData?.method?.description || "Follow the funding guidance supplied by ExaEarn for this fiat method before sending money."}</p>
              </div>

              {Array.isArray(fiatReviewData?.disclosures) && fiatReviewData.disclosures.length > 1 ? (
                <div className="mt-4 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 text-xs text-slate-400">
                  <ul className="space-y-2">
                    {fiatReviewData.disclosures.slice(1).map((item) => (
                      <li key={item}>- {item}</li>
                    ))}
                  </ul>
                </div>
              ) : null}

              <div className="mt-5 grid grid-cols-2 gap-3">
                <button
                  type="button"
                  onClick={() => setFiatReviewOpen(false)}
                  className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-4 py-3 text-sm font-semibold text-slate-200 transition hover:border-[var(--exa-border-active)]"
                >
                  Edit details
                </button>
                {fiatReviewData?.instructions?.checkout_url ? (
                  <button
                    type="button"
                    onClick={() => window.open(fiatReviewData.instructions.checkout_url, "_blank", "noopener,noreferrer")}
                    className="rounded-2xl exa-button-primary px-4 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] transition hover:brightness-105"
                  >
                    {fiatReviewData?.instructions?.action_label || "Continue to checkout"}
                  </button>
                ) : (
                  <button
                    type="button"
                    onClick={() => void markFiatPaymentSent()}
                    disabled={markPaidLoading}
                    className="rounded-2xl exa-button-primary px-4 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-55"
                  >
                    {markPaidLoading ? <LoaderCircle className="mx-auto h-4 w-4 animate-spin" /> : "I've sent payment"}
                  </button>
                )}
              </div>
            </div>
          </div>
        ) : null}
      </div>
    </main>
  );
}

function LoadingShell({ onBack }) {
  return (
    <main className="min-h-[100dvh] bg-[var(--exa-bg-primary)] px-3 pb-8 pt-3 text-slate-100 sm:px-4 lg:px-6">
      <div className="mx-auto flex w-full max-w-[1380px] flex-col gap-4">
        <header className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] px-4 py-4">
          <div className="flex items-center gap-3">
            <button type="button" onClick={onBack} className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-slate-100">
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div className="space-y-2">
              <div className="h-3 w-20 animate-pulse rounded-full bg-white/[0.05]" />
              <div className="h-8 w-40 animate-pulse rounded-xl bg-white/[0.05]" />
            </div>
          </div>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {[0, 1, 2, 3].map((item) => (
              <div key={item} className="h-20 animate-pulse rounded-2xl bg-white/[0.04]" />
            ))}
          </div>
        </header>
      </div>
    </main>
  );
}

const ASSET_ICON_MAP = {
  ETH: ethereumLogo,
  XRP: xrpLogo,
  BTC: "https://cryptoicons.org/api/icon/btc/200",
  USDT: "https://cryptoicons.org/api/icon/usdt/200",
  USDC: "https://cryptoicons.org/api/icon/usdc/200",
  SOL: "https://cryptoicons.org/api/icon/sol/200",
  BNB: "https://cryptoicons.org/api/icon/bnb/200",
  ADA: "https://cryptoicons.org/api/icon/ada/200",
  AVAX: "https://cryptoicons.org/api/icon/avax/200",
  DOT: "https://cryptoicons.org/api/icon/dot/200",
  MATIC: "https://cryptoicons.org/api/icon/matic/200",
  POL: "https://cryptoicons.org/api/icon/matic/200",
  TON: "https://cryptoicons.org/api/icon/ton/200",
  DOGE: "https://cryptoicons.org/api/icon/doge/200",
  LINK: "https://cryptoicons.org/api/icon/link/200",
  LTC: "https://cryptoicons.org/api/icon/ltc/200",
  BCH: "https://cryptoicons.org/api/icon/bch/200",
  TRX: "https://cryptoicons.org/api/icon/trx/200",
};

function AssetMark({ symbol, small = false }) {
  const normalized = String(symbol || "").toUpperCase();
  const logo = ASSET_ICON_MAP[normalized] || null;

  return (
    <span className={`relative inline-flex overflow-hidden ${small ? "h-7 w-7 text-[11px]" : "h-10 w-10 text-sm"} items-center justify-center rounded-2xl border border-white/10 bg-white/[0.05] font-semibold text-slate-200`}>
      {logo ? (
        <img
          src={logo}
          alt={`${normalized} logo`}
          className="h-full w-full object-cover"
          loading="lazy"
          onError={(event) => {
            event.currentTarget.style.display = "none";
          }}
        />
      ) : null}
      <span className={`${logo ? "absolute inset-0 hidden items-center justify-center bg-[var(--exa-gold-gradient)] text-[var(--exa-gold-contrast)]" : ""}`}>
        {String(symbol || "?").slice(0, 3)}
      </span>
      {!logo ? String(symbol || "?").slice(0, 3) : null}
    </span>
  );
}

function Metric({ label, value, compact = false }) {
  return (
    <div className={`rounded-2xl border border-white/8 ${compact ? "bg-black/15 px-4 py-3" : "bg-white/[0.03] px-4 py-3"}`}>
      <p className="text-xs uppercase tracking-[0.14em] text-slate-500">{label}</p>
      <p className={`${compact ? "mt-2 text-sm" : "mt-2 text-lg"} font-semibold text-white`}>{value || "--"}</p>
    </div>
  );
}

function Banner({ children, tone = "rose" }) {
  const styles = tone === "rose" ? "border-rose-400/20 bg-rose-400/10 text-rose-100" : "border-white/8 bg-white/[0.03] text-slate-300";
  return <div className={`rounded-2xl border px-4 py-3 text-sm ${styles}`}>{children}</div>;
}

function EmptyState({ message, icon: Icon = Wallet, tall = false }) {
  return (
    <div className={`rounded-2xl border border-dashed border-white/10 bg-white/[0.02] px-4 ${tall ? "py-8" : "py-5"} text-center`}>
      <Icon className="mx-auto h-10 w-10 text-[var(--exa-gold-light)]" />
      <p className="mt-3 text-sm leading-6 text-slate-400">{message}</p>
    </div>
  );
}

function Line({ label, value }) {
  return (
    <div className="flex items-center justify-between gap-3 border-b border-white/6 pb-2 last:border-0 last:pb-0">
      <span className="text-slate-500">{label}</span>
      <span className="text-right font-medium text-white">{value || "--"}</span>
    </div>
  );
}

function IdentityCard({ label, value, onCopy, copied }) {
  return (
    <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4">
      <p className="text-[11px] uppercase tracking-[0.14em] text-slate-500">{label}</p>
      <p className="mt-2 break-all text-sm font-semibold text-white">{value || "--"}</p>
      <button
        type="button"
        onClick={onCopy}
        disabled={!value || value === "--" || value === "Not available"}
        className="mt-3 inline-flex items-center gap-2 rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2 text-xs font-semibold text-slate-200 transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)] disabled:cursor-not-allowed disabled:opacity-50"
      >
        <Copy className="h-3.5 w-3.5" />
        {copied ? "Copied" : "Copy"}
      </button>
    </div>
  );
}


function MiniButton({ children, onClick }) {
  return (
    <button type="button" onClick={onClick} className="inline-flex items-center gap-2 rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2 text-xs font-semibold text-slate-200 transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]">
      <Copy className="h-3.5 w-3.5" />
      {children}
    </button>
  );
}

function Safety({ icon: Icon, title, body }) {
  return (
    <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
      <div className="flex items-start gap-3">
        <span className="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-white/[0.05] text-[var(--exa-gold-light)]">
          <Icon className="h-4 w-4" />
        </span>
        <div>
          <p className="text-sm font-semibold text-white">{title}</p>
          <p className="mt-1 text-xs leading-5 text-slate-400">{body}</p>
        </div>
      </div>
    </div>
  );
}

function formatCountdown(value) {
  if (!value) return "--";
  const target = new Date(value).getTime();
  if (Number.isNaN(target)) return "--";

  const diff = target - Date.now();
  if (diff <= 0) return "Expired";

  const totalMinutes = Math.floor(diff / 60000);
  const days = Math.floor(totalMinutes / 1440);
  const hours = Math.floor((totalMinutes % 1440) / 60);
  const minutes = totalMinutes % 60;

  if (days > 0) return `${days}d ${hours}h`;
  if (hours > 0) return `${hours}h ${minutes}m`;
  return `${Math.max(minutes, 1)}m`;
}

function formatAmount(value, suffix) {
  const number = Number(value);
  if (!Number.isFinite(number)) return suffix ? `-- ${suffix}` : "--";
  const digits = number >= 1000 ? 2 : number >= 1 ? 4 : 8;
  const formatted = number.toLocaleString(undefined, { maximumFractionDigits: digits });
  return suffix ? `${formatted} ${suffix}` : formatted;
}

function formatDate(value) {
  if (!value) return "--";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "--";
  return parsed.toLocaleString(undefined, { month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
}

function truncateMiddle(value, head = 8, tail = 6) {
  if (!value || value.length <= head + tail + 3) return value || "--";
  return `${value.slice(0, head)}...${value.slice(-tail)}`;
}

export default AddFundsPage;


