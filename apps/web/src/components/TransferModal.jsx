import { useEffect, useMemo, useState } from "react";
import { ArrowUpDown, ChevronDown, X } from "lucide-react";

function createIdempotencyKey() {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }

  return `exa-transfer-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function TransferModal({ isOpen, onClose, onTransfer, assets = [], balances = [] }) {
  const [fromWallet, setFromWallet] = useState("funding");
  const [toWallet, setToWallet] = useState("unified_trading");
  const [asset, setAsset] = useState(assets[0] || "USDT");
  const [amount, setAmount] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");

  const wallets = useMemo(() => balances.map((item) => item.key).filter(Boolean), [balances]);
  const accountMap = useMemo(() => new Map(balances.map((item) => [item.key, item])), [balances]);

  const sourceAccount = accountMap.get(fromWallet);
  const sourceAccountAssets = useMemo(() => (sourceAccount?.assets || []).map((item) => item.asset).filter(Boolean), [sourceAccount]);
  const sourceAsset = useMemo(() => (sourceAccount?.assets || []).find((item) => item.asset === asset), [asset, sourceAccount]);
  const availableBalance = sourceAsset?.transferable || "0";
  const inUseBalance = sourceAsset?.inUse || sourceAsset?.locked || "0";

  useEffect(() => {
    const options = sourceAccountAssets.length ? sourceAccountAssets : assets;
    if (options.length && !options.includes(asset)) {
      setAsset(options[0]);
    }
  }, [asset, assets, sourceAccountAssets]);

  useEffect(() => {
    if (fromWallet === toWallet && wallets.length > 1) {
      setToWallet(wallets.find((wallet) => wallet !== fromWallet) || wallets[0]);
    }
  }, [fromWallet, toWallet, wallets]);

  const handleSwap = () => {
    const previousFrom = fromWallet;
    setFromWallet(toWallet);
    setToWallet(previousFrom);
  };

  const handleMax = () => {
    setAmount(String(availableBalance || "0"));
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setSubmitting(true);
    setError("");

    try {
      await onTransfer({
        from_account: fromWallet,
        to_account: toWallet,
        asset,
        amount,
        idempotency_key: createIdempotencyKey(),
      });
      setAmount("");
      onClose();
    } catch (transferError) {
      setError(transferError?.message || "Unable to complete transfer.");
    } finally {
      setSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm lg:items-center lg:p-6">
      <div className="w-full max-w-md rounded-t-[28px] border border-white/10 bg-[#0b0f16] p-5 shadow-2xl lg:rounded-[28px]">
        <div className="mb-4 flex items-center justify-between">
          <div>
            <h2 className="text-lg font-semibold text-white">Transfer Funds</h2>
            <p className="mt-1 text-xs text-slate-500">Move assets between Funding and Unified Trading.</p>
          </div>
          <button onClick={onClose} className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-white/[0.04] text-slate-300">
            <X className="h-4 w-4" />
          </button>
        </div>

        {error ? <div className="mb-4 rounded-xl border border-rose-400/20 bg-rose-400/10 px-3 py-2 text-sm text-rose-100">{error}</div> : null}

        <form onSubmit={handleSubmit} className="space-y-4">
          <Field label="From Account">
            <select value={fromWallet} onChange={(event) => setFromWallet(event.target.value)} className="w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 py-3 text-white outline-none">
              {wallets.map((wallet) => <option key={wallet} value={wallet} disabled={wallet === toWallet}>{wallet === "unified_trading" ? "Unified Trading Account" : "Funding Account"}</option>)}
            </select>
          </Field>

          <div className="flex items-center justify-center">
            <button type="button" onClick={handleSwap} className="rounded-full border border-white/10 bg-white/[0.04] p-2 text-slate-300">
              <ArrowUpDown className="h-4 w-4" />
            </button>
          </div>

          <Field label="To Account">
            <select value={toWallet} onChange={(event) => setToWallet(event.target.value)} className="w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 py-3 text-white outline-none">
              {wallets.map((wallet) => <option key={wallet} value={wallet} disabled={wallet === fromWallet}>{wallet === "unified_trading" ? "Unified Trading Account" : "Funding Account"}</option>)}
            </select>
          </Field>

          <Field label="Asset">
            <div className="relative">
              <select value={asset} onChange={(event) => setAsset(event.target.value)} className="w-full appearance-none rounded-xl border border-white/10 bg-white/[0.04] px-3 py-3 pr-10 text-white outline-none">
                {(sourceAccountAssets.length ? sourceAccountAssets : assets).map((item) => <option key={item} value={item}>{item}</option>)}
              </select>
              <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
            </div>
          </Field>

          <Field label="Amount">
            <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-3">
              <div className="flex items-center gap-3">
                <input type="number" value={amount} onChange={(event) => setAmount(event.target.value)} placeholder="0.00" step="0.00000001" className="min-w-0 flex-1 bg-transparent text-lg font-semibold text-white outline-none placeholder:text-slate-500" />
                <button type="button" onClick={handleMax} className="rounded-full border border-amber-300/30 bg-amber-300/10 px-3 py-1 text-xs font-semibold text-amber-100">MAX</button>
                <span className="text-sm font-medium text-slate-300">{asset}</span>
              </div>
              <div className="mt-3 flex items-center justify-between text-xs text-slate-500">
                <span>{fromWallet === "funding" ? "Available" : "Transferable"}: {availableBalance} {asset}</span>
                <span>In use: {inUseBalance} {asset}</span>
              </div>
            </div>
          </Field>

          <button type="submit" disabled={submitting || !amount || fromWallet === toWallet} className="w-full rounded-xl bg-amber-300 px-4 py-3 text-sm font-semibold text-black disabled:opacity-60">
            {submitting ? "Transferring..." : "Transfer Now"}
          </button>
        </form>
      </div>
    </div>
  );
}

function Field({ label, children }) {
  return (
    <label className="block space-y-2">
      <span className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">{label}</span>
      {children}
    </label>
  );
}

export default TransferModal;
