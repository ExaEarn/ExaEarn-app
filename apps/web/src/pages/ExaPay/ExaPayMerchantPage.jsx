import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, BadgeCheck, Banknote, Code2, Copy, CreditCard, Link2, LoaderCircle, RefreshCw, ShieldCheck, Webhook } from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import { applyForMerchant, createMerchantApiKey, createPaymentIntent, createPaymentLink, getMerchantOverview, getMerchants, getPaymentLinks } from "../../services/exaPayApi";

function fmt(value, currency = "NGN") {
  const n = Number(value || 0);
  return `${currency} ${Number.isFinite(n) ? n.toLocaleString(undefined, { maximumFractionDigits: 2 }) : "0"}`;
}

function idempotency(prefix) {
  return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export default function ExaPayMerchantPage({ onBack }) {
  const { request } = useAuth();
  const [merchants, setMerchants] = useState([]);
  const [overview, setOverview] = useState(null);
  const [links, setLinks] = useState([]);
  const [busy, setBusy] = useState("");
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const selected = useMemo(() => merchants[0], [merchants]);

  async function load() {
    setError("");
    try {
      const result = await getMerchants(request);
      const rows = Array.isArray(result) ? result : [];
      setMerchants(rows);
      if (rows[0]?.merchant_id) {
        const [summary, linkRows] = await Promise.all([getMerchantOverview(request, rows[0].merchant_id), getPaymentLinks(request, rows[0].merchant_id)]);
        setOverview(summary);
        setLinks(Array.isArray(linkRows) ? linkRows : []);
      }
    } catch (err) {
      setError(err.message || "We could not load ExaPay merchant data.");
    }
  }

  useEffect(() => { load(); }, []);

  async function apply() {
    setBusy("apply");
    setError("");
    try {
      await applyForMerchant(request, {
        business_name: "My ExaPay Business",
        country: "NG",
        business_type: "GENERAL_COMMERCE",
        settlement_currency: "NGN",
        environment: "SANDBOX",
      });
      setNotice("Merchant application created. KYB review is required before live payment acceptance.");
      await load();
    } catch (err) {
      setError(err.message || "Merchant application failed.");
    } finally {
      setBusy("");
    }
  }

  async function createLink() {
    if (!selected) return;
    setBusy("link");
    try {
      const link = await createPaymentLink(request, selected.merchant_id, {
        title: "Sandbox payment",
        amount_mode: "FIXED",
        amount: "1000",
        currency: selected.settlement_currency || "NGN",
        maximum_uses: 20,
      });
      setNotice(`Payment link created: ${link.link_id}`);
      await load();
    } catch (err) {
      setError(err.message || "Payment link failed.");
    } finally {
      setBusy("");
    }
  }

  async function createIntent() {
    if (!selected) return;
    setBusy("intent");
    try {
      const intent = await createPaymentIntent(request, selected.merchant_id, {
        amount: "1000",
        currency: selected.settlement_currency || "NGN",
        description: "Sandbox checkout",
        environment: "SANDBOX",
        payment_method: "EXAEARN_BALANCE",
        idempotency_key: idempotency("web-exapay-intent"),
      });
      setNotice(`Hosted checkout token created for ${intent.public_reference}.`);
      await navigator.clipboard?.writeText?.(`${window.location.origin}/api/exapay/checkout/${intent.checkout_token}`);
      await load();
    } catch (err) {
      setError(err.message || "Payment intent failed.");
    } finally {
      setBusy("");
    }
  }

  async function createKey() {
    if (!selected) return;
    setBusy("key");
    try {
      const key = await createMerchantApiKey(request, selected.merchant_id, {
        name: "Sandbox server key",
        environment: "SANDBOX",
        scopes: ["payments.read", "payments.create"],
      });
      setNotice(`API key created. Secret shown once: ${key.secret}`);
    } catch (err) {
      setError(err.message || "API key creation failed.");
    } finally {
      setBusy("");
    }
  }

  return (
    <main className="min-h-screen bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
      <header className="sticky top-0 z-20 border-b border-[var(--exa-border-active)] bg-[var(--exa-surface)]/95 backdrop-blur">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
          <button type="button" onClick={onBack} className="grid h-10 w-10 place-items-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-gold-light)]">
            <ArrowLeft size={20} />
          </button>
          <div className="text-right">
            <p className="text-[11px] uppercase tracking-[0.22em] text-[var(--exa-gold-light)]">ExaPay Merchant</p>
            <h1 className="text-lg font-semibold">Payments</h1>
          </div>
        </div>
      </header>

      <section className="mx-auto grid max-w-6xl gap-4 px-4 py-5 lg:grid-cols-[1.1fr_0.9fr]">
        <div className="rounded-[28px] border border-[var(--exa-border-active)] bg-[linear-gradient(145deg,rgba(17,24,39,.96),rgba(3,7,18,.98))] p-5 shadow-[var(--exa-shadow-soft)]">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--exa-gold-light)]">Merchant command center</p>
              <h2 className="mt-2 text-2xl font-semibold">Accept payments with ExaPay.</h2>
              <p className="mt-2 max-w-xl text-sm leading-6 text-[var(--exa-text-secondary)]">
                Create hosted checkouts, payment links, refunds, webhooks and settlement reports using ExaEarn's canonical payment infrastructure.
              </p>
            </div>
            <ShieldCheck className="h-9 w-9 text-emerald-300" />
          </div>
          {selected ? (
            <div className="mt-5 grid gap-3 sm:grid-cols-3">
              <Metric label="Gross volume" value={fmt(overview?.metrics?.gross_amount, selected.settlement_currency)} />
              <Metric label="Net payable" value={fmt(overview?.metrics?.net_payable, selected.settlement_currency)} />
              <Metric label="Success rate" value={`${Math.round((overview?.metrics?.success_rate || 0) * 100)}%`} />
            </div>
          ) : (
            <div className="mt-5 rounded-2xl border border-dashed border-[var(--exa-border-active)] bg-white/[0.03] p-4">
              <h3 className="font-semibold">Start with a merchant application</h3>
              <p className="mt-1 text-sm text-[var(--exa-text-secondary)]">Sandbox setup is available first. Production remains gated by KYB, provider and banking readiness.</p>
              <button type="button" onClick={apply} disabled={busy === "apply"} className="mt-4 rounded-full bg-[var(--exa-gold)] px-4 py-2 text-sm font-bold text-black">
                {busy === "apply" ? "Creating..." : "Apply for ExaPay"}
              </button>
            </div>
          )}
        </div>

        <div className="rounded-[28px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-5">
          <div className="flex items-center justify-between">
            <h2 className="text-base font-semibold">Operational status</h2>
            <button type="button" onClick={load} className="grid h-9 w-9 place-items-center rounded-full border border-[var(--exa-border)] text-[var(--exa-gold-light)]"><RefreshCw size={16} /></button>
          </div>
          <div className="mt-4 space-y-3 text-sm">
            <Status label="Merchant" value={selected?.status || "NOT_APPLIED"} />
            <Status label="KYB" value={selected?.kyb_status || "REQUIRED"} />
            <Status label="Risk" value={selected?.risk_status || "PENDING"} />
            <Status label="Environment" value={selected?.environment || "SANDBOX"} />
            <Status label="Real providers" value="OPERATIONAL SETUP REQUIRED" muted />
          </div>
        </div>
      </section>

      {error ? <Notice tone="danger" text={error} /> : null}
      {notice ? <Notice tone="success" text={notice} /> : null}

      {selected ? (
        <section className="mx-auto grid max-w-6xl gap-4 px-4 pb-8 lg:grid-cols-3">
          <ActionCard icon={CreditCard} title="Hosted checkout" text="Create a short-lived checkout token for a real payment intent." action="Create checkout" busy={busy === "intent"} onClick={createIntent} />
          <ActionCard icon={Link2} title="Payment links" text="Create fixed or variable links that generate payment intents per use." action="Create link" busy={busy === "link"} onClick={createLink} />
          <ActionCard icon={Code2} title="API keys" text="Issue scoped sandbox merchant keys. Secret is shown once." action="Create key" busy={busy === "key"} onClick={createKey} />

          <div className="rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 lg:col-span-2">
            <h2 className="text-base font-semibold">Recent payments</h2>
            <div className="mt-3 divide-y divide-[var(--exa-border)]">
              {(overview?.recent_payments || []).slice(0, 8).map((payment) => (
                <div key={payment.pay_intent_id} className="flex items-center justify-between gap-3 py-3 text-sm">
                  <div>
                    <p className="font-semibold">{payment.public_reference}</p>
                    <p className="text-xs text-[var(--exa-text-muted)]">{payment.description || "Payment intent"} · {payment.status}</p>
                  </div>
                  <span className="font-semibold">{fmt(payment.amount, payment.currency)}</span>
                </div>
              ))}
              {!(overview?.recent_payments || []).length ? <p className="py-5 text-sm text-[var(--exa-text-secondary)]">No merchant payments yet.</p> : null}
            </div>
          </div>

          <div className="rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
            <h2 className="text-base font-semibold">Payment links</h2>
            <div className="mt-3 space-y-2">
              {links.slice(0, 6).map((link) => (
                <div key={link.link_id} className="rounded-2xl border border-[var(--exa-border)] bg-white/[0.03] p-3 text-sm">
                  <div className="flex items-center justify-between gap-2">
                    <strong>{link.title}</strong>
                    <button type="button" onClick={() => navigator.clipboard?.writeText?.(link.link_id)} className="text-[var(--exa-gold-light)]"><Copy size={15} /></button>
                  </div>
                  <p className="mt-1 text-xs text-[var(--exa-text-muted)]">{fmt(link.amount, link.currency)} · {link.status} · {link.uses_count}/{link.maximum_uses ?? "∞"}</p>
                </div>
              ))}
              {!links.length ? <p className="text-sm text-[var(--exa-text-secondary)]">No payment links yet.</p> : null}
            </div>
          </div>

          <div className="rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 lg:col-span-3">
            <h2 className="text-base font-semibold">Merchant software boundary</h2>
            <div className="mt-3 grid gap-3 sm:grid-cols-4">
              <Boundary icon={BadgeCheck} title="KYB gated" />
              <Boundary icon={Banknote} title="Canonical settlement" />
              <Boundary icon={Webhook} title="Signed webhooks" />
              <Boundary icon={ShieldCheck} title="Risk monitored" />
            </div>
          </div>
        </section>
      ) : null}
    </main>
  );
}

