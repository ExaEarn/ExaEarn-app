import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, Check, Info, LocateFixed, RefreshCw } from "lucide-react";

const currencyOptions = [
  { code: "NGN", name: "Nigerian Naira", flag: "NGN", type: "fiat" },
  { code: "USD", name: "US Dollar", flag: "USD", type: "fiat" },
  { code: "EUR", name: "Euro", flag: "EUR", type: "fiat" },
  { code: "GBP", name: "British Pound", flag: "GBP", type: "fiat" },
  { code: "CAD", name: "Canadian Dollar", flag: "CAD", type: "fiat" },
  { code: "AED", name: "UAE Dirham", flag: "AED", type: "fiat" },
  { code: "USDT", name: "Tether", flag: "USDT", type: "crypto" },
  { code: "EXA", name: "ExaEarn Token", flag: "EXA", type: "crypto" },
];

const storageKey = "exaearn-payment-currency";

function PaymentCurrencyPage({ onBack }) {
  const [selected, setSelected] = useState("USD");
  const [saved, setSaved] = useState("USD");
  const [autoDetect, setAutoDetect] = useState(true);
  const [savedAutoDetect, setSavedAutoDetect] = useState(true);
  const [toast, setToast] = useState("");
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    try {
      const raw = localStorage.getItem(storageKey);
      if (raw) {
        const parsed = JSON.parse(raw);
        const code = parsed.code || "USD";
        const detect = typeof parsed.autoDetect === "boolean" ? parsed.autoDetect : true;
        setSelected(code);
        setSaved(code);
        setAutoDetect(detect);
        setSavedAutoDetect(detect);
      }
    } catch (error) {
      console.error("Unable to load payment currency", error);
    }
  }, []);

  const selectedMeta = useMemo(() => currencyOptions.find((item) => item.code === selected) || currencyOptions[1], [selected]);
  const hasChanges = selected !== saved || autoDetect !== savedAutoDetect;

  const savePreference = async () => {
    if (!hasChanges || saving) return;
    setSaving(true);
    await new Promise((resolve) => setTimeout(resolve, 650));
    localStorage.setItem(storageKey, JSON.stringify({ code: selected, autoDetect }));
    setSaved(selected);
    setSavedAutoDetect(autoDetect);
    setSaving(false);
    setToast("Payment currency updated successfully.");
    setTimeout(() => setToast(""), 2200);
  };

  return (
    <main className="relative min-h-screen bg-[var(--exa-bg-primary)] text-white">
      <div className="pointer-events-none absolute inset-0 opacity-25 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.2)_1px,transparent_0)] [background-size:24px_24px]" />
      <div className="pointer-events-none absolute inset-0 opacity-20 [background-image:linear-gradient(rgba(138,43,226,.17)_1px,transparent_1px),linear-gradient(90deg,rgba(0,229,255,.12)_1px,transparent_1px)] [background-size:72px_72px]" />

      <header
        className="sticky top-0 z-30 border-b border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-3xl px-4 pb-3 pt-3 sm:px-6">
          <div className="flex items-center justify-between">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] p-2 text-[var(--exa-text-secondary)] shadow-[var(--exa-shadow-soft)]"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <h1 className="text-lg font-semibold text-[var(--exa-text-primary)]">Payment Currency</h1>
            <button
              type="button"
              className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] p-2 text-[var(--exa-text-secondary)] shadow-[var(--exa-shadow-soft)]"
            >
              <Info className="h-4 w-4" />
            </button>
          </div>
          <div className="mt-3 h-px w-full bg-gradient-to-r from-transparent via-[#8A2BE2]/70 to-transparent" />
        </div>
      </header>

      <section
        className="relative mx-auto w-full max-w-3xl space-y-4 px-4 pb-28 pt-4 sm:px-6"
        style={{ paddingBottom: "calc(96px + env(safe-area-inset-bottom))" }}
      >
        <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)] backdrop-blur">
          <p className="text-xs uppercase tracking-[0.12em] text-[var(--exa-text-muted)]">Current Payment Currency</p>
          <div className="mt-2 flex items-center justify-between gap-3">
            <div>
              <p className="text-base font-semibold text-[var(--exa-text-primary)]">
                {selectedMeta.flag} {selectedMeta.code} - {selectedMeta.name}
              </p>
              <p className="mt-1 text-xs text-[var(--exa-text-muted)]">
                This currency will be used for payments, subscriptions, and course purchases.
              </p>
            </div>
            <span className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)] shadow-[var(--exa-shadow-gold)]">
              <Check className="h-4 w-4" />
            </span>
          </div>
        </article>

        <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)]">
          <button
            type="button"
            onClick={() => setAutoDetect((prev) => !prev)}
            className="mb-3 flex w-full items-center justify-between rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2.5 text-left"
          >
            <div>
              <p className="text-sm font-medium text-[var(--exa-text-secondary)]">Auto Detect Currency</p>
              <p className="text-xs text-[var(--exa-text-muted)]">Automatically detect currency based on your region.</p>
            </div>
            <span className={`relative h-6 w-11 rounded-full transition-colors duration-300 ${autoDetect ? "bg-[var(--exa-gold)]" : "bg-[var(--exa-surface-hover)]"}`}>
              <span className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all duration-300 ${autoDetect ? "left-5" : "left-0.5"}`} />
            </span>
          </button>

          <div className="max-h-72 space-y-2 overflow-y-auto pr-1">
            {currencyOptions.map((item) => {
              const active = selected === item.code;
              return (
                <button
                  key={item.code}
                  type="button"
                  onClick={() => setSelected(item.code)}
                  className={`flex w-full items-center justify-between rounded-xl border px-3 py-2.5 text-left transition ${
                    active
                      ? "translate-y-[-1px] border-[var(--exa-border-active)] bg-[var(--exa-surface-hover)] shadow-[var(--exa-shadow-soft)]"
                      : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] hover:border-[var(--exa-border)]"
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <span className={`inline-flex h-9 w-9 items-center justify-center rounded-full border ${
                      item.type === "crypto"
                        ? "border-cyan-300/35 bg-cyan-300/10 shadow-[0_0_14px_rgba(0,229,255,0.25)]"
                        : "border-[var(--exa-border)] bg-violet-300/10"
                    }`}>
                      {item.flag}
                    </span>
                    <div>
                      <p className="text-sm text-[var(--exa-text-secondary)]">{item.name}</p>
                      <p className="text-xs text-[var(--exa-text-muted)]">{item.code}</p>
                    </div>
                    {item.type === "crypto" ? (
                      <span className="rounded-full border border-cyan-300/35 bg-cyan-300/10 px-2 py-0.5 text-[10px] font-semibold text-cyan-200">
                        Web3
                      </span>
                    ) : null}
                  </div>
                  <span className={`inline-flex h-5 w-5 items-center justify-center rounded-full border ${active ? "border-[var(--exa-border-active)] shadow-[var(--exa-shadow-gold)]" : "border-[var(--exa-border)]"}`}>
                    {active ? <Check className="h-3.5 w-3.5 text-[var(--exa-gold-light)]" /> : null}
                  </span>
                </button>
              );
            })}
          </div>
        </article>

        <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)]">
          <div className="mb-2 flex items-center justify-between">
            <p className="text-sm font-semibold text-[var(--exa-text-primary)]">Live Exchange Rate</p>
            <RefreshCw className="h-4 w-4 animate-[spin_3.6s_linear_infinite] text-cyan-300/80" />
          </div>
          <div className="space-y-1 text-sm">
            <p className="text-[#86EFAC]">1 USD = 1,550 NGN</p>
            <p className="text-cyan-200">1 EXA = $0.0456</p>
          </div>
        </article>
      </section>

      <section
        className="fixed inset-x-0 bottom-0 z-40 border-t border-[var(--exa-border)] bg-[var(--exa-surface)] p-3 backdrop-blur"
        style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}
      >
        <div className="mx-auto w-full max-w-3xl">
          <button
            type="button"
            onClick={savePreference}
            disabled={!hasChanges || saving}
            className="w-full rounded-full bg-gradient-to-r from-[var(--exa-gold-light)] via-[var(--exa-gold)] to-[var(--exa-gold-dark)] py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] disabled:cursor-not-allowed disabled:opacity-45"
          >
            {saving ? "Saving..." : "Save Currency Preference"}
          </button>
        </div>
      </section>

      {toast ? (
        <div className="fixed right-4 top-24 z-50 rounded-xl border border-[#16C784]/35 bg-[#16C784]/15 px-3 py-2 text-xs text-[#BBF7D0] shadow-lg">
          {toast}
        </div>
      ) : null}
    </main>
  );
}

export default PaymentCurrencyPage;

