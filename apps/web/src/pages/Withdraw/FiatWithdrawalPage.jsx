import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  BadgeCheck,
  Banknote,
  ChevronRight,
  Eye,
  EyeOff,
  Landmark,
  LoaderCircle,
  Search,
  ShieldCheck,
  Wallet,
  X,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import { createFiatWithdrawalApi } from "../../services/fiatWithdrawalApi";

const defaultForm = {
  country: "NG",
  currency: "NGN",
  source_account: "funding",
  bank_code: "",
  bank_name: "",
  account_number: "",
  account_name: "",
  amount: "",
  narration: "ExaEarn Withdrawal",
  beneficiary_id: "",
  save_beneficiary: true,
  is_default_beneficiary: false,
};

function FiatWithdrawalPage({ onBack }) {
  const { request } = useAuth();
  const api = useMemo(() => createFiatWithdrawalApi(request), [request]);
  const [meta, setMeta] = useState(null);
  const [form, setForm] = useState(defaultForm);
  const [beneficiaryMode, setBeneficiaryMode] = useState("new");
  const [beneficiaries, setBeneficiaries] = useState([]);
  const [banks, setBanks] = useState([]);
  const [bankSheetOpen, setBankSheetOpen] = useState(false);
  const [beneficiarySheetOpen, setBeneficiarySheetOpen] = useState(false);
  const [bankSearch, setBankSearch] = useState("");
  const [quote, setQuote] = useState(null);
  const [intent, setIntent] = useState(null);
  const [history, setHistory] = useState([]);
  const [screen, setScreen] = useState("form");
  const [loading, setLoading] = useState(true);
  const [resolving, setResolving] = useState(false);
  const [quoting, setQuoting] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [verificationOpen, setVerificationOpen] = useState(false);
  const [verificationMethod, setVerificationMethod] = useState("email");
  const [verificationCode, setVerificationCode] = useState("");
  const [verificationMeta, setVerificationMeta] = useState(null);
  const [hideBalance, setHideBalance] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const loadAll = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const nextMeta = await api.meta(form.currency);
      setMeta(nextMeta);
      setLoading(false);

      const [banksResult, beneficiariesResult, historyResult] = await Promise.allSettled([
        api.banks({ country: form.country, currency: form.currency }),
        api.beneficiaries(form.currency),
        api.history(),
      ]);

      if (banksResult.status === "fulfilled") setBanks(banksResult.value);
      if (beneficiariesResult.status === "fulfilled") setBeneficiaries(beneficiariesResult.value);
      if (historyResult.status === "fulfilled") setHistory(historyResult.value);

      if ([banksResult, beneficiariesResult, historyResult].some((result) => result.status === "rejected")) {
        setError("Fiat withdrawal loaded, but some saved data is temporarily unavailable. You can retry or continue with available options.");
      }
    } catch (loadError) {
      setError(loadError?.message || "Unable to load fiat withdrawal.");
    } finally {
      setLoading(false);
    }
  }, [api, form.country, form.currency]);

  useEffect(() => {
    void loadAll();
  }, [loadAll]);

  useEffect(() => {
    if (beneficiaryMode === "saved") return;
    const digits = form.account_number.replace(/\D/g, "");
    if (!form.bank_code || digits.length < 10) {
      setForm((current) => ({ ...current, account_name: "" }));
      return;
    }
    let ignore = false;
    const timer = window.setTimeout(async () => {
      setResolving(true);
      setError("");
      try {
        const resolved = await api.resolveAccount({
          country: form.country,
          currency: form.currency,
          bank_code: form.bank_code,
          account_number: digits,
        });
        if (!ignore) {
          setForm((current) => ({
            ...current,
            account_number: digits,
            account_name: resolved.account_name,
            bank_name: resolved.bank?.name || current.bank_name,
          }));
        }
      } catch (resolveError) {
        if (!ignore) {
          setForm((current) => ({ ...current, account_name: "" }));
          setError(resolveError?.message || "Unable to verify account.");
        }
      } finally {
        if (!ignore) setResolving(false);
      }
    }, 450);
    return () => {
      ignore = true;
      window.clearTimeout(timer);
    };
  }, [api, beneficiaryMode, form.account_number, form.bank_code, form.country, form.currency]);

  useEffect(() => {
    if (!form.amount || Number(form.amount) <= 0) {
      setQuote(null);
      return;
    }
    let ignore = false;
    const timer = window.setTimeout(async () => {
      setQuoting(true);
      try {
        const nextQuote = await api.quote({
          source_account: form.source_account,
          currency: form.currency,
          amount: form.amount,
        });
        if (!ignore) setQuote(nextQuote);
      } catch (quoteError) {
        if (!ignore) {
          setQuote(null);
          setError(quoteError?.message || "Unable to calculate withdrawal quote.");
        }
      } finally {
        if (!ignore) setQuoting(false);
      }
    }, 350);
    return () => {
      ignore = true;
      window.clearTimeout(timer);
    };
  }, [api, form.amount, form.currency, form.source_account]);

  const selectedSource = useMemo(() => {
    const accounts = Array.isArray(meta?.source_accounts) ? meta.source_accounts : [];
    return accounts.find((account) => account.key === form.source_account) || accounts[0] || null;
  }, [form.source_account, meta]);

  const filteredBanks = useMemo(() => {
    const needle = bankSearch.trim().toLowerCase();
    return banks.filter((bank) => !needle || bank.name.toLowerCase().includes(needle));
  }, [bankSearch, banks]);

  const amountAvailable = selectedSource?.available || meta?.balance?.available || "0";
  const canReview = Boolean(form.currency && form.amount && Number(form.amount) > 0 && quote && form.account_name && (form.bank_code || form.beneficiary_id));

  const setField = (key, value) => {
    setError("");
    setForm((current) => ({ ...current, [key]: value }));
  };

  const chooseBank = (bank) => {
    setForm((current) => ({ ...current, bank_code: bank.code, bank_name: bank.name, account_name: "", beneficiary_id: "" }));
    setBankSheetOpen(false);
  };

  const chooseBeneficiary = (beneficiary) => {
    setForm((current) => ({
      ...current,
      beneficiary_id: beneficiary.id,
      bank_code: "__saved__",
      bank_name: beneficiary.bank_name,
      account_number: beneficiary.masked_account_number,
      account_name: beneficiary.account_name,
      currency: beneficiary.currency,
    }));
    setBeneficiaryMode("saved");
    setBeneficiarySheetOpen(false);
  };

  const useMax = () => {
    setField("amount", String(Number(amountAvailable || 0)));
  };

  const reviewWithdrawal = async () => {
    setSubmitting(true);
    setError("");
    try {
      const created = await api.createIntent({
        ...form,
        bank_code: form.bank_code === "__saved__" ? undefined : form.bank_code,
        account_number: form.beneficiary_id ? undefined : form.account_number,
        beneficiary_id: form.beneficiary_id || undefined,
        save_beneficiary: beneficiaryMode === "new" && form.save_beneficiary,
      }, crypto.randomUUID());
      setIntent(created);
      setScreen("review");
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (reviewError) {
      setError(reviewError?.message || "Unable to prepare withdrawal review.");
    } finally {
      setSubmitting(false);
    }
  };

  const openVerification = async () => {
    if (!intent?.uuid) return;
    setSubmitting(true);
    setError("");
    try {
      const challenge = await api.challenge(intent.uuid, verificationMethod);
      setVerificationMeta(challenge);
      setVerificationOpen(true);
    } catch (challengeError) {
      setError(challengeError?.message || "Unable to start verification.");
    } finally {
      setSubmitting(false);
    }
  };

  const confirmVerification = async () => {
    if (!intent?.uuid) return;
    setSubmitting(true);
    setError("");
    try {
      const updated = await api.verify(intent.uuid, { method: verificationMethod, code: verificationCode });
      setIntent(updated);
      setVerificationOpen(false);
      setScreen("submitted");
      setSuccess("Withdrawal submitted.");
      await loadAll();
    } catch (verifyError) {
      setError(verifyError?.message || "Unable to verify withdrawal.");
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return <Shell onBack={onBack}><div className="rounded-2xl border border-white/8 bg-white/[0.03] p-6 text-sm text-slate-300">Loading fiat withdrawal...</div></Shell>;
  }

  return (
    <Shell onBack={onBack}>
      {screen === "form" ? (
        <div className="grid gap-4 lg:grid-cols-[1fr_360px]">
          <section className="min-w-0 space-y-4 pb-28">
            <BalanceCard meta={meta} hideBalance={hideBalance} setHideBalance={setHideBalance} onHistory={() => document.getElementById("fiat-withdraw-history")?.scrollIntoView({ behavior: "smooth" })} />

            <Panel title="Withdraw From" icon={Wallet}>
              <div className="grid gap-3 sm:grid-cols-2">
                {(meta?.source_accounts || []).map((account) => (
                  <button key={account.key} type="button" onClick={() => setField("source_account", account.key)} className={`rounded-2xl border p-4 text-left ${form.source_account === account.key ? "border-[#d1ab55]/60 bg-[#d1ab55]/10" : "border-white/8 bg-black/20"}`}>
                    <p className="text-sm font-semibold text-white">{account.label}</p>
                    <p className="mt-2 text-xs text-slate-400">Available</p>
                    <p className="mt-1 font-mono text-sm text-[#f4d37d]">{formatMoney(account.available, account.currency)}</p>
                  </button>
                ))}
              </div>
            </Panel>

            <Panel title="Recipient" icon={Landmark} action={<button type="button" onClick={() => setBeneficiarySheetOpen(true)} className="text-xs font-semibold text-[#f4d37d]">Saved Beneficiaries</button>}>
              <div className="grid grid-cols-2 gap-2 rounded-2xl border border-white/8 bg-black/20 p-1">
                <ToggleButton active={beneficiaryMode === "new"} onClick={() => { setBeneficiaryMode("new"); setField("beneficiary_id", ""); }}>New Beneficiary</ToggleButton>
                <ToggleButton active={beneficiaryMode === "saved"} onClick={() => setBeneficiarySheetOpen(true)}>Saved Beneficiary</ToggleButton>
              </div>

              {beneficiaryMode === "new" ? (
                <div className="mt-4 grid gap-3">
                  <div className="grid grid-cols-2 gap-3">
                    <Input label="Country" value={form.country} onChange={(value) => setField("country", value.toUpperCase())} />
                    <Input label="Currency" value={form.currency} onChange={(value) => setField("currency", value.toUpperCase())} />
                  </div>
                  <button type="button" onClick={() => setBankSheetOpen(true)} className="flex min-h-[52px] items-center justify-between rounded-2xl border border-white/8 bg-black/20 px-4 text-left text-sm text-white">
                    <span>{form.bank_name || "Search Bank"}</span>
                    <Search className="h-4 w-4 text-slate-500" />
                  </button>
                  <Input label="Account Number" value={form.account_number} onChange={(value) => setField("account_number", value.replace(/\D/g, "").slice(0, 10))} inputMode="numeric" placeholder="10 digit account number" />
                  <div className="rounded-2xl border border-white/8 bg-black/20 px-4 py-3">
                    <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Verified Account Name</p>
                    <p className="mt-1 flex items-center gap-2 text-sm font-semibold text-white">
                      {resolving ? <LoaderCircle className="h-4 w-4 animate-spin text-[#f4d37d]" /> : form.account_name ? <BadgeCheck className="h-4 w-4 text-emerald-300" /> : null}
                      {resolving ? "Verifying account..." : form.account_name || "Account name will appear after verification"}
                    </p>
                  </div>
                </div>
              ) : (
                <div className="mt-4 rounded-2xl border border-white/8 bg-black/20 p-4">
                  <p className="text-sm font-semibold text-white">{form.account_name || "No saved beneficiary selected"}</p>
                  <p className="mt-1 text-xs text-slate-400">{form.bank_name || "Choose from saved beneficiaries"} {form.account_number ? `- ${form.account_number}` : ""}</p>
                </div>
              )}
            </Panel>

            <Panel title="Withdrawal Amount" icon={Banknote}>
              <Input label="Amount" value={form.amount} onChange={(value) => setField("amount", value)} inputMode="decimal" right={<button type="button" onClick={useMax} className="rounded-full border border-[#d1ab55]/40 px-3 py-1 text-xs font-semibold text-[#f4d37d]">MAX</button>} />
              <p className="mt-2 text-xs text-slate-500">Available: {formatMoney(amountAvailable, form.currency)}</p>
              <div className="mt-4 grid gap-2 rounded-2xl border border-white/8 bg-black/20 p-4 text-sm">
                <Line label="Fee" value={quoting ? "Calculating..." : formatMoney(quote?.fee, form.currency)} />
                <Line label="Recipient receives" value={formatMoney(quote?.recipient_receives, form.currency)} />
                <Line label="Estimated arrival" value={quote?.estimated_arrival || "--"} />
                <Line label="Minimum withdrawal" value={formatMoney(quote?.minimum || meta?.limits?.minimum, form.currency)} />
                <Line label="Maximum withdrawal" value={formatMoney(quote?.maximum || meta?.limits?.maximum, form.currency)} />
                <Line label="Daily remaining limit" value={formatMoney(quote?.daily_remaining_limit || meta?.limits?.remaining_daily, form.currency)} />
              </div>
              <Input label="Narration" value={form.narration} onChange={(value) => setField("narration", value)} placeholder="Add a transfer description" />
            </Panel>

            <Panel title="Withdrawal Summary" icon={ShieldCheck}>
              <Line label="Source Account" value={selectedSource?.label || "Funding Wallet"} />
              <Line label="Recipient" value={form.account_name || "--"} />
              <Line label="Bank" value={form.bank_name || "--"} />
              <Line label="Account Number" value={form.account_number ? maskAccount(form.account_number) : "--"} />
              <Line label="Currency" value={form.currency} />
              <Line label="Amount" value={formatMoney(quote?.amount || form.amount, form.currency)} />
              <Line label="Fee" value={formatMoney(quote?.fee, form.currency)} />
              <Line label="Recipient Receives" value={formatMoney(quote?.recipient_receives, form.currency)} />
              <Line label="Estimated Arrival" value={quote?.estimated_arrival || "--"} />
            </Panel>

            {error ? <Banner tone="error">{error}</Banner> : null}
            {success ? <Banner>{success}</Banner> : null}
            <button type="button" onClick={reviewWithdrawal} disabled={!canReview || submitting} className="hidden h-12 w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#f7df8f] via-[#d1ab55] to-[#ad832a] text-sm font-semibold text-[#1d1608] shadow-[0_14px_34px_rgba(209,171,85,.26)] disabled:cursor-not-allowed disabled:opacity-45 lg:flex">
              {submitting ? <LoaderCircle className="h-4 w-4 animate-spin" /> : null} Review Withdrawal
            </button>
          </section>

          <aside id="fiat-withdraw-history" className="min-w-0 space-y-3">
            <Panel title="Withdraw History" icon={Banknote}>
              <div className="space-y-2">
                {history.length ? history.slice(0, 8).map((item) => (
                  <div key={item.reference} className="rounded-2xl border border-white/8 bg-black/20 p-3">
                    <div className="flex items-center justify-between gap-3">
                      <p className="text-sm font-semibold text-white">{formatMoney(item.amount, item.currency)}</p>
                      <span className="rounded-full border border-white/10 px-2 py-1 text-[11px] text-slate-300">{statusLabel(item.status)}</span>
                    </div>
                    <p className="mt-1 text-xs text-slate-500">{item.bank} - {item.masked_account_number}</p>
                  </div>
                )) : <p className="text-sm text-slate-500">No fiat withdrawal history yet.</p>}
              </div>
            </Panel>
          </aside>

          <StickyAction disabled={!canReview || submitting} onClick={reviewWithdrawal}>
            {submitting ? <LoaderCircle className="h-4 w-4 animate-spin" /> : null} Review Withdrawal
          </StickyAction>
        </div>
      ) : null}

      {screen === "review" && intent ? (
        <ReviewScreen intent={intent} onEdit={() => setScreen("form")} onConfirm={openVerification} submitting={submitting} error={error} />
      ) : null}

      {screen === "submitted" && intent ? (
        <SubmittedScreen intent={intent} onBack={onBack} />
      ) : null}

      {bankSheetOpen ? <BankSheet banks={filteredBanks} search={bankSearch} setSearch={setBankSearch} onClose={() => setBankSheetOpen(false)} onChoose={chooseBank} /> : null}
      {beneficiarySheetOpen ? <BeneficiarySheet beneficiaries={beneficiaries} onClose={() => setBeneficiarySheetOpen(false)} onChoose={chooseBeneficiary} /> : null}
      {verificationOpen ? (
        <VerificationModal
          method={verificationMethod}
          setMethod={setVerificationMethod}
          code={verificationCode}
          setCode={setVerificationCode}
          meta={verificationMeta}
          intent={intent}
          methods={meta?.verification_methods || []}
          onCancel={() => setVerificationOpen(false)}
          onConfirm={confirmVerification}
          submitting={submitting}
          error={error}
        />
      ) : null}
    </Shell>
  );
}

function Shell({ children, onBack }) {
  return (
    <main className="min-h-[100dvh] bg-[#05070c] px-3 pb-[calc(88px+env(safe-area-inset-bottom))] pt-3 text-slate-100 sm:px-4 lg:px-6">
      <div className="mx-auto w-full max-w-[1280px]">
        <header className="mb-4 rounded-2xl border border-white/8 bg-[#0b0f18] p-4">
          <button type="button" onClick={onBack} className="inline-flex items-center gap-2 text-sm font-semibold text-slate-300 hover:text-[#f4d37d]">
            <ArrowLeft className="h-4 w-4" /> Back
          </button>
          <h1 className="mt-3 text-2xl font-semibold tracking-tight text-white">Fiat Withdrawal</h1>
          <p className="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Withdraw funds from your ExaEarn account directly to your bank account or supported payout destination.</p>
        </header>
        {children}
      </div>
    </main>
  );
}

function BalanceCard({ meta, hideBalance, setHideBalance, onHistory }) {
  const balance = meta?.balance || {};
  return (
    <section className="rounded-2xl border border-[#d1ab55]/25 bg-gradient-to-br from-[#171109] via-[#0b0f18] to-[#05070c] p-4 shadow-[0_20px_50px_rgba(0,0,0,.35)]">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs uppercase tracking-[0.18em] text-[#f4d37d]/75">Available Balance</p>
          <p className="mt-2 font-mono text-3xl font-semibold text-white">{hideBalance ? "â€¢â€¢â€¢â€¢â€¢â€¢" : formatMoney(balance.available, balance.currency)}</p>
          <p className="mt-1 text-xs text-slate-400">Equivalent Local Currency: {hideBalance ? "â€¢â€¢â€¢â€¢" : formatMoney(balance.local_equivalent, balance.local_currency)}</p>
        </div>
        <div className="flex gap-2">
          <button type="button" onClick={() => setHideBalance((value) => !value)} className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/[0.04]" aria-label="Hide or show balance">{hideBalance ? <Eye className="h-4 w-4" /> : <EyeOff className="h-4 w-4" />}</button>
          <button type="button" onClick={onHistory} className="rounded-full border border-[#d1ab55]/30 px-4 text-xs font-semibold text-[#f4d37d]">Withdraw History</button>
        </div>
      </div>
      <div className="mt-4 grid gap-2 text-sm sm:grid-cols-4">
        <MiniStat label="Funding Account" value={hideBalance ? "â€¢â€¢â€¢â€¢" : formatMoney(balance.available, balance.currency)} />
        <MiniStat label="Pending Withdrawals" value={hideBalance ? "â€¢â€¢â€¢â€¢" : formatMoney(balance.pending_withdrawals, balance.currency)} />
        <MiniStat label="Daily Withdrawal Limit" value={hideBalance ? "â€¢â€¢â€¢â€¢" : formatMoney(balance.daily_limit, balance.currency)} />
        <MiniStat label="Remaining Daily Limit" value={hideBalance ? "â€¢â€¢â€¢â€¢" : formatMoney(balance.remaining_daily_limit, balance.currency)} />
      </div>
    </section>
  );
}

function ReviewScreen({ intent, onEdit, onConfirm, submitting, error }) {
  return (
    <section className="mx-auto max-w-2xl space-y-4 pb-28">
      <Panel title="Review Withdrawal" icon={ShieldCheck}>
        <Line label="Recipient Name" value={intent.recipient} />
        <Line label="Bank" value={intent.bank} />
        <Line label="Account Number" value={intent.masked_account_number} />
        <Line label="Amount" value={formatMoney(intent.amount, intent.currency)} />
        <Line label="Fee" value={formatMoney(intent.fee, intent.currency)} />
        <Line label="Narration" value={intent.narration} />
        <Line label="Estimated arrival" value={intent.estimated_arrival || "--"} />
        <Line label="Remaining balance after withdrawal" value={formatMoney(intent.remaining_balance_after, intent.currency)} />
        <div className="mt-4 rounded-2xl border border-amber-300/20 bg-amber-400/10 p-4 text-xs leading-5 text-amber-100">Bank transfers cannot always be reversed after processing. Review the recipient and amount carefully before confirming.</div>
        {error ? <Banner tone="error">{error}</Banner> : null}
      </Panel>
      <div className="fixed inset-x-0 bottom-0 z-20 border-t border-white/8 bg-[#05070c]/95 p-3 backdrop-blur lg:static lg:border-0 lg:bg-transparent lg:p-0">
        <div className="mx-auto flex max-w-2xl gap-3">
          <button type="button" onClick={onEdit} className="h-12 flex-1 rounded-2xl border border-white/12 text-sm font-semibold text-white">Edit</button>
          <button type="button" onClick={onConfirm} disabled={submitting} className="h-12 flex-1 rounded-2xl bg-gradient-to-r from-[#f7df8f] via-[#d1ab55] to-[#ad832a] text-sm font-semibold text-[#1d1608] disabled:opacity-50">{submitting ? "Preparing..." : "Confirm Withdrawal"}</button>
        </div>
      </div>
    </section>
  );
}

function SubmittedScreen({ intent, onBack }) {
  return (
    <section className="mx-auto max-w-2xl space-y-4">
      <Panel title="Withdrawal Submitted" icon={BadgeCheck}>
        <Line label="Reference" value={intent.reference} />
        <Line label="Status" value={statusLabel(intent.status)} />
        <Line label="Estimated arrival" value={intent.estimated_arrival || "--"} />
        <Line label="Recipient" value={intent.recipient} />
        <Line label="Amount" value={formatMoney(intent.amount, intent.currency)} />
        <Line label="Fee" value={formatMoney(intent.fee, intent.currency)} />
        <Line label="Bank" value={intent.bank} />
        <p className="mt-4 rounded-2xl border border-white/8 bg-black/20 p-4 text-xs leading-5 text-slate-400">Your withdrawal is processing. ExaEarn will mark it successful only after provider confirmation.</p>
        <button type="button" className="mt-4 h-12 w-full rounded-2xl border border-white/12 text-sm font-semibold text-white">View Details</button>
        <button type="button" onClick={onBack} className="mt-3 h-12 w-full rounded-2xl bg-gradient-to-r from-[#f7df8f] via-[#d1ab55] to-[#ad832a] text-sm font-semibold text-[#1d1608]">Back to Wallet</button>
      </Panel>
    </section>
  );
}

function VerificationModal({ method, setMethod, code, setCode, meta, intent, methods, onCancel, onConfirm, submitting, error }) {
  return (
    <div className="fixed inset-0 z-50 flex items-end bg-black/70 p-3 backdrop-blur sm:items-center sm:justify-center">
      <div className="w-full max-w-md rounded-3xl border border-white/10 bg-[#0b0f18] p-4 shadow-2xl">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold text-white">Verify Withdrawal</h2>
            <p className="mt-1 text-xs text-slate-400">{formatMoney(intent?.amount, intent?.currency)} to {intent?.recipient} - {intent?.bank}</p>
          </div>
          <button type="button" onClick={onCancel} className="rounded-full border border-white/10 p-2"><X className="h-4 w-4" /></button>
        </div>
        <div className="mt-4 grid gap-2">
          {methods.filter((item) => item.enabled).map((item) => (
            <button key={item.key} type="button" onClick={() => setMethod(item.key)} className={`rounded-2xl border px-4 py-3 text-left text-sm ${method === item.key ? "border-[#d1ab55]/60 bg-[#d1ab55]/10 text-[#f4d37d]" : "border-white/8 bg-black/20 text-white"}`}>{item.label}</button>
          ))}
        </div>
        <Input label="6 digit code" value={code} onChange={(value) => setCode(value.replace(/\D/g, "").slice(0, 6))} inputMode="numeric" placeholder="000000" />
        {meta?.development_code ? <p className="mt-2 text-xs text-amber-200">Development OTP: {meta.development_code}</p> : null}
        <p className="mt-2 text-xs text-slate-500">Code expires at {meta?.expires_at ? new Date(meta.expires_at).toLocaleTimeString() : "--"}. Resend becomes available after the countdown.</p>
        {error ? <Banner tone="error">{error}</Banner> : null}
        <div className="mt-4 grid grid-cols-2 gap-3">
          <button type="button" onClick={onCancel} className="h-12 rounded-2xl border border-white/12 text-sm font-semibold text-white">Cancel</button>
          <button type="button" onClick={onConfirm} disabled={submitting || code.length !== 6} className="h-12 rounded-2xl bg-gradient-to-r from-[#f7df8f] via-[#d1ab55] to-[#ad832a] text-sm font-semibold text-[#1d1608] disabled:opacity-50">{submitting ? "Confirming..." : "Confirm"}</button>
        </div>
      </div>
    </div>
  );
}

function BankSheet({ banks, search, setSearch, onClose, onChoose }) {
  return (
    <Sheet title="Search Bank" onClose={onClose}>
      <div className="mb-3 flex items-center gap-2 rounded-2xl border border-white/8 bg-black/20 px-4 py-3"><Search className="h-4 w-4 text-slate-500" /><input value={search} onChange={(event) => setSearch(event.target.value)} className="w-full bg-transparent text-sm text-white outline-none" placeholder="Search..." /></div>
      <div className="max-h-[52dvh] space-y-2 overflow-y-auto">
        {banks.map((bank) => <button key={bank.code} type="button" onClick={() => onChoose(bank)} className="flex w-full items-center justify-between rounded-2xl border border-white/8 bg-black/20 px-4 py-3 text-left text-sm text-white"><span>{bank.name}</span><ChevronRight className="h-4 w-4 text-slate-500" /></button>)}
      </div>
    </Sheet>
  );
}

function BeneficiarySheet({ beneficiaries, onClose, onChoose }) {
  return (
    <Sheet title="Saved Beneficiaries" onClose={onClose}>
      <div className="max-h-[56dvh] space-y-2 overflow-y-auto">
        {beneficiaries.length ? beneficiaries.map((item) => (
          <button key={item.id} type="button" onClick={() => onChoose(item)} className="w-full rounded-2xl border border-white/8 bg-black/20 p-4 text-left">
            <p className="text-sm font-semibold text-white">{item.account_name}</p>
            <p className="mt-1 text-xs text-slate-400">{item.bank_name} - {item.masked_account_number} - {item.currency}</p>
          </button>
        )) : <p className="rounded-2xl border border-white/8 bg-black/20 p-4 text-sm text-slate-400">No saved beneficiary yet. Add a new beneficiary on the withdrawal form.</p>}
      </div>
    </Sheet>
  );
}

function Sheet({ title, children, onClose }) {
  return (
    <div className="fixed inset-0 z-40 flex items-end bg-black/70 p-3 backdrop-blur sm:items-center sm:justify-center">
      <div className="w-full max-w-lg rounded-3xl border border-white/10 bg-[#0b0f18] p-4 shadow-2xl">
        <div className="mb-4 flex items-center justify-between"><h2 className="text-lg font-semibold text-white">{title}</h2><button type="button" onClick={onClose} className="rounded-full border border-white/10 p-2"><X className="h-4 w-4" /></button></div>
        {children}
      </div>
    </div>
  );
}

function Panel({ title, icon: Icon, children, action }) {
  return (
    <section className="rounded-2xl border border-white/8 bg-[#0b0f18] p-4">
      <div className="mb-4 flex items-center justify-between gap-3">
        <div className="flex items-center gap-2"><Icon className="h-4 w-4 text-[#f4d37d]" /><h2 className="text-sm font-semibold uppercase tracking-[0.12em] text-slate-300">{title}</h2></div>
        {action}
      </div>
      {children}
    </section>
  );
}

function Input({ label, value, onChange, placeholder, inputMode, right }) {
  return (
    <label className="mt-3 block">
      <span className="mb-2 block text-xs uppercase tracking-[0.14em] text-slate-500">{label}</span>
      <div className="flex min-h-[52px] items-center gap-2 rounded-2xl border border-white/8 bg-black/20 px-4">
        <input value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} inputMode={inputMode} className="w-full bg-transparent text-sm text-white outline-none placeholder:text-slate-500" />
        {right}
      </div>
    </label>
  );
}

function ToggleButton({ active, onClick, children }) {
  return <button type="button" onClick={onClick} className={`h-10 rounded-xl text-xs font-semibold ${active ? "bg-[#d1ab55] text-[#1d1608]" : "text-slate-400"}`}>{children}</button>;
}

function StickyAction({ disabled, onClick, children }) {
  return (
    <div className="fixed inset-x-0 bottom-0 z-20 border-t border-white/8 bg-[#05070c]/95 p-3 backdrop-blur lg:hidden">
      <button type="button" onClick={onClick} disabled={disabled} className="mx-auto flex h-12 w-full max-w-xl items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#f7df8f] via-[#d1ab55] to-[#ad832a] text-sm font-semibold text-[#1d1608] shadow-[0_14px_34px_rgba(209,171,85,.26)] disabled:cursor-not-allowed disabled:opacity-45">{children}</button>
    </div>
  );
}

function Line({ label, value }) {
  return <div className="flex items-center justify-between gap-3 py-1.5 text-sm"><span className="text-slate-500">{label}</span><span className="min-w-0 truncate text-right font-medium text-slate-100">{value || "--"}</span></div>;
}

function MiniStat({ label, value }) {
  return <div className="rounded-2xl border border-white/8 bg-black/20 p-3"><p className="text-xs text-slate-500">{label}</p><p className="mt-1 truncate font-mono text-sm text-white">{value}</p></div>;
}

function Banner({ children, tone = "success" }) {
  const classes = tone === "error" ? "border-rose-300/25 bg-rose-500/10 text-rose-100" : "border-emerald-300/20 bg-emerald-500/10 text-emerald-100";
  return <div className={`mt-3 rounded-2xl border p-3 text-sm ${classes}`}>{children}</div>;
}

function formatMoney(value, currency = "USD") {
  if (value === undefined || value === null || value === "") return "--";
  const number = Number(value);
  if (!Number.isFinite(number)) return "--";
  return `${currency || ""} ${number.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`.trim();
}

function maskAccount(value) {
  const digits = String(value || "").replace(/\D/g, "");
  if (digits.length <= 4) return digits || "--";
  return `${"*".repeat(Math.max(digits.length - 4, 2))}${digits.slice(-4)}`;
}

function statusLabel(status) {
  return String(status || "pending").replace(/_/g, " ").replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export default FiatWithdrawalPage;


