import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import {
  ArrowDown,
  ArrowRight,
  Bell,
  BookOpen,
  Bot,
  BrainCircuit,
  ChartNoAxesCombined,
  CheckCircle2,
  ChevronDown,
  Coins,
  Cpu,
  Disc3,
  Download,
  Fingerprint,
  Gem,
  GraduationCap,
  HandCoins,
  Landmark,
  Layers3,
  Leaf,
  Link,
  LockKeyhole,
  Menu,
  MessageCircle,
  Network,
  Orbit,
  Play,
  Radio,
  Rocket,
  ShieldCheck,
  ShoppingBag,
  Sparkles,
  Sprout,
  Twitter,
  UsersRound,
  Wallet,
  Zap,
} from "lucide-react";
import logo from "./assets/exaearn1.5logo.jpg";
import "./styles/index.css";

const metrics = [
  { label: "Secured ecosystem modules", value: "11+" },
  { label: "Reward rails designed", value: "$2.8M" },
  { label: "Community access nodes", value: "52K" },
  { label: "Infrastructure uptime target", value: "99.9%" },
];

const appScreens = [
  {
    title: "Wallet",
    value: "$18,420.56",
    caption: "Multi-asset balance",
    items: ["EXA", "USDT", "XRP"],
    icon: Wallet,
  },
  {
    title: "Staking",
    value: "14.8% APY",
    caption: "Compounding engine",
    items: ["Flexible", "90 days", "Locked"],
    icon: Coins,
  },
  {
    title: "Rewards",
    value: "8,940 XP",
    caption: "Referral + learn-to-earn",
    items: ["Daily", "Network", "Education"],
    icon: Sparkles,
  },
  {
    title: "Market",
    value: "Live",
    caption: "NFTs, agriculture, utility",
    items: ["NFT", "Farm", "Giftcards"],
    icon: ShoppingBag,
  },
];

const candleSets = [
  [42, 48, 45, 52, 49, 58, 54, 62, 60, 68, 64, 72],
  [60, 56, 62, 58, 66, 70, 64, 73, 69, 76, 72, 80],
  [34, 40, 38, 46, 44, 50, 48, 57, 52, 61, 58, 67],
  [72, 66, 70, 63, 68, 74, 71, 78, 73, 82, 79, 86],
];

const pillars = [
  ["Financial Empowerment", "Wallets, staking, rewards, and ownership tools for everyday digital wealth.", HandCoins],
  ["Real-World Utility", "Marketplace systems that connect crypto rails to practical services and commerce.", Landmark],
  ["Web3 Accessibility", "A clean entry point for users who should not need to understand every protocol layer.", Link],
  ["Intelligent Rewards", "Behavior-aware reward systems for referrals, learning, activity, and ecosystem growth.", BrainCircuit],
  ["Agriculture Integration", "A real-economy layer for farm projects, harvest flows, and community-backed utility.", Leaf],
  ["Learn-to-Earn Education", "Education pathways where users gain knowledge and earn participation value.", GraduationCap],
  ["Tokenized Economy", "ExaToken powers access, incentives, governance, marketplace utility, and staking.", Coins],
  ["Secure Infrastructure", "Authentication, transparency, risk signals, and audit-ready operating foundations.", ShieldCheck],
  ["Community-Driven", "A contributor ecosystem shaped by users, builders, ambassadors, and local networks.", UsersRound],
];

const features = [
  ["Smart Multi-Asset Wallet", "Hold, monitor, and move digital assets through a secure wallet layer.", Wallet],
  ["Advanced Staking Engine", "Stake assets and participate in programmable yield opportunities.", Coins],
  ["Real-World Marketplace", "Access digital services, commerce rails, agriculture, and utility products.", ShoppingBag],
  ["Referral Reward Economy", "Turn community growth into transparent, trackable reward flows.", HandCoins],
  ["ExaToken Infrastructure", "Use EXA across rewards, access, marketplace utility, and governance.", Cpu],
  ["Intelligent Reward Analytics", "Understand earnings, streaks, network growth, and ecosystem activity.", ChartNoAxesCombined],
  ["Crypto to Fiat Integration", "Bridge digital finance to familiar payment rails and local utility.", Landmark],
  ["Learn-to-Earn System", "Earn through courses, knowledge milestones, and ecosystem education.", BookOpen],
  ["NFT Marketplace", "Trade ownership, memberships, collectibles, and financial NFTs.", Gem],
  ["Agriculture Economy Layer", "Connect blockchain participation to food systems and farm-backed projects.", Sprout],
  ["AI Financial Systems", "Risk signals, insights, automation, and intelligent financial guidance.", Bot],
];

