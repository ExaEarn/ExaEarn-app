import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft, ChevronRight, CircleDollarSign, CreditCard, Eye,
  LockKeyhole, LoaderCircle, MoreHorizontal, ShieldAlert,
  ShieldCheck, SlidersHorizontal, Sparkles, WalletCards, X,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import {
  createFundingQuote, freezeCard, fundCard, getCardAuthorizations,
  getCardDetailsToken, getCardProducts, getCardRealtimeReplay, getCardTransactions,
  getCards, issueCard, reportCardLostOrStolen, unloadCard, unfreezeCard,
  updateCardControls, updateCardLimits,
} from "../../services/exaCardApi";

function money(value, currency = "USD") {
  const amount = Number(value || 0);
  return `${currency} ${Number.isFinite(amount) ? amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "0.00"}`;
}

function idem(prefix) { return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2)}`; }

export default function ExaCardPage({ onBack }) {
  const { request } = useAuth();
  const [products, setProducts] = useState([]);
  const [cards, setCards] = useState([]);
  const [selectedCardUuid, setSelectedCardUuid] = useState("");
  const [selectedProduct, setSelectedProduct] = useState("USD_VIRTUAL");
  const [transactions, setTransactions] = useState([]);
  const [authorizations, setAuthorizations] = useState([]);
  const [amount, setAmount] = useState("50");
  const [sourceAsset, setSourceAsset] = useState("USD");
  const [quote, setQuote] = useState(null);
  const [dailyLimit, setDailyLimit] = useState("");
  const [busy, setBusy] = useState("");
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [sheet, setSheet] = useState("");
  const [selectedActivity, setSelectedActivity] = useState(null);
  const [unloadAmount, setUnloadAmount] = useState("");
  const [activityFilter, setActivityFilter] = useState("All");
  const [realtimeState, setRealtimeState] = useState("connecting");
  const selectedCard = useMemo(() => cards.find((card) => card.card_uuid === selectedCardUuid) || cards[0], [cards, selectedCardUuid]);
  const product = useMemo(() => products.find((item) => item.product_code === selectedProduct) || products[0], [products, selectedProduct]);
  const available = selectedCard?.balance?.available;
  const pending = authorizations.filter((item) => !["DECLINED", "COMPLETED", "CANCELED"].includes(String(item.status).toUpperCase()));
  const activity = [...transactions, ...pending.map((item) => ({ ...item, transaction_uuid: item.authorization_uuid, billing_amount: item.amount, billing_currency: item.currency, type: "AUTHORIZATION" }))].sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
  const filteredActivity = activityFilter === "All" ? activity : activity.filter((item) => String(item.status).toUpperCase() === activityFilter.toUpperCase());

  useEffect(() => {
    let mounted = true;
    Promise.all([getCardProducts(request), getCards(request)]).then(([catalog, result]) => {
      if (!mounted) return;
      setProducts(catalog.products || []);
      setCards(Array.isArray(result) ? result : []);
      setSelectedCardUuid((Array.isArray(result) && result[0]?.card_uuid) || "");
    }).catch((loadError) => mounted && setError(loadError.message || "We couldn't load your ExaCard."));
    return () => { mounted = false; };
  }, [request]);

  useEffect(() => {
    if (!selectedCard?.card_uuid) return undefined;
    let mounted = true;
    Promise.all([getCardTransactions(request, selectedCard.card_uuid), getCardAuthorizations(request, selectedCard.card_uuid)]).then(([tx, auths]) => {
      if (mounted) { setTransactions(Array.isArray(tx) ? tx : []); setAuthorizations(Array.isArray(auths) ? auths : []); setDailyLimit(selectedCard.limits?.daily ? String(selectedCard.limits.daily) : ""); }
    }).catch(() => mounted && setError("Couldn't load card activity."));
    return () => { mounted = false; };
  }, [request, selectedCard?.card_uuid]);

  useEffect(() => {
    let mounted = true;
    const poll = async () => {
      try { const replay = await getCardRealtimeReplay(request, 0); if (mounted) setRealtimeState(replay.reconcile_required ? "delayed" : "live"); }
      catch { if (mounted) setRealtimeState("delayed"); }
    };
    poll();
    const timer = window.setInterval(poll, 15000);
    return () => { mounted = false; window.clearInterval(timer); };
  }, [request]);

  async function refreshCards() {
    const next = await getCards(request);
    setCards(Array.isArray(next) ? next : []);
  }
  async function runAction(name, action, success) {
    setBusy(name); setError(""); setNotice("");
    try { await action(); setNotice(success); await refreshCards(); }
    catch (actionError) { setError(actionError.message || "The card action could not be completed."); }
    finally { setBusy(""); }
  }
  async function handleIssue() {
    await runAction("issue", async () => { const card = await issueCard(request, { productCode: selectedProduct, idempotencyKey: idem("issue-card") }); setSelectedCardUuid(card.card_uuid); }, "Your ExaCard is ready.");
  }
  async function handleQuote() {
    if (!selectedCard) return;
    setBusy("quote"); setError("");
    try { setQuote(await createFundingQuote(request, selectedCard.card_uuid, { sourceAsset, amount })); }
    catch (quoteError) { setError(quoteError.message || "We couldn't create a funding quote."); }
    finally { setBusy(""); }
  }
  async function handleFund() {
    if (!quote) return;
    await runAction("fund", async () => { await fundCard(request, { quoteUuid: quote.quote_uuid, idempotencyKey: idem("fund-card") }); setQuote(null); setSheet(""); }, "Funding submitted.");
  }
  async function toggleFreeze() {
    if (!selectedCard) return;
    const frozen = ["FROZEN", "BLOCKED"].includes(String(selectedCard.status).toUpperCase());
    if (!frozen && !window.confirm("Freeze this card? New card payments will be declined until you unfreeze it.")) return;
    await runAction("freeze", () => frozen ? unfreezeCard(request, selectedCard.card_uuid, "User requested unfreeze.") : freezeCard(request, selectedCard.card_uuid, "User requested temporary freeze."), frozen ? "Card unfrozen." : "Card frozen.");
  }
  async function showDetails() {
    if (!selectedCard) return;
    await runAction("details", () => getCardDetailsToken(request, selectedCard.card_uuid), "Secure card details requested. Follow the provider's secure display flow.");
  }
  async function handleUnload() {
    if (!selectedCard || !unloadAmount) return;
    await runAction("unload", () => unloadCard(request, selectedCard.card_uuid, { amount: unloadAmount, idempotencyKey: idem("unload-card") }), "Unload submitted.");
    setUnloadAmount("");
    setSheet("");
  }

  return <main className="min-h-screen bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
    <header className="border-b border-[var(--exa-border)] bg-[var(--exa-surface)]/90 backdrop-blur">
      <div className="mx-auto flex max-w-6xl items-center gap-3 px-4 py-4 sm:px-6">
        <button type="button" onClick={onBack} aria-label="Back" className="icon-button"><ArrowLeft className="h-5 w-5" /></button>
        <div><p className="eyebrow">ExaCard</p><h1 className="text-xl font-semibold">Your card</h1></div>
        {selectedCard && <span className={`ml-auto status-badge ${selectedCard.status === "ACTIVE" ? "status-good" : "status-warn"}`}>{selectedCard.status}</span>}
      </div>
    </header>
    <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:py-10">
      {error && <div role="alert" className="mb-5 flex items-center justify-between rounded-xl border border-red-400/25 bg-red-400/10 p-4 text-sm text-red-100"><span>{error}</span><button type="button" onClick={() => setError("")} aria-label="Dismiss error"><X className="h-4 w-4" /></button></div>}
      {notice && <div role="status" className="mb-5 rounded-xl border border-emerald-400/25 bg-emerald-400/10 p-4 text-sm text-emerald-100">{notice}</div>}
      {!selectedCard ? <EmptyState product={product} onIssue={handleIssue} busy={busy === "issue"} onProduct={setSelectedProduct} products={products} /> : <>
        <section className="grid items-start gap-8 lg:grid-cols-[minmax(0,1.1fr)_minmax(300px,.9fr)]">
          <CardVisual card={selectedCard} frozen={["FROZEN", "BLOCKED"].includes(String(selectedCard.status).toUpperCase())} />
          <div className="space-y-5 lg:pt-5">
            <div><p className="eyebrow">Available to spend</p><p className="balance-value">{available == null ? "--" : money(available, selectedCard.currency)}</p><p className="mt-2 text-sm text-[var(--exa-text-secondary)]">{selectedCard.type === "VIRTUAL" ? "Virtual ExaCard" : "ExaCard"} <span className="mx-2 text-[var(--exa-text-muted)]">•</span> {selectedCard.currency}</p></div>
            <div className="grid grid-cols-2 gap-3"><Stat label="Pending" value={money(pending.reduce((sum, item) => sum + Number(item.amount || 0), 0), selectedCard.currency)} /><Stat label="Card balance" value={money(selectedCard.balance?.total ?? available, selectedCard.currency)} /></div>
            <div className="quick-actions"><QuickAction label="Fund" icon={CircleDollarSign} onClick={() => setSheet("fund")} /><QuickAction label="Details" icon={Eye} onClick={showDetails} /><QuickAction label={String(selectedCard.status).toUpperCase() === "FROZEN" ? "Unfreeze" : "Freeze"} icon={LockKeyhole} onClick={toggleFreeze} /><QuickAction label="More" icon={MoreHorizontal} onClick={() => setSheet("more")} /></div>
          </div>
        </section>
        <section className="mt-10 grid gap-6 lg:grid-cols-[1.25fr_.75fr]">
          <Panel><div className="section-heading"><div><p className="eyebrow">Activity</p><h2>Recent activity</h2></div><button type="button" className="text-button">View all <ChevronRight className="h-4 w-4" /></button></div><div className="filter-row">{["All", "Completed", "Pending", "Declined"].map((filter) => <button type="button" key={filter} onClick={() => setActivityFilter(filter)} className={activityFilter === filter ? "filter-active" : "filter-button"}>{filter}</button>)}</div><div className="mt-3">{filteredActivity.length ? filteredActivity.slice(0, 8).map((item) => <ActivityRow key={item.transaction_uuid || item.authorization_uuid} item={item} currency={selectedCard.currency} onClick={() => setSelectedActivity(item)} />) : <EmptyInline text="Your card activity will appear here." />}</div></Panel>
          <div className="space-y-6"><Panel><div className="section-heading"><div><p className="eyebrow">Protection</p><h2>Card controls</h2></div><ShieldCheck className="h-5 w-5 text-[var(--exa-gold-light)]" /></div><Control label="Online payments" enabled={selectedCard.controls?.online} onClick={() => runAction("controls", () => updateCardControls(request, selectedCard.card_uuid, { online: !selectedCard.controls?.online }), "Card control updated.")} busy={busy === "controls"} /><Control label="International payments" enabled={selectedCard.controls?.international} onClick={() => runAction("controls", () => updateCardControls(request, selectedCard.card_uuid, { international: !selectedCard.controls?.international }), "Card control updated.")} busy={busy === "controls"} /><Control label="ATM withdrawals" enabled={selectedCard.controls?.atm} onClick={() => runAction("controls", () => updateCardControls(request, selectedCard.card_uuid, { atm: !selectedCard.controls?.atm }), "Card control updated.")} busy={busy === "controls"} /></Panel><Panel><div className="section-heading"><div><p className="eyebrow">Guardrails</p><h2>Spending limits</h2></div><SlidersHorizontal className="h-5 w-5 text-[var(--exa-gold-light)]" /></div><Limit label="Daily spending" value={selectedCard.limits?.daily} max={selectedCard.limits?.daily} /><Limit label="Monthly spending" value={selectedCard.limits?.monthly} max={selectedCard.limits?.monthly} /><div className="mt-4 flex gap-2"><input aria-label="Daily spending limit" value={dailyLimit} onChange={(event) => setDailyLimit(event.target.value)} placeholder="Daily limit" className="field" /><button type="button" onClick={() => runAction("limits", () => updateCardLimits(request, selectedCard.card_uuid, { daily: dailyLimit }), "Spending limit updated.")} disabled={!dailyLimit || busy === "limits"} className="gold-button px-3">Save</button></div></Panel></div>
        </section>
        {realtimeState === "delayed" && <p className="mt-5 text-sm text-amber-200">Card activity may be temporarily delayed.</p>}
      </>}
    </div>
    {sheet === "fund" && <FundSheet amount={amount} setAmount={setAmount} sourceAsset={sourceAsset} setSourceAsset={setSourceAsset} quote={quote} onQuote={handleQuote} onFund={handleFund} busy={busy} onClose={() => { setSheet(""); setQuote(null); }} />}
    {sheet === "more" && <MoreSheet onClose={() => setSheet("")} onLost={() => runAction("lost", () => reportCardLostOrStolen(request, selectedCard.card_uuid, "User reported card lost or stolen."), "Card blocked and report recorded.")} onUnload={() => setSheet("unload")} />}
    {sheet === "unload" && <UnloadSheet amount={unloadAmount} setAmount={setUnloadAmount} onClose={() => setSheet("")} onUnload={handleUnload} busy={busy === "unload"} />}
    {selectedActivity && <ActivityDetails item={selectedActivity} currency={selectedCard?.currency} onClose={() => setSelectedActivity(null)} />}
  </main>;
}

function CardVisual({ card, frozen }) { return <div className={`exa-card-visual ${frozen ? "exa-card-frozen" : ""}`}><div className="flex items-start justify-between"><span className="font-semibold tracking-[.18em]">EXAEARN</span><Sparkles className="h-5 w-5 text-[var(--exa-gold)]" /></div><div className="mt-12"><div className="chip" /><p className="mt-5 text-lg tracking-[.22em]">•••• •••• •••• {card.last_four || "----"}</p></div><div className="mt-10 flex items-end justify-between"><div><p className="card-label">Cardholder</p><p className="mt-1 font-semibold uppercase">{card.nickname || "ExaEarn member"}</p></div><div><p className="card-label">Valid thru</p><p className="mt-1 font-semibold">{card.expiry_month && card.expiry_year ? `${String(card.expiry_month).padStart(2, "0")}/${String(card.expiry_year).slice(-2)}` : "--/--"}</p></div><span className="text-xs font-semibold tracking-[.16em]">{card.type || "VIRTUAL"}</span></div>{frozen && <div className="absolute inset-0 flex items-center justify-center rounded-3xl bg-black/45 text-sm font-semibold tracking-[.16em] text-white">CARD FROZEN</div>}</div>; }
function EmptyState({ product, products, onProduct, onIssue, busy }) { return <section className="mx-auto max-w-4xl py-4 lg:py-12"><div className="grid items-center gap-8 lg:grid-cols-[.85fr_1.15fr]"><CardVisual card={{ type: "VIRTUAL", last_four: null, nickname: "Your name", expiry_month: null, expiry_year: null }} /><div><p className="eyebrow">ExaCard</p><h2 className="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Your ExaCard is waiting.</h2><p className="mt-4 max-w-md text-[var(--exa-text-secondary)]">Spend your ExaEarn balance wherever your ExaCard is supported.</p><select aria-label="Card product" value={product?.product_code || ""} onChange={(event) => onProduct(event.target.value)} className="field mt-6 max-w-xs">{products.filter((item) => item.enabled).map((item) => <option key={item.product_code} value={item.product_code}>{item.currency} virtual card</option>)}</select><button type="button" onClick={onIssue} disabled={busy || !product?.enabled} className="gold-button mt-4">{busy ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <CreditCard className="h-4 w-4" />} Get ExaCard</button><p className="mt-5 text-xs text-[var(--exa-text-muted)]">Eligibility and verification requirements apply.</p></div></div></section>; }
function FundSheet({ amount, setAmount, sourceAsset, setSourceAsset, quote, onQuote, onFund, busy, onClose }) { return <Modal title="Fund ExaCard" onClose={onClose}><div className="grid gap-4 sm:grid-cols-2"><label className="label">From<select value={sourceAsset} onChange={(event) => setSourceAsset(event.target.value)} className="field mt-2"><option>USD</option><option>USDT</option><option>USDC</option></select></label><label className="label">Amount<input type="number" min="0" value={amount} onChange={(event) => setAmount(event.target.value)} className="field mt-2" /></label></div><div className="mt-3 flex gap-2">{["50", "100", "250", "500"].map((value) => <button type="button" key={value} onClick={() => setAmount(value)} className="filter-button">${value}</button>)}</div>{quote ? <div className="summary-box mt-6"><Line label="You receive" value={money(quote.card_amount, quote.card_currency)} /><Line label="Fee" value={money(Number(quote.card_fee || 0) + Number(quote.provider_fee || 0), quote.source_asset)} /><Line label="Total" value={money(quote.total_debit, quote.source_asset)} />{quote.expires_at && <p className="mt-3 text-xs text-[var(--exa-text-muted)]">Quote expires {new Date(quote.expires_at).toLocaleTimeString()}</p>}<button type="button" onClick={onFund} disabled={busy === "fund"} className="gold-button mt-5 w-full">{busy === "fund" ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <ShieldCheck className="h-4 w-4" />} Fund Card</button></div> : <button type="button" onClick={onQuote} disabled={busy === "quote"} className="gold-button mt-6 w-full">{busy === "quote" ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <SlidersHorizontal className="h-4 w-4" />} Review funding</button>}</Modal>; }
function MoreSheet({ onClose, onLost, onUnload }) { return <Modal title="Card management" onClose={onClose}><button type="button" onClick={onUnload} className="management-row"><WalletCards className="h-5 w-5" /> Unload card balance <ChevronRight className="ml-auto h-4 w-4" /></button><button type="button" onClick={onLost} className="management-row text-red-200"><ShieldAlert className="h-5 w-5" /> Report lost or stolen <ChevronRight className="ml-auto h-4 w-4" /></button></Modal>; }
function Modal({ title, children, onClose }) { return <div className="modal-backdrop" role="presentation"><div role="dialog" aria-modal="true" aria-labelledby="modal-title" className="modal-sheet"><div className="section-heading"><h2 id="modal-title">{title}</h2><button type="button" onClick={onClose} className="icon-button" aria-label="Close"><X className="h-5 w-5" /></button></div>{children}</div></div>; }
function Panel({ children }) { return <section className="surface-panel">{children}</section>; }
function Stat({ label, value }) { return <div className="stat-box"><p>{label}</p><strong>{value}</strong></div>; }
function QuickAction({ label, icon: Icon, onClick }) { return <button type="button" onClick={onClick} className="quick-action"><span><Icon className="h-5 w-5" /></span><small>{label}</small></button>; }
function Control({ label, enabled, onClick, busy }) { return <div className="control-row"><span>{label}<small>{enabled ? "Enabled" : "Disabled"}</small></span><button type="button" aria-label={`${label}: ${enabled ? "Enabled" : "Disabled"}`} onClick={onClick} disabled={busy} className={`toggle ${enabled ? "toggle-on" : ""}`}><span /></button></div>; }
function Limit({ label, value, max }) { const numeric = Number(value || 0); return <div className="mb-4"><div className="flex justify-between text-sm"><span className="text-[var(--exa-text-secondary)]">{label}</span><strong>{value ? money(value) : "Not set"}</strong></div><div className="limit-track"><span style={{ width: `${max ? Math.min(100, numeric / Number(max) * 100) : 0}%` }} /></div></div>; }
function ActivityRow({ item, currency, onClick }) { const positive = String(item.type).toUpperCase().includes("FUND") || Number(item.billing_amount || item.amount || 0) < 0; return <button type="button" onClick={onClick} className="activity-row w-full text-left"><span className="merchant-icon"><CreditCard className="h-4 w-4" /></span><span className="min-w-0 flex-1"><strong className="block truncate">{item.merchant || (item.type === "AUTHORIZATION" ? "Card verification" : "Card activity")}</strong><small>{item.created_at ? new Date(item.created_at).toLocaleDateString() : "Recently"}</small></span><span className="text-right"><strong className={positive ? "text-emerald-300" : ""}>{positive ? "+" : "-"}{money(Math.abs(Number(item.billing_amount || item.amount || 0)), item.billing_currency || item.currency || currency)}</strong><small className="block">{item.status}</small></span></button>; }
function UnloadSheet({ amount, setAmount, onClose, onUnload, busy }) { return <Modal title="Unload card balance" onClose={onClose}><label className="label">Amount<input type="number" min="0" value={amount} onChange={(event) => setAmount(event.target.value)} className="field mt-2" placeholder="Amount to return" /></label><p className="mt-3 text-sm text-[var(--exa-text-secondary)]">Funds return to your ExaEarn funding wallet.</p><button type="button" onClick={onUnload} disabled={!amount || busy} className="gold-button mt-6 w-full">{busy ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <WalletCards className="h-4 w-4" />} Unload balance</button></Modal>; }
function ActivityDetails({ item, currency, onClose }) { const amount = Number(item.billing_amount || item.amount || 0); return <Modal title={item.merchant || (item.type === "AUTHORIZATION" ? "Card verification" : "Card activity")} onClose={onClose}><div className="space-y-3"><Line label="Amount" value={money(Math.abs(amount), item.billing_currency || item.currency || currency)} /><Line label="Status" value={item.status || "Unknown"} /><Line label="Date" value={item.created_at ? new Date(item.created_at).toLocaleString() : "Unavailable"} />{item.transaction_uuid && <Line label="Reference" value={item.transaction_uuid} />}</div></Modal>; }
function EmptyInline({ text }) { return <div className="empty-inline"><CreditCard className="mx-auto mb-2 h-6 w-6" /><p>{text}</p></div>; }
function Line({ label, value }) { return <div className="flex justify-between gap-3 text-sm"><span className="text-[var(--exa-text-secondary)]">{label}</span><strong>{value}</strong></div>; }
