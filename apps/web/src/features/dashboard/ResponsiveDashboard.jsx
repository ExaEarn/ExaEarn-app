import { memo, useMemo, useState } from "react";
import {
  ArrowDownLeft,
  ArrowRight,
  ArrowUpRight,
  BarChart3,
  Bell,
  Bot,
  ChevronDown,
  ChevronRight,
  CircleDollarSign,
  Coins,
  Copy,
  CreditCard,
  Eye,
  EyeOff,
  HandCoins,
  Landmark,
  MoreHorizontal,
  RefreshCw,
  Search,
  Settings,
  Sparkles,
  TrendingDown,
  TrendingUp,
  User,
  Wallet,
} from "lucide-react";
import exaearnLogo from "../../assets/images/exaearn-logo.png";
import "./responsiveDashboard.css";

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
  return amount.toLocaleString(undefined, {
    minimumFractionDigits: amount < 1 ? 4 : 2,
    maximumFractionDigits: amount < 1 ? 6 : 2,
  });
}

function normalizeMarket(item) {
  const pair = item.pair || [item.base, item.quote].filter(Boolean).join("/");
  const [base, quote] = pair.split("/");
  return {
    ...item,
    pair,
    base: item.base || base,
    quote: item.quote || quote || "USDT",
    last: Number(item.last),
    change24h: Number(item.change24h),
    volume: Number(item.volume || item.volume_24h || item.quote_volume_24h || 0),
  };
}

const DesktopNavMenu = memo(function DesktopNavMenu({ label, items, open, onToggle, onNavigate }) {
  return (
    <div className="rd-nav-menu">
      <button type="button" className="rd-nav-link" onClick={onToggle} aria-expanded={open}>
        {label} <ChevronDown size={14} />
      </button>
      {open ? (
        <div className="rd-dropdown">
          {items.map((item) => (
            <button type="button" key={item.route} onClick={() => onNavigate(item.route)}>
              <item.icon size={18} />
              <span>
                <strong>{item.title}</strong>
                <small>{item.description}</small>
              </span>
            </button>
          ))}
        </div>
      ) : null}
    </div>
  );
});

const MarketTable = memo(function MarketTable({ markets, loading, offline, onRetry, onNavigate }) {
  const [tab, setTab] = useState("Hot");
  const rows = useMemo(() => {
    const valid = (markets || [])
      .map(normalizeMarket)
      .filter((item) => item.pair && Number.isFinite(item.last) && Number.isFinite(item.change24h));

    const filtered = [...valid].sort((left, right) => {
      if (tab === "Favorites") return Number(right.favorite || 0) - Number(left.favorite || 0);
      if (tab === "Gainers") return right.change24h - left.change24h;
      if (tab === "Losers") return left.change24h - right.change24h;
      if (tab === "New") return String(right.created_at || "").localeCompare(String(left.created_at || ""));
      return right.volume - left.volume;
    });

    return filtered.slice(0, 10);
  }, [markets, tab]);

  return (
    <section className="rd-card rd-live-markets">
      <header className="rd-section-head">
        <div>
          <span>Live Markets</span>
          <h2>Markets moving now</h2>
        </div>
        <button type="button" onClick={() => onNavigate("market")}>
          View All Markets <ChevronRight size={15} />
        </button>
      </header>
      <div className="rd-tabs" role="tablist" aria-label="Market filters">
        {["Favorites", "Hot", "Gainers", "Losers", "New"].map((item) => (
          <button
            type="button"
            key={item}
            className={tab === item ? "active" : ""}
            onClick={() => setTab(item)}
            role="tab"
            aria-selected={tab === item}
          >
            {item}
          </button>
        ))}
        {offline ? <em>Reconnecting</em> : null}
      </div>
      {loading ? (
        <div className="rd-market-skeleton" aria-label="Loading markets">
          <i /><i /><i /><i /><i />
        </div>
      ) : rows.length ? (
        <div className="rd-market-table">
          <div className="rd-market-head">
            <span>Pair</span>
            <span>Last Price</span>
            <span>24h</span>
            <span>Volume</span>
            <span>Trend</span>
            <span />
          </div>
          {rows.map((item) => {
            const positive = item.change24h >= 0;
            return (
              <button type="button" className="rd-market-row" key={item.pair} onClick={() => onNavigate("trade", item.pair)}>
                <span className="rd-pair">
                  <span className="rd-asset-icon">
                    <img src={item.logo_url || item.icon_url || ASSET_LOGOS[item.base]} alt="" onError={(event) => { event.currentTarget.hidden = true; }} />
                  </span>
                  <span><strong>{item.base}/{item.quote}</strong><small>{item.base}</small></span>
                </span>
                <span>${formatPrice(item.last)}</span>
                <span className={positive ? "positive" : "negative"}>{positive ? "+" : ""}{item.change24h.toFixed(2)}%</span>
                <span>{item.volume ? `$${formatPrice(item.volume)}` : "--"}</span>
                <span className={`rd-spark ${positive ? "up" : "down"}`} aria-hidden="true"><i /><i /><i /><i /><i /></span>
                <span className="rd-trade-action">Trade</span>
              </button>
            );
          })}
        </div>
      ) : (
        <div className="rd-empty-state">
          <strong>Market data is temporarily unavailable.</strong>
          <button type="button" onClick={onRetry}>Retry</button>
        </div>
      )}
    </section>
  );
});