function Metric({ label, value }) {
  return <div className="rounded-2xl border border-[var(--exa-border)] bg-white/[0.04] p-3"><p className="text-xs text-[var(--exa-text-muted)]">{label}</p><strong className="mt-1 block text-lg">{value}</strong></div>;
}

function Status({ label, value, muted }) {
  return <div className="flex items-center justify-between gap-3"><span className="text-[var(--exa-text-secondary)]">{label}</span><strong className={muted ? "text-amber-200" : "text-[var(--exa-text-primary)]"}>{value}</strong></div>;
}

function Notice({ tone, text }) {
  return <div className="mx-auto max-w-6xl px-4 pb-4"><div className={`rounded-2xl border p-3 text-sm ${tone === "danger" ? "border-red-400/30 bg-red-500/10 text-red-100" : "border-emerald-400/30 bg-emerald-500/10 text-emerald-100"}`}>{text}</div></div>;
}

function ActionCard({ icon: Icon, title, text, action, busy, onClick }) {
  return <div className="rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4"><Icon className="h-7 w-7 text-[var(--exa-gold-light)]" /><h3 className="mt-3 font-semibold">{title}</h3><p className="mt-1 min-h-[42px] text-sm text-[var(--exa-text-secondary)]">{text}</p><button type="button" onClick={onClick} disabled={busy} className="mt-4 inline-flex items-center gap-2 rounded-full border border-[var(--exa-border-active)] px-4 py-2 text-sm font-semibold text-[var(--exa-gold-light)]">{busy ? <LoaderCircle className="h-4 w-4 animate-spin" /> : null}{action}</button></div>;
}

function Boundary({ icon: Icon, title }) {
  return <div className="flex items-center gap-2 rounded-2xl border border-[var(--exa-border)] bg-white/[0.03] p-3 text-sm font-semibold"><Icon className="h-4 w-4 text-emerald-300" />{title}</div>;
}
