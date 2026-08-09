import { useMemo, useState } from "react";
import {
  ArrowLeft,
  Check,
  CreditCard,
  Plus,
  ShieldCheck,
  Wallet,
  X,
} from "lucide-react";

const methods = [
  { id: "card", icon: "CARD", title: "Debit / Credit Card", desc: "Visa, MasterCard, Verve", kind: "fiat" },
  { id: "bank", icon: "BANK", title: "Virtual Account", desc: "Dedicated NGN account for deposits", kind: "fiat" },
  { id: "exa", icon: "EXA", title: "EXA Token", desc: "Use EXA for platform payments", kind: "crypto", recommended: true },
  { id: "usdt", icon: "USDT", title: "USDT", desc: "TRC20 / ERC20 settlement", kind: "crypto" },
  { id: "wallet", icon: "WEB3", title: "Crypto Wallet Connect", desc: "WalletConnect / MetaMask", kind: "crypto" },
];

const storageKey = "exaearn-payment-method";

function PaymentMethodsPage({ onBack }) {
  const [selectedMethod, setSelectedMethod] = useState("card");
  const [savedMethod, setSavedMethod] = useState("card");
  const [showAddCardModal, setShowAddCardModal] = useState(false);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState("");
  const [form, setForm] = useState({
    cardNumber: "",
    expiry: "",
    cvv: "",
    name: "",
  });

  useState(() => {
    try {
      const raw = localStorage.getItem(storageKey);
      if (raw) {
        setSelectedMethod(raw);
        setSavedMethod(raw);
      }
    } catch {
      // Ignore storage read failures.
    }
  });

  const selectedMeta = useMemo(
    () => methods.find((item) => item.id === selectedMethod) || methods[0],
    [selectedMethod]
  );

  const hasChanges = selectedMethod !== savedMethod;

  const saveMethod = async () => {
    if (!hasChanges || saving) return;
    setSaving(true);
    await new Promise((resolve) => setTimeout(resolve, 700));
    localStorage.setItem(storageKey, selectedMethod);
    setSavedMethod(selectedMethod);
    setSaving(false);
    setToast("Payment method updated successfully.");
    setTimeout(() => setToast(""), 2200);
  };

  const addCard = () => {
    if (!form.cardNumber || !form.expiry || !form.cvv || !form.name) return;
    setShowAddCardModal(false);
    setForm({ cardNumber: "", expiry: "", cvv: "", name: "" });
    setSelectedMethod("card");
    setToast("Card method added successfully.");
    setTimeout(() => setToast(""), 2200);
  };

  return (
    <main className="relative min-h-screen bg-[var(--exa-bg-primary)] text-white">
      <div className="pointer-events-none absolute inset-0 opacity-20 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.20)_1px,transparent_0)] [background-size:22px_22px]" />
      <div className="pointer-events-none absolute inset-0 opacity-15 [background-image:linear-gradient(rgba(212,175,55,.16)_1px,transparent_1px),linear-gradient(90deg,rgba(127,70,212,.14)_1px,transparent_1px)] [background-size:72px_72px]" />
      <div className="pointer-events-none absolute -right-10 top-16 h-40 w-40 rounded-full bg-[#8A2BE2]/20 blur-3xl" />
      <div className="pointer-events-none absolute -left-10 bottom-24 h-40 w-40 rounded-full bg-[#00E5FF]/15 blur-3xl" />

      <header
        className="sticky top-0 z-30 border-b border-[#D4AF37]/25 bg-[var(--exa-surface-elevated)] backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="w-full max-w-3xl px-4 pt-3 pb-3 mx-auto sm:px-6">
          <div className="flex items-center justify-between">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] p-2 text-[var(--exa-text-secondary)] shadow-[var(--exa-shadow-soft)]"
            >
              <ArrowLeft className="w-4 h-4" />
            </button>
            <div className="text-center">
              <h1 className="text-lg font-semibold text-[var(--exa-text-primary)]">Payment Method</h1>
              <span className="mx-auto mt-1 block h-px w-16 bg-gradient-to-r from-transparent via-[#D4AF37] to-transparent" />
            </div>
            <span className="rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] p-2 text-[var(--exa-gold-light)]">
              <ShieldCheck className="w-4 h-4" />
            </span>
          </div>
        </div>
      </header>

      <section
        className="relative w-full max-w-3xl px-4 pt-4 mx-auto space-y-4 pb-28 sm:px-6"
        style={{ paddingBottom: "calc(96px + env(safe-area-inset-bottom))" }}
      >
        <article className="rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)]">
          <p className="text-xs uppercase tracking-[0.12em] text-[var(--exa-gold-light)]/85">Default Payment Method</p>
          <div className="mt-2 flex items-center justify-between gap-3 rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-3 py-2.5 shadow-[var(--exa-shadow-soft)]">
            <div>
              <p className="text-sm font-semibold text-[var(--exa-text-primary)]">
                {selectedMeta.icon} {selectedMeta.title}
              </p>
              <p className="text-xs text-[var(--exa-text-muted)]">
                This method will be used for payments, subscriptions, and course purchases.
              </p>
            </div>
            <span className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-[var(--exa-border-active)]/50 bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]">
              <Check className="w-4 h-4" />
            </span>
          </div>
        </article>

        <article className="p-4 border rounded-2xl border-[var(--exa-border)] bg-[var(--exa-surface)] shadow-[var(--exa-shadow-panel)]">
          <h2 className="mb-3 text-sm font-semibold text-[var(--exa-text-primary)]">Payment Methods</h2>
          <div className="pr-1 space-y-2 overflow-y-auto max-h-72">
            {methods.map((item) => {
              const active = selectedMethod === item.id;
              const cryptoStyle = item.kind === "crypto";
              return (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => setSelectedMethod(item.id)}
                  className={`group flex w-full items-center justify-between rounded-xl border px-3 py-2.5 text-left transition ${
                    active
                      ? "translate-y-[-1px] border-[var(--exa-border-active)] bg-[var(--exa-surface-hover)] shadow-[var(--exa-shadow-soft)]"
                      : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] hover:border-[var(--exa-border)]"
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <span className={`inline-flex h-9 w-9 items-center justify-center rounded-full border ${
                      cryptoStyle
                        ? "border-cyan-300/35 bg-cyan-300/10 shadow-[0_0_14px_rgba(0,229,255,0.24)]"
                        : "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]"
                    }`}>
                      {item.icon}
                    </span>
                    <div>
                      <p className="text-sm text-[var(--exa-text-secondary)]">{item.title}</p>
                      <p className="text-xs text-[var(--exa-text-muted)]">{item.desc}</p>
                    </div>
                    {item.recommended ? (
                      <span className="rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-2 py-0.5 text-[10px] font-semibold text-[var(--exa-gold-light)]">
                        Recommended
                      </span>
                    ) : null}
                  </div>
                  <span className={`inline-flex h-5 w-5 items-center justify-center rounded-full border ${
                    active ? "border-[var(--exa-border-active)] shadow-[var(--exa-shadow-gold)]" : "border-[var(--exa-border)]"
                  }`}>
                    {active ? <Check className="h-3.5 w-3.5 text-[var(--exa-gold-light)]" /> : null}
                  </span>
                </button>
              );
            })}
          </div>
        </article>

        <article className="p-4 border rounded-2xl border-[var(--exa-border)] bg-[var(--exa-surface)]">
          <button
            type="button"
            onClick={() => setShowAddCardModal(true)}
            className="flex w-full items-center gap-3 rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-hover)] px-3 py-3 text-left transition hover:border-[var(--exa-border-active)]"
          >
            <span className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]">
              <Plus className="w-4 h-4" />
            </span>
            <span className="text-sm font-medium text-[var(--exa-text-secondary)]">Add New Payment Method</span>
          </button>
        </article>

        <article className="p-4 border rounded-2xl border-[var(--exa-border)] bg-[var(--exa-surface)]">
          <p className="flex items-start gap-2 text-sm text-[var(--exa-text-secondary)]">
            <ShieldCheck className="mt-0.5 h-4 w-4 text-[var(--exa-gold-light)]" />
            Secured by Blockchain & Bank-Grade Encryption
          </p>
        </article>
      </section>

      <section
        className="fixed inset-x-0 bottom-0 z-40 p-3 border-t border-[var(--exa-border)] bg-[var(--exa-surface)] backdrop-blur"
        style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}
      >
        <div className="w-full max-w-3xl mx-auto">
          <button
            type="button"
            onClick={saveMethod}
            disabled={!hasChanges || saving}
            className="relative w-full overflow-hidden rounded-full bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] disabled:cursor-not-allowed disabled:opacity-45"
          >
            <span className="pointer-events-none absolute inset-y-0 -left-10 w-14 skew-x-[-20deg] bg-white/35 animate-[shine_2.6s_linear_infinite]" />
            {saving ? "Saving..." : "Confirm Payment Method"}
          </button>
        </div>
      </section>

      {showAddCardModal ? (
        <div className="fixed inset-0 z-50 flex items-end bg-black/50 sm:items-center sm:justify-center">
          <button type="button" className="absolute inset-0" onClick={() => setShowAddCardModal(false)} />
          <div className="relative w-full p-4 border rounded-t-2xl border-[var(--exa-border)] bg-[var(--exa-surface)] sm:max-w-sm sm:rounded-2xl">
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-base font-semibold text-[var(--exa-text-primary)]">Add Card</h3>
              <button type="button" onClick={() => setShowAddCardModal(false)} className="text-[var(--exa-text-secondary)]">
                <X className="w-4 h-4" />
              </button>
            </div>
            <div className="space-y-3">
              <Field label="Card Number">
                <input value={form.cardNumber} onChange={(e) => setForm((p) => ({ ...p, cardNumber: e.target.value }))} className={inputCls} />
              </Field>
              <div className="grid grid-cols-2 gap-2">
                <Field label="Expiry Date">
                  <input value={form.expiry} onChange={(e) => setForm((p) => ({ ...p, expiry: e.target.value }))} className={inputCls} placeholder="MM/YY" />
                </Field>
                <Field label="CVV">
                  <input value={form.cvv} onChange={(e) => setForm((p) => ({ ...p, cvv: e.target.value }))} className={inputCls} />
                </Field>
              </div>
              <Field label="Name on Card">
                <input value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} className={inputCls} />
              </Field>
              <button
                type="button"
                onClick={addCard}
                className="w-full rounded-xl bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] py-2.5 text-sm font-semibold text-[var(--exa-gold-contrast)]"
              >
                Save Card
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {toast ? (
        <div className="fixed right-4 top-24 z-50 rounded-xl border border-[#16C784]/35 bg-[#16C784]/15 px-3 py-2 text-xs text-[#BBF7D0] shadow-lg">
          {toast}
        </div>
      ) : null}
    </main>
  );
}

function Field({ label, children }) {
  return (
    <label className="block">
      <span className="block mb-1 text-xs text-[var(--exa-text-muted)]">{label}</span>
      {children}
    </label>
  );
}

const inputCls =
  "h-10 w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm text-[var(--exa-text-secondary)] outline-none focus:border-[#D4AF37]/70";

export default PaymentMethodsPage;