const flowSteps = [
  ["Create Account", "Open your identity layer."],
  ["Smart Onboarding", "Activate profile, security, and preferences."],
  ["Connect Wallet", "Link assets and begin participation."],
  ["Enter Ecosystem", "Access staking, rewards, market, and education."],
  ["Stake / Earn / Trade", "Build value across multiple modules."],
  ["Grow Wealth", "Withdraw, reinvest, or compound growth."],
];

const securityItems = [
  "Advanced encryption",
  "Secure wallet infrastructure",
  "Fraud prevention systems",
  "Smart authentication",
  "Blockchain transparency",
  "Audit-ready architecture",
];

const tokenUtilities = [
  ["Rewards", "Earn EXA through verified participation and community growth."],
  ["Staking", "Use EXA to access ecosystem yield and long-term alignment."],
  ["Marketplace", "Spend or unlock benefits across commerce, NFTs, and real-world utility."],
  ["Governance", "Participate in future ecosystem proposals and priority decisions."],
];

const ecosystemNodes = [
  ["Mobile App", Wallet],
  ["Wallet Infrastructure", LockKeyhole],
  ["Staking Engine", Coins],
  ["Rewards System", Sparkles],
  ["NFT Economy", Gem],
  ["Marketplace", ShoppingBag],
  ["Blockchain Layer", Network],
  ["Admin Systems", Layers3],
  ["AI Infrastructure", BrainCircuit],
  ["Community Layer", UsersRound],
];

const roadmap = [
  ["Phase 1", "Foundation & Community", "Brand, access layer, core community, reward logic, and trust foundations."],
  ["Phase 2", "Wallet + Staking Infrastructure", "Secure wallet systems, staking pools, asset dashboards, and reward rails."],
  ["Phase 3", "Marketplace + NFT Expansion", "Utility marketplace, financial NFTs, creator access, and commerce modules."],
  ["Phase 4", "Agriculture + Real Economy Integration", "Farm-backed opportunities, produce tracking, and real-world participation."],
  ["Phase 5", "AI Financial Ecosystem", "Signals, automation, risk intelligence, and intelligent user guidance."],
  ["Phase 6", "Global Expansion", "Regional growth, partner networks, developer systems, and cross-border utility."],
];

const faqs = [
  ["What is ExaEarn?", "ExaEarn is a decentralized financial ecosystem that brings wallets, rewards, staking, education, NFTs, marketplace utility, agriculture, and intelligent finance into one connected economy."],
  ["How does ExaEarn work?", "Users create an account, secure their identity, connect a wallet, then participate through staking, rewards, learning, marketplace access, and token-powered ecosystem modules."],
  ["Is ExaEarn decentralized?", "ExaEarn is designed around Web3 infrastructure and blockchain transparency while keeping the user experience simple enough for mainstream adoption."],
  ["How secure is the wallet?", "The wallet experience is positioned around encryption, smart authentication, monitoring, risk prevention, and audit-ready infrastructure."],
  ["How does staking work?", "Staking lets users lock or allocate eligible assets into ecosystem pools to earn rewards based on program rules and participation terms."],
  ["Is KYC required?", "Some features may require identity verification where regulation, fiat rails, fraud prevention, or higher-risk activity requires it."],
  ["What is ExaToken?", "ExaToken is the ecosystem utility token for rewards, staking, marketplace access, governance participation, and network incentives."],
  ["How do rewards work?", "Rewards are earned through eligible activities such as referrals, learning, staking, daily engagement, ecosystem contributions, and marketplace participation."],
];

function ButtonLink({ href, className = "", children }) {
  return (
    <a className={`action-button ${className}`} href={href}>
      {children}
    </a>
  );
}

function AmbientParticles() {
  return (
    <div className="ambient-particles" aria-hidden="true">
      {Array.from({ length: 60 }, (_, index) => (
        <span key={index} style={{ "--i": index }} />
      ))}
    </div>
  );
}

function HeroObject() {
  return (
    <div className="hero-object" aria-hidden="true">
      <div className="orbit-system">
        <span />
        <span />
        <span />
      </div>
      <div className="economy-core">
        <div className="core-face">
          <img src={logo} alt="" />
        </div>
        <i className="core-glow" />
      </div>
      <div className="signal-chip chip-alpha">Wallet secured</div>
      <div className="signal-chip chip-beta">EXA network live</div>
      <div className="signal-chip chip-gamma">Reward rail active</div>
    </div>
  );
}

function Hero() {
  return (
    <section className="hero section-band" id="top">
      <AmbientParticles />
      <div className="hero-grid" aria-hidden="true" />
      <div className="hero-copy">
        <p className="eyebrow">ExaEarn - the future digital economy</p>
        <h1>Enter the ExaEarn Economy</h1>
        <p>
          A decentralized ecosystem where finance, blockchain, agriculture, education,
          rewards, and digital assets merge into one intelligent financial network.
        </p>
        <div className="hero-actions">
          <ButtonLink className="primary" href="#ecosystem">
            <Rocket size={18} /> Enter Ecosystem
          </ButtonLink>
          <ButtonLink href="#download">
            <Download size={18} /> Download App
          </ButtonLink>
          <ButtonLink href="#connect">
            <Wallet size={18} /> Connect Wallet
          </ButtonLink>
        </div>
        <div className="trust-strip" aria-label="Ecosystem indicators">
          {metrics.map((metric) => (
            <div key={metric.label}>
              <strong>{metric.value}</strong>
              <span>{metric.label}</span>
            </div>
          ))}
        </div>
      </div>
      <HeroObject />
      <a className="scroll-cue" href="#mobile" aria-label="Scroll to app showcase">
        <ArrowDown size={18} />
      </a>
    </section>
  );
}

function PhoneMockup({ screen, index }) {
  const Icon = screen.icon;
  const candles = candleSets[index % candleSets.length];
  return (
    <article className="phone-shell" style={{ "--phone": index }}>
      <div className="phone-camera" />
      <div className="phone-screen">
        <div className="phone-topline">
          <span>{screen.title}</span>
          <Bell size={15} />
        </div>
        <div className="phone-hero-card">
          <Icon size={28} />
          <small>{screen.caption}</small>
          <strong>{screen.value}</strong>
        </div>
        <div className="phone-chart" aria-label={`${screen.title} live market chart`}>
          <div className="chart-toolbar">
            <span>EXA / USDT</span>
            <strong>{index % 2 === 0 ? "+8.42%" : "+3.18%"}</strong>
          </div>
          <div className="candle-grid" aria-hidden="true">
            {candles.map((value, candleIndex) => {
              const previous = candles[Math.max(0, candleIndex - 1)];
              const isUp = value >= previous;
              return (
                <span
                  className={isUp ? "candle up" : "candle down"}
                  key={`${value}-${candleIndex}`}
                  style={{
                    "--candle": candleIndex,
                    "--high": `${Math.min(86, value + 12)}%`,
                    "--low": `${Math.max(10, value - 18)}%`,
                    "--open": `${previous}%`,
                    "--close": `${value}%`,
                  }}
                />
              );
            })}
            <i className="chart-line" />
          </div>
        </div>
        <div className="phone-list">
          {screen.items.map((item) => (
            <div key={item}>
              <span>{item}</span>
              <strong>Active</strong>
            </div>
          ))}
        </div>
      </div>
    </article>
  );
}

function MobileShowcase() {
  return (
    <section className="section-band app-showcase" id="mobile">
      <div className="section-heading split">
        <div>
          <p className="eyebrow">Mobile-first ecosystem</p>
          <h2>A premium financial universe in your pocket.</h2>
        </div>
        <p>
          ExaEarn is designed around daily mobile participation: wallet visibility,
          staking flows, marketplace access, rewards, referrals, NFTs, and analytics.
        </p>
      </div>
      <div className="phone-stage">
        {appScreens.map((screen, index) => (
          <PhoneMockup key={screen.title} screen={screen} index={index} />
        ))}
      </div>
    </section>
  );
}

function WhyExaEarn() {
  return (
    <section className="section-band" id="why">
      <div className="section-heading">
        <p className="eyebrow">Why ExaEarn</p>
        <h2>A real-world decentralized financial ecosystem, not another crypto exchange.</h2>
      </div>
      <div className="pillar-grid">
        {pillars.map(([title, text, Icon]) => (
          <article className="glass-module" key={title}>
            <Icon size={25} />
            <h3>{title}</h3>
            <p>{text}</p>
          </article>
        ))}
      </div>
    </section>
  );
}

function FeatureGrid() {
  return (
    <section className="section-band feature-section" id="features">
      <div className="section-heading split">
        <div>
          <p className="eyebrow">Core features</p>
          <h2>Modules inside a financial operating system.</h2>
        </div>
        <p>
          Every feature is connected to the same economy: ownership, rewards,
          identity, commerce, education, agriculture, and intelligent finance.
        </p>
      </div>
      <div className="feature-grid">
        {features.map(([title, text, Icon]) => (
          <article className="feature-card" key={title}>
            <div className="feature-icon">
              <Icon size={24} />
            </div>
            <h3>{title}</h3>
            <p>{text}</p>
          </article>
        ))}
      </div>
    </section>
  );
}

function HowItWorks() {
  return (
    <section className="section-band flow-section" id="how">
      <div className="flow-copy">
        <p className="eyebrow">How ExaEarn works</p>
        <h2>Simple entry. Intelligent participation. Connected value.</h2>
        <p>
          ExaEarn hides protocol complexity behind a clear journey so users can
          move from onboarding to earning without feeling overwhelmed.
        </p>
      </div>
      <div className="flow-chain">
        {flowSteps.map(([title, text], index) => (
          <article className="flow-step" key={title}>
            <span>{String(index + 1).padStart(2, "0")}</span>
            <div>
              <h3>{title}</h3>
              <p>{text}</p>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}

function TrustSecurity() {
  return (
    <section className="section-band security-section" id="security">
      <div className="security-visual" aria-hidden="true">
        <div className="vault-shell">
          <ShieldCheck size={72} />
          <span />
          <span />
          <span />
        </div>
      </div>
      <div className="security-copy">
        <p className="eyebrow">Trust + security</p>
        <h2>Built to feel secure enough for serious financial participation.</h2>
        <p>
          ExaEarn presents a protected environment with wallet security,
          authentication, fraud intelligence, transparent records, and operating
          architecture designed for audits and scale.
        </p>
        <div className="security-list">
          {securityItems.map((item) => (
            <span key={item}>
              <CheckCircle2 size={17} /> {item}
            </span>
          ))}
        </div>
      </div>
    </section>
  );
}

function TokenSection() {
  return (
    <section className="section-band token-section" id="token">
      <div className="token-core" aria-hidden="true">
        <Disc3 size={92} />
        <img src={logo} alt="" />
      </div>
      <div className="token-copy">
        <p className="eyebrow">ExaToken utility</p>
        <h2>The coordination asset for participation, access, and rewards.</h2>
        <p>
          ExaToken is designed as the ecosystem utility layer. It supports staking,
          rewards, marketplace access, governance participation, and incentives
          across the ExaEarn economy.
        </p>
      </div>
      <div className="utility-grid">
        {tokenUtilities.map(([title, text]) => (
          <article key={title}>
            <h3>{title}</h3>
            <p>{text}</p>
          </article>
        ))}
      </div>
    </section>
  );
}

function EcosystemMap() {
  return (
    <section className="section-band ecosystem-map" id="ecosystem">
      <div className="section-heading">
        <p className="eyebrow">ExaEarn ecosystem map</p>
        <h2>An entire digital economy, connected around one intelligent core.</h2>
      </div>
      <div className="map-stage">
        <div className="map-core">
          <img src={logo} alt="ExaEarn" />
          <strong>ExaEarn Core</strong>
        </div>
        {ecosystemNodes.map(([title, Icon], index) => (
          <article className="map-node" key={title} style={{ "--node": index }}>
            <Icon size={22} />
            <span>{title}</span>
          </article>
        ))}
      </div>
    </section>
  );
}

function Roadmap() {
  return (
    <section className="section-band roadmap-section" id="roadmap">
      <div className="section-heading">
        <p className="eyebrow">Roadmap</p>
        <h2>Expansion phases for the future decentralized economy.</h2>
      </div>
      <div className="timeline">
        {roadmap.map(([phase, title, text], index) => (
          <article className="timeline-item" key={phase} style={{ "--phase": index }}>
            <span>{phase}</span>
            <h3>{title}</h3>
            <p>{text}</p>
          </article>
        ))}
      </div>
    </section>
  );
}

function Community() {
  return (
    <section className="section-band community-section" id="community">
      <div>
        <p className="eyebrow">Community + ecosystem</p>
        <h2>Built with a global network of users, builders, and contributors.</h2>
        <p>
          Web3 economies become real through participation. ExaEarn is shaped for
          Telegram communities, Discord contributors, X updates, regional ambassadors,
          ecosystem releases, and builders who want practical utility.
        </p>
      </div>
      <div className="community-grid">
        <a href="#community"><MessageCircle size={22} /> Telegram</a>
        <a href="#community"><UsersRound size={22} /> Discord</a>
        <a href="#community"><Twitter size={22} /> X / Twitter</a>
        <a href="#community"><Radio size={22} /> Updates</a>
      </div>
    </section>
  );
}

function DownloadAccess() {
  return (
    <section className="section-band download-section" id="download">
      <div className="download-panel">
        <div>
          <p className="eyebrow">Download / access</p>
          <h2>Access the future financial system.</h2>
          <p>
            Join the waitlist, prepare your wallet, and step into the ExaEarn app
            experience as the ecosystem opens across mobile and Web3 rails.
          </p>
        </div>
        <div className="download-actions">
          <ButtonLink className="store" href="#download">
            <Download size={19} /> Download on App Store
          </ButtonLink>
          <ButtonLink className="store" href="#download">
            <Play size={19} /> Get it on Google Play
          </ButtonLink>
          <ButtonLink className="primary" href="#connect">
            <Rocket size={19} /> Join Waitlist
          </ButtonLink>
        </div>
      </div>
    </section>
  );
}

function FAQ() {
  return (
    <section className="section-band faq-section" id="faq">
      <div className="section-heading">
        <p className="eyebrow">FAQ</p>
        <h2>Clear answers for a serious ecosystem.</h2>
      </div>
      <div className="faq-list">
        {faqs.map(([question, answer]) => (
          <details key={question}>
            <summary>
              <span>{question}</span>
              <ChevronDown size={18} />
            </summary>
            <p>{answer}</p>
          </details>
        ))}
      </div>
    </section>
  );
}

function FinalCta() {
  return (
    <section className="final-cta" id="connect">
      <AmbientParticles />
      <div className="final-symbol" aria-hidden="true">
        <img src={logo} alt="" />
      </div>
      <p className="eyebrow">Join the economy</p>
      <h2>The future is not coming. It is being built inside ExaEarn.</h2>
      <p>
        Join the decentralized ecosystem redefining digital finance, rewards,
        ownership, and real-world utility.
      </p>
      <div className="hero-actions">
        <ButtonLink className="primary" href="/app">
          <Rocket size={18} /> Join the Economy
        </ButtonLink>
        <ButtonLink href="#download">
          <Download size={18} /> Download App
        </ButtonLink>
        <ButtonLink href="#connect">
          <Wallet size={18} /> Connect Wallet
        </ButtonLink>
      </div>
    </section>
  );
}

function App() {
  return (
    <main className="site-shell">
      <nav className="topbar" aria-label="Primary navigation">
        <a className="brand" href="#top">
          <img src={logo} alt="ExaEarn" />
          <span>ExaEarn</span>
        </a>
        <div className="nav-links">
          <a href="#mobile">App</a>
          <a href="#features">Features</a>
          <a href="#security">Security</a>
          <a href="#roadmap">Roadmap</a>
          <a href="#faq">FAQ</a>
        </div>
        <a className="nav-action" href="/app">
          <LockKeyhole size={16} /> Launch App
        </a>
        <details className="mobile-menu">
          <summary aria-label="Open navigation menu">
            <Menu size={22} />
          </summary>
          <div className="mobile-menu-panel">
            <div className="mobile-menu-links">
              <a href="#mobile">App</a>
              <a href="#features">Features</a>
              <a href="#security">Security</a>
              <a href="#roadmap">Roadmap</a>
              <a href="#faq">FAQ</a>
            </div>
            <div className="mobile-auth-actions" aria-label="Account actions">
              <a href="/app">Sign in</a>
              <a href="/app">Sign up</a>
            </div>
            <div className="mobile-store-actions" aria-label="Download app">
              <a href="#download">
                <Download size={17} /> App Store
              </a>
              <a href="#download">
                <Play size={17} /> Play Store
              </a>
            </div>
          </div>
        </details>
      </nav>
      <Hero />
      <MobileShowcase />
      <WhyExaEarn />
      <FeatureGrid />
      <HowItWorks />
      <TrustSecurity />
      <TokenSection />
      <EcosystemMap />
      <Roadmap />
      <Community />
      <DownloadAccess />
      <FAQ />
      <FinalCta />
    </main>
  );
}

createRoot(document.getElementById("root")).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
