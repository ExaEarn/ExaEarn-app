import { memo, useEffect, useMemo, useRef, useState } from "react";
import { AlertCircle, ArrowDownLeft, ArrowUpRight, BarChart3, Bell, Bot, Check, ChevronLeft, ChevronRight, Copy, CopyIcon, CreditCard, Eye, EyeOff, HandCoins, Landmark, MoreHorizontal, RefreshCw, Search, Settings, ShieldCheck, Sparkles, Wallet, X } from "lucide-react";
import ProfileIdentity from "../../components/profile/ProfileIdentity";
import "./universalHome.css";
import "./homeBrand.css";

const MARKET_TABS = ["Hot", "Gainers", "Losers"];
const ASSET_LOGOS = {
  BTC: "https://assets.coincap.io/assets/icons/btc@2x.png",
  ETH: "https://assets.coincap.io/assets/icons/eth@2x.png",
  SOL: "https://assets.coincap.io/assets/icons/sol@2x.png",
  USDT: "https://assets.coincap.io/assets/icons/usdt@2x.png",
  BNB: "https://assets.coincap.io/assets/icons/bnb@2x.png",
  XRP: "https://assets.coincap.io/assets/icons/xrp@2x.png",
};

function formatMoney(value, currency = "USDT") {
  const amount = Number(value);
  if (!Number.isFinite(amount)) return "--";
  return `${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
}

function formatPrice(value) {
  const amount = Number(value);
  if (!Number.isFinite(amount) || amount <= 0) return "--";
  return amount.toLocaleString(undefined, { minimumFractionDigits: amount < 1 ? 4 : 2, maximumFractionDigits: amount < 1 ? 6 : 2 });
}

const QuickAction = memo(function QuickAction({ icon: Icon, label, onClick }) {
  return <button type="button" className="uh-quick-action" onClick={onClick}><span><Icon size={17} aria-hidden="true" /></span><b>{label}</b></button>;
});

function MarketRows({ pairs, filter, onOpen, onRetry }) {
  const markets = useMemo(() => {
    const valid = pairs.map((item) => {
      const pair = item.pair || [item.base, item.quote].filter(Boolean).join("/");
      const [base, quote] = pair.split("/");
      return { ...item, pair, base: item.base || base, quote: item.quote || quote || "USDT" };
    }).filter((item) => item.pair && Number.isFinite(Number(item.last)) && Number.isFinite(Number(item.change24h)));
    const preferred = ["BTC", "ETH", "SOL"].map((symbol) => valid.find((item) => item.base === symbol)).filter(Boolean);
    const candidates = filter === "Hot" && preferred.length ? preferred : [...valid];
    candidates.sort((left, right) => filter === "Gainers"
      ? Number(right.change24h) - Number(left.change24h)
      : filter === "Losers"
        ? Number(left.change24h) - Number(right.change24h)
        : Number(right.volume || 0) - Number(left.volume || 0));
    return candidates.slice(0, 5);
  }, [filter, pairs]);

  if (!markets.length) return <div className="uh-empty"><AlertCircle size={14} /><span>Market data unavailable</span><button type="button" onClick={onRetry}>Retry</button></div>;
  return markets.map((item) => {
    const change = Number(item.change24h);
    const positive = change >= 0;
    return (
      <button className="uh-market-row" type="button" key={item.pair} onClick={() => onOpen("trade", item.pair)}>
        <span className="uh-asset-icon" aria-hidden="true"><img src={item.logo_url || item.icon_url || ASSET_LOGOS[item.base]} alt="" onError={(event) => { event.currentTarget.hidden = true; event.currentTarget.nextElementSibling.hidden = false; }} /><b hidden>{item.base.slice(0, 1)}</b></span>
        <span className="uh-market-symbol"><strong>{item.base}</strong><small>{item.base}/{item.quote}</small></span>
        <span className={`uh-spark ${positive ? "up" : "down"}`} aria-hidden="true"><i /><i /><i /><i /><i /></span>
        <span className="uh-market-values"><strong>${formatPrice(item.last)}</strong><small className={positive ? "is-positive" : "is-negative"}>{positive ? "+" : ""}{change.toFixed(2)}%</small></span>
      </button>
    );
  });
}

function FeatureCard({ icon: Icon, title, description, tone, badge, onClick, disabled }) {
  return (
    <button type="button" className={`uh-feature-card ${tone || ""}`} onClick={onClick} disabled={disabled} aria-disabled={disabled || undefined}>
      <span className="uh-feature-icon"><Icon size={18} aria-hidden="true" /></span>
      <span><strong>{title}</strong><small>{description}</small></span>
      {badge ? <em>{badge}</em> : <ChevronRight size={15} aria-hidden="true" />}
    </button>
  );
}

function ContentCarousel({ items, loading, onNavigate, onInteract, onOpenFeed }) {
  const [index, setIndex] = useState(0);
  const startX = useRef(null);
  useEffect(() => { if (index >= items.length) setIndex(0); }, [index, items.length]);
  useEffect(() => { if (items[index]) onInteract(items[index], "impression", { position: index }); }, [index, items, onInteract]);
  if (loading) return <div className="uh-promotion-skeleton" aria-label="Loading recommendations" />;
  if (!items.length) return null;
  const item = items[index];
  const move = (direction) => setIndex((current) => (current + direction + items.length) % items.length);
  const open = () => { onInteract(item, "click", { position: index }); if (item.cta_route) onNavigate(item.cta_route, item.cta_payload); };
  return <section className="uh-content-carousel" aria-roledescription="carousel" aria-label="Recommended content" onTouchStart={(event) => { startX.current = event.touches[0].clientX; }} onTouchEnd={(event) => { if (startX.current == null) return; const delta = event.changedTouches[0].clientX - startX.current; if (Math.abs(delta) > 45) move(delta > 0 ? -1 : 1); startX.current = null; }}>
    <article className="uh-promotion">
      <button type="button" className="uh-promotion-main" onClick={open}><span><small>{item.badge || item.type?.replaceAll("_", " ") || "FOR YOU"}</small><strong>{item.title}</strong>{item.subtitle || item.body ? <em>{item.subtitle || item.body}</em> : null}</span><b>{item.cta_label || "View Details"}<ChevronRight size={13} /></b></button>
      <button type="button" className="uh-promotion-menu" onClick={() => onInteract(item, "dismiss", { position: index })} aria-label="Hide this recommendation"><MoreHorizontal size={17} /></button>
    </article>
    <footer><div>{items.map((entry, itemIndex) => <button type="button" key={entry.id} className={itemIndex === index ? "active" : ""} onClick={() => setIndex(itemIndex)} aria-label={`Show recommendation ${itemIndex + 1}`} aria-current={itemIndex === index ? "true" : undefined} />)}</div><span>{items.length > 1 ? <><button type="button" onClick={() => move(-1)} aria-label="Previous"><ChevronLeft /></button><button type="button" onClick={() => move(1)} aria-label="Next"><ChevronRight /></button></> : null}<button type="button" onClick={onOpenFeed}>For You</button></span></footer>
  </section>;
}

export default function UniversalHome({
  user, apiBaseUrl, portfolioValue, portfolioCurrency, portfolioDayPnl, portfolioDayPnlPercent, portfolioLoading, portfolioError, onRetryPortfolio,
  markets, marketOffline, marketLoading, notifications, notificationLoading,
  notificationOpen, unreadNotificationCount, onToggleNotifications, onCloseNotifications,
  onOpenNotification, onNavigate, personalizedContent, personalizedContentLoading, onContentInteraction, criticalAlerts, earnApy, onRetryMarkets, configuration,
}) {
  const [balanceVisible, setBalanceVisible] = useState(true);
  const [marketFilter, setMarketFilter] = useState("Hot");
  const [uidCopied, setUidCopied] = useState(false);
  const uid = user?.unique_user_id || user?.uid || user?.id || "Pending";
  const copyUid = async () => {
    if (uid === "Pending") return;
    await navigator.clipboard?.writeText(String(uid));
    setUidCopied(true);
    window.setTimeout(() => setUidCopied(false), 1400);
  };

  return (
    <main className={`universal-home mode-${configuration?.experienceMode === "pro" ? "pro" : "lite"}`}>
      <header className="uh-header">
        <div className="uh-identity">
          <button type="button" className="uh-avatar" onClick={() => onNavigate("profileAppearance")} aria-label="Open profile"><ProfileIdentity user={user} apiBaseUrl={apiBaseUrl} size="sm" alt="Profile" /></button>
          <div><small>ExaEarn UID</small><button type="button" onClick={copyUid} aria-label="Copy ExaEarn UID"><strong>{uid}</strong>{uidCopied ? <Check size={12} /> : <CopyIcon size={12} />}</button></div>
        </div>
        <div className="uh-header-actions">
          <button type="button" onClick={() => onNavigate("market")} aria-label="Search markets"><Search size={18} /></button>
          <div className="uh-notification-wrap">
            <button type="button" onClick={onToggleNotifications} aria-label="Open notifications" aria-expanded={notificationOpen}>
              <Bell size={18} />{unreadNotificationCount ? <span>{unreadNotificationCount > 9 ? "9+" : unreadNotificationCount}</span> : null}
            </button>
            {notificationOpen ? (
              <section className="uh-notification-tray" aria-label="Notifications">
                <header><strong>Notifications</strong><button type="button" onClick={onCloseNotifications} aria-label="Close notifications"><X size={16} /></button></header>
                {notificationLoading ? <p>Loading notifications...</p> : notifications.length ? notifications.slice(0, 6).map((item) => (
                  <button type="button" key={item.id} onClick={() => onOpenNotification(item)}><strong>{item.title || "Account update"}</strong><small>{item.message || "Open to view details."}</small></button>
                )) : <p>You are all caught up.</p>}
              </section>
            ) : null}
          </div>
          <button type="button" onClick={() => onNavigate("settings")} aria-label="Open settings"><Settings size={18} /></button>
        </div>
      </header>

      <section className="uh-portfolio" aria-label="Portfolio overview">
        <div className="uh-portfolio-heading">
          <span>Total Portfolio Value</span>
          <button type="button" onClick={() => setBalanceVisible((visible) => !visible)} aria-label={balanceVisible ? "Hide balance" : "Show balance"}>{balanceVisible ? <Eye size={16} /> : <EyeOff size={16} />}</button>
        </div>
        <div className="uh-portfolio-core">
          <div>
            {portfolioLoading ? <span className="uh-balance-skeleton" /> : <div className="uh-balance">{balanceVisible ? formatMoney(portfolioValue, portfolioCurrency) : "••••••"}</div>}
            <div className={`uh-day-pnl ${Number(portfolioDayPnl) < 0 ? "negative" : ""}`}><span>Today's PnL</span><strong>{portfolioDayPnl == null ? "Unavailable" : `${Number(portfolioDayPnl) >= 0 ? "+" : ""}${formatMoney(portfolioDayPnl, portfolioCurrency)}`}</strong>{portfolioDayPnlPercent != null ? <small>{Number(portfolioDayPnlPercent) >= 0 ? "+" : ""}{Number(portfolioDayPnlPercent).toFixed(2)}%</small> : null}</div>
          </div>
          <button type="button" className="uh-assets-link" onClick={() => onNavigate("assets")}>Assets <ChevronRight size={13} /></button>
        </div>
        {portfolioError ? <button type="button" className="uh-inline-error" onClick={onRetryPortfolio}>Portfolio unavailable · Retry</button> : null}
      </section>

      <nav className="uh-actions" aria-label="Primary financial actions">
        <QuickAction icon={ArrowDownLeft} label="Deposit" onClick={() => onNavigate("addFunds")} />
        <QuickAction icon={ArrowUpRight} label="Withdraw" onClick={() => onNavigate("withdraw")} />
        <QuickAction icon={RefreshCw} label="Convert" onClick={() => onNavigate("swap")} />
        <QuickAction icon={Wallet} label="Transfer" onClick={() => onNavigate("send")} />
      </nav>

      {criticalAlerts.length ? <button type="button" className="uh-alert" onClick={() => onNavigate(criticalAlerts[0].kind === "security" ? "settings" : "transactions")}><ShieldCheck size={16} /><span><strong>{criticalAlerts[0].title}</strong><small>{criticalAlerts[0].message}</small></span><ChevronRight size={14} /></button> : null}

      <section className="uh-module uh-buy-trade">
        <div className="uh-module-head"><h2>Buy & Trade</h2></div>
        <div className="uh-trade-grid">
          <FeatureCard icon={Landmark} title="Buy Crypto" description="Bank, Card, P2P" tone="gold" onClick={() => onNavigate("addFunds")} />
          <FeatureCard icon={BarChart3} title="Trade Crypto" description="Spot, Futures & more" tone="violet" onClick={() => onNavigate("trade")} />
        </div>
      </section>

      <section className="uh-module uh-markets">
        <div className="uh-module-head"><h2>Markets</h2><button type="button" onClick={() => onNavigate("market")}>View Markets <ChevronRight size={13} /></button></div>
        <div className="uh-market-tabs" role="tablist" aria-label="Market filters">
          {MARKET_TABS.map((tab) => <button type="button" role="tab" aria-selected={marketFilter === tab} className={marketFilter === tab ? "active" : ""} key={tab} onClick={() => setMarketFilter(tab)}>{tab}</button>)}
          {marketOffline ? <em>Delayed</em> : null}
        </div>
        {marketLoading ? <div className="uh-market-loading"><i /><i /><i /></div> : <MarketRows pairs={markets} filter={marketFilter} onOpen={onNavigate} onRetry={onRetryMarkets} />}
      </section>

      <section className="uh-module uh-smart">
        <div className="uh-module-head"><h2>Smart Trading</h2></div>
        <div className="uh-feature-grid">
          <FeatureCard icon={Bot} title="ExaAI" description="Market intelligence" tone="cyan" badge="BETA" onClick={() => onNavigate("aiAssistant")} />
          <FeatureCard icon={Copy} title="Copy Trading" description="Eligible strategies" tone="purple" badge="PRIVATE BETA" disabled />
        </div>
      </section>

      <section className="uh-module uh-services">
        <div className="uh-module-head"><h2>Earn & More</h2><button type="button" onClick={() => onNavigate("more")}>All Services <ChevronRight size={13} /></button></div>
        <div className="uh-service-grid">
          <button type="button" onClick={() => onNavigate("staking")}><HandCoins size={18} /><strong>Earn</strong><small>{earnApy ? `Up to ${earnApy}% APY` : "Explore products"}</small></button>
          <button type="button" onClick={() => onNavigate("addFunds")}><Wallet size={18} /><strong>ExaPay</strong><small>Fast payments</small></button>
          <button type="button" onClick={() => onNavigate("exacard")}><CreditCard size={18} /><strong>ExaCard</strong><small>Spend eligible funds</small></button>
          <button type="button" onClick={() => onNavigate("more")}><Sparkles size={18} /><strong>More</strong><small>All services</small></button>
        </div>
      </section>

      <ContentCarousel items={personalizedContent || []} loading={personalizedContentLoading} onNavigate={onNavigate} onInteract={onContentInteraction} onOpenFeed={() => onNavigate("forYou")} />
    </main>
  );
}
