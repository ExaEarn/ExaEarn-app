
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  ChevronDown,
  ChevronRight,
  ExternalLink,
  Landmark,
  LoaderCircle,
  Search,
  ShieldCheck,
  UserRound,
  Wallet,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";
const ROUTE_METHODS = [
  {
    id: "crypto",
    title: "Crypto Withdrawal",
    description: "Choose a coin and open its dedicated withdrawal page.",
    enabled: true,
  },
  {
    id: "sell_fiat",
    title: "Fiat Withdrawal",
    description: "Withdraw fiat from your Funding Wallet to a supported bank or payout destination.",
    enabled: true,
  },
  {
    id: "p2p",
    title: "P2P Trading",
    description: "Open P2P Sell to sell crypto directly to verified users.",
    enabled: true,
  },
];

function Withdraw({ onBack, onOpenSwap, onOpenP2P, onOpenFiatWithdrawal }) {
  const { request } = useAuth();
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [activeView, setActiveView] = useState("hub");
  const [selectedMethod, setSelectedMethod] = useState("crypto");
  const [selectedAssetSymbol, setSelectedAssetSymbol] = useState("");
  const [hideZeroBalances, setHideZeroBalances] = useState(false);
  const [assetSearch, setAssetSearch] = useState("");
  const [withdrawType, setWithdrawType] = useState("on_chain");
  const [network, setNetwork] = useState("");
  const [address, setAddress] = useState("");
  const [memo, setMemo] = useState("");
  const [amount, setAmount] = useState("");
  const [twoFactorCode, setTwoFactorCode] = useState("");
  const [recipientType, setRecipientType] = useState("email");
  const [recipientValue, setRecipientValue] = useState("");
  const [recipientMatch, setRecipientMatch] = useState(null);
  const [lookupBusy, setLookupBusy] = useState(false);
  const [lookupError, setLookupError] = useState("");
  const [preview, setPreview] = useState(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [banks, setBanks] = useState([]);

  const unwrap = (payload) => payload?.data ?? payload ?? {};

  const loadMeta = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const payload = unwrap(await request("/api/wallet/withdraw/meta", { method: "GET", timeoutMs: 30000 }));
      setMeta(payload);
      const assets = Array.isArray(payload.assets) ? payload.assets : [];
      setSelectedAssetSymbol((current) => (current && assets.some((asset) => asset.symbol === current) ? current : assets[0]?.symbol || ""));
    } catch (loadError) {
      setError(loadError?.message || "We could not load the withdrawal center right now.");
    } finally {
      setLoading(false);
    }
  }, [request]);

  useEffect(() => {
    void loadMeta();
  }, [loadMeta]);

  const methods = Array.isArray(meta?.methods) && meta.methods.length ? meta.methods : ROUTE_METHODS;
  const assets = Array.isArray(meta?.assets) ? meta.assets : [];
  const selectedAsset = useMemo(() => assets.find((asset) => asset.symbol === selectedAssetSymbol) || null, [assets, selectedAssetSymbol]);
  const filteredAssets = useMemo(() => {
    const needle = assetSearch.trim().toLowerCase();
    return assets.filter((asset) => {
      if (hideZeroBalances && Number(asset.withdrawableBalance || 0) <= 0) return false;
      if (!needle) return true;
      return String(asset.symbol || "").toLowerCase().includes(needle) || String(asset.name || "").toLowerCase().includes(needle);
    });
  }, [assetSearch, assets, hideZeroBalances]);
  const networks = Array.isArray(selectedAsset?.supportedNetworks) ? selectedAsset.supportedNetworks : [];
  const selectedNetwork = useMemo(() => networks.find((item) => item.id === network) || networks[0] || null, [network, networks]);

  useEffect(() => {
    if (!networks.length) {
      setNetwork("");
      return;
    }
    setNetwork((current) => (networks.some((item) => item.id === current) ? current : networks[0]?.id || ""));
  }, [networks]);

  const refreshPreview = useCallback(async () => {
    if (!selectedAsset?.symbol || !amount || Number(amount) <= 0) {
      setPreview(null);
      return;
    }
    setPreviewLoading(true);
    try {
      const payload = unwrap(await request("/api/wallet/withdraw/preview", {
        method: "POST",
        body: JSON.stringify({
          flow: withdrawType,
          currency: selectedAsset.symbol,
          amount,
          network: withdrawType === "on_chain" ? network : undefined,
        }),
      }));
      setPreview(payload);
    } catch {
      setPreview(null);
    } finally {
      setPreviewLoading(false);
    }
  }, [amount, network, request, selectedAsset?.symbol, withdrawType]);

  useEffect(() => {
    void refreshPreview();
  }, [refreshPreview]);

  const lookupRecipient = async () => {
    if (!recipientValue.trim()) return;
    setLookupBusy(true);
    setLookupError("");
    setRecipientMatch(null);
    try {
      const payload = unwrap(await request("/api/wallet/withdraw/internal-lookup", {
        method: "POST",
        body: JSON.stringify({ identifier_type: recipientType, identifier: recipientValue.trim() }),
      }));
      setRecipientMatch(payload);
    } catch (lookupIssue) {
      setLookupError(lookupIssue?.message || "We could not verify that ExaEarn recipient.");
    } finally {
      setLookupBusy(false);
    }
  };

  useEffect(() => {
    if (selectedMethod !== "sell_fiat" || banks.length) return;
    let ignore = false;
    (async () => {
      try {
        const payload = unwrap(await request("/api/wallet/withdraw/fiat/banks", { method: "GET" }));
        if (!ignore) setBanks(Array.isArray(payload.items) ? payload.items : []);
      } catch {
        if (!ignore) setBanks([]);
      }
    })();
    return () => {
      ignore = true;
    };
  }, [banks.length, request, selectedMethod]);

  const resetFormState = () => {
    setAmount("");
    setAddress("");
    setMemo("");
    setTwoFactorCode("");
    setRecipientValue("");
    setRecipientMatch(null);
    setLookupError("");
    setPreview(null);
  };

  const openMethod = (methodId) => {
    if (methodId === "p2p") {
      if (onOpenP2P) {
        onOpenP2P("sell");
        return;
      }
      setError("P2P Sell is temporarily unavailable from this screen.");
      return;
    }
    setSelectedMethod(methodId);
    setSuccess("");
    setError("");
    resetFormState();
    if (methodId === "crypto") {
      setActiveView("crypto-list");
      return;
    }
    if (methodId === "sell_fiat") {
      if (onOpenFiatWithdrawal) {
        onOpenFiatWithdrawal();
        return;
      }
      setError("Fiat Withdrawal is temporarily unavailable from this screen.");
      return;
    }
    setActiveView(methodId);
  };

  const useMaxAmount = () => {
    const balance = Number(selectedAsset?.withdrawableBalance || 0);
    const fee = Number(preview?.fee || 0);
    const safeValue = withdrawType === "internal" ? balance : Math.max(balance - fee, 0);
    setAmount(safeValue > 0 ? String(safeValue) : "0");
  };
  const submitOnChain = async () => {
    setSubmitting(true);
    setError("");
    setSuccess("");
    try {
      const payload = unwrap(await request("/api/wallet/withdraw/on-chain", {
        method: "POST",
        body: JSON.stringify({
          currency: selectedAsset.symbol,
          network,
          address: address.trim(),
          amount,
          memo: memo.trim() || undefined,
          two_factor_code: twoFactorCode.trim() || undefined,
        }),
      }));
      setSuccess(payload.reference ? `Withdrawal request submitted. Reference: ${payload.reference}` : "Withdrawal request submitted.");
      resetFormState();
      await loadMeta();
    } catch (submitError) {
      setError(submitError?.message || "We could not submit this withdrawal safely.");
    } finally {
      setSubmitting(false);
    }
  };

  const submitInternal = async () => {
    setSubmitting(true);
    setError("");
    setSuccess("");
    try {
      const payload = unwrap(await request("/api/wallet/withdraw/internal-transfer", {
        method: "POST",
        body: JSON.stringify({
          currency: selectedAsset.symbol,
          amount,
          identifier_type: recipientType,
          identifier: recipientValue.trim(),
          two_factor_code: twoFactorCode.trim() || undefined,
        }),
      }));
      setSuccess(payload.reference ? `Internal transfer completed. Reference: ${payload.reference}` : "Internal transfer completed.");
      resetFormState();
      await loadMeta();
    } catch (submitError) {
      setError(submitError?.message || "We could not complete the internal transfer.");
    } finally {
      setSubmitting(false);
    }
  };

  const currentFee = preview?.fee ?? (withdrawType === "internal" ? "0" : selectedNetwork?.fee ?? "--");
  const currentReceived = preview?.amount_received ?? (amount || "0");
  const canSubmitOnChain = Boolean(selectedAsset?.symbol && network && address.trim() && amount && Number(amount) > 0 && (!selectedNetwork?.memoRequired || memo.trim()));
  const canSubmitInternal = Boolean(selectedAsset?.symbol && recipientValue.trim() && recipientMatch && amount && Number(amount) > 0);

  return (
    <main className="min-h-[100dvh] bg-[var(--exa-bg-primary)] px-3 pb-20 pt-3 text-slate-100 sm:px-4 lg:px-6">
      <div className="mx-auto flex w-full max-w-[1280px] flex-col gap-4">
        <header className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] px-4 py-4 shadow-[0_22px_55px_rgba(0,0,0,.35)]">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={activeView === "hub" ? onBack : () => setActiveView("hub")}
                  className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-slate-100 transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
                  aria-label={activeView === "hub" ? "Go back" : "Back to withdrawal methods"}
                >
                  <ArrowLeft className="h-4 w-4" />
                </button>
                <div>
                  <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Assets</p>
                  <h1 className="text-2xl font-semibold tracking-tight text-white sm:text-3xl">Withdraw</h1>
                </div>
              </div>
              <p className="mt-3 max-w-2xl text-sm text-slate-400">
                {activeView === "hub"
                  ? "Choose a withdrawal route first, then continue into a dedicated page to complete the flow."
                  : selectedMethod === "sell_fiat"
                    ? "Open the dedicated fiat withdrawal route to send funds to a supported bank or payout destination."
                    : "Select the asset, review the network or internal transfer details, and confirm securely."}
              </p>
            </div>
            <div className="rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-3 py-2 text-[11px] font-semibold text-[var(--exa-gold-light)]">
              Exchange-grade flow
            </div>
          </div>
        </header>

        {error ? <Banner tone="rose">{error}</Banner> : null}
        {success ? <Banner tone="emerald">{success}</Banner> : null}

        {activeView === "hub" ? (
          <section className="rounded-[28px] border border-[var(--exa-border)] bg-[var(--exa-surface)] px-4 py-5 shadow-[0_30px_90px_rgba(0,0,0,.28)] sm:px-5 lg:px-6">
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Withdrawal Center</p>
                <h2 className="mt-1 text-xl font-semibold text-white">Choose how you want to withdraw</h2>
                <p className="mt-2 text-sm text-slate-400">Crypto withdrawals, fiat payouts, and P2P sell flows now open as dedicated exchange-grade pages.</p>
              </div>
            </div>
            <div className="mt-5 grid gap-3 lg:grid-cols-3">
              {methods.map((method) => {
                const Icon = method.id === "crypto" ? Wallet : method.id === "sell_fiat" ? Landmark : UserRound;
                const title = method.id === "sell_fiat" ? "Fiat Withdrawal" : method.title;
                const description = method.id === "p2p" ? "Open P2P Sell to sell crypto directly to verified users." : method.description;
                return (
                  <button
                    key={method.id}
                    type="button"
                    disabled={!method.enabled}
                    onClick={() => openMethod(method.id)}
                    className="flex items-start justify-between gap-3 rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-4 text-left transition hover:border-[var(--exa-border-active)] hover:bg-white/[0.04] disabled:opacity-45"
                  >
                    <span className="flex items-start gap-3">
                      <span className="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-white/[0.05] text-slate-200"><Icon className="h-5 w-5" /></span>
                      <span>
                        <p className="text-sm font-semibold text-white">{title}</p>
                        <p className="mt-1 text-xs leading-5 text-slate-400">{description}</p>
                      </span>
                    </span>
                    <ChevronRight className="mt-1 h-4 w-4 text-slate-500" />
                  </button>
                );
              })}
            </div>
          </section>
        ) : null}

        {activeView === "crypto-list" ? (
          <section className="rounded-[28px] border border-[var(--exa-border)] bg-[var(--exa-surface)] px-4 py-5 shadow-[0_30px_90px_rgba(0,0,0,.28)] sm:px-5 lg:px-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Crypto withdrawal</p>
                <h2 className="mt-1 text-xl font-semibold text-white">Choose a coin to open its withdrawal page</h2>
              </div>
              <label className="inline-flex items-center gap-2 rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2 text-xs font-semibold text-slate-200">
                <input type="checkbox" checked={hideZeroBalances} onChange={(event) => setHideZeroBalances(event.target.checked)} className="h-4 w-4 rounded border-white/20 bg-transparent text-amber-300" /> Hide zero balances
              </label>
            </div>
            <div className="mt-4 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2">
              <div className="flex items-center gap-2"><Search className="h-4 w-4 text-slate-500" /><input value={assetSearch} onChange={(event) => setAssetSearch(event.target.value)} placeholder="Search asset" className="h-9 w-full bg-transparent text-sm text-white outline-none placeholder:text-slate-500" /></div>
            </div>
            <div className="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              {filteredAssets.map((asset) => (
                <button
                  key={asset.symbol}
                  type="button"
                  onClick={() => {
                    setSelectedAssetSymbol(asset.symbol);
                    setWithdrawType("on_chain");
                    resetFormState();
                    setActiveView("crypto-detail");
                  }}
                  className="flex items-center justify-between gap-3 rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-4 text-left transition hover:border-[var(--exa-border-active)] hover:bg-white/[0.04]"
                >
                  <div className="flex items-center gap-3"><AssetMark symbol={asset.symbol} /><div><p className="text-sm font-semibold text-white">{asset.symbol}</p><p className="text-xs text-slate-400">{asset.name}</p></div></div>
                  <div className="text-right"><p className="text-sm font-semibold text-white">{formatAsset(asset.withdrawableBalance, asset.symbol)}</p><p className="text-xs text-slate-500">Available</p></div>
                </button>
              ))}
            </div>
          </section>
        ) : null}
        {activeView === "crypto-detail" && selectedAsset ? (
          <section className="grid gap-4 xl:grid-cols-[1.05fr_0.95fr]">
            <div className="space-y-4">
              <section className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Selected asset</p>
                    <h2 className="mt-1 text-lg font-semibold text-white">{selectedAsset.symbol} withdrawal</h2>
                  </div>
                  <button type="button" onClick={() => setActiveView("crypto-list")} className="inline-flex items-center gap-2 rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2 text-sm font-semibold text-white transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]">Change asset</button>
                </div>
                <div className="mt-4 grid gap-3 md:grid-cols-2">
                  <button type="button" onClick={() => setWithdrawType("on_chain")} className={`flex items-start justify-between rounded-2xl border px-4 py-4 text-left transition ${withdrawType === "on_chain" ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]" : "border-white/8 bg-white/[0.02] hover:border-[var(--exa-border-active)] hover:bg-white/[0.03]"}`}><div><p className="text-sm font-semibold text-white">Crypto withdrawal</p><p className="mt-1 text-xs leading-5 text-slate-400">Send to an external blockchain address.</p></div><ChevronRight className="h-4 w-4 text-slate-500" /></button>
                  <button type="button" onClick={() => setWithdrawType("internal")} className={`flex items-start justify-between rounded-2xl border px-4 py-4 text-left transition ${withdrawType === "internal" ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]" : "border-white/8 bg-white/[0.02] hover:border-[var(--exa-border-active)] hover:bg-white/[0.03]"}`}><div><p className="text-sm font-semibold text-white">Send to ExaEarn user</p><p className="mt-1 text-xs leading-5 text-slate-400">Transfer instantly within ExaEarn.</p></div><ChevronRight className="h-4 w-4 text-slate-500" /></button>
                </div>
              </section>

              {withdrawType === "on_chain" ? (
                <section className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                  <div><p className="text-xs uppercase tracking-[0.18em] text-slate-500">Withdrawal details</p><h2 className="mt-1 text-lg font-semibold text-white">External wallet transfer</h2></div>
                  <div className="mt-4 space-y-4">
                    <label className="block"><span className="mb-2 block text-xs uppercase tracking-[0.14em] text-slate-500">Address</span><input value={address} onChange={(event) => setAddress(event.target.value)} placeholder="Enter or paste withdrawal address" className="w-full rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-4 py-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-[var(--exa-border-active)]" /></label>
                    <div className="grid gap-4 md:grid-cols-2">
                      <label className="block"><span className="mb-2 block text-xs uppercase tracking-[0.14em] text-slate-500">Network</span><span className="relative block"><select value={network} onChange={(event) => setNetwork(event.target.value)} className="w-full appearance-none rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-4 py-3 text-sm text-white outline-none focus:border-[var(--exa-border-active)]">{networks.map((item) => <option key={item.id} value={item.id} className="bg-[#111827] text-white">{item.name} ({item.standard})</option>)}</select><ChevronDown className="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" /></span></label>
                      <label className="block"><span className="mb-2 block text-xs uppercase tracking-[0.14em] text-slate-500">Amount</span><div className="flex items-center gap-2 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-4 py-3"><input value={amount} onChange={(event) => setAmount(event.target.value)} placeholder="0.00" inputMode="decimal" className="w-full bg-transparent text-sm text-white outline-none placeholder:text-slate-500" /><button type="button" onClick={useMaxAmount} className="rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-3 py-1 text-xs font-semibold text-[var(--exa-gold-light)]">MAX</button></div><p className="mt-2 text-xs text-slate-500">Available: {formatAsset(selectedAsset.withdrawableBalance, selectedAsset.symbol)}</p></label>
                    </div>
                    {selectedNetwork?.memoRequired ? <label className="block"><span className="mb-2 block text-xs uppercase tracking-[0.14em] text-slate-500">Destination tag / memo</span><input value={memo} onChange={(event) => setMemo(event.target.value)} placeholder="Enter required memo or destination tag" className="w-full rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-4 py-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-[var(--exa-border-active)]" /></label> : null}
                    <label className="block"><span className="mb-2 block text-xs uppercase tracking-[0.14em] text-slate-500">Security code</span><input value={twoFactorCode} onChange={(event) => setTwoFactorCode(event.target.value)} placeholder="Enter 2FA or security code" className="w-full rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-4 py-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-[var(--exa-border-active)]" /></label>
                  </div>
                </section>
              ) : (
                <section className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                  <div><p className="text-xs uppercase tracking-[0.18em] text-slate-500">Transfer details</p><h2 className="mt-1 text-lg font-semibold text-white">Send to another ExaEarn user</h2></div>
                  <div className="mt-4 space-y-4">
                    <div><span className="mb-2 block text-xs uppercase tracking-[0.14em] text-slate-500">Recipient type</span><div className="flex flex-wrap gap-2">{(meta?.recipient_types || []).map((type) => <button key={type.id} type="button" onClick={() => setRecipientType(type.id)} className={`rounded-full border px-3 py-2 text-xs font-semibold transition ${recipientType === type.id ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]" : "border-white/10 bg-white/[0.03] text-slate-300 hover:border-[var(--exa-border-active)]"}`}>{type.label}</button>)}</div></div>
                    <div className="grid gap-3 md:grid-cols-[1fr_auto]"><label className="block"><span className="mb-2 block text-xs uppercase tracking-[0.14em] text-slate-500">Recipient</span><input value={recipientValue} onChange={(event) => setRecipientValue(event.target.value)} placeholder={`Enter recipient ${recipientType.replace("_", " ")}`} className="w-full rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-4 py-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-[var(--exa-border-active)]" /></label><button type="button" onClick={() => void lookupRecipient()} disabled={lookupBusy || !recipientValue.trim()} className="mt-6 inline-flex h-12 items-center justify-center gap-2 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-4 text-sm font-semibold text-slate-200 transition hover:border-[var(--exa-border-active)] disabled:opacity-45">{lookupBusy ? <LoaderCircle className="h-4 w-4 animate-spin" /> : null}Verify</button></div>
                    {lookupError ? <Banner tone="rose">{lookupError}</Banner> : null}
                    {recipientMatch ? <div className="rounded-2xl border border-emerald-300/20 bg-emerald-400/10 p-4 text-sm text-emerald-100"><p className="font-semibold">Recipient confirmed</p><p className="mt-2">{recipientMatch.display_name}</p><p className="text-xs text-emerald-100/80">{recipientMatch.exaearn_id} - {recipientMatch.masked_email}</p></div> : null}
                    <div className="grid gap-4 md:grid-cols-2"><label className="block"><span className="mb-2 block text-xs uppercase tracking-[0.14em] text-slate-500">Amount</span><div className="flex items-center gap-2 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-4 py-3"><input value={amount} onChange={(event) => setAmount(event.target.value)} placeholder="0.00" inputMode="decimal" className="w-full bg-transparent text-sm text-white outline-none placeholder:text-slate-500" /><button type="button" onClick={useMaxAmount} className="rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-3 py-1 text-xs font-semibold text-[var(--exa-gold-light)]">MAX</button></div><p className="mt-2 text-xs text-slate-500">Available: {formatAsset(selectedAsset.withdrawableBalance, selectedAsset.symbol)}</p></label><label className="block"><span className="mb-2 block text-xs uppercase tracking-[0.14em] text-slate-500">Security code</span><input value={twoFactorCode} onChange={(event) => setTwoFactorCode(event.target.value)} placeholder="Enter 2FA or security code" className="w-full rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-4 py-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-[var(--exa-border-active)]" /></label></div>
                  </div>
                </section>
              )}
            </div>

            <aside className="space-y-4">
              <section className="rounded-[24px] border border-[var(--exa-border-active)] bg-[linear-gradient(135deg,var(--exa-gold-surface),var(--exa-surface-elevated)_48%,var(--exa-bg-tertiary))] p-4">
                <div className="flex items-center justify-between gap-3"><div><p className="text-xs uppercase tracking-[0.14em] text-slate-500">Summary</p><p className="mt-1 text-base font-semibold text-white">{withdrawType === "internal" ? "Internal Transfer" : `${selectedAsset.symbol} On-Chain`}</p></div><span className="rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-2.5 py-1 text-[11px] font-semibold text-[var(--exa-gold-light)]">{withdrawType === "internal" ? "Zero network fee" : selectedNetwork?.status || "Available"}</span></div>
                <div className="mt-4 space-y-2 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4 text-xs text-slate-300">
                  <Line label="Available" value={formatAsset(selectedAsset.withdrawableBalance, selectedAsset.symbol)} />
                  {withdrawType === "on_chain" ? <Line label="Network" value={selectedNetwork ? `${selectedNetwork.name} (${selectedNetwork.standard})` : "Select network"} /> : <Line label="Recipient" value={recipientMatch?.display_name || "Verify recipient"} />}
                  <Line label={withdrawType === "internal" ? "Transfer fee" : "Network fee"} value={previewLoading ? "Calculating..." : formatAsset(currentFee, selectedAsset.symbol)} />
                  <Line label="Amount received" value={previewLoading ? "Calculating..." : formatAsset(currentReceived, selectedAsset.symbol)} />
                  <Line label="Daily limit" value={meta?.limits?.daily_limit ? formatAsset(meta.limits.daily_limit, selectedAsset.symbol) : "--"} />
                </div>
                <div className="mt-4 rounded-2xl border border-amber-300/20 bg-amber-400/10 p-4 text-xs text-amber-100"><p className="font-semibold">Security note</p><p className="mt-1 leading-5">Withdrawals sent to the wrong address or network may be permanently lost. Review every detail before confirming.</p></div>
                <button type="button" onClick={() => (withdrawType === "internal" ? submitInternal() : submitOnChain())} disabled={withdrawType === "internal" ? !canSubmitInternal || submitting : !canSubmitOnChain || submitting} className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-2xl exa-button-primary px-4 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-45">{submitting ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <ShieldCheck className="h-4 w-4" />}{withdrawType === "internal" ? "Confirm Transfer" : "Confirm Withdrawal"}</button>
              </section>
            </aside>
          </section>
        ) : null}

        {activeView === "sell-list" ? (
          <section className="rounded-[28px] border border-[var(--exa-border)] bg-[var(--exa-surface)] px-4 py-5 shadow-[0_30px_90px_rgba(0,0,0,.28)] sm:px-5 lg:px-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Fiat Withdrawal</p>
                <h2 className="mt-1 text-xl font-semibold text-white">Choose the asset you want to sell</h2>
              </div>
              <div className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2 text-xs font-semibold text-slate-300">Preferred fiat: {meta?.preferred_fiat_currency || "NGN"}</div>
            </div>
            <div className="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              {filteredAssets.map((asset) => (
                <button key={asset.symbol} type="button" onClick={() => { setSelectedAssetSymbol(asset.symbol); setActiveView("sell-detail"); }} className="flex items-center justify-between gap-3 rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-4 text-left transition hover:border-[var(--exa-border-active)] hover:bg-white/[0.04]">
                  <div className="flex items-center gap-3"><AssetMark symbol={asset.symbol} /><div><p className="text-sm font-semibold text-white">{asset.symbol}</p><p className="text-xs text-slate-400">{asset.name}</p></div></div>
                  <div className="text-right"><p className="text-sm font-semibold text-white">{formatAsset(asset.withdrawableBalance, asset.symbol)}</p><p className="text-xs text-slate-500">Available</p></div>
                </button>
              ))}
            </div>
          </section>
        ) : null}

        {activeView === "sell-detail" && selectedAsset ? (
          <section className="grid gap-4 xl:grid-cols-[1.02fr_0.98fr]">
            <section className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Fiat Withdrawal</p>
                  <h2 className="mt-1 text-lg font-semibold text-white">Withdraw {meta?.preferred_fiat_currency || "NGN"}</h2>
                  <p className="mt-2 text-sm text-slate-400">Open the dedicated fiat withdrawal page to send funds to a supported bank or payout destination.</p>
                </div>
                <button type="button" onClick={() => setActiveView("sell-list")} className="inline-flex items-center gap-2 rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2 text-sm font-semibold text-white transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]">Change asset</button>
              </div>
              <div className="mt-5 rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 text-sm text-slate-300">
                <div className="flex items-center gap-3"><AssetMark symbol={selectedAsset.symbol} /><div><p className="text-base font-semibold text-white">{selectedAsset.symbol}</p><p className="text-xs text-slate-400">{selectedAsset.name}</p></div></div>
                <div className="mt-4 space-y-2">
                  <Line label="Withdrawable balance" value={formatAsset(selectedAsset.withdrawableBalance, selectedAsset.symbol)} />
                  <Line label="Preferred fiat" value={meta?.preferred_fiat_currency || "NGN"} />
                  <Line label="Available bank rails" value={banks.length ? `${banks.length} configured` : "Will load in sell route"} />
                </div>
              </div>
            </section>
            <aside className="rounded-[24px] border border-[var(--exa-border-active)] bg-[linear-gradient(135deg,var(--exa-gold-surface),var(--exa-surface-elevated)_48%,var(--exa-bg-tertiary))] p-4">
              <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Next step</p>
              <h3 className="mt-1 text-lg font-semibold text-white">Continue to Fiat Withdrawal</h3>
              <p className="mt-3 text-sm leading-6 text-slate-400">You'll complete the payout setup in the dedicated fiat withdrawal route, following the same exchange-grade flow as the rest of ExaEarn.</p>
              <div className="mt-4 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4 text-sm text-slate-300">
                {banks.length ? `Supported payout rails include ${banks.slice(0, 4).map((bank) => bank.name).join(", ")}${banks.length > 4 ? " and more" : ""}.` : "Supported fiat payout rails will appear in the next step when available."}
              </div>
              <button type="button" onClick={() => { try { localStorage.setItem("exaearn_convert_preset", JSON.stringify({ fromCode: selectedAsset.symbol, toCode: meta?.preferred_fiat_currency || "NGN", entry: "withdraw_sell" })); } catch { /* ignore local preset write errors */ } onOpenSwap(); }} className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-2xl exa-button-primary px-4 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] transition hover:brightness-105">Continue to Fiat Withdrawal <ExternalLink className="h-4 w-4" /></button>
            </aside>
          </section>
        ) : null}
      </div>
    </main>
  );
}
function LoadingShell({ onBack }) {
  return (
    <main className="min-h-[100dvh] bg-[var(--exa-bg-primary)] px-3 pb-20 pt-3 text-slate-100 sm:px-4 lg:px-6">
      <div className="mx-auto flex w-full max-w-[1280px] flex-col gap-4">
        <header className="rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] px-4 py-4">
          <div className="flex items-center gap-3">
            <button type="button" onClick={onBack} className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-slate-100"><ArrowLeft className="h-4 w-4" /></button>
            <div className="space-y-2"><div className="h-3 w-20 animate-pulse rounded-full bg-white/[0.05]" /><div className="h-8 w-40 animate-pulse rounded-xl bg-white/[0.05]" /></div>
          </div>
        </header>
      </div>
    </main>
  );
}

function AssetMark({ symbol }) {
  return <span className="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-[var(--exa-gold-gradient)] text-sm font-semibold text-[var(--exa-gold-contrast)]">{String(symbol || "?").slice(0, 3)}</span>;
}

function Banner({ children, tone = "rose" }) {
  const styles = tone === "emerald" ? "border-emerald-400/20 bg-emerald-400/10 text-emerald-100" : "border-rose-400/20 bg-rose-400/10 text-rose-100";
  return <div className={`rounded-2xl border px-4 py-3 text-sm ${styles}`}>{children}</div>;
}

function Line({ label, value }) {
  return <div className="flex items-center justify-between gap-3 border-b border-white/6 pb-2 last:border-0 last:pb-0"><span className="text-slate-500">{label}</span><span className="text-right font-medium text-white">{value || "--"}</span></div>;
}

function formatAsset(value, symbol) {
  const number = Number(value);
  if (!Number.isFinite(number)) return symbol ? `-- ${symbol}` : "--";
  const digits = number >= 1000 ? 2 : number >= 1 ? 4 : 8;
  const formatted = number.toLocaleString(undefined, { maximumFractionDigits: digits });
  return symbol ? `${formatted} ${symbol}` : formatted;
}

export default Withdraw;

