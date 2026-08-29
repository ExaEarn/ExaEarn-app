import React, { StrictMode, useEffect, useMemo, useRef, useState, type ComponentType, type ReactNode } from "react";
import { createRoot } from "react-dom/client";
import {
  ArrowRight, BarChart3, Bot, Building2, Check, ChevronDown, CircleDollarSign,
  Coins, Code2, CreditCard, Globe2, GraduationCap, Landmark, Leaf, LockKeyhole,
  Menu, Play, Search, ShieldCheck, Smartphone, Sparkles, Store,
  TrendingUp, Users, Wallet, X, Zap, ArrowDownLeft, ArrowUpRight, RefreshCw, Send,
} from "lucide-react";
import logo from "./assets/exaearn-logo.png";
import "./styles/index.css";

type Status = "LIVE" | "BETA" | "IN DEVELOPMENT" | "COMING SOON";
type Ticker = {
  symbol: string;
  last_price?: string | null;
  reference_price?: string | null;
  price_change_percent?: string | null;
  quote_volume_24h?: string | null;
  source?: string;
};
type MarketState = { loading: boolean; error: boolean; stale: boolean; tickers: Ticker[]; updatedAt?: number };
type Product = {
  title: string;
  copy: string;
  href: string;
  status: Status;
  icon: ComponentType<{ size?: number; "aria-hidden"?: boolean }>;
};

function isLoopbackUrl(value: string) {
  try {
    const host = new URL(value).hostname;
    return host === "localhost" || host === "127.0.0.1" || host === "::1";
  } catch {
    return false;
  }
}

function resolveWebAppBaseUrl() {
  const configured = String(import.meta.env.VITE_WEB_APP_URL || "").trim();
  if (configured && (import.meta.env.DEV || !isLoopbackUrl(configured))) return configured.replace(/\/+$/, "");
  return import.meta.env.DEV ? "http://127.0.0.1:5173" : "/app";
}

const WEB_APP = resolveWebAppBaseUrl();
const API_BASE = String(import.meta.env.VITE_API_URL || "").trim().replace(/\/+$/, "");
const DEVELOPERS = String(import.meta.env.VITE_DEVELOPER_PORTAL_URL || "/developers").trim();
const LISTING = String(import.meta.env.VITE_LISTING_PORTAL_URL || "/listing").trim();
const appUrl = (path: string) => `${WEB_APP}${path.startsWith("/") ? path : `/${path}`}`;
const apiUrl = (path: string) => `${API_BASE}${path}`;

const statusMap: Record<string, Status> = {
  markets: "LIVE", spot: "LIVE", futures: "LIVE", convert: "LIVE", p2p: "LIVE",
  wallet: "LIVE", earn: "BETA", staking: "BETA", exaai: "BETA", exapay: "BETA",
  fiat: "BETA", exacard: "BETA", copy: "BETA", mobile: "IN DEVELOPMENT",
  institutional: "BETA", developers: "LIVE", exatoken: "IN DEVELOPMENT",
  exaskills: "BETA", nft: "BETA", agriculture: "BETA", crowdfunding: "BETA",
  giftcards: "BETA", reserves: "IN DEVELOPMENT",
};

const tradeProducts: Product[] = [
  { title: "Spot", copy: "Trade supported digital assets through ExaEarn markets.", href: appUrl("/trade/spot"), status: statusMap.spot, icon: BarChart3 },
  { title: "Futures", copy: "Access perpetual markets with advanced order and risk controls.", href: appUrl("/trade/futures"), status: statusMap.futures, icon: TrendingUp },
  { title: "Convert", copy: "Exchange supported assets through a clear quote-based flow.", href: appUrl("/convert"), status: statusMap.convert, icon: CircleDollarSign },
  { title: "P2P", copy: "Buy and sell crypto using supported peer-to-peer payment methods.", href: appUrl("/p2p"), status: statusMap.p2p, icon: Users },
];

const exploreProducts: Product[] = [
  { title: "ExaSkills", copy: "Learn, teach and participate in digital education.", href: "/exaskills", status: statusMap.exaskills, icon: GraduationCap },
  { title: "NFT Marketplace", copy: "Explore digital ownership and marketplace experiences.", href: "/nft", status: statusMap.nft, icon: Sparkles },
  { title: "Agriculture", copy: "Discover verified, eligible real-economy opportunities.", href: "/agriculture", status: statusMap.agriculture, icon: Leaf },
  { title: "Crowdfunding", copy: "Discover eligible community and innovation campaigns.", href: "/crowdfunding", status: statusMap.crowdfunding, icon: Users },
  { title: "Gift Cards", copy: "Connect digital assets with supported digital commerce.", href: "/gift-cards", status: statusMap.giftcards, icon: Store },
  { title: "ExaToken", copy: "Explore the utility layer for supported ExaEarn experiences.", href: "/exatoken", status: statusMap.exatoken, icon: Coins },
];