function ResponsiveDashboard({
  user,
  portfolioValue,
  portfolioCurrency,
  portfolioDayPnl,
  portfolioDayPnlPercent,
  portfolioLoading,
  portfolioError,
  onRetryPortfolio,
  markets,
  marketLoading,
  marketOffline,
  onRetryMarkets,
  notifications,
  unreadNotifications,
  onToggleNotifications,
  onNavigate,
  onPersonalize,
  personalizedContent,
  personalizedContentLoading,
  earnApy,
  showAiQuickLaunch = true,
  onAiAssistantOpen,
}) {
  const [openMenu, setOpenMenu] = useState(null);
  const [balanceVisible, setBalanceVisible] = useState(true);

  const normalizedMarkets = useMemo(() => (markets || []).map(normalizeMarket), [markets]);
  const watchlist = useMemo(() => {
    const favorites = normalizedMarkets.filter((item) => item.favorite);
    return (favorites.length ? favorites : normalizedMarkets).slice(0, 5);
  }, [normalizedMarkets]);
  const marketPulse = useMemo(() => {
    const valid = normalizedMarkets.filter((item) => Number.isFinite(item.change24h));
    const gainers = valid.filter((item) => item.change24h >= 0).length;
    const losers = valid.length - gainers;
    return { tracked: valid.length, gainers, losers };
  }, [normalizedMarkets]);
  const updates = (personalizedContent || []).slice(0, 3);
  const activity = (notifications || []).slice(0, 4);

  const tradeItems = [
    { route: "trade", icon: BarChart3, title: "Spot", description: "Buy and sell crypto on ExaEarn markets." },
    { route: "futures", icon: TrendingUp, title: "Futures", description: "Trade perpetual markets with risk controls." },
    { route: "swap", icon: RefreshCw, title: "Convert", description: "Swap assets through protected quotes." },
    { route: "aiAssistant", icon: Bot, title: "ExaAI Trading", description: "Open intelligent trading assistance." },
  ];
  const earnItems = [
    { route: "staking", icon: HandCoins, title: "Staking & Earn", description: "Access supported earning products." },
    { route: "rewards", icon: Coins, title: "Daily Rewards", description: "Claim eligible engagement rewards." },
  ];
  const moreItems = [
    { route: "exacard", icon: CreditCard, title: "ExaCard", description: "Manage eligible card spending." },
    { route: "giftcard", icon: CircleDollarSign, title: "Gift Cards", description: "Buy and sell supported gift cards." },
    { route: "edtech", icon: Sparkles, title: "ExaSkills", description: "Explore learning experiences." },
    { route: "more", icon: MoreHorizontal, title: "View All Services", description: "Open the ExaEarn services hub." },
  ];

  const go = (page) => {
    setOpenMenu(null);
    onNavigate?.(page);
  };

  return (
    <div className="responsive-dashboard rd-exchange-shell">
      <header className="rd-top-nav">
        <button type="button" className="rd-brand" onClick={() => go("home")} aria-label="ExaEarn home">
          <img src={exaearnLogo} alt="" />
          <strong>ExaEarn</strong>
        </button>
        <nav className="rd-primary-nav" aria-label="Primary">
          <button type="button" onClick={() => go("addFunds")}>Buy Crypto</button>
          <button type="button" onClick={() => go("market")}>Markets</button>
          <DesktopNavMenu label="Trade" items={tradeItems} open={openMenu === "trade"} onToggle={() => setOpenMenu(openMenu === "trade" ? null : "trade")} onNavigate={go} />
          <DesktopNavMenu label="Earn" items={earnItems} open={openMenu === "earn"} onToggle={() => setOpenMenu(openMenu === "earn" ? null : "earn")} onNavigate={go} />
          <button type="button" onClick={() => go("aiAssistant")}>ExaAI</button>
          <button type="button" onClick={() => go("p2pMarketplace")}>P2P</button>
          <DesktopNavMenu label="More" items={moreItems} open={openMenu === "more"} onToggle={() => setOpenMenu(openMenu === "more" ? null : "more")} onNavigate={go} />
        </nav>
        <div className="rd-nav-actions">
          <button type="button" className="rd-search" onClick={() => go("market")}><Search size={16} /> Search</button>
          <button type="button" onClick={onToggleNotifications} aria-label={`Notifications (${unreadNotifications || 0} unread)`}>
            <Bell size={18} />{unreadNotifications ? <span>{unreadNotifications > 9 ? "9+" : unreadNotifications}</span> : null}
          </button>
          <button type="button" onClick={() => go("assets")}><Wallet size={18} /> Assets</button>
          <button type="button" onClick={() => go("profile")} aria-label={user?.username || "Profile"}><User size={18} /></button>
        </div>
      </header>

      <main className="rd-desktop-home">
        <section className="rd-card rd-portfolio">
          <div>
            <span className="rd-eyebrow">Portfolio Overview</span>
            <div className="rd-balance-line">
              <h1>{portfolioLoading ? "Loading..." : balanceVisible ? formatMoney(portfolioValue, portfolioCurrency) : "••••••"}</h1>
              <button type="button" onClick={() => setBalanceVisible((value) => !value)} aria-label={balanceVisible ? "Hide balance" : "Show balance"}>
                {balanceVisible ? <Eye size={17} /> : <EyeOff size={17} />}
              </button>
            </div>
            <p className={Number(portfolioDayPnl) < 0 ? "negative" : "positive"}>
              Today's PnL: {portfolioDayPnl == null ? "Unavailable" : `${Number(portfolioDayPnl) >= 0 ? "+" : ""}${formatMoney(portfolioDayPnl, portfolioCurrency)}`}
              {portfolioDayPnlPercent != null ? ` (${Number(portfolioDayPnlPercent) >= 0 ? "+" : ""}${Number(portfolioDayPnlPercent).toFixed(2)}%)` : ""}
            </p>
            {portfolioError ? <button type="button" className="rd-inline-retry" onClick={onRetryPortfolio}>Portfolio unavailable. Retry</button> : null}
          </div>
          <div className="rd-money-actions">
            <button type="button" className="primary" onClick={() => go("addFunds")}><ArrowDownLeft size={18} /> Deposit</button>
            <button type="button" onClick={() => go("withdraw")}><ArrowUpRight size={18} /> Withdraw</button>
            <button type="button" className="primary-soft" onClick={() => go("addFunds")}><Landmark size={18} /> Buy Crypto</button>
            <button type="button" onClick={() => go("swap")}><RefreshCw size={18} /> Convert</button>
            <button type="button" onClick={() => go("send")}><Wallet size={18} /> Transfer</button>
          </div>
        </section>

        <div className="rd-market-layout">
          <MarketTable markets={markets} loading={marketLoading} offline={marketOffline} onRetry={onRetryMarkets} onNavigate={onNavigate} />
          <aside className="rd-side-column">
            <section className="rd-card rd-watchlist">
              <header className="rd-section-head compact"><h2>Watchlist</h2><button type="button" onClick={() => go("market")}>Manage</button></header>
              {watchlist.length ? watchlist.map((item) => (
                <button type="button" key={item.pair} onClick={() => go("trade")} className="rd-watch-row">
                  <span>{item.base}/{item.quote}</span>
                  <strong>${formatPrice(item.last)}</strong>
                  <small className={item.change24h >= 0 ? "positive" : "negative"}>{item.change24h >= 0 ? "+" : ""}{Number(item.change24h || 0).toFixed(2)}%</small>
                </button>
              )) : <p className="rd-muted">No watchlist markets yet.</p>}
            </section>
            <section className="rd-card rd-pulse">
              <header className="rd-section-head compact"><h2>Market Pulse</h2></header>
              <div className="rd-pulse-grid">
                <span><strong>{marketPulse.tracked}</strong><small>Tracked</small></span>
                <span><strong className="positive">{marketPulse.gainers}</strong><small>Gainers</small></span>
                <span><strong className="negative">{marketPulse.losers}</strong><small>Losers</small></span>
              </div>
            </section>
          </aside>
        </div>

        <section className="rd-card rd-smart">
          <header className="rd-section-head"><div><span>Trade Smarter</span><h2>Intelligence and automation</h2></div></header>
          <div className="rd-card-grid three">
            <button type="button" onClick={onAiAssistantOpen || (() => go("aiAssistant"))}><Bot size={20} /><strong>ExaAI</strong><small>Market intelligence and strategy assistance.</small><ArrowRight size={15} /></button>
            <button type="button" onClick={() => go("more")}><Copy size={20} /><strong>Copy Trading</strong><small>Discover eligible trading strategies.</small><ArrowRight size={15} /></button>
            <button type="button" onClick={() => go("trade")}><BarChart3 size={20} /><strong>Spot & Futures</strong><small>Open the professional trading workspace.</small><ArrowRight size={15} /></button>
          </div>
        </section>

        <div className="rd-lower-grid">
          <section className="rd-card">
            <header className="rd-section-head compact"><h2>Earn</h2><button type="button" onClick={() => go("staking")}>Explore Earn</button></header>
            <div className="rd-earn-row">
              <HandCoins size={20} />
              <span><strong>Staking & Earn</strong><small>{earnApy ? `Available products up to ${earnApy}% APY` : "Explore supported earning products."}</small></span>
              <ChevronRight size={16} />
            </div>
          </section>
          <section className="rd-card">
            <header className="rd-section-head compact"><h2>ExaEarn Updates</h2><button type="button" onClick={onPersonalize}>Personalize</button></header>
            {personalizedContentLoading ? <div className="rd-market-skeleton small"><i /><i /><i /></div> : updates.length ? updates.map((item) => (
              <button type="button" className="rd-update-row" key={item.id} onClick={() => item.cta_route && go(item.cta_route)}>
                <strong>{item.title}</strong><small>{item.subtitle || item.body || item.badge || "Platform update"}</small>
              </button>
            )) : <p className="rd-muted">No updates right now.</p>}
          </section>
        </div>

        <section className="rd-card rd-activity">
          <header className="rd-section-head compact"><h2>Recent Activity</h2><button type="button" onClick={() => go("transactions")}>View All</button></header>
          {activity.length ? activity.map((item) => (
            <button type="button" className="rd-activity-row" key={item.id} onClick={() => go("transactions")}>
              <span><strong>{item.title || "Account update"}</strong><small>{item.message || "Open activity details."}</small></span>
              <ChevronRight size={15} />
            </button>
          )) : <p className="rd-muted">No recent account activity.</p>}
        </section>
      </main>
    </div>
  );
}

export default ResponsiveDashboard;
