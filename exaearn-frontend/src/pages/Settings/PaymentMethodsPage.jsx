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
  { id: "card", icon: "💳", title: "Debit / Credit Card", desc: "Visa, MasterCard, Verve", kind: "fiat" },
  { id: "bank", icon: "🏦", title: "Bank Transfer", desc: "Local and international rails", kind: "fiat" },
  { id: "exa", icon: "🪙", title: "EXA Token", desc: "Use EXA for platform payments", kind: "crypto", recommended: true },
  { id: "usdt", icon: "🌍", title: "USDT", desc: "TRC20 / ERC20 settlement", kind: "crypto" },
  { id: "wallet", icon: "🔗", title: "Crypto Wallet Connect", desc: "WalletConnect / MetaMask", kind: "crypto" },
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
    <main className="relative min-h-screen bg-gradient-to-br from-[#0B0F1F] via-[#140B2D] to-[#1C0F3F] text-white">
      <div className="pointer-events-none absolute inset-0 opacity-20 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.20)_1px,transparent_0)] [background-size:22px_22px]" />
      <div className="pointer-events-none absolute inset-0 opacity-15 [background-image:linear-gradient(rgba(212,175,55,.16)_1px,transparent_1px),linear-gradient(90deg,rgba(127,70,212,.14)_1px,transparent_1px)] [background-size:72px_72px]" />
      <div className="pointer-events-none absolute -right-10 top-16 h-40 w-40 rounded-full bg-[#8A2BE2]/20 blur-3xl" />
      <div className="pointer-events-none absolute -left-10 bottom-24 h-40 w-40 rounded-full bg-[#00E5FF]/15 blur-3xl" />

      <header
        className="sticky top-0 z-30 border-b border-[#D4AF37]/25 bg-cosmic-900/88 backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-3xl px-4 pb-3 pt-3 sm:px-6">
          <div className="flex items-center justify-between">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-violet-300/25 bg-cosmic-800/70 p-2 text-violet-100 shadow-[0_0_14px_rgba(212,175,55,0.25)]"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div className="text-center">
              <h1 className="text-lg font-semibold text-violet-50">Payment Method</h1>
              <span className="mx-auto mt-1 block h-px w-16 bg-gradient-to-r from-transparent via-[#D4AF37] to-transparent" />
            </div>
            <span className="rounded-xl border border-[#D4AF37]/40 bg-[#D4AF37]/12 p-2 text-[#F5D76E]">
              <ShieldCheck className="h-4 w-4" />
            </span>
          </div>
        </div>
      </header>

      <section
        className="relative mx-auto w-full max-w-3xl space-y-4 px-4 pb-28 pt-4 sm:px-6"
        style={{ paddingBottom: "calc(96px + env(safe-area-inset-bottom))" }}
      >
        <article className="rounded-2xl border border-[#D4AF37]/30 bg-cosmic-900/70 p-4 shadow-cosmic-card">
          <p className="text-xs uppercase tracking-[0.12em] text-[#F5D76E]/85">Default Payment Method</p>
          <div className="mt-2 flex items-center justify-between gap-3 rounded-xl border border-[#D4AF37]/40 bg-[#D4AF37]/10 px-3 py-2.5 shadow-[inset_0_0_18px_rgba(245,215,110,0.16)]">
            <div>
              <p className="text-sm font-semibold text-violet-50">
                {selectedMeta.icon} {selectedMeta.title}
              </p>
              <p className="text-xs text-violet-100/65">
                This method will be used for payments, subscriptions, and course purchases.
              </p>
            </div>
            <span className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-[#F5D76E]/50 bg-[#D4AF37]/20 text-[#F5D76E]">
              <Check className="h-4 w-4" />
            </span>
          </div>
        </article>

        <article className="rounded-2xl border border-violet-300/20 bg-cosmic-900/70 p-4 shadow-cosmic-card">
          <h2 className="mb-3 text-sm font-semibold text-violet-50">Payment Methods</h2>
          <div className="max-h-72 space-y-2 overflow-y-auto pr-1">
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
                      ? "translate-y-[-1px] border-[#D4AF37]/45 bg-cosmic-800/80 shadow-[0_0_18px_rgba(212,175,55,0.22)]"
                      : "border-violet-300/15 bg-cosmic-800/60 hover:border-violet-300/35"
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <span className={`inline-flex h-9 w-9 items-center justify-center rounded-full border ${
                      cryptoStyle
                        ? "border-cyan-300/35 bg-cyan-300/10 shadow-[0_0_14px_rgba(0,229,255,0.24)]"
                        : "border-[#D4AF37]/30 bg-[#D4AF37]/12"
                    }`}>
                      {item.icon}
                    </span>
                    <div>
                      <p className="text-sm text-violet-100">{item.title}</p>
                      <p className="text-xs text-violet-100/60">{item.desc}</p>
                    </div>
                    {item.recommended ? (
                      <span className="rounded-full border border-[#D4AF37]/45 bg-[#D4AF37]/18 px-2 py-0.5 text-[10px] font-semibold text-[#F5D76E]">
                        Recommended
                      </span>
                    ) : null}
                  </div>
                  <span className={`inline-flex h-5 w-5 items-center justify-center rounded-full border ${
                    active ? "border-[#F5D76E] shadow-[0_0_12px_rgba(245,215,110,0.35)]" : "border-violet-300/35"
                  }`}>
                    {active ? <Check className="h-3.5 w-3.5 text-[#F5D76E]" /> : null}
                  </span>
                </button>
              );
            })}
          </div>
        </article>

        <article className="rounded-2xl border border-violet-300/20 bg-cosmic-900/70 p-4">
          <button
            type="button"
            onClick={() => setShowAddCardModal(true)}
            className="flex w-full items-center gap-3 rounded-xl border border-[#D4AF37]/30 bg-cosmic-800/65 px-3 py-3 text-left transition hover:border-[#D4AF37]/55"
          >
            <span className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[#D4AF37]/45 bg-[#D4AF37]/12 text-[#F5D76E]">
              <Plus className="h-4 w-4" />
            </span>
            <span className="text-sm font-medium text-violet-100">Add New Payment Method</span>
          </button>
        </article>

        <article className="rounded-2xl border border-violet-300/20 bg-cosmic-900/70 p-4">
          <p className="flex items-start gap-2 text-sm text-violet-100">
            <ShieldCheck className="mt-0.5 h-4 w-4 text-[#F5D76E]" />
            Secured by Blockchain & Bank-Grade Encryption
          </p>
        </article>
      </section>

      <section
        className="fixed inset-x-0 bottom-0 z-40 border-t border-violet-300/20 bg-cosmic-900/90 p-3 backdrop-blur"
        style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}
      >
        <div className="mx-auto w-full max-w-3xl">
          <button
            type="button"
            onClick={saveMethod}
            disabled={!hasChanges || saving}
            className="relative w-full overflow-hidden rounded-full bg-gradient-to-r from-[#B8860B] via-[#D4AF37] to-[#F5D76E] py-3 text-sm font-semibold text-[#1C0F3F] shadow-[0_10px_24px_rgba(212,175,55,0.35)] disabled:cursor-not-allowed disabled:opacity-45"
          >
            <span className="pointer-events-none absolute inset-y-0 -left-10 w-14 skew-x-[-20deg] bg-white/35 animate-[shine_2.6s_linear_infinite]" />
            {saving ? "Saving..." : "Confirm Payment Method"}
          </button>
        </div>
      </section>

      {showAddCardModal ? (
        <div className="fixed inset-0 z-50 flex items-end bg-black/50 sm:items-center sm:justify-center">
          <button type="button" className="absolute inset-0" onClick={() => setShowAddCardModal(false)} />
          <div className="relative w-full rounded-t-2xl border border-violet-300/20 bg-cosmic-900 p-4 sm:max-w-sm sm:rounded-2xl">
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-base font-semibold text-violet-50">Add Card</h3>
              <button type="button" onClick={() => setShowAddCardModal(false)} className="text-violet-100/70">
                <X className="h-4 w-4" />
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
                className="w-full rounded-xl bg-gradient-to-r from-[#B8860B] via-[#D4AF37] to-[#F5D76E] py-2.5 text-sm font-semibold text-[#1C0F3F]"
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
      <span className="mb-1 block text-xs text-violet-100/65">{label}</span>
      {children}
    </label>
  );
}

const inputCls =
  "h-10 w-full rounded-xl border border-violet-300/20 bg-cosmic-800 px-3 text-sm text-violet-100 outline-none focus:border-[#D4AF37]/70";

export default PaymentMethodsPage;