const productPages: Record<string, { eyebrow: string; title: string; copy: string; status: Status; action: string; href: string; points: string[] }> = {
  "/buy-crypto": { eyebrow: "BUY CRYPTO", title: "A clear path into digital assets.", copy: "Use supported fiat and payment routes to purchase eligible digital assets through your ExaEarn account.", status: "BETA", action: "Open Buy Crypto", href: appUrl("/buy-crypto"), points: ["Supported payment methods", "Server-calculated fees", "Account verification controls"] },
  "/spot": { eyebrow: "SPOT", title: "Trade digital assets directly.", copy: "Access ExaEarn Spot markets, order controls and transparent market activity.", status: "LIVE", action: "Trade Spot", href: appUrl("/trade/spot"), points: ["Live market data", "Limit and market orders", "Canonical settlement"] },
  "/futures": { eyebrow: "FUTURES", title: "Advanced markets with risk controls.", copy: "Use perpetual markets, margin controls and supported conditional orders.", status: "LIVE", action: "Explore Futures", href: appUrl("/trade/futures"), points: ["Mark and index pricing", "Position risk controls", "Reduce-only exits"] },
  "/convert": { eyebrow: "CONVERT", title: "Swap assets without the order book.", copy: "Review a time-bound quote, fees and expected amount before confirming.", status: "LIVE", action: "Convert Assets", href: appUrl("/convert"), points: ["Quote expiry", "Internal liquidity first", "Idempotent settlement"] },
  "/p2p": { eyebrow: "P2P", title: "Crypto and local payments, connected.", copy: "Use supported payment methods with escrow, order controls and dispute workflows.", status: "LIVE", action: "Explore P2P", href: appUrl("/p2p"), points: ["Escrow protection", "Merchant profiles", "Payment evidence controls"] },
  "/exaai": { eyebrow: "EXAAI", title: "Trade with more context.", copy: "Market analysis, risk intelligence, strategy assistance and automation under server-side governance.", status: "BETA", action: "Explore ExaAI", href: appUrl("/exaai"), points: ["Market analysis", "Risk signals", "Portfolio insight"] },
  "/earn": { eyebrow: "EARN", title: "Put eligible assets to work.", copy: "Access supported Earn, staking and reward opportunities without fabricated yield claims.", status: "BETA", action: "Explore Earn", href: appUrl("/earn"), points: ["Supported products", "Clear terms", "Provider-aware availability"] },
  "/staking": { eyebrow: "STAKING", title: "Provider-backed staking, when available.", copy: "Review supported assets, product state and verified reward allocations.", status: "BETA", action: "View Staking", href: appUrl("/staking"), points: ["Asset eligibility", "Unstaking lifecycle", "Verified rewards"] },
  "/wallet": { eyebrow: "WALLET", title: "Manage supported assets securely.", copy: "Hold, receive, send and transfer digital assets through ExaEarn custody and account controls.", status: "LIVE", action: "Open Wallet", href: appUrl("/assets"), points: ["Deposit and withdrawal history", "Network controls", "Internal transfers"] },
  "/exapay": { eyebrow: "EXAPAY", title: "Move value through ExaEarn.", copy: "Access supported payment and merchant experiences through your authenticated account.", status: "BETA", action: "Explore ExaPay", href: appUrl("/exapay"), points: ["Payment status", "Transaction references", "Provider-aware processing"] },
  "/exacard": { eyebrow: "EXACARD", title: "Crypto that moves with you.", copy: "ExaCard is designed to connect eligible ExaEarn balances with supported card payment activity.", status: "BETA", action: "Explore ExaCard", href: appUrl("/exacard"), points: ["Virtual-card controls", "Card funding", "Real-time activity"] },
  "/exatoken": { eyebrow: "EXATOKEN", title: "A utility layer, not the whole platform.", copy: "Explore ExaToken's planned and supported role across eligible ExaEarn experiences.", status: "IN DEVELOPMENT", action: "View in App", href: appUrl("/token"), points: ["No fabricated market data", "Utility-led design", "Availability disclosed"] },
  "/exaskills": { eyebrow: "EXASKILLS", title: "Learn, teach and build credentials.", copy: "Discover courses, challenges and eligible digital education opportunities.", status: "BETA", action: "Explore ExaSkills", href: appUrl("/exaskills"), points: ["Courses", "Challenges", "Credentials"] },
  "/nft": { eyebrow: "NFT MARKETPLACE", title: "Digital ownership with clear status.", copy: "Explore supported collections, listings and marketplace activity.", status: "BETA", action: "Explore NFTs", href: appUrl("/nft"), points: ["Collections", "Listings", "Ownership records"] },
  "/agriculture": { eyebrow: "AGRITECH", title: "Verified projects with controlled access.", copy: "Discover agricultural projects with explicit evidence, eligibility and product-status controls.", status: "BETA", action: "Explore Agriculture", href: appUrl("/agriculture"), points: ["Evidence review", "Fail-closed eligibility", "Canonical settlement"] },
  "/crowdfunding": { eyebrow: "CROWDFUNDING", title: "Support eligible ideas transparently.", copy: "Discover community campaigns with clear funding and campaign status.", status: "BETA", action: "Explore Campaigns", href: appUrl("/crowdfunding"), points: ["Campaign discovery", "Contribution records", "Status visibility"] },
  "/gift-cards": { eyebrow: "GIFT CARDS", title: "Digital commerce through supported providers.", copy: "Buy, submit and track eligible gift-card activity with provider-aware status.", status: "BETA", action: "Explore Gift Cards", href: appUrl("/giftcards"), points: ["Live product catalogues", "Order tracking", "Refund state"] },
};

function useMarkets(): MarketState {
  const [state, setState] = useState<MarketState>(() => {
    try {
      const cached = JSON.parse(sessionStorage.getItem("exaearn.public-markets") || "null");
      if (Array.isArray(cached?.tickers)) return { loading: true, error: false, stale: true, tickers: cached.tickers, updatedAt: cached.updatedAt };
    } catch { /* use empty loading state */ }
    return { loading: true, error: false, stale: false, tickers: [] };
  });
  useEffect(() => {
    let active = true;
    const load = async () => {
      try {
        const response = await fetch(apiUrl("/api/v1/market/tickers"), { headers: { Accept: "application/json" } });
        if (!response.ok) throw new Error("market request failed");
        const payload = await response.json();
        const rows = payload?.data ?? payload;
        if (active) {
          const next = { loading: false, error: false, stale: false, tickers: Array.isArray(rows) ? rows : [], updatedAt: Date.now() };
          setState(next);
          sessionStorage.setItem("exaearn.public-markets", JSON.stringify({ tickers: next.tickers, updatedAt: next.updatedAt }));
        }
      } catch {
        if (active) setState(current => ({ ...current, loading: false, error: current.tickers.length === 0, stale: current.tickers.length > 0 }));
      }
    };
    void load();
    const timer = window.setInterval(load, 30000);
    return () => { active = false; window.clearInterval(timer); };
  }, []);
  return state;
}

