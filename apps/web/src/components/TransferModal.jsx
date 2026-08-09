import { useEffect, useMemo, useState } from "react";
import { ArrowUpDown, ChevronDown, X } from "lucide-react";
import { ExaButton, ExaField } from "./ui";
import { useLanguage } from "../context/LanguageContext.jsx";

function createIdempotencyKey() {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") return crypto.randomUUID();
  return `exa-transfer-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function TransferModal({ isOpen, onClose, onTransfer, assets = [], balances = [] }) {
  const { t } = useLanguage();
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
    if (options.length && !options.includes(asset)) setAsset(options[0]);
  }, [asset, assets, sourceAccountAssets]);

  useEffect(() => {
    if (fromWallet === toWallet && wallets.length > 1) setToWallet(wallets.find((wallet) => wallet !== fromWallet) || wallets[0]);
  }, [fromWallet, toWallet, wallets]);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setSubmitting(true);
    setError("");
    try {
      await onTransfer({ from_account: fromWallet, to_account: toWallet, asset, amount, idempotency_key: createIdempotencyKey() });
      setAmount("");
      onClose();
    } catch (transferError) {
      setError(transferError?.message || t("transfer.unableToComplete"));
    } finally {
      setSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-0 backdrop-blur-sm lg:items-center lg:p-6">
      <div className="w-full max-w-md rounded-t-[28px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-5 shadow-[var(--exa-shadow-md)] lg:rounded-[28px]">
        <div className="mb-5 flex items-start justify-between gap-4">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--exa-gold-light)]/80">{t("transfer.eyebrow")}</p>
            <h2 className="mt-1 text-xl font-semibold text-[var(--exa-text-primary)]">{t("transfer.title")}</h2>
            <p className="mt-1 text-xs text-[var(--exa-text-muted)]">{t("transfer.subtitle")}</p>
          </div>
          <button type="button" onClick={onClose} className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[var(--exa-border)] bg-white/[0.04] text-[var(--exa-text-secondary)] exa-focusable" aria-label={t("transfer.close")}>
            <X className="h-4 w-4" />
          </button>
        </div>

        {error ? <div className="mb-4 rounded-xl border border-rose-400/20 bg-rose-400/10 px-3 py-2 text-sm text-rose-100">{error}</div> : null}

        <form onSubmit={handleSubmit} className="space-y-4">
          <ExaField label={t("transfer.fromAccount")}>
            <select value={fromWallet} onChange={(event) => setFromWallet(event.target.value)} className="w-full bg-transparent text-sm text-[var(--exa-text-primary)] outline-none">
              {wallets.map((wallet) => <option key={wallet} value={wallet} disabled={wallet === toWallet}>{wallet === "unified_trading" ? t("transfer.unifiedTradingAccount") : t("transfer.fundingAccount")}</option>)}
            </select>
          </ExaField>

          <div className="flex items-center justify-center">
            <button type="button" onClick={() => { const previousFrom = fromWallet; setFromWallet(toWallet); setToWallet(previousFrom); }} className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)] transition hover:rotate-180 exa-focusable" aria-label={t("transfer.swapDirection")}>
              <ArrowUpDown className="h-4 w-4" />
            </button>
          </div>

          <ExaField label={t("transfer.toAccount")}>
            <select value={toWallet} onChange={(event) => setToWallet(event.target.value)} className="w-full bg-transparent text-sm text-[var(--exa-text-primary)] outline-none">
              {wallets.map((wallet) => <option key={wallet} value={wallet} disabled={wallet === fromWallet}>{wallet === "unified_trading" ? t("transfer.unifiedTradingAccount") : t("transfer.fundingAccount")}</option>)}
            </select>
          </ExaField>

          <ExaField label={t("transfer.asset")} suffix={<ChevronDown className="h-4 w-4" />}>
            <select value={asset} onChange={(event) => setAsset(event.target.value)} className="w-full appearance-none bg-transparent text-sm text-[var(--exa-text-primary)] outline-none">
              {(sourceAccountAssets.length ? sourceAccountAssets : assets).map((item) => <option key={item} value={item}>{item}</option>)}
            </select>
          </ExaField>

          <label className="block space-y-2">
            <span className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--exa-gold-light)]/80">{t("transfer.amount")}</span>
            <div className="rounded-2xl border border-[var(--exa-border-subtle)] bg-white/[0.035] p-3 transition focus-within:border-[var(--exa-border-active)] focus-within:shadow-[var(--exa-focus-ring)]">
              <div className="flex items-center gap-3">
                <input type="number" value={amount} onChange={(event) => setAmount(event.target.value)} placeholder="0.00" step="0.00000001" className="min-w-0 flex-1 bg-transparent text-lg font-semibold text-[var(--exa-text-primary)] outline-none placeholder:text-[var(--exa-text-disabled)]" />
                <button type="button" onClick={() => setAmount(String(availableBalance || "0"))} className="rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-3 py-1 text-xs font-semibold text-[var(--exa-gold-light)] exa-focusable">{t("transfer.max")}</button>
                <span className="text-sm font-medium text-[var(--exa-text-secondary)]">{asset}</span>
              </div>
              <div className="mt-3 flex items-center justify-between gap-3 text-xs text-[var(--exa-text-muted)]">
                <span>{fromWallet === "funding" ? t("transfer.available") : t("transfer.transferable")}: {availableBalance} {asset}</span>
                <span>{t("transfer.inUse")}: {inUseBalance} {asset}</span>
              </div>
            </div>
          </label>

          <ExaButton type="submit" loading={submitting} disabled={submitting || !amount || fromWallet === toWallet} className="w-full">
            {submitting ? t("transfer.transferring") : t("transfer.transferNow")}
          </ExaButton>
        </form>
      </div>
    </div>
  );
}

export default TransferModal;