import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  Ban,
  CreditCard,
  Eye,
  LoaderCircle,
  Lock,
  RefreshCw,
  ShieldAlert,
  ShieldCheck,
  SlidersHorizontal,
  WalletCards,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import {
  createFundingQuote,
  freezeCard,
  fundCard,
  getCardAuthorizations,
  getCardDetailsToken,
  getCardRealtimeReplay,
  getCardProducts,
  getCardTransactions,
  getCards,
  issueCard,
  reportCardLostOrStolen,
  unloadCard,
  unfreezeCard,
  updateCardControls,
  updateCardLimits,
} from "../../services/exaCardApi";

function money(value, currency = "USD") {
  const n = Number(value || 0);
  return `${currency} ${Number.isFinite(n) ? n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "0.00"}`;
}

function idem(prefix) {
  return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export default function ExaCardPage({ onBack }) {
  const { request } = useAuth();
  const [products, setProducts] = useState([]);
  const [provider, setProvider] = useState(null);
  const [cards, setCards] = useState([]);
  const [selectedCardUuid, setSelectedCardUuid] = useState("");
  const [selectedProduct, setSelectedProduct] = useState("USD_VIRTUAL");
  const [amount, setAmount] = useState("50");
  const [sourceAsset, setSourceAsset] = useState("USD");
  const [unloadAmount, setUnloadAmount] = useState("25");
  const [quote, setQuote] = useState(null);
  const [transactions, setTransactions] = useState([]);
  const [authorizations, setAuthorizations] = useState([]);
  const [dailyLimit, setDailyLimit] = useState("");
  const [busy, setBusy] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [realtimeState, setRealtimeState] = useState({ status: "connecting", sequence: 0 });

  const selectedCard = useMemo(() => cards.find((card) => card.card_uuid === selectedCardUuid) || cards[0], [cards, selectedCardUuid]);
  const product = useMemo(() => products.find((item) => item.product_code === selectedProduct) || products[0], [products, selectedProduct]);

  useEffect(() => {
    let mounted = true;
    async function load() {
      setBusy("loading");
      setError("");
      try {
        const [productPayload, cardPayload] = await Promise.all([getCardProducts(request), getCards(request)]);
        if (!mounted) return;
        setProducts(productPayload.products || []);
        setProvider(productPayload.provider || null);
        setCards(Array.isArray(cardPayload) ? cardPayload : []);
        setSelectedCardUuid((Array.isArray(cardPayload) && cardPayload[0]?.card_uuid) || "");
      } catch (loadError) {
        if (mounted) setError(loadError.message || "Unable to load ExaCard.");
      } finally {
        if (mounted) setBusy("");
      }
    }
    load();
    return () => {
      mounted = false;
    };
  }, [request]);

  useEffect(() => {
    if (!selectedCard?.card_uuid) {
      setTransactions([]);
      setAuthorizations([]);
      return;
    }

    let mounted = true;
    async function loadActivity() {
      try {
        const [tx, auths] = await Promise.all([
          getCardTransactions(request, selectedCard.card_uuid),
          getCardAuthorizations(request, selectedCard.card_uuid),
        ]);
        if (!mounted) return;
        setTransactions(Array.isArray(tx) ? tx : []);
        setAuthorizations(Array.isArray(auths) ? auths : []);
        setDailyLimit(selectedCard.limits?.daily ? String(selectedCard.limits.daily) : "");
      } catch {
        if (mounted) {
          setTransactions([]);
          setAuthorizations([]);
        }
      }
    }
    loadActivity();
    return () => {
      mounted = false;
    };
  }, [request, selectedCard?.card_uuid]);

  useEffect(() => {
    let mounted = true;
    let timer;

    async function applyRealtime() {
      try {
        const replay = await getCardRealtimeReplay(request, realtimeState.sequence);
        if (!mounted) return;
        const events = replay.events || [];
        if (replay.reconcile_required) {
          await refreshCards();
        }
        for (const event of events) {
          const card = event.payload?.card;
          if (card?.card_uuid) {
            setCards((prev) => {
              const exists = prev.some((item) => item.card_uuid === card.card_uuid);
              return exists ? prev.map((item) => (item.card_uuid === card.card_uuid ? card : item)) : [card, ...prev];
            });
            setSelectedCardUuid((current) => current || card.card_uuid);
          }
        }
        if (events.some((event) => String(event.event_type || "").startsWith("card.transaction") || String(event.event_type || "").startsWith("card.authorization") || String(event.event_type || "").startsWith("card.refund") || String(event.event_type || "").startsWith("card.chargeback"))) {
          const activeCardUuid = selectedCard?.card_uuid;
          if (activeCardUuid) {
            const [tx, auths] = await Promise.all([
              getCardTransactions(request, activeCardUuid),
              getCardAuthorizations(request, activeCardUuid),
            ]);
            if (mounted) {
              setTransactions(Array.isArray(tx) ? tx : []);
              setAuthorizations(Array.isArray(auths) ? auths : []);
            }
          }
        }
        setRealtimeState({ status: replay.reconcile_required ? "reconciled" : "live", sequence: replay.latest_sequence || realtimeState.sequence });
      } catch {
        if (mounted) setRealtimeState((current) => ({ ...current, status: "degraded" }));
      } finally {
        if (mounted) timer = window.setTimeout(applyRealtime, 10000);
      }
    }

    applyRealtime();
    return () => {
      mounted = false;
      window.clearTimeout(timer);
    };
  }, [request, realtimeState.sequence, selectedCard?.card_uuid]);

  async function refreshCards() {
    const next = await getCards(request);
    setCards(Array.isArray(next) ? next : []);
    if (!selectedCardUuid && Array.isArray(next) && next[0]) setSelectedCardUuid(next[0].card_uuid);
  }

  async function runAction(name, action, success) {
    setBusy(name);
    setError("");
    setMessage("");
    try {
      await action();
      setMessage(success);
      await refreshCards();
    } catch (actionError) {
      setError(actionError.message || "The card action could not be completed.");
    } finally {
      setBusy("");
    }
  }

  async function handleIssue() {
    await runAction("issue", async () => {
      const card = await issueCard(request, { productCode: selectedProduct, idempotencyKey: idem("issue-card") });
      setSelectedCardUuid(card.card_uuid);
    }, "ExaCard issued successfully.");
  }

  async function handleQuote() {
    if (!selectedCard) return;
    setBusy("quote");
    setError("");
    setMessage("");
    try {
      const nextQuote = await createFundingQuote(request, selectedCard.card_uuid, { sourceAsset, amount });
      setQuote(nextQuote);
      setMessage("Funding quote created. Review total debit before funding.");
    } catch (quoteError) {
      setError(quoteError.message || "Unable to create quote.");
    } finally {
      setBusy("");
    }
  }

  async function handleFund() {
    if (!quote) return;
    await runAction("fund", async () => {
      await fundCard(request, { quoteUuid: quote.quote_uuid, idempotencyKey: idem("fund-card") });
      setQuote(null);
    }, "Card funding submitted. Provider-confirmed funding settles through the ledger.");
  }

  async function handleUnload() {
    if (!selectedCard) return;
    await runAction("unload", async () => {
      await unloadCard(request, selectedCard.card_uuid, { amount: unloadAmount, idempotencyKey: idem("unload-card") });
    }, "Unload submitted and settled back to your funding account after provider confirmation.");
  }

  async function toggleOnline() {
    if (!selectedCard) return;
    await runAction("controls", async () => {
      const updated = await updateCardControls(request, selectedCard.card_uuid, { online: !selectedCard.controls?.online });
      setCards((prev) => prev.map((card) => (card.card_uuid === updated.card_uuid ? updated : card)));
    }, "Card control updated.");
  }

  async function updateDailyLimit() {
    if (!selectedCard || !dailyLimit) return;
    await runAction("limits", async () => {
      await updateCardLimits(request, selectedCard.card_uuid, { daily: dailyLimit });
    }, "Card limit updated.");
  }

  return (
    <main className="min-h-screen bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
      <header className="sticky top-0 z-30 border-b border-[var(--exa-border)] bg-[var(--exa-surface)]/95 backdrop-blur">
        <div className="mx-auto flex max-w-6xl items-center gap-3 px-4 py-4 sm:px-6">
          <button type="button" onClick={onBack} className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-gold-light)]" aria-label="Back">
            <ArrowLeft className="h-5 w-5" />
          </button>
          <div>
            <p className="text-xs uppercase tracking-[0.18em] text-[var(--exa-gold-light)]">ExaCard</p>
            <h1 className="text-xl font-semibold sm:text-2xl">Cards, funding, controls and activity</h1>
          </div>
        </div>
      </header>

      <section className="mx-auto grid max-w-6xl gap-4 px-4 py-5 sm:px-6 lg:grid-cols-[1.1fr_0.9fr]">
        <Panel>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-sm text-[var(--exa-text-secondary)]">Provider mode</p>
              <h2 className="mt-1 text-lg font-semibold">{provider?.provider || "ExaCard"} {provider?.mode ? `(${provider.mode})` : ""}</h2>
            </div>
            <span className="rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-3 py-1 text-xs font-semibold text-[var(--exa-gold-light)]">{provider?.status || "Loading"}</span>
          </div>
          <p className={`mt-3 rounded-xl border px-3 py-2 text-xs ${realtimeState.status === "degraded" ? "border-amber-400/25 bg-amber-400/10 text-amber-100" : "border-emerald-400/20 bg-emerald-400/10 text-emerald-100"}`}>
            Realtime {realtimeState.status}. Last sequence {realtimeState.sequence}.
          </p>

          <div className="mt-5 grid gap-3 sm:grid-cols-2">
            {products.map((item) => (
              <button key={item.product_code} type="button" onClick={() => setSelectedProduct(item.product_code)} className={`rounded-2xl border p-4 text-left transition ${selectedProduct === item.product_code ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)]"}`}>
                <div className="flex items-center justify-between gap-3">
                  <CreditCard className="h-5 w-5 text-[var(--exa-gold-light)]" />
                  <span className="text-xs text-[var(--exa-text-muted)]">{item.enabled ? "Enabled" : "Disabled"}</span>
                </div>
                <h3 className="mt-3 font-semibold">{item.product_code.replace("_", " ")}</h3>
                <p className="mt-1 text-sm text-[var(--exa-text-secondary)]">{item.currency} {item.type?.toLowerCase()} card</p>
              </button>
            ))}
          </div>

          <button type="button" onClick={handleIssue} disabled={busy === "issue" || !product?.enabled} className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-[var(--exa-gold)] px-4 py-3 font-semibold text-[var(--exa-gold-contrast)] disabled:opacity-50 sm:w-auto">
            {busy === "issue" ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <WalletCards className="h-4 w-4" />} Issue selected card
          </button>
        </Panel>

        <Panel>
          <h2 className="text-lg font-semibold">Your cards</h2>
          <div className="mt-4 space-y-3">
            {cards.length ? cards.map((card) => (
              <button key={card.card_uuid} type="button" onClick={() => setSelectedCardUuid(card.card_uuid)} className={`w-full rounded-2xl border p-4 text-left ${selectedCard?.card_uuid === card.card_uuid ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)]"}`}>
                <div className="flex items-center justify-between gap-3">
                  <span className="font-semibold">{card.network || "CARD"} **** {card.last_four || "----"}</span>
                  <span className="text-xs text-[var(--exa-text-muted)]">{card.status}</span>
                </div>
                <p className="mt-2 text-sm text-[var(--exa-text-secondary)]">{money(card.balance?.available, card.currency)} available</p>
              </button>
            )) : <p className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 text-sm text-[var(--exa-text-muted)]">No ExaCard has been issued yet.</p>}
          </div>
        </Panel>

        <Panel wide>
          <div className="grid gap-4 lg:grid-cols-[1fr_0.8fr]">
            <div>
              <h2 className="text-lg font-semibold">Fund selected card</h2>
              <div className="mt-4 grid gap-3 sm:grid-cols-3">
                <Field label="Source asset" value={sourceAsset} onChange={(value) => setSourceAsset(value.toUpperCase())} />
                <Field label="Card amount" value={amount} onChange={setAmount} />
                <ActionButton onClick={handleQuote} disabled={!selectedCard || busy === "quote"} icon={busy === "quote" ? LoaderCircle : SlidersHorizontal} spin={busy === "quote"}>Quote</ActionButton>
              </div>
            </div>
            <Summary quote={quote} onConfirm={handleFund} busy={busy === "fund"} />
          </div>
        </Panel>

        {selectedCard ? (
          <Panel wide>
            <div className="grid gap-5 lg:grid-cols-[1fr_0.9fr]">
              <div>
                <h2 className="text-lg font-semibold">Security controls</h2>
                <div className="mt-4 flex flex-wrap gap-3">
                  <ActionButton onClick={toggleOnline} disabled={busy === "controls"} icon={Lock}>Online: {selectedCard.controls?.online ? "On" : "Off"}</ActionButton>
                  <ActionButton onClick={() => runAction("freeze", () => freezeCard(request, selectedCard.card_uuid, "User requested temporary freeze."), "Card frozen.")} icon={Ban}>Freeze</ActionButton>
                  <ActionButton onClick={() => runAction("unfreeze", () => unfreezeCard(request, selectedCard.card_uuid, "User requested unfreeze."), "Card unfrozen.")} icon={RefreshCw}>Unfreeze</ActionButton>
                  <ActionButton onClick={() => runAction("details", () => getCardDetailsToken(request, selectedCard.card_uuid), "Secure card-detail token created. Sensitive details remain provider-hosted.")} icon={Eye}>View details</ActionButton>
                  <ActionButton onClick={() => runAction("lost", () => reportCardLostOrStolen(request, selectedCard.card_uuid, "User reported card lost or stolen."), "Card blocked and report recorded.")} icon={ShieldAlert}>Report lost/stolen</ActionButton>
                </div>
              </div>
              <div>
                <h2 className="text-lg font-semibold">Unload and limits</h2>
                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                  <Field label="Unload amount" value={unloadAmount} onChange={setUnloadAmount} />
                  <ActionButton onClick={handleUnload} disabled={busy === "unload"} icon={WalletCards}>Unload</ActionButton>
                  <Field label="Daily limit" value={dailyLimit} onChange={setDailyLimit} />
                  <ActionButton onClick={updateDailyLimit} disabled={busy === "limits"} icon={SlidersHorizontal}>Save limit</ActionButton>
                </div>
              </div>
            </div>
          </Panel>
        ) : null}

        <Panel wide>
          <div className="grid gap-5 lg:grid-cols-2">
            <ActivityList title="Recent transactions" items={transactions} empty="Card transactions will appear after provider webhooks are processed." />
            <ActivityList title="Authorizations" items={authorizations} empty="Open card authorizations appear here." />
          </div>
        </Panel>

        {message ? <p className="rounded-2xl border border-emerald-400/20 bg-emerald-400/10 p-4 text-sm text-emerald-200 lg:col-span-2">{message}</p> : null}
        {error ? <p className="rounded-2xl border border-red-400/20 bg-red-400/10 p-4 text-sm text-red-200 lg:col-span-2">{error}</p> : null}
        {busy === "loading" ? <p className="text-sm text-[var(--exa-text-muted)] lg:col-span-2">Loading ExaCard...</p> : null}
      </section>
    </main>
  );
}

function Panel({ children, wide = false }) {
  return <div className={`rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-5 ${wide ? "lg:col-span-2" : ""}`}>{children}</div>;
}

function Field({ label, value, onChange }) {
  return (
    <label className="text-sm text-[var(--exa-text-secondary)]">
      {label}
      <input value={value} onChange={(event) => onChange(event.target.value)} className="mt-2 h-12 w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 text-[var(--exa-text-primary)] outline-none focus:border-[var(--exa-border-active)]" />
    </label>
  );
}

function ActionButton({ children, disabled, icon: Icon, onClick, spin = false }) {
  return (
    <button type="button" onClick={onClick} disabled={disabled} className="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 text-sm font-semibold text-[var(--exa-text-primary)] transition hover:border-[var(--exa-border-active)] disabled:opacity-50">
      <Icon className={`h-4 w-4 text-[var(--exa-gold-light)] ${spin ? "animate-spin" : ""}`} /> {children}
    </button>
  );
}

function Summary({ quote, onConfirm, busy }) {
  return (
    <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
      <h3 className="font-semibold">Funding summary</h3>
      {quote ? (
        <div className="mt-3 space-y-2 text-sm text-[var(--exa-text-secondary)]">
          <Line label="Card receives" value={money(quote.card_amount, quote.card_currency)} />
          <Line label="Card fee" value={money(quote.card_fee, quote.source_asset)} />
          <Line label="Provider fee" value={money(quote.provider_fee, quote.source_asset)} />
          <Line label="Total debit" value={money(quote.total_debit, quote.source_asset)} />
          <button type="button" onClick={onConfirm} disabled={busy} className="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[var(--exa-gold)] px-4 py-3 font-semibold text-[var(--exa-gold-contrast)] disabled:opacity-50">
            {busy ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <ShieldCheck className="h-4 w-4" />} Confirm funding
          </button>
        </div>
      ) : <p className="mt-3 text-sm text-[var(--exa-text-muted)]">Create a quote to see provider-confirmed funding details.</p>}
    </div>
  );
}

function ActivityList({ title, items, empty }) {
  return (
    <div>
      <h2 className="text-lg font-semibold">{title}</h2>
      <div className="mt-4 space-y-2">
        {items.length ? items.slice(0, 8).map((item) => (
          <div key={item.transaction_uuid || item.authorization_uuid} className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3 text-sm">
            <div className="flex items-center justify-between gap-3">
              <span className="font-semibold">{item.merchant || item.type || item.status}</span>
              <span className="text-[var(--exa-text-muted)]">{item.status}</span>
            </div>
            <p className="mt-1 text-[var(--exa-text-secondary)]">{money(item.billing_amount || item.amount, item.billing_currency || item.currency)}</p>
          </div>
        )) : <p className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3 text-sm text-[var(--exa-text-muted)]">{empty}</p>}
      </div>
    </div>
  );
}

function Line({ label, value }) {
  return <div className="flex items-center justify-between gap-3"><span>{label}</span><strong className="text-[var(--exa-text-primary)]">{value}</strong></div>;
}