function formatNumber(value?: string | null, options: Intl.NumberFormatOptions = {}) {
  if (value === null || value === undefined || value === "") return "--";
  const number = Number(value);
  return Number.isFinite(number) ? number.toLocaleString(undefined, options) : "--";
}

function StatusBadge({ status }: { status: Status }) {
  return <span className={`status status-${status.toLowerCase().replaceAll(" ", "-")}`}>{status}</span>;
}

function Logo() {
  return <a className="brand" href="/" aria-label="ExaEarn home"><img src={logo} alt="" /><strong>ExaEarn</strong></a>;
}

function Header() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [tradeOpen, setTradeOpen] = useState(false);
  const [moreOpen, setMoreOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  useEffect(() => { const update = () => setScrolled(window.scrollY > 36); update(); window.addEventListener("scroll", update, { passive: true }); return () => window.removeEventListener("scroll", update); }, []);
  const tradeLinks = [["Spot", "/spot"], ["Futures", "/futures"], ["Convert", "/convert"], ["P2P", "/p2p"], ["Copy Trading", appUrl("/copy-trading")]];
  const moreLinks = [["Wallet", "/wallet"], ["ExaPay", "/exapay"], ["ExaToken", "/exatoken"], ["ExaSkills", "/exaskills"], ["NFT Marketplace", "/nft"], ["Agriculture", "/agriculture"], ["Crowdfunding", "/crowdfunding"], ["Gift Cards", "/gift-cards"], ["Support", "/support"]];
  const close = () => { setMenuOpen(false); setTradeOpen(false); setMoreOpen(false); };
  return (
    <header className={`site-header ${scrolled ? "is-scrolled" : ""}`}>
      <nav className="nav-shell" aria-label="Primary navigation">
        <Logo />
        <div className="desktop-nav">
          <a href="/buy-crypto">Buy Crypto</a><a href="/markets">Markets</a>
          <Dropdown label="Trade" open={tradeOpen} setOpen={setTradeOpen} links={tradeLinks} />
          <a href="/earn">Earn</a><a href="/exaai">ExaAI</a><a href="/exacard">ExaCard</a>
          <Dropdown label="More" open={moreOpen} setOpen={setMoreOpen} links={moreLinks} wide />
        </div>
        <div className="nav-right"><a className="nav-specialist" href="/institutional">Institutional</a><a className="nav-specialist" href={DEVELOPERS}>Developers</a><a href={appUrl("/login")}>Log In</a><a className="button button-primary button-small" href={appUrl("/register")}>Sign Up</a></div>
        <div className="mobile-nav-actions"><a href="/spot">Trade</a><button type="button" onClick={() => setMenuOpen(true)} aria-label="Open menu"><Menu size={22} /></button></div>
      </nav>
      {menuOpen ? <div className="mobile-drawer" role="dialog" aria-modal="true" aria-label="Navigation"><div className="drawer-head"><Logo /><button type="button" onClick={close} aria-label="Close menu"><X size={22} /></button></div><div className="drawer-links">{[["Buy Crypto", "/buy-crypto"], ["Markets", "/markets"], ...tradeLinks, ["Earn", "/earn"], ["ExaAI", "/exaai"], ["ExaCard", "/exacard"], ...moreLinks, ["Institutional", "/institutional"], ["Developers", DEVELOPERS]].map(([label, href]) => <a key={`${label}-${href}`} href={href} onClick={close}>{label}<ArrowRight size={16} /></a>)}</div><div className="drawer-auth"><a href={appUrl("/login")}>Log In</a><a className="button button-primary" href={appUrl("/register")}>Create Account</a></div></div> : null}
    </header>
  );
}

function Dropdown({ label, open, setOpen, links, wide = false }: { label: string; open: boolean; setOpen: (value: boolean) => void; links: string[][]; wide?: boolean }) {
  return <div className="nav-dropdown"><button type="button" onClick={() => setOpen(!open)} aria-expanded={open}>{label}<ChevronDown size={14} /></button>{open ? <div className={`dropdown-panel ${wide ? "wide" : ""}`}>{links.map(([text, href]) => <a key={text} href={href}>{text}<ArrowRight size={14} /></a>)}</div> : null}</div>;
}

function HeroDevice({ rows, loading }: { rows: Ticker[]; loading: boolean }) {
  return <div className="hero-device-stage" aria-label="ExaEarn product dashboard preview"><div className="network-orbit" aria-hidden="true"/><div className="hero-phone"><div className="device-speaker"/><div className="device-screen"><div className="device-header"><div className="device-avatar">EX</div><span><small>ExaEarn UID</small><b>PRODUCT PREVIEW</b></span><i/></div><section className="device-portfolio"><span>Total portfolio value</span><strong>--</strong><small>Sign in to view your balance</small></section><nav className="device-actions" aria-label="Dashboard actions">{[[ArrowDownLeft,"Deposit"],[ArrowUpRight,"Withdraw"],[RefreshCw,"Convert"],[Send,"Transfer"]].map(([Icon,label]) => <span key={String(label)}><i><Icon size={13}/></i><b>{String(label)}</b></span>)}</nav><section className="device-markets"><header><strong>Markets</strong><span>{rows.length ? "LIVE DATA" : "CONNECTING"}</span></header>{loading ? <div className="device-skeleton"/> : rows.slice(0,3).map(row => <div key={row.symbol}><span><i>{row.symbol.split("/")[0].slice(0,1)}</i><b>{row.symbol}</b></span><strong>{formatNumber(row.last_price ?? row.reference_price,{maximumFractionDigits:5})}</strong><small className={Number(row.price_change_percent || 0) >= 0 ? "positive" : "negative"}>{formatPercent(row.price_change_percent)}</small></div>)}</section><section className="device-smart"><Bot size={16}/><span><small>ExaAI</small><b>Market context and governed automation</b></span><em>BETA</em></section></div></div><aside className="hero-ai-float"><Bot size={17}/><span><small>EXAAI INTELLIGENCE</small><b>Risk-aware market context</b><em>Governed automation · Beta</em></span></aside><aside className="hero-system-float"><Check size={15}/><span><b>One connected account</b><small>Trade · Pay · Earn</small></span></aside></div>;
}

function MarketTicker({ state }: { state: MarketState }) {
  if (state.loading && !state.tickers.length) return <div className="market-transition"><span>Connecting to ExaEarn markets</span><i/></div>;
  if (!state.tickers.length) return <div className="market-transition is-stale"><span>Market connection unavailable</span><a href="/markets">Open Markets</a></div>;
  const rows = state.tickers.slice(0,6);
  return <section className="live-ticker" aria-label="ExaEarn market ticker"><div className={`ticker-status ${state.stale ? "stale" : ""}`}><i/>{state.stale ? "Last known" : "Live markets"}</div><div className="live-ticker-track">{[...rows,...rows].map((row,index) => <a href={`/markets/${row.symbol.replace("/","-")}`} key={`${row.symbol}-${index}`}><strong>{row.symbol}</strong><span>{formatNumber(row.last_price ?? row.reference_price,{maximumFractionDigits:6})}</span><em className={Number(row.price_change_percent || 0) >= 0 ? "positive" : "negative"}>{formatPercent(row.price_change_percent)}</em></a>)}</div></section>;
}

function TelegramIcon() {
  return <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21.8 4.1 18.5 20c-.2 1-.8 1.2-1.6.7l-4.8-3.5-2.3 2.2c-.3.3-.5.5-1 .5l.3-4.9 8.9-8c.4-.3-.1-.5-.6-.2L6.4 13.7 1.7 12.2c-1-.3-1-1 0-1.4L20.1 3.7c.8-.3 1.6.2 1.7.4Z"/></svg>;
}

function ProductDemo({ onClose }: { onClose: () => void }) {
  const closeRef = useRef<HTMLButtonElement>(null);
  useEffect(() => { closeRef.current?.focus(); const close = (event: KeyboardEvent) => { if (event.key === "Escape") onClose(); }; window.addEventListener("keydown", close); return () => window.removeEventListener("keydown", close); }, [onClose]);
  return <div className="demo-backdrop" role="presentation" onMouseDown={onClose}><section className="demo-modal" role="dialog" aria-modal="true" aria-labelledby="demo-title" onMouseDown={event => event.stopPropagation()}><header><div><small>PRODUCT WALKTHROUGH</small><h2 id="demo-title">See ExaEarn in action</h2></div><button ref={closeRef} type="button" onClick={onClose} aria-label="Close product demo"><X/></button></header><div className="demo-journey"><article><span>01</span><ArrowDownLeft/><strong>Fund</strong><p>Choose an eligible deposit or payment route.</p></article><article><span>02</span><RefreshCw/><strong>Convert</strong><p>Review a server-calculated quote before confirmation.</p></article><article><span>03</span><BarChart3/><strong>Trade</strong><p>Access Spot or eligible advanced markets.</p></article><article><span>04</span><Bot/><strong>Understand</strong><p>Use ExaAI for governed market and risk context.</p></article></div><p className="demo-disclosure">Interactive product preview. Account values and financial results are not simulated.</p><a className="button button-primary" href={appUrl("/register")}>Create your account<ArrowRight size={16}/></a></section></div>;
}

function formatPercent(value?: string | null) {
  const number = Number(value || 0);
  return `${number >= 0 ? "+" : ""}${number.toFixed(2)}%`;
}

function SectionHead({ eyebrow, title, copy }: { eyebrow: string; title: string; copy: string }) {
  return <div className="section-head"><span>{eyebrow}</span><h2>{title}</h2><p>{copy}</p></div>;
}

function HomePage() {
  const markets = useMarkets();
  const rows = markets.tickers.slice(0, 6);
  const [demoOpen, setDemoOpen] = useState(false);
  return <>
    <Header />
    <main>
      <section className="hero-network" id="top"><div className="network-grid" aria-hidden="true"/><div className="network-nodes" aria-hidden="true">{Array.from({length:18}).map((_,index)=><i key={index} style={{"--node":index} as React.CSSProperties}/>)}</div><div className="hero section"><div className="hero-copy"><span className="eyebrow">DIGITAL-ASSET EXCHANGE</span><h1>Trade crypto.<br />Move money.<br /><em>Do more.</em></h1><p>Trade digital assets across Spot and Futures, buy and convert crypto, access P2P markets and manage your financial activity from one ExaEarn account.</p><div className="hero-actions"><a className="button button-primary" href={appUrl("/trade/spot")}>Start Trading<ArrowRight size={17} /></a><a className="button" href="/markets">Explore Markets</a><a className="text-link" href="/buy-crypto">Buy Crypto<ArrowRight size={15} /></a></div><button className="demo-trigger" type="button" onClick={() => setDemoOpen(true)}><Play size={17}/>See ExaEarn in action</button><div className="hero-products">Spot <i /> Futures <i /> P2P <i /> Convert <i /> ExaAI <i /> Earn</div></div><HeroDevice rows={rows} loading={markets.loading}/></div></section>
      <MarketTicker state={markets}/>

      <MarketsSection state={markets} />

      <section className="section" id="trade"><SectionHead eyebrow="TRADE" title="One account. Multiple ways to trade." copy="Move from simple asset conversion to advanced digital-asset markets without leaving ExaEarn."/><div className="product-grid trade-grid">{tradeProducts.map((product) => <ProductCard key={product.title} product={product} />)}</div><div className="copy-strip"><div><StatusBadge status={statusMap.copy}/><strong>Copy Trading</strong><span>Discover eligible trading strategies and automate supported trade replication.</span></div><a href={appUrl("/copy-trading")}>Learn more<ArrowRight size={15}/></a></div></section>

      <section className="section intelligence"><SectionHead eyebrow="TRADE SMARTER. GROW FURTHER." title="Intelligence and earning, built into ExaEarn." copy="Use analytical context and supported earning products without moving between disconnected platforms."/><div className="split-feature"><article><div className="feature-icon"><Bot size={23}/></div><StatusBadge status={statusMap.exaai}/><h3>Trade with more context.</h3><p>ExaAI brings market analysis, risk intelligence, strategy assistance and automation into the ExaEarn experience.</p><FeatureList items={["Market Analysis", "Risk Signals", "Strategy Assistance", "Portfolio Insights", "Automation"]}/><a className="text-link" href="/exaai">Explore ExaAI<ArrowRight size={15}/></a></article><article><div className="feature-icon"><Coins size={23}/></div><StatusBadge status={statusMap.earn}/><h3>Put your assets to work.</h3><p>Access supported staking, Earn products and eligible reward opportunities from your ExaEarn account.</p><FeatureList items={["Staking", "Earn", "Rewards", "Referral"]}/><a className="text-link" href="/earn">Explore Earn<ArrowRight size={15}/></a></article></div></section>

      <section className="section money"><SectionHead eyebrow="MONEY" title="From digital assets to everyday money." copy="Hold it. Send it. Convert it. Pay with it. ExaEarn connects digital assets with practical financial activity."/><div className="money-flow">{[[Wallet,"Wallet",statusMap.wallet],[Zap,"ExaPay",statusMap.exapay],[Landmark,"Fiat",statusMap.fiat],[Users,"P2P",statusMap.p2p],[CreditCard,"ExaCard",statusMap.exacard]].map(([Icon,label,status], index) => <div key={String(label)}><span><Icon size={20}/></span><strong>{String(label)}</strong><StatusBadge status={status as Status}/>{index < 4 ? <ArrowRight className="flow-arrow" size={17}/> : null}</div>)}</div><div className="card-feature"><div className="exa-card"><div className="card-brand">EXAEARN</div><div className="card-chip"/><div className="card-number">•••• •••• ••••</div><div><span>EXACARD</span><small>ELIGIBLE ACCOUNTS</small></div></div><div><StatusBadge status={statusMap.exacard}/><h3>Crypto that moves with you.</h3><p>ExaCard is designed to connect eligible ExaEarn balances with supported card payment activity. Card availability depends on provider and regional eligibility.</p><a className="button" href="/exacard">Explore ExaCard<ArrowRight size={16}/></a></div></div></section>

      <section className="section why"><SectionHead eyebrow="WHY EXAEARN" title="More utility. Less fragmentation." copy="ExaEarn brings trading, payments, intelligent tools and digital-asset utility together so users can do more without moving between disconnected platforms."/><div className="three-grid">{[[BarChart3,"Trade","Spot, Futures, Convert and P2P from one connected account."],[Wallet,"Use","Wallet, payments and ExaCard extend digital assets beyond trading."],[Sparkles,"Grow","ExaAI, staking, Earn and broader ecosystem opportunities create more ways to participate."]].map(([Icon,title,copy]) => <article key={String(title)}><Icon size={22}/><h3>{String(title)}</h3><p>{String(copy)}</p></article>)}</div></section>

      <section className="section security"><div><SectionHead eyebrow="SECURITY" title="Built around control and protection." copy="ExaEarn combines account security, wallet controls, transaction safeguards and operational monitoring across the platform."/><div className="security-links"><a href="/security">Security Center</a><a href="/status">System Status</a><a href="/risk">Risk Disclosure</a><span>Proof of Reserves — In Development</span></div></div><div className="security-list">{[[LockKeyhole,"Account security","Authentication, 2FA and device-level protection."],[Wallet,"Asset controls","Withdrawal safeguards and wallet-security architecture."],[ShieldCheck,"Risk monitoring","Operational controls designed to identify abnormal activity."],[BarChart3,"Transparency","Clear account activity, transaction records and system information."]].map(([Icon,title,copy]) => <div key={String(title)}><Icon size={20}/><span><strong>{String(title)}</strong><small>{String(copy)}</small></span></div>)}</div></section>

      <section className="section explore"><SectionHead eyebrow="EXPLORE MORE" title="Beyond the exchange." copy="Discover additional experiences connected to the broader ExaEarn economy."/><div className="explore-scroll">{exploreProducts.map(product => <ProductCard product={product} key={product.title}/>)}</div><a className="text-link section-link" href="/products">Explore all products<ArrowRight size={15}/></a></section>

      <section className="section business"><SectionHead eyebrow="BUSINESS" title="Built for more than retail." copy="Professional infrastructure and developer access, separated from the everyday trading experience."/><div className="business-grid"><article><Building2 size={24}/><StatusBadge status={statusMap.institutional}/><h3>Institutional</h3><p>Advanced infrastructure for professional participants, liquidity partners and market makers.</p><a href="/institutional">Explore Institutional<ArrowRight size={15}/></a></article><article><Code2 size={24}/><StatusBadge status={statusMap.developers}/><h3>Developers</h3><p>Build with ExaEarn market data, trading APIs, WebSocket infrastructure and developer tools.</p><a href={DEVELOPERS}>Explore Developers<ArrowRight size={15}/></a></article></div></section>

      <section className="section mobile"><div><StatusBadge status={statusMap.mobile}/><SectionHead eyebrow="EXAEARN MOBILE" title="Your exchange wherever you go." copy="Trade, manage assets, access P2P, monitor Earn products and use ExaAI from the ExaEarn mobile experience."/><div className="feature-tags"><span>Trading</span><span>Wallet</span><span>P2P</span><span>Earn</span><span>ExaAI</span><span>ExaCard</span></div><a className="button" href="/mobile">Mobile app status<ArrowRight size={16}/></a></div><PhonePreview rows={rows}/></section>

      <FAQ />
      <section className="get-started"><span>GET STARTED</span><strong>Create your account. Fund it. Start trading.</strong><a className="button button-primary" href={appUrl("/register")}>Create Account<ArrowRight size={16}/></a></section>
      <section className="final-cta"><h2>Trade. Pay. Earn.<br/>One ExaEarn account.</h2><p>Enter a digital-asset platform built for markets, money and more.</p><div><a className="button button-primary" href={appUrl("/register")}>Create Account<ArrowRight size={16}/></a><a className="button" href="/markets">Explore Markets</a></div></section>
    </main>
    <Footer />
    {demoOpen ? <ProductDemo onClose={() => setDemoOpen(false)}/> : null}
  </>;
}

function MarketsSection({ state }: { state: MarketState }) {
  const [tab, setTab] = useState("Popular");
  const rows = useMemo(() => {
    const copy = [...state.tickers];
    if (tab === "Top Gainers") copy.sort((a,b) => Number(b.price_change_percent || 0) - Number(a.price_change_percent || 0));
    if (tab === "Top Volume") copy.sort((a,b) => Number(b.quote_volume_24h || 0) - Number(a.quote_volume_24h || 0));
    return copy.slice(0, 6);
  }, [state.tickers, tab]);
  return <section className="section markets" id="markets"><div className="markets-heading"><SectionHead eyebrow="MARKETS" title="Markets moving now." copy="Explore supported digital assets and trading opportunities across ExaEarn."/><a href="/markets">View all markets<ArrowRight size={15}/></a></div><div className="market-tabs" role="tablist">{["Popular","Top Gainers","Top Volume","New Listings"].map(item => <button type="button" role="tab" aria-selected={tab === item} onClick={() => setTab(item)} key={item}>{item}</button>)}</div><div className="market-table"><div className="market-row market-head"><span>Pair</span><span>Price</span><span>24h</span><span>Volume</span><span>Trend</span><span/></div>{state.loading ? <MarketSkeleton/> : state.error ? <div className="market-message">Market data is temporarily unavailable.</div> : rows.length ? rows.map(row => { const change = Number(row.price_change_percent || 0); return <a className="market-row" href={`/markets/${row.symbol.replace("/", "-")}`} key={row.symbol}><strong>{row.symbol}</strong><span>{formatNumber(row.last_price ?? row.reference_price, { maximumFractionDigits: 8 })}</span><em className={change >= 0 ? "positive" : "negative"}>{formatPercent(row.price_change_percent)}</em><span>{formatNumber(row.quote_volume_24h, { notation: "compact", maximumFractionDigits: 2 })}</span><i className={change >= 0 ? "trend-up" : "trend-down"}/><b>Trade<ArrowRight size={14}/></b></a>}) : <div className="market-message">Market data is temporarily unavailable.</div>}</div></section>;
}

function MarketSkeleton() { return <div className="market-skeleton" aria-label="Loading markets"><i/><i/><i/><i/></div>; }
function FeatureList({ items }: { items: string[] }) { return <div className="feature-list">{items.map(item => <span key={item}><Check size={14}/>{item}</span>)}</div>; }
function ProductCard({ product }: { product: Product }) { const Icon = product.icon; return <article className="product-card"><div className="product-card-top"><span><Icon size={21}/></span><StatusBadge status={product.status}/></div><h3>{product.title}</h3><p>{product.copy}</p><a href={product.href}>Explore<ArrowRight size={15}/></a></article>; }

function PhonePreview({ rows }: { rows: Ticker[] }) { return <div className="phone"><div className="phone-island"/><div className="phone-screen"><div className="phone-brand"><img src={logo} alt=""/><span>ExaEarn</span></div><small>Markets</small>{(rows.length ? rows.slice(0,4) : [{symbol:"BTC/USDT"},{symbol:"ETH/USDT"},{symbol:"SOL/USDT"}]).map(row => <div className="phone-market" key={row.symbol}><span>{row.symbol}</span><strong>{row.last_price ? formatNumber(row.last_price,{maximumFractionDigits:4}) : "--"}</strong></div>)}<div className="phone-actions"><span>Trade</span><span>Wallet</span><span>Earn</span></div></div></div>; }

const faqs = [
  ["What is ExaEarn?", "ExaEarn is a digital-asset exchange and financial platform for supported trading, money movement and digital-asset products."],
  ["How do I start trading?", "Create an account, complete required verification, fund a supported balance and choose an available Spot, Futures, Convert or P2P product."],
  ["Which digital assets are supported?", "The Markets page displays assets currently returned by ExaEarn's public market API. Availability can vary by product and jurisdiction."],
  ["What is Spot trading?", "Spot trading exchanges one supported asset for another with settlement to your ExaEarn account."],
  ["What is Futures trading?", "Futures are leveraged derivative products with liquidation risk. Eligibility and availability restrictions apply."],
  ["How does P2P work?", "P2P connects eligible buyers and sellers using supported payment methods and an escrow-based order workflow."],
  ["What is ExaAI?", "ExaAI provides analysis, risk context and governed automation. It does not guarantee accuracy or returns."],
  ["What is ExaCard?", "ExaCard is a provider-dependent card product designed for eligible ExaEarn balances and regions."],
  ["How does Earn or Staking work?", "Available products show their terms, asset eligibility and provider state. Returns are not guaranteed."],
  ["How do deposits and withdrawals work?", "Select a supported asset and network, then follow the account instructions and required security checks."],
  ["Is account verification required?", "Verification requirements depend on the product, amount, risk controls and jurisdiction."],
  ["How does ExaEarn protect accounts?", "ExaEarn uses authentication, 2FA, device controls, withdrawal safeguards, monitoring and auditable account activity."],
  ["Which countries are supported?", "Product availability is determined server-side by jurisdiction and compliance policy. Confirm eligibility during onboarding."],
];
function FAQ() { return <section className="section faq" id="faq"><SectionHead eyebrow="FAQ" title="What you should know." copy="Clear answers about trading, products, access and account protection."/><div className="faq-list">{faqs.map(([question,answer]) => <details key={question}><summary>{question}<ChevronDown size={17}/></summary><p>{answer}</p></details>)}</div></section>; }

const footerGroups: Record<string, string[][]> = {
  Trade: [["Buy Crypto","/buy-crypto"],["Markets","/markets"],["Spot","/spot"],["Futures","/futures"],["Convert","/convert"],["P2P","/p2p"]],
  Earn: [["Staking","/staking"],["Earn","/earn"],["Rewards",appUrl("/rewards")],["Affiliate",appUrl("/referral")]],
  Money: [["Wallet","/wallet"],["ExaPay","/exapay"],["Fiat",appUrl("/fiat")],["ExaCard","/exacard"]],
  Products: [["ExaAI","/exaai"],["ExaToken","/exatoken"],["ExaSkills","/exaskills"],["NFT Marketplace","/nft"],["Agriculture","/agriculture"],["Crowdfunding","/crowdfunding"],["Gift Cards","/gift-cards"]],
  Business: [["Institutional","/institutional"],["Market Makers","/institutional"],["OTC","/institutional"],["Listings",LISTING]],
  Developers: [["API",DEVELOPERS],["Documentation",DEVELOPERS],["SDK",DEVELOPERS],["System Status","/status"]],
  Support: [["Help Center","/support"],["Fees","/fees"],["Announcements","/roadmap"],["Contact","/support"],["FAQ","/#faq"]],
  Company: [["About","/about"],["Careers","/about"],["Partners","/about"],["Roadmap","/roadmap"]],
  Legal: [["Terms","/terms"],["Privacy","/privacy"],["Risk Disclosure","/risk"],["AML/KYC","/legal"],["Security","/security"]],
};
function Footer() { return <footer><div className="footer-main"><div className="footer-intro"><Logo/><p>A digital-asset exchange and financial platform for markets, money and more.</p><div className="social-row"><a href="https://t.me/ExaEarn" rel="noreferrer" target="_blank" aria-label="ExaEarn on Telegram"><TelegramIcon/>Telegram</a></div></div><div className="footer-groups">{Object.entries(footerGroups).map(([group,links]) => <div key={group}><strong>{group}</strong>{links.map(([label,href]) => <a key={label} href={href}>{label}</a>)}</div>)}</div></div><div className="footer-bottom"><span>© {new Date().getFullYear()} ExaEarn. Product availability varies by jurisdiction.</span><span>Digital assets involve risk.</span></div></footer>; }

function MarketsPage() { const state = useMarkets(); return <StandardShell><section className="page-hero"><span>EXAEARN MARKETS</span><h1>Explore digital-asset markets.</h1><p>Public market information comes from the ExaEarn Market Data API and identifies internally operated and reference sources.</p><a className="button button-primary" href={appUrl("/trade/spot")}>Start Trading<ArrowRight size={16}/></a></section><MarketsSection state={state}/></StandardShell>; }

function ProductPage({ data }: { data: (typeof productPages)[string] }) { return <StandardShell><section className="page-hero product-page-hero"><StatusBadge status={data.status}/><span>{data.eyebrow}</span><h1>{data.title}</h1><p>{data.copy}</p><a className="button button-primary" href={data.href}>{data.action}<ArrowRight size={16}/></a></section><section className="page-points">{data.points.map(item => <div key={item}><Check size={18}/><strong>{item}</strong></div>)}</section><section className="page-disclosure"><ShieldCheck size={21}/><div><strong>Availability and risk</strong><p>Features are enabled server-side based on product status, account eligibility, jurisdiction, providers and operational controls. Nothing on this page guarantees availability or financial performance.</p></div></section></StandardShell>; }

function ProductsPage() { return <StandardShell><section className="page-hero"><span>PRODUCTS</span><h1>The ExaEarn platform, organized clearly.</h1><p>Start with markets and trading, then explore money, intelligence, earning and broader products.</p></section><section className="all-products"><h2>Trade</h2><div className="product-grid">{tradeProducts.map(item => <ProductCard key={item.title} product={item}/>)}</div><h2>Explore more</h2><div className="product-grid">{exploreProducts.map(item => <ProductCard key={item.title} product={item}/>)}</div></section></StandardShell>; }

function InfoPage({ kind }: { kind: string }) {
  const pages: Record<string, [string,string,string[]]> = {
    security: ["Security", "Control and protection across ExaEarn.", ["Account authentication and 2FA", "Device and session controls", "Withdrawal safeguards", "Operational risk monitoring"]],
    status: ["System status", "Operational visibility without invented uptime claims.", ["Market data health", "Trading services", "Payments and providers", "Maintenance notices"]],
    fees: ["Fees", "Server-calculated commercial terms.", ["Trading fees depend on product and tier", "Provider fees may apply", "Quotes show applicable charges", "Final fees appear before confirmation"]],
    risk: ["Risk disclosure", "Understand the products you use.", ["Digital assets can lose value", "Futures can liquidate positions", "Earn returns are not guaranteed", "Provider and jurisdiction risk can affect availability"]],
    legal: ["Legal and compliance", "Product access follows applicable policy.", ["KYC and identity controls", "Jurisdiction eligibility", "AML monitoring", "Product-specific disclosures"]],
    terms: ["Terms", "Rules governing ExaEarn access.", ["Account responsibilities", "Product eligibility", "Prohibited activity", "Service availability"]],
    privacy: ["Privacy", "How account information is handled.", ["Data minimization", "Security controls", "Service providers", "User rights"]],
    support: ["Support", "Help with your ExaEarn account.", ["Account access", "Deposits and withdrawals", "Trading and orders", "Security concerns"]],
    about: ["About ExaEarn", "A digital-asset exchange built around markets, money and useful products.", ["Retail exchange", "Financial products", "Institutional infrastructure", "Developer platform"]],
    mobile: ["ExaEarn Mobile", "The mobile application is in development.", ["Trading", "Wallet", "P2P", "Earn and ExaAI"]],
  };
  const [eyebrow,title,points] = pages[kind] || pages.about;
  return <StandardShell><section className="page-hero"><StatusBadge status={kind === "mobile" ? statusMap.mobile : "LIVE"}/><span>{eyebrow.toUpperCase()}</span><h1>{title}</h1><p>Use the authenticated ExaEarn app for account-specific information and actions.</p></section><section className="page-points">{points.map(point => <div key={point}><Check size={18}/><strong>{point}</strong></div>)}</section></StandardShell>;
}

function RoadmapPage() { return <StandardShell><section className="page-hero"><span>ROADMAP</span><h1>What is live, developing and planned.</h1><p>Status reflects software and public-product availability conservatively. External approvals and providers remain separate gates.</p></section><section className="roadmap-grid">{(["LIVE","BETA","IN DEVELOPMENT","COMING SOON"] as Status[]).map(status => <article key={status}><StatusBadge status={status}/><h2>{status === "LIVE" ? "Available now" : status}</h2>{Object.entries(statusMap).filter(([,value]) => value === status).map(([name]) => <span key={name}>{name.replaceAll("_"," ")}</span>)}</article>)}</section></StandardShell>; }

function InstitutionalPage() { return <StandardShell><section className="page-hero"><StatusBadge status={statusMap.institutional}/><span>INSTITUTIONAL</span><h1>Infrastructure for professional digital-asset operations.</h1><p>Institutional onboarding, team controls, subaccounts, treasury operations and scoped API access are subject to KYB and approval.</p><a className="button button-primary" href={appUrl("/register")}>Start secure application<ArrowRight size={16}/></a></section><section className="page-points">{["KYB-gated onboarding","Team permissions","Segregated subaccounts","Institutional API access"].map(point => <div key={point}><Check size={18}/><strong>{point}</strong></div>)}</section></StandardShell>; }
function DeveloperPage() { return <StandardShell><section className="page-hero"><StatusBadge status={statusMap.developers}/><span>DEVELOPERS</span><h1>Build on ExaEarn infrastructure.</h1><p>Use public market data, signed private APIs, WebSocket streams, sandbox projects and supported SDK tooling.</p><a className="button button-primary" href={DEVELOPERS}>Open Developer Portal<ArrowRight size={16}/></a></section></StandardShell>; }
function ListingPage() { return <StandardShell><section className="page-hero"><StatusBadge status="BETA"/><span>ASSET LISTING</span><h1>Apply through the dedicated listing portal.</h1><p>Project submissions, evidence, due diligence and review status are handled outside the retail exchange experience.</p><a className="button button-primary" href={LISTING === "/listing" ? appUrl("/support") : LISTING}>Open Listing Portal<ArrowRight size={16}/></a></section></StandardShell>; }
function StandardShell({ children }: { children: ReactNode }) { return <><Header/><main className="standard-page">{children}</main><Footer/></>; }

function setPageMetadata(path: string) {
  const item = productPages[path];
  const title = item ? `${item.eyebrow} | ExaEarn` : path === "/" ? "ExaEarn | Crypto Exchange and Digital-Asset Platform" : `${path.slice(1).replaceAll("-", " ")} | ExaEarn`;
  document.title = title;
  const description = item?.copy || "Trade crypto and explore supported digital-asset products through ExaEarn.";
  document.querySelector('meta[name="description"]')?.setAttribute("content", description);
  document.querySelector('meta[property="og:title"]')?.setAttribute("content", title);
  document.querySelector('meta[property="og:description"]')?.setAttribute("content", description);
  document.querySelector('meta[name="twitter:title"]')?.setAttribute("content", title);
  document.querySelector('meta[name="twitter:description"]')?.setAttribute("content", description);
  document.querySelector('link[rel="canonical"]')?.setAttribute("href", `https://exaearn-website.vercel.app${path === "/" ? "/" : path}`);
}

function App() {
  const path = window.location.pathname.replace(/\/+$/, "") || "/";
  useEffect(() => { setPageMetadata(path); window.scrollTo(0, 0); }, [path]);
  if (path === "/") return <HomePage/>;
  if (path === "/markets" || path.startsWith("/markets/")) return <MarketsPage/>;
  if (path === "/products") return <ProductsPage/>;
  if (path === "/roadmap") return <RoadmapPage/>;
  if (path === "/institutional") return <InstitutionalPage/>;
  if (path === "/developers") return <DeveloperPage/>;
  if (path === "/listing") return <ListingPage/>;
  if (productPages[path]) return <ProductPage data={productPages[path]}/>;
  if (["/security","/status","/fees","/risk","/legal","/terms","/privacy","/support","/about","/mobile"].includes(path)) return <InfoPage kind={path.slice(1)}/>;
  return <StandardShell><section className="page-hero"><span>404</span><h1>Page not found.</h1><p>The page may have moved as ExaEarn's public website was reorganized.</p><a className="button button-primary" href="/">Return home</a></section></StandardShell>;
}

createRoot(document.getElementById("root")!).render(<StrictMode><App/></StrictMode>);
