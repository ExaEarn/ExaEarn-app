// @ts-nocheck
import { StrictMode, useEffect, useMemo, useRef, useState } from "react";
import { createRoot } from "react-dom/client";
import { AnimatePresence, motion, useMotionTemplate, useMotionValue, useScroll, useSpring, useTransform } from "framer-motion";
import {
  Apple,
  ArrowDown,
  ArrowRight,
  Bell,
  BookOpen,
  Bot,
  BrainCircuit,
  Check,
  ChartNoAxesCombined,
  CheckCircle2,
  ChevronDown,
  Coins,
  Globe2,
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
  LogOut,
  Menu,
  MessageCircle,
  Network,
  Orbit,
  Play,
  QrCode,
  Radio,
  RefreshCcw,
  Rocket,
  Settings,
  ShieldCheck,
  Search,
  Smartphone,
  ShoppingBag,
  Sparkles,
  Sprout,
  Twitter,
  UsersRound,
  Wallet,
  X,
  Zap,
} from "lucide-react";
import {
  DEFAULT_LANGUAGE_CODE,
  LANGUAGE_STORAGE_KEY,
  formatLanguageLabel,
  getLanguageByCode,
  resolvePreferredLanguage,
  searchLanguages,
} from "@exaearn/config";
import logo from "./assets/exaearn1.5logo.jpg";
import "./styles/index.css";

const cinematicEase = [0.19, 1, 0.22, 1];

function isLoopbackUrl(value) {
  try {
    const host = new URL(value).hostname;
    return host === "localhost" || host === "127.0.0.1" || host === "::1";
  } catch {
    return false;
  }
}

function resolveWebAppBaseUrl() {
  const configuredUrl = import.meta.env.VITE_WEB_APP_URL?.trim();
  if (configuredUrl && (import.meta.env.DEV || !isLoopbackUrl(configuredUrl))) {
    return configuredUrl.replace(/\/+$/, "");
  }

  if (import.meta.env.DEV) {
    return "http://127.0.0.1:5173";
  }

  return "/app";
}

const webAppBaseUrl = resolveWebAppBaseUrl();

function webAppUrl(path = "") {
  const normalizedPath = path.startsWith("/") ? path : `/${path}`;
  return `${webAppBaseUrl}${normalizedPath}`;
}

const WEB_APP_SIGNUP_URL = webAppUrl("/register");
const WEB_APP_LOGIN_URL = webAppUrl("/login");

const fadeUp = {
  hidden: { opacity: 1, y: 10 },
  visible: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.32, ease: cinematicEase },
  },
};

const staggerGroup = {
  hidden: {},
  visible: {
    transition: {
      staggerChildren: 0.025,
      delayChildren: 0.02,
    },
  },
};

function Reveal({ children, className = "", delay = 0, as: Component = motion.div, ...props }) {
  return (
    <Component
      className={className}
      variants={fadeUp}
      initial="hidden"
      whileInView="visible"
      viewport={{ once: true, amount: 0.08, margin: "0px 0px 12% 0px" }}
      transition={{ delay }}
      {...props}
    >
      {children}
    </Component>
  );
}

const metrics = [
  { label: "Secured ecosystem modules", value: "11+" },
  { label: "Reward rails designed", value: "$2.8M" },
  { label: "Community access nodes", value: "52K" },
  { label: "Infrastructure uptime target", value: "99.9%" },
];

const institutionalSignals = [
  ["Institutional-grade treasury logic", "Policy-aware wallet, staking, and marketplace flows designed for auditability.", ShieldCheck],
  ["Programmable reward intelligence", "Behavior-aware incentives that align community growth with real ecosystem utility.", BrainCircuit],
  ["Real-world asset connectivity", "Agriculture, learning, commerce, and NFT rails connected through one financial layer.", Landmark],
];

const trustRails = [
  ["Risk", "Adaptive monitoring, fraud signals, and transparent account activity across treasury and wallet flows."],
  ["Scale", "Composable modules for wallets, staking, markets, rewards, and institution-ready rails."],
  ["Access", "Mobile-first onboarding and wallet access with low-friction participation for every user."],
  ["Utility", "EXA-native coordination across rewards, marketplace access, governance, and real-world asset rails."],
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
  ["Web3 Accessibility", "A clean entry point for users who want Web3 power without protocol complexity.", Link],
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

const footerLinks = [
  {
    title: "Platform",
    links: [
      ["Mobile app", "#mobile"],
      ["Core features", "#features"],
      ["Security", "#security"],
      ["Roadmap", "#roadmap"],
    ],
  },
  {
    title: "Ecosystem",
    links: [
      ["Wallet infrastructure", "#ecosystem"],
      ["ExaToken utility", "#token"],
      ["Rewards engine", "#why"],
      ["Marketplace rails", "#features"],
    ],
  },
  {
    title: "Company",
    links: [
      ["Community", "#community"],
      ["Download", "#download"],
      ["FAQ", "#faq"],
      ["Launch app", WEB_APP_LOGIN_URL],
    ],
  },
];

const footerSignals = [
  ["Protocol", "Online", ShieldCheck],
  ["Treasury", "Audit-ready", Landmark],
  ["Network", "Scaling", Network],
];

const footerHighlights = [
  ["11+", "ecosystem modules"],
  ["52K", "access nodes"],
  ["99.9%", "uptime target"],
];

const footerChannels = [
  ["Telegram", "Community desk", MessageCircle, "https://t.me/ExaEarn"],
  ["X / Twitter", "Market updates", Twitter, "#community"],
  ["Release notes", "Product signals", Radio, "#roadmap"],
];

const footerTrustNotes = [
  "Encrypted wallet architecture",
  "Fraud-aware reward activity",
  "Audit-ready treasury records",
];

const downloadPlatforms = [
  {
    title: "Android",
    description: "Download from Google Play for the best Android experience.",
    action: "Get on Google Play",
    href: "#download-android",
    icon: Smartphone,
    accent: "android",
  },
  {
    title: "iPhone",
    description: "Download from the App Store and enjoy ExaEarn on iOS.",
    action: "Download on App Store",
    href: "#download-ios",
    icon: Apple,
    accent: "ios",
  },
];

const modalTrustBadges = ["Secure", "Encrypted", "Web3 Powered"];

const walletOptions = [
  ["MetaMask", "Connect using the world's most trusted Web3 wallet.", "MM", "metamask"],
  ["Trust Wallet", "Access ExaEarn securely from mobile and desktop devices.", "TW", "trust"],
  ["Coinbase Wallet", "Secure self-custody wallet with seamless Web3 access.", "CB", "coinbase"],
  ["OKX Wallet", "Multi-chain wallet optimized for DeFi and Web3 applications.", "OK", "okx"],
  ["WalletConnect", "Instantly connect hundreds of supported wallets.", "WC", "walletconnect"],
];

const walletFlowSteps = [
  "Establishing Secure Wallet Connection",
  "Verifying Wallet Ownership",
  "Detecting Blockchain Network",
  "Linking Wallet Verification",
  "Preparing Signup Session",
];

const walletUseCases = [
  "Staking",
  "Earning Rewards",
  "NFT Minting",
  "Marketplace Activities",
  "Blockchain Gaming",
  "Deposits & Withdrawals",
  "Referral Rewards",
  "On-Chain Transactions",
  "Future Ecosystem Features",
];

const walletActivities = ["Staking", "Rewards", "NFTs", "Gaming", "Marketplace", "Deposits", "Withdrawals"];

const walletTrustBadges = [
  "Non-Custodial",
  "Secure Wallet Connection",
  "Encrypted Session",
  "Verified Identity",
  "Web3 Powered",
];

const accountMenuItems = [
  ["Continue Signup", "Create your ExaEarn profile", Rocket, WEB_APP_SIGNUP_URL],
  ["Identity Setup", "Add personal account details", Fingerprint, WEB_APP_SIGNUP_URL],
  ["Security Setup", "Protect your new account", LockKeyhole, WEB_APP_SIGNUP_URL],
  ["Wallet Verification", "Review linked wallet status", ShieldCheck, WEB_APP_SIGNUP_URL],
  ["Rewards Preview", "See what unlocks after signup", Sparkles, WEB_APP_SIGNUP_URL],
  ["Account Preferences", "Prepare settings after registration", Settings, WEB_APP_SIGNUP_URL],
];

const connectedWalletProfile = {
  address: "0x73A4...9F2C",
  network: "BNB Smart Chain",
  status: "Wallet Verified",
  identity: "Signup Required",
};

function isMobileViewport() {
  if (typeof window === "undefined") return false;
  return window.matchMedia("(max-width: 760px)").matches;
}

function ButtonLink({ href, className = "", children, onClick, ...props }) {
  return (
    <motion.a
      className={`action-button ${className}`}
      href={href}
      onClick={onClick}
      whileHover={{ y: -4, scale: 1.015 }}
      whileTap={{ scale: 0.975 }}
      transition={{ type: "spring", stiffness: 420, damping: 28 }}
      {...props}
    >
      {children}
    </motion.a>
  );
}

function DownloadAppModal({ isOpen, onClose }) {
  const closeButtonRef = useRef(null);

  useEffect(() => {
    if (!isOpen) return undefined;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    window.setTimeout(() => closeButtonRef.current?.focus(), 80);

    const handleKeyDown = (event) => {
      if (event.key === "Escape") {
        onClose();
      }
    };

    window.addEventListener("keydown", handleKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", handleKeyDown);
    };
  }, [isOpen, onClose]);

  return (
    <AnimatePresence>
      {isOpen ? (
        <motion.div
          className="download-modal-layer"
          role="presentation"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.24, ease: "easeOut" }}
        >
          <motion.button
            className="download-modal-backdrop"
            aria-label="Close download modal"
            type="button"
            onClick={onClose}
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
          />
          <motion.section
            className="download-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="download-modal-title"
            aria-describedby="download-modal-description"
            initial={{ opacity: 0, scale: 0.92, y: 26, filter: "blur(10px)" }}
            animate={{ opacity: 1, scale: 1, y: 0, filter: "blur(0px)" }}
            exit={{ opacity: 0, scale: 0.94, y: 18, filter: "blur(8px)" }}
            transition={{ duration: 0.42, ease: cinematicEase }}
          >
            <motion.div
              className="download-modal-glow glow-a"
              aria-hidden="true"
              animate={{ x: [0, 16, -8, 0], y: [0, -12, 10, 0], opacity: [0.48, 0.82, 0.56, 0.48] }}
              transition={{ duration: 7, repeat: Infinity, ease: "easeInOut" }}
            />
            <motion.div
              className="download-modal-glow glow-b"
              aria-hidden="true"
              animate={{ x: [0, -14, 10, 0], y: [0, 12, -8, 0], opacity: [0.34, 0.66, 0.44, 0.34] }}
              transition={{ duration: 8.4, repeat: Infinity, ease: "easeInOut" }}
            />
            <div className="download-modal-grid" aria-hidden="true" />

            <header className="download-modal-header">
              <motion.div className="download-modal-brand" whileHover={{ scale: 1.025 }}>
                <img src={logo} alt="ExaEarn" />
                <span>
                  <strong id="download-modal-title">Download ExaEarn</strong>
                  <small>Premium mobile access</small>
                </span>
              </motion.div>
              <motion.button
                ref={closeButtonRef}
                className="download-modal-close"
                type="button"
                aria-label="Close download modal"
                onClick={onClose}
                whileHover={{ y: -2, scale: 1.04 }}
                whileTap={{ scale: 0.95 }}
              >
                <X size={18} />
              </motion.button>
            </header>

            <div className="download-modal-copy">
              <p id="download-modal-description">
                Choose your preferred platform and start earning, staking, swapping, and growing with ExaEarn.
              </p>
            </div>

            <div className="download-platform-grid">
              {downloadPlatforms.map(({ title, description, action, href, icon: Icon, accent }) => (
                <motion.a
                  className={`download-platform-card ${accent}`}
                  key={title}
                  href={href}
                  whileHover={{ y: -10, scale: 1.018 }}
                  whileTap={{ scale: 0.98 }}
                  transition={{ type: "spring", stiffness: 320, damping: 24 }}
                >
                  <span className="download-platform-orb">
                    <Icon size={30} aria-hidden="true" />
                  </span>
                  <span className="download-platform-content">
                    <strong>{title}</strong>
                    <small>{description}</small>
                  </span>
                  <span className="download-store-button">
                    {accent === "ios" ? <Apple size={18} /> : <Play size={18} />}
                    {action}
                  </span>
                </motion.a>
              ))}
            </div>

            <div className="download-modal-lower">
              <section className="download-trust-panel" aria-label="Security and trust">
                <div className="download-badge-row">
                  {modalTrustBadges.map((badge) => (
                    <motion.span key={badge} whileHover={{ y: -3 }}>
                      <ShieldCheck size={15} />
                      {badge}
                    </motion.span>
                  ))}
                </div>
                <p>
                  Trusted by users earning through staking, rewards, swaps, referrals, and decentralized finance.
                </p>
              </section>

              <section className="download-qr-panel" aria-labelledby="download-qr-title">
                <div>
                  <h3 id="download-qr-title">Scan to Download</h3>
                  <p>Open your phone camera and scan to install ExaEarn instantly.</p>
                </div>
                <motion.div className="download-qr-shell" whileHover={{ scale: 1.025, rotate: -0.5 }}>
                  <QrCode className="download-qr-icon" size={28} aria-hidden="true" />
                  <div className="download-qr-code" aria-label="ExaEarn download QR code">
                    {Array.from({ length: 49 }, (_, index) => (
                      <span key={index} className={(index * 7 + index % 5) % 3 === 0 ? "active" : ""} />
                    ))}
                  </div>
                </motion.div>
              </section>
            </div>
          </motion.section>
        </motion.div>
      ) : null}
    </AnimatePresence>
  );
}

function WalletOnboardingModal({ isOpen, onClose, onConnected, isConnected, onDisconnect }) {
  const closeButtonRef = useRef(null);
  const [selectedWallet, setSelectedWallet] = useState(null);
  const [phase, setPhase] = useState("select");
  const [activeStep, setActiveStep] = useState(0);
  const [isCompactWalletFlow, setIsCompactWalletFlow] = useState(() => isMobileViewport());

  useEffect(() => {
    if (!isOpen) return undefined;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    window.setTimeout(() => closeButtonRef.current?.focus(), 80);

    const handleKeyDown = (event) => {
      if (event.key === "Escape") {
        onClose();
      }
    };

    window.addEventListener("keydown", handleKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", handleKeyDown);
    };
  }, [isOpen, onClose]);

  useEffect(() => {
    if (!isOpen) return undefined;

    const mediaQuery = window.matchMedia("(max-width: 760px)");
    const syncCompactState = () => setIsCompactWalletFlow(mediaQuery.matches);

    syncCompactState();
    mediaQuery.addEventListener("change", syncCompactState);

    return () => mediaQuery.removeEventListener("change", syncCompactState);
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) return;

    if (isConnected) {
      setPhase("success");
      setActiveStep(walletFlowSteps.length);
      return;
    }

    setSelectedWallet(null);
    setPhase("select");
    setActiveStep(0);
  }, [isOpen, isConnected]);

  useEffect(() => {
    if (phase !== "connecting") return undefined;

    setActiveStep(0);
    if (isMobileViewport()) {
      setActiveStep(walletFlowSteps.length);
      setPhase("success");
      onConnected();
      return undefined;
    }

    const firstDelay = isCompactWalletFlow ? 180 : 420;
    const stepDelay = isCompactWalletFlow ? 140 : 320;
    const finalDelay = isCompactWalletFlow ? 160 : 320;
    const timers = [];
    const fallbackTimer = window.setTimeout(() => {
      setActiveStep(walletFlowSteps.length);
      setPhase("success");
      onConnected();
    }, firstDelay + walletFlowSteps.length * stepDelay + finalDelay + 240);
    timers.push(fallbackTimer);
    walletFlowSteps.forEach((_, index) => {
      const timer = window.setTimeout(() => {
        setActiveStep(index + 1);
        if (index === walletFlowSteps.length - 1) {
          const successTimer = window.setTimeout(() => {
            setPhase("success");
            onConnected();
          }, finalDelay);
          timers.push(successTimer);
        }
      }, firstDelay + index * stepDelay);
      timers.push(timer);
    });

    return () => {
      timers.forEach((timer) => window.clearTimeout(timer));
    };
  }, [phase, isCompactWalletFlow, onConnected]);

  const selectWallet = (wallet) => {
    setSelectedWallet(wallet);

    if (isCompactWalletFlow || isMobileViewport()) {
      setActiveStep(walletFlowSteps.length);
      setPhase("success");
      onConnected();
      return;
    }

    setPhase("connecting");
  };

  const switchWallet = () => {
    onDisconnect();
    setSelectedWallet(null);
    setActiveStep(0);
    setPhase("select");
  };

  return (
    <AnimatePresence>
      {isOpen ? (
        <motion.div
          className="wallet-modal-layer"
          role="presentation"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.24, ease: "easeOut" }}
        >
          <motion.button className="wallet-modal-backdrop" type="button" aria-label="Close wallet onboarding" onClick={onClose} />
          <motion.section
            className="wallet-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="wallet-modal-title"
            aria-describedby="wallet-modal-description"
            initial={{ opacity: 0, scale: 0.92, y: 28, filter: "blur(10px)" }}
            animate={{ opacity: 1, scale: 1, y: 0, filter: "blur(0px)" }}
            exit={{ opacity: 0, scale: 0.94, y: 18, filter: "blur(8px)" }}
            transition={{ duration: 0.42, ease: cinematicEase }}
          >
            <motion.div
              className="wallet-modal-orb orb-a"
              aria-hidden="true"
              animate={{ x: [0, 18, -10, 0], y: [0, -14, 10, 0], opacity: [0.5, 0.82, 0.56, 0.5] }}
              transition={{ duration: 7.2, repeat: Infinity, ease: "easeInOut" }}
            />
            <motion.div
              className="wallet-modal-orb orb-b"
              aria-hidden="true"
              animate={{ x: [0, -16, 12, 0], y: [0, 12, -10, 0], opacity: [0.34, 0.64, 0.42, 0.34] }}
              transition={{ duration: 8.6, repeat: Infinity, ease: "easeInOut" }}
            />
            <div className="wallet-modal-grid" aria-hidden="true" />

            <header className="wallet-modal-header">
              <div className="wallet-modal-brand">
                <img src={logo} alt="ExaEarn" />
                <span>
                  <strong id="wallet-modal-title">Unlock Your ExaEarn Account</strong>
                  <small>Secure wallet identity activation</small>
                </span>
              </div>
              <motion.button
                ref={closeButtonRef}
                className="download-modal-close"
                type="button"
                aria-label="Close wallet onboarding"
                onClick={onClose}
                whileHover={{ y: -2, scale: 1.04 }}
                whileTap={{ scale: 0.95 }}
              >
                <X size={18} />
              </motion.button>
            </header>

            <div className="wallet-modal-layout">
              <aside className="wallet-identity-panel">
                <p id="wallet-modal-description">
                  Connect your wallet to securely verify ownership before creating your ExaEarn account.
                </p>
                <p>
                  Your wallet becomes the verified on-chain layer for your profile, but signup still completes inside the ExaEarn app with your account details, security settings, and onboarding preferences.
                </p>
                <div className="wallet-usecase-grid" aria-label="Wallet use cases">
                  {walletUseCases.map((item) => (
                    <span key={item}>
                      <CheckCircle2 size={14} />
                      {item}
                    </span>
                  ))}
                </div>
              </aside>

              <div className="wallet-flow-panel">
                <AnimatePresence mode="wait">
                  {phase === "select" ? (
                    <motion.div
                      key="select"
                      initial={{ opacity: 0, y: 14 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: -14 }}
                      transition={{ duration: 0.25 }}
                    >
                      <div className="wallet-panel-heading">
                        <span>Wallet Selection</span>
                        <small>Choose a secure provider</small>
                      </div>
                      <div className="wallet-option-grid">
                        {walletOptions.map((wallet) => {
                          const [name, description, mark, accent] = wallet;
                          return (
                            <motion.button
                              type="button"
                              className={`wallet-option-card ${accent}`}
                              key={name}
                              onClick={() => selectWallet(wallet)}
                              whileHover={{ y: -7, scale: 1.012 }}
                              whileTap={{ scale: 0.98 }}
                              transition={{ type: "spring", stiffness: 340, damping: 24 }}
                            >
                              <span className="wallet-logo-mark">{mark}</span>
                              <span>
                                <strong>{name}</strong>
                                <small>{description}</small>
                              </span>
                              <i aria-hidden="true" />
                            </motion.button>
                          );
                        })}
                      </div>
                    </motion.div>
                  ) : null}

                  {phase === "connecting" ? (
                    <motion.div
                      key="connecting"
                      className="wallet-progress-state"
                      initial={{ opacity: 0, y: 14 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: -14 }}
                      transition={{ duration: 0.25 }}
                    >
                      <div className="wallet-progress-core" aria-hidden="true">
                        <motion.span
                          animate={{ rotate: 360 }}
                          transition={{ duration: 1.2, repeat: Infinity, ease: "linear" }}
                        />
                        <strong>{selectedWallet?.[0]}</strong>
                      </div>
                      <div className="wallet-progress-list">
                        {walletFlowSteps.map((step, index) => {
                          const isComplete = activeStep > index;
                          const isCurrent = activeStep === index;
                          return (
                            <motion.div className={isComplete ? "complete" : isCurrent ? "current" : ""} key={step}>
                              <span>{isComplete ? <Check size={15} /> : String(index + 1)}</span>
                              <p>{step}</p>
                            </motion.div>
                          );
                        })}
                      </div>
                    </motion.div>
                  ) : null}

                  {phase === "success" ? (
                    <motion.div
                      key="success"
                      className="wallet-success-state"
                      initial={{ opacity: 0, y: 14 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: -14 }}
                      transition={{ duration: 0.28 }}
                    >
                      <motion.div
                        className="wallet-success-check"
                        initial={{ scale: 0.74, opacity: 0 }}
                        animate={{ scale: 1, opacity: 1 }}
                        transition={{ type: "spring", stiffness: 360, damping: 20 }}
                      >
                        <Check size={34} />
                      </motion.div>
                      <h3>Wallet Verification Complete</h3>
                      <p>
                        Your wallet has been verified and reserved for your ExaEarn signup. Continue registration to create your full account profile, secure your identity, and unlock staking, rewards, NFTs, marketplace activity, gaming, referrals, deposits, withdrawals, and future ecosystem services.
                      </p>
                      <div className="wallet-context-grid">
                        <span><small>Wallet Address</small><strong>{connectedWalletProfile.address}</strong></span>
                        <span><small>Connected Network</small><strong>{connectedWalletProfile.network}</strong></span>
                        <span><small>Wallet Status</small><strong>{connectedWalletProfile.status}</strong></span>
                        <span><small>Account Stage</small><strong>{connectedWalletProfile.identity}</strong></span>
                      </div>
                      <div className="wallet-activity-row" aria-label="Supported wallet activities">
                        {walletActivities.map((activity) => <span key={activity}>{activity}</span>)}
                      </div>
                      <div className="wallet-success-actions">
                        <ButtonLink className="primary" href={WEB_APP_SIGNUP_URL}>Continue Signup</ButtonLink>
                        <motion.button type="button" onClick={switchWallet} whileHover={{ y: -3 }} whileTap={{ scale: 0.97 }}>
                          <RefreshCcw size={16} /> Switch Wallet
                        </motion.button>
                        <button className="wallet-text-action" type="button" onClick={switchWallet}>Disconnect Wallet</button>
                      </div>
                    </motion.div>
                  ) : null}
                </AnimatePresence>
              </div>
            </div>

            <footer className="wallet-trust-area">
              <div>
                {walletTrustBadges.map((badge) => (
                  <span key={badge}>
                    <ShieldCheck size={14} />
                    {badge}
                  </span>
                ))}
              </div>
              <p>
                ExaEarn never stores your private keys, recovery phrases, or wallet credentials. Your wallet remains fully under your ownership and control while interacting securely with the ExaEarn ecosystem.
              </p>
            </footer>
          </motion.section>
        </motion.div>
      ) : null}
    </AnimatePresence>
  );
}

function WalletAccountControl({ isConnected, onOpenWallet, onDisconnect, label }) {
  const [isMenuOpen, setIsMenuOpen] = useState(false);

  if (!isConnected) {
    return (
      <motion.button
        className="wallet-connect-control"
        type="button"
        onClick={onOpenWallet}
        whileHover={{ y: -3, scale: 1.02 }}
        whileTap={{ scale: 0.97 }}
      >
        <Wallet size={16} /> {label ?? "Connect Wallet"}
      </motion.button>
    );
  }

  return (
    <div className="wallet-account">
      <motion.button
        className="wallet-account-button"
        type="button"
        aria-expanded={isMenuOpen}
        aria-haspopup="menu"
        onClick={() => setIsMenuOpen((value) => !value)}
        whileHover={{ y: -3, scale: 1.02 }}
        whileTap={{ scale: 0.97 }}
      >
        <ShieldCheck size={16} />
        <span>{connectedWalletProfile.address}</span>
        <small>Verified</small>
      </motion.button>
      <AnimatePresence>
        {isMenuOpen ? (
          <motion.div
            className="wallet-account-menu"
            role="menu"
            initial={{ opacity: 0, y: 10, scale: 0.96 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 8, scale: 0.98 }}
            transition={{ duration: 0.22, ease: "easeOut" }}
          >
            {accountMenuItems.map(([label, note, Icon, href]) => (
              <a key={label} href={href} role="menuitem">
                <Icon size={17} />
                <span>
                  <strong>{label}</strong>
                  <small>{note}</small>
                </span>
              </a>
            ))}
            <button type="button" role="menuitem" onClick={onDisconnect}>
              <LogOut size={17} />
              <span>
                <strong>Disconnect Wallet</strong>
                <small>Secure Logout</small>
              </span>
            </button>
          </motion.div>
        ) : null}
      </AnimatePresence>
    </div>
  );
}

function useCinematicPageEffects() {
  useEffect(() => {
    const root = document.documentElement;
    const handlePointerMove = (event) => {
      root.style.setProperty("--cursor-x", `${event.clientX}px`);
      root.style.setProperty("--cursor-y", `${event.clientY}px`);
    };

    window.addEventListener("pointermove", handlePointerMove, { passive: true });

    return () => {
      window.removeEventListener("pointermove", handlePointerMove);
    };
  }, []);
}

function AmbientParticles() {
  return (
    <div className="ambient-particles" aria-hidden="true">
      {Array.from({ length: 34 }, (_, index) => {
        const size = 1 + (index % 4);
        return (
          <motion.span
            key={index}
            animate={{
              opacity: [0.18, 0.7, 0.2],
              scale: [0.75, 1.35, 0.82],
              x: [0, seededNoise(index, 61) * 28 - 14, 0],
              y: [0, seededNoise(index, 62) * -48, 0],
            }}
            transition={{
              duration: 7 + seededNoise(index, 63) * 8,
              repeat: Infinity,
              ease: "easeInOut",
              delay: seededNoise(index, 64) * -8,
            }}
            style={{
              "--i": index,
              "--particle-x": `${(index * 19) % 100}%`,
              "--particle-y": `${(index * 31) % 100}%`,
              "--particle-size": `${size}px`,
              "--particle-gold": `${(index % 5) * 6}%`,
            }}
          />
        );
      })}
    </div>
  );
}

function seededNoise(index, salt = 1) {
  const value = Math.sin(index * 91.73 + salt * 37.41) * 10000;
  return value - Math.floor(value);
}

const networkNodes = Array.from({ length: 88 }, (_, index) => {
  const rightCluster = index < 38;
  const edgeCluster = index >= 38 && index < 66;
  const x = rightCluster
    ? 520 + seededNoise(index, 2) * 430
    : edgeCluster
      ? 120 + seededNoise(index, 7) * 850
      : 700 + Math.cos(index * 1.7) * (150 + seededNoise(index, 4) * 110);
  const y = rightCluster
    ? 70 + seededNoise(index, 3) * 560
    : edgeCluster
      ? (seededNoise(index, 8) > 0.5 ? seededNoise(index, 9) * 120 : 560 + seededNoise(index, 10) * 100)
      : 350 + Math.sin(index * 1.7) * (120 + seededNoise(index, 5) * 90);
  return {
    x: Math.round(x),
    y: Math.round(y),
    r: Number((1.35 + seededNoise(index, 11) * 2.8).toFixed(2)),
    opacity: Number((0.34 + seededNoise(index, 12) * 0.58).toFixed(2)),
    gold: seededNoise(index, 13) > 0.86,
  };
});

const networkLinks = networkNodes.flatMap((node, index) => {
  const distances = networkNodes
    .map((target, targetIndex) => ({
      targetIndex,
      distance: Math.hypot(node.x - target.x, node.y - target.y),
    }))
    .filter(({ targetIndex, distance }) => targetIndex > index && distance < 178)
    .sort((a, b) => a.distance - b.distance)
    .slice(0, seededNoise(index, 14) > 0.68 ? 3 : 2);

  return distances.map(({ targetIndex }) => [index, targetIndex]);
});

const protocolModules = [
  ["Wallet security", Wallet],
  ["Reward engine", HandCoins],
  ["Staking vault", Coins],
  ["AI risk layer", BrainCircuit],
  ["Marketplace rails", ShoppingBag],
  ["Audit ledger", ShieldCheck],
  ["Token utility", Disc3],
  ["Global access", Network],
];

const activityEvents = [
  ["Lagos", "completed a USDT trade", "+$420.00", Wallet],
  ["Abuja", "staked into the reward vault", "50 USDT", Coins],
  ["Port Harcourt", "claimed referral rewards", "+1,240 XP", HandCoins],
  ["Kano", "sold a financial NFT", "0.84 ETH", Gem],
  ["Ibadan", "completed a gift card trade", "$150 Apple", ShoppingBag],
  ["Enugu", "swapped tokens through ExaEarn", "EXA/USDT", Disc3],
  ["Accra", "joined as an affiliate", "Tier 1", UsersRound],
  ["Nairobi", "received a reward distribution", "+38 EXA", Sparkles],
];

const exaAiCapabilities = [
  ["Trading guidance", "Reads market motion, risk profile, and wallet context before suggesting next steps.", ChartNoAxesCombined],
  ["Staking assistance", "Models flexible and locked vault outcomes across reward windows.", Coins],
  ["NFT discovery", "Ranks marketplace opportunities by utility, liquidity, and portfolio fit.", Gem],
  ["Gift card support", "Surfaces rate intelligence, route confidence, and settlement clarity.", ShoppingBag],
  ["Referral insights", "Identifies network growth paths and reward simulations.", UsersRound],
  ["Ecosystem navigation", "Turns complex modules into intelligent next-best actions.", Orbit],
];

const exaAiPrompts = [
  "How can I grow my rewards this week?",
  "Should I stake or keep my USDT flexible?",
  "Find gift card routes with better settlement confidence.",
  "Show me NFT opportunities connected to real utility.",
];

const dashboardModules = [
  {
    key: "stake",
    label: "Stake",
    headline: "Staking vault simulation",
    value: "14.8% APY",
    detail: "Projected rewards route into EXA and USDT balances.",
    action: "Stake 50 USDT",
    icon: Coins,
  },
  {
    key: "trade",
    label: "Trade",
    headline: "Live trading rail",
    value: "+3.42%",
    detail: "Market activity animates wallet, order, and reward signals.",
    action: "Open trade",
    icon: ChartNoAxesCombined,
  },
  {
    key: "nft",
    label: "NFT",
    headline: "Marketplace preview",
    value: "0.84 ETH",
    detail: "Utility NFTs show ownership, access, and resale activity.",
    action: "View listing",
    icon: Gem,
  },
  {
    key: "giftcard",
    label: "Gift Card",
    headline: "Gift card trade flow",
    value: "$150",
    detail: "Rate locks, route quality, and payout status stay visible.",
    action: "Preview trade",
    icon: ShoppingBag,
  },
  {
    key: "referral",
    label: "Referral",
    headline: "Referral earnings engine",
    value: "+1,240 XP",
    detail: "Network rewards update as new users activate modules.",
    action: "Simulate rewards",
    icon: UsersRound,
  },
  {
    key: "swap",
    label: "Swap",
    headline: "Token swap interface",
    value: "EXA/USDT",
    detail: "Balances, route fees, and settlement confidence update together.",
    action: "Swap tokens",
    icon: Disc3,
  },
];

function BlockchainCanvas() {
  const canvasRef = useRef(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return undefined;
    if (isMobileViewport()) return undefined;

    const context = canvas.getContext("2d");
    const mediaQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
    let animationFrame = 0;
    let width = 0;
    let height = 0;
    let nodes = [];
    let links = [];
    let isVisible = true;

    const shouldAnimate = () => !mediaQuery.matches && window.innerWidth >= 900;

    const makeNodes = () => {
      const nodeCount = window.innerWidth < 760 ? 28 : 52;
      nodes = Array.from({ length: nodeCount }, (_, index) => {
        const layer = seededNoise(index, 41);
        const orbital = index < nodeCount * 0.48;
        const edge = index >= nodeCount * 0.48 && index < nodeCount * 0.78;
        const radius = orbital
          ? 0.18 + seededNoise(index, 42) * 0.32
          : edge
            ? 0.42 + seededNoise(index, 43) * 0.24
            : 0.08 + seededNoise(index, 44) * 0.2;
        const angle = orbital
          ? index * 0.43 + seededNoise(index, 45) * 0.9
          : seededNoise(index, 46) * Math.PI * 2;
        const centerX = width * 0.76;
        const centerY = height * 0.48;
        return {
          x: edge ? width * (0.46 + seededNoise(index, 47) * 0.52) : centerX + Math.cos(angle) * width * radius,
          y: edge ? height * (0.08 + seededNoise(index, 48) * 0.86) : centerY + Math.sin(angle) * height * radius,
          baseX: 0,
          baseY: 0,
          depth: layer,
          radius: 1.2 + seededNoise(index, 49) * 3.2,
          gold: seededNoise(index, 50) > 0.88,
          phase: seededNoise(index, 51) * Math.PI * 2,
        };
      }).map((node) => ({ ...node, baseX: node.x, baseY: node.y }));

      links = nodes.flatMap((node, index) => nodes
        .map((target, targetIndex) => ({
          targetIndex,
          distance: Math.hypot(node.baseX - target.baseX, node.baseY - target.baseY),
        }))
        .filter(({ targetIndex, distance }) => targetIndex > index && distance < width * 0.13)
        .sort((a, b) => a.distance - b.distance)
        .slice(0, seededNoise(index, 52) > 0.62 ? 3 : 2)
        .map(({ targetIndex }) => [index, targetIndex]));
    };

    const resize = () => {
      const rect = canvas.getBoundingClientRect();
      const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
      width = Math.max(1, rect.width);
      height = Math.max(1, rect.height);
      canvas.width = Math.floor(width * pixelRatio);
      canvas.height = Math.floor(height * pixelRatio);
      context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
      makeNodes();
    };

    const draw = (time = 0) => {
      const tick = mediaQuery.matches ? 0 : time * 0.001;
      context.clearRect(0, 0, width, height);
      context.globalCompositeOperation = "lighter";

      links.forEach(([from, to], index) => {
        const start = nodes[from];
        const end = nodes[to];
        const alpha = 0.08 + Math.min(start.depth, end.depth) * 0.22;
        const pulse = (tick * (0.18 + seededNoise(index, 53) * 0.28) + seededNoise(index, 54)) % 1;
        const pulseX = start.x + (end.x - start.x) * pulse;
        const pulseY = start.y + (end.y - start.y) * pulse;
        const gradient = context.createLinearGradient(start.x, start.y, end.x, end.y);
        gradient.addColorStop(0, `rgba(56, 232, 255, ${alpha * 0.3})`);
        gradient.addColorStop(0.55, `rgba(92, 246, 163, ${alpha})`);
        gradient.addColorStop(1, `rgba(245, 199, 99, ${alpha * 0.42})`);

        context.beginPath();
        context.moveTo(start.x, start.y);
        context.lineTo(end.x, end.y);
        context.strokeStyle = gradient;
        context.lineWidth = 0.65 + Math.min(start.depth, end.depth) * 0.75;
        context.stroke();

        context.beginPath();
        context.arc(pulseX, pulseY, 1.1 + start.depth * 1.9, 0, Math.PI * 2);
        context.fillStyle = `rgba(223, 255, 255, ${0.12 + start.depth * 0.34})`;
        context.shadowColor = start.gold ? "rgba(245, 199, 99, 0.78)" : "rgba(56, 232, 255, 0.82)";
        context.shadowBlur = 14 + start.depth * 16;
        context.fill();
        context.shadowBlur = 0;
      });

      nodes.forEach((node, index) => {
        node.x = node.baseX + Math.cos(tick * 0.35 + node.phase) * (4 + node.depth * 12);
        node.y = node.baseY + Math.sin(tick * 0.28 + node.phase) * (3 + node.depth * 10);
        const glow = 0.2 + Math.sin(tick * 1.6 + index) * 0.08 + node.depth * 0.42;
        context.beginPath();
        context.arc(node.x, node.y, node.radius * (1 + node.depth * 0.8), 0, Math.PI * 2);
        context.fillStyle = node.gold ? `rgba(245, 199, 99, ${glow})` : `rgba(56, 232, 255, ${glow})`;
        context.shadowColor = node.gold ? "rgba(245, 199, 99, 0.86)" : "rgba(56, 232, 255, 0.9)";
        context.shadowBlur = 18 + node.depth * 22;
        context.fill();
        context.shadowBlur = 0;
      });

      context.globalCompositeOperation = "source-over";
      animationFrame = 0;

      if (shouldAnimate() && isVisible) {
        animationFrame = requestAnimationFrame(draw);
      }
    };

    resize();
    draw();
    window.addEventListener("resize", resize);
    const observer = new IntersectionObserver(
      ([entry]) => {
        isVisible = entry.isIntersecting;

        if (isVisible && !animationFrame) {
          if (shouldAnimate()) {
            animationFrame = requestAnimationFrame(draw);
          } else {
            draw();
          }
        } else if (!isVisible && animationFrame) {
          cancelAnimationFrame(animationFrame);
          animationFrame = 0;
        }
      },
      { rootMargin: "220px" },
    );
    observer.observe(canvas);

    return () => {
      cancelAnimationFrame(animationFrame);
      observer.disconnect();
      window.removeEventListener("resize", resize);
    };
  }, []);

  return <canvas className="network-canvas" ref={canvasRef} />;
}

function SectionSignalCanvas({ className = "", density = 64, drift = 1 }) {
  const canvasRef = useRef(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return undefined;
    if (isMobileViewport()) return undefined;

    const context = canvas.getContext("2d");
    const mediaQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
    let animationFrame = 0;
    let width = 0;
    let height = 0;
    let points = [];
    let isVisible = false;

    const shouldAnimate = () => !mediaQuery.matches && window.innerWidth >= 900;

    const makePoints = () => {
      const pointCount = Math.min(density, window.innerWidth < 760 ? 18 : 34);
      points = Array.from({ length: pointCount }, (_, index) => ({
        x: seededNoise(index, 101) * width,
        y: seededNoise(index, 102) * height,
        baseX: seededNoise(index, 101) * width,
        baseY: seededNoise(index, 102) * height,
        size: 0.7 + seededNoise(index, 103) * 2.2,
        depth: seededNoise(index, 104),
        phase: seededNoise(index, 105) * Math.PI * 2,
        warm: seededNoise(index, 106) > 0.78,
      }));
    };

    const resize = () => {
      const rect = canvas.getBoundingClientRect();
      const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
      width = Math.max(1, rect.width);
      height = Math.max(1, rect.height);
      canvas.width = Math.floor(width * pixelRatio);
      canvas.height = Math.floor(height * pixelRatio);
      context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
      makePoints();
    };

    const draw = (time = 0) => {
      const tick = mediaQuery.matches ? 0 : time * 0.001 * drift;
      context.clearRect(0, 0, width, height);
      context.globalCompositeOperation = "lighter";

      points.forEach((point, index) => {
        point.x = point.baseX + Math.cos(tick * (0.18 + point.depth * 0.2) + point.phase) * (8 + point.depth * 18);
        point.y = point.baseY + Math.sin(tick * (0.22 + point.depth * 0.16) + point.phase) * (6 + point.depth * 14);

        for (let targetIndex = index + 1; targetIndex < points.length; targetIndex += 1) {
          const target = points[targetIndex];
          const distance = Math.hypot(point.x - target.x, point.y - target.y);
          const limit = Math.min(width, height) * (0.18 + point.depth * 0.04);

          if (distance < limit) {
            const alpha = (1 - distance / limit) * 0.16;
            const gradient = context.createLinearGradient(point.x, point.y, target.x, target.y);
            gradient.addColorStop(0, `rgba(56, 232, 255, ${alpha})`);
            gradient.addColorStop(0.62, `rgba(92, 246, 163, ${alpha * 0.88})`);
            gradient.addColorStop(1, `rgba(245, 199, 99, ${alpha * 0.5})`);
            context.beginPath();
            context.moveTo(point.x, point.y);
            context.lineTo(target.x, target.y);
            context.strokeStyle = gradient;
            context.lineWidth = 0.55 + point.depth * 0.5;
            context.stroke();
          }
        }
      });

      points.forEach((point) => {
        const alpha = 0.22 + point.depth * 0.42;
        context.beginPath();
        context.arc(point.x, point.y, point.size, 0, Math.PI * 2);
        context.fillStyle = point.warm ? `rgba(245, 199, 99, ${alpha})` : `rgba(56, 232, 255, ${alpha})`;
        context.shadowColor = point.warm ? "rgba(245, 199, 99, 0.66)" : "rgba(56, 232, 255, 0.72)";
        context.shadowBlur = 10 + point.depth * 20;
        context.fill();
        context.shadowBlur = 0;
      });

      context.globalCompositeOperation = "source-over";
      animationFrame = 0;

      if (shouldAnimate() && isVisible) {
        animationFrame = requestAnimationFrame(draw);
      }
    };

    resize();
    window.addEventListener("resize", resize);
    const observer = new IntersectionObserver(
      ([entry]) => {
        isVisible = entry.isIntersecting;

        if (isVisible && !animationFrame) {
          if (shouldAnimate()) {
            animationFrame = requestAnimationFrame(draw);
          } else {
            draw();
          }
        } else if (!isVisible && animationFrame) {
          cancelAnimationFrame(animationFrame);
          animationFrame = 0;
        }
      },
      { rootMargin: "260px" },
    );
    observer.observe(canvas);

    return () => {
      cancelAnimationFrame(animationFrame);
      observer.disconnect();
      window.removeEventListener("resize", resize);
    };
  }, [density, drift]);

  return <canvas className={`section-signal-canvas ${className}`} ref={canvasRef} aria-hidden="true" />;
}

function BlockchainNetworkBackground() {
  return (
    <div className="blockchain-background" aria-hidden="true">
      <div className="network-core" />
      <BlockchainCanvas />
      <div className="solar-wavefield" />
      <div className="hex-grid-layer" />
      <svg className="network-web network-web-deep" viewBox="0 0 1000 700" preserveAspectRatio="xMidYMid slice">
        <defs>
          <linearGradient id="networkLine" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stopColor="#38e8ff" stopOpacity="0.05" />
            <stop offset="45%" stopColor="#38e8ff" stopOpacity="0.52" />
            <stop offset="74%" stopColor="#9c62ff" stopOpacity="0.32" />
            <stop offset="100%" stopColor="#f5c763" stopOpacity="0.18" />
          </linearGradient>
          <radialGradient id="nodeGlow" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stopColor="#f6fbff" stopOpacity="0.94" />
            <stop offset="36%" stopColor="#38e8ff" stopOpacity="0.72" />
            <stop offset="100%" stopColor="#38e8ff" stopOpacity="0" />
          </radialGradient>
        </defs>
        {networkLinks.map(([from, to], index) => {
          const start = networkNodes[from];
          const end = networkNodes[to];
          return (
            <line
              key={`${from}-${to}`}
              x1={start.x}
              y1={start.y}
              x2={end.x}
              y2={end.y}
              pathLength="100"
              style={{ "--link": index }}
            />
          );
        })}
        {networkNodes.map(({ x, y, r, opacity, gold }, index) => (
          <g key={`${x}-${y}`} style={{ "--node": index }}>
            <circle className="node-aura" cx={x} cy={y} r={r * 6} opacity={opacity * 0.16} />
            <circle className={gold ? "node-point solar" : "node-point"} cx={x} cy={y} r={r} opacity={opacity} />
          </g>
        ))}
      </svg>
      <svg className="network-web network-web-foreground" viewBox="0 0 1000 700" preserveAspectRatio="xMidYMid slice">
        {networkLinks.slice(18, 88).map(([from, to], index) => {
          const start = networkNodes[from];
          const end = networkNodes[to];
          return (
            <line
              key={`fg-${from}-${to}`}
              x1={start.x}
              y1={start.y}
              x2={end.x}
              y2={end.y}
              pathLength="100"
              style={{ "--link": index + 5 }}
            />
          );
        })}
      </svg>
      <div className="micro-ledger">
        {Array.from({ length: 32 }, (_, index) => (
          <span
            key={index}
            style={{
              "--dot": index,
              "--dot-x": `${42 + seededNoise(index, 21) * 58}%`,
              "--dot-y": `${seededNoise(index, 22) * 100}%`,
              "--dot-size": `${1 + seededNoise(index, 23) * 2.4}px`,
            }}
          />
        ))}
      </div>
      <div className="holographic-frames">
        <span />
        <span />
        <span />
      </div>
      <div className="data-shards">
        {Array.from({ length: 18 }, (_, index) => (
          <span
            key={index}
            style={{
              "--shard": index,
              "--shard-x": `${28 + seededNoise(index, 31) * 70}%`,
              "--shard-y": `${8 + seededNoise(index, 32) * 84}%`,
            }}
          />
        ))}
      </div>
      <div className="quantum-streaks">
        {Array.from({ length: 9 }, (_, index) => (
          <span
            key={index}
            style={{
              "--streak": index,
              "--streak-top": `${9 + ((index * 13) % 78)}%`,
            }}
          />
        ))}
      </div>
      <div className="depth-vignette" />
    </div>
  );
}

function HeroObject() {
  const { scrollYProgress } = useScroll();
  const y = useTransform(scrollYProgress, [0, 0.22], [0, -92]);
  const rotateX = useTransform(scrollYProgress, [0, 0.22], [0, 8]);
  const opacity = useTransform(scrollYProgress, [0, 0.2, 0.34], [1, 0.82, 0.42]);

  return (
    <motion.div
      className="hero-object"
      aria-hidden="true"
      initial={{ opacity: 0, scale: 0.92, rotateY: -8 }}
      animate={{ opacity: 1, scale: 1, rotateY: 0 }}
      transition={{ duration: 1.25, ease: cinematicEase, delay: 0.2 }}
      style={{ y, rotateX, opacity }}
    >
      <div className="hero-network-sphere" />
      <div className="protocol-orbits">
        <span className="orbit-ring orbit-ring-outer" />
        <span className="orbit-ring orbit-ring-mid" />
        <span className="orbit-ring orbit-ring-inner" />
        {protocolModules.map(([label, Icon], index) => (
          <i className="protocol-node" key={label} style={{ "--module": index }} title={label}>
            <Icon size={18} />
          </i>
        ))}
      </div>
      <div className="economy-core">
        <div className="core-face" />
        <i className="core-glow" />
      </div>
      <motion.div
        className="exaai-hero-node"
        initial={{ opacity: 0, y: 16, scale: 0.94 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.8, ease: cinematicEase, delay: 0.75 }}
      >
        <BrainCircuit size={22} />
        <span>
          <strong>ExaAi</strong>
          Intelligence layer linked to live nodes
        </span>
      </motion.div>
    </motion.div>
  );
}

function Hero({ onOpenDownload, onOpenWallet, isWalletConnected }) {
  const mouseX = useMotionValue(72);
  const mouseY = useMotionValue(40);
  const smoothX = useSpring(mouseX, { stiffness: 80, damping: 24, mass: 0.4 });
  const smoothY = useSpring(mouseY, { stiffness: 80, damping: 24, mass: 0.4 });
  const aurora = useMotionTemplate`radial-gradient(520px circle at ${smoothX}% ${smoothY}%, rgba(56, 232, 255, 0.19), transparent 58%), radial-gradient(360px circle at ${smoothX}% ${smoothY}%, rgba(245, 199, 99, 0.09), transparent 72%)`;

  const updateHeroLight = (event) => {
    const rect = event.currentTarget.getBoundingClientRect();
    mouseX.set(((event.clientX - rect.left) / rect.width) * 100);
    mouseY.set(((event.clientY - rect.top) / rect.height) * 100);
  };

  return (
    <motion.section
      className="hero section-band"
      id="top"
      initial="hidden"
      animate="visible"
      variants={staggerGroup}
      onPointerMove={updateHeroLight}
    >
      <BlockchainNetworkBackground />
      <AmbientParticles />
      <div className="hero-grid" aria-hidden="true" />
      <div className="hero-light-probe" aria-hidden="true" />
      <motion.div className="hero-mouse-aurora" aria-hidden="true" style={{ background: aurora }} />
      <motion.div className="hero-copy" variants={staggerGroup}>
        <motion.div className="hero-status" variants={fadeUp}>
          <span />
          ExaEarn protocol environment online
        </motion.div>
        <motion.p className="eyebrow" variants={fadeUp}>ExaEarn - future digital finance infrastructure</motion.p>
        <motion.h1 variants={fadeUp}>Enter the intelligent ExaEarn economy.</motion.h1>
        <motion.p variants={fadeUp}>
          A cinematic Web3 financial operating system where wallets, staking,
          rewards, education, agriculture, commerce, and digital assets connect
          through one secure intelligence layer.
        </motion.p>
        <motion.div className="hero-actions" variants={fadeUp}>
          <ButtonLink className="primary" href="#ecosystem">
            <Rocket size={18} /> Enter Ecosystem
          </ButtonLink>
          <ButtonLink href="#download" onClick={onOpenDownload}>
            <Download size={18} /> Download App
          </ButtonLink>
          <ButtonLink href="#connect" onClick={onOpenWallet}>
            <Wallet size={18} /> {isWalletConnected ? connectedWalletProfile.address : "Connect Wallet"}
          </ButtonLink>
        </motion.div>
        <motion.div className="hero-proofline" aria-label="Platform proof points" variants={staggerGroup}>
          <motion.span variants={fadeUp}><ShieldCheck size={15} /> Audit-ready architecture</motion.span>
          <motion.span variants={fadeUp}><Zap size={15} /> Reward automation</motion.span>
          <motion.span variants={fadeUp}><Network size={15} /> Multi-module network</motion.span>
        </motion.div>
        <motion.div className="trust-strip" aria-label="Ecosystem indicators" variants={staggerGroup}>
          {metrics.map((metric, index) => (
            <motion.div
              key={metric.label}
              variants={fadeUp}
              whileHover={{ y: -8, scale: 1.025 }}
              transition={{ type: "spring", stiffness: 340, damping: 26 }}
              style={{ "--metric": index }}
            >
              <strong>{metric.value}</strong>
              <span>{metric.label}</span>
            </motion.div>
          ))}
        </motion.div>
      </motion.div>
      <HeroObject />
      <a className="scroll-cue" href="#activity" aria-label="Scroll to proof of activity">
        <ArrowDown size={18} />
      </a>
    </motion.section>
  );
}

function ProofOfActivity() {
  const [activeEvent, setActiveEvent] = useState(0);

  useEffect(() => {
    const interval = window.setInterval(() => {
      setActiveEvent((current) => (current + 1) % activityEvents.length);
    }, 2600);
    return () => window.clearInterval(interval);
  }, []);

  const highlighted = activityEvents[activeEvent];
  const HighlightIcon = highlighted[3];

  return (
    <Reveal as={motion.section} className="section-band activity-section" id="activity">
      <SectionSignalCanvas className="activity-signal-canvas" density={82} drift={0.55} />
      <div className="section-heading split">
        <div>
          <p className="eyebrow">Proof of activity</p>
          <h2>Watch the economy move in real time.</h2>
        </div>
        <p>
          Every signal reflects the kind of value flowing through ExaEarn:
          trades completed, rewards claimed, assets staked, affiliates joining,
          NFTs sold, and gift cards settled across the ecosystem.
        </p>
      </div>
      <div className="activity-layout">
        <motion.div className="activity-command" variants={fadeUp} whileHover={{ y: -8 }}>
          <div className="activity-command-top">
            <span><Radio size={16} /> Live ecosystem center</span>
            <strong>Streaming</strong>
          </div>
          <motion.div
            className="activity-featured"
            key={`${highlighted[0]}-${highlighted[1]}`}
            initial={{ opacity: 0, y: 18, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            transition={{ duration: 0.48, ease: cinematicEase }}
          >
            <span className="activity-pulse" />
            <HighlightIcon size={30} />
            <div>
              <small>{highlighted[0]} node</small>
              <h3>User in {highlighted[0]} {highlighted[1]}</h3>
              <p>{highlighted[2]} settled through verified ecosystem rails.</p>
            </div>
          </motion.div>
          <div className="activity-metrics" aria-label="Live activity metrics">
            <span><strong>184</strong><small>events today</small></span>
            <span><strong>7</strong><small>active rails</small></span>
            <span><strong>99.9%</strong><small>uptime target</small></span>
          </div>
        </motion.div>
        <motion.div className="activity-feed" variants={staggerGroup} initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.2 }}>
          {activityEvents.map(([city, event, value, Icon], index) => (
            <motion.article className={index === activeEvent ? "is-active" : ""} key={`${city}-${event}`} variants={fadeUp} whileHover={{ x: 8 }}>
              <span className="feed-dot" />
              <Icon size={19} />
              <div>
                <strong>{city}</strong>
                <p>{event}</p>
              </div>
              <time>{index === activeEvent ? "now" : `${(index + 1) * 11}s ago`}</time>
              <em>{value}</em>
            </motion.article>
          ))}
        </motion.div>
      </div>
      <div className="activity-ticker" aria-label="Continuous ExaEarn activity">
        <motion.div animate={{ x: ["0%", "-50%"] }} transition={{ duration: 28, repeat: Infinity, ease: "linear" }}>
          {[...activityEvents, ...activityEvents].map(([city, event, value], index) => (
            <span key={`${city}-${index}`}>
              <i />
              {city}: {event} <strong>{value}</strong>
            </span>
          ))}
        </motion.div>
      </div>
    </Reveal>
  );
}

function ExaAiShowcase() {
  const [question, setQuestion] = useState(exaAiPrompts[0]);
  const [submittedQuestion, setSubmittedQuestion] = useState(exaAiPrompts[0]);

  const askExaAi = (event) => {
    event.preventDefault();
    setSubmittedQuestion(question.trim() || exaAiPrompts[0]);
  };

  return (
    <Reveal as={motion.section} className="section-band exaai-section" id="exaai">
      <div className="exaai-visual" aria-hidden="true">
        <SectionSignalCanvas className="exaai-signal-canvas" density={96} drift={0.62} />
        <div className="exaai-brain">
          <BrainCircuit size={76} />
          <span />
          <span />
          <span />
        </div>
        <div className="exaai-orbit-card card-one">Risk context</div>
        <div className="exaai-orbit-card card-two">Reward route</div>
        <div className="exaai-orbit-card card-three">Next action</div>
      </div>
      <div className="exaai-copy">
        <p className="eyebrow">ExaAi intelligence layer</p>
        <h2>Ask the intelligence behind your digital economy.</h2>
        <p>
          ExaAi studies your activity, goals, market context, reward routes, and
          ecosystem opportunities, then turns them into clear next moves across
          trading, staking, NFTs, gift cards, referrals, and wallet growth.
        </p>
        <motion.form className="exaai-ask-panel" onSubmit={askExaAi} variants={fadeUp}>
          <label htmlFor="exaai-question">Ask ExaAi</label>
          <div className="exaai-input-row">
            <input
              id="exaai-question"
              value={question}
              onChange={(event) => setQuestion(event.target.value)}
              placeholder="Ask about staking, rewards, trading, NFTs, gift cards..."
            />
            <motion.button type="submit" whileHover={{ y: -3 }} whileTap={{ scale: 0.96 }}>
              <Sparkles size={17} /> Ask
            </motion.button>
          </div>
          <div className="exaai-prompt-row" aria-label="Suggested ExaAi prompts">
            {exaAiPrompts.map((prompt) => (
              <button key={prompt} type="button" onClick={() => {
                setQuestion(prompt);
                setSubmittedQuestion(prompt);
              }}>
                {prompt}
              </button>
            ))}
          </div>
          <motion.div
            className="exaai-answer"
            key={submittedQuestion}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.38, ease: cinematicEase }}
          >
            <span><BrainCircuit size={16} /> ExaAi insight</span>
            <p>
              For "{submittedQuestion}", ExaAi would compare your wallet balance,
              risk preference, reward eligibility, market timing, and active
              ecosystem routes, then suggest the highest-confidence action to take next.
            </p>
          </motion.div>
        </motion.form>
        <motion.div className="exaai-capability-grid" variants={staggerGroup} initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.2 }}>
          {exaAiCapabilities.map(([title, text, Icon]) => (
            <motion.article key={title} variants={fadeUp} whileHover={{ y: -6 }}>
              <Icon size={19} />
              <div>
                <h3>{title}</h3>
                <p>{text}</p>
              </div>
            </motion.article>
          ))}
        </motion.div>
      </div>
    </Reveal>
  );
}

function LivingDashboardPreview() {
  const [activeModule, setActiveModule] = useState("stake");
  const [tick, setTick] = useState(0);
  const active = dashboardModules.find((module) => module.key === activeModule) ?? dashboardModules[0];
  const ActiveIcon = active.icon;

  useEffect(() => {
    const interval = window.setInterval(() => setTick((current) => current + 1), 1500);
    return () => window.clearInterval(interval);
  }, []);

  return (
    <Reveal as={motion.section} className="section-band dashboard-section" id="dashboard">
      <div className="section-heading split">
        <div>
          <p className="eyebrow">Living dashboard preview</p>
          <h2>Try the ExaEarn experience before you create an account.</h2>
        </div>
        <p>
          Explore a live-feeling console where portfolio growth, staking rewards,
          referrals, trades, swaps, NFT activity, and wallet updates respond as
          one connected economy.
        </p>
      </div>
      <div className="dashboard-console">
        <div className="dashboard-sidebar" aria-label="Dashboard demo modules">
          {dashboardModules.map((module) => {
            const Icon = module.icon;
            return (
              <button
                className={module.key === activeModule ? "is-active" : ""}
                key={module.key}
                type="button"
                onClick={() => setActiveModule(module.key)}
              >
                <Icon size={18} />
                <span>{module.label}</span>
              </button>
            );
          })}
        </div>
        <motion.div
          className="dashboard-main"
          key={active.key}
          initial={{ opacity: 0, y: 18 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.42, ease: cinematicEase }}
        >
          <div className="dashboard-header">
            <div>
              <small>Interactive demo environment</small>
              <h3>{active.headline}</h3>
            </div>
            <span><ActiveIcon size={18} /> {active.value}</span>
          </div>
          <div className="dashboard-live-grid">
            <div className="portfolio-card">
              <small>Total portfolio</small>
              <strong>${(18420 + tick * 37).toLocaleString()}</strong>
              <p>Simulated balance grows as rewards, swaps, and trades settle.</p>
            </div>
            <div className="reward-card">
              <small>Reward stream</small>
              <strong>+{(8940 + tick * 18).toLocaleString()} XP</strong>
              <p>Referral and staking events distribute in near real time.</p>
            </div>
            <div className="wallet-card">
              <small>Wallet activity</small>
              <strong>{12 + (tick % 8)} live</strong>
              <p>{active.detail}</p>
            </div>
          </div>
          <div className="dashboard-chart" aria-label="Animated ExaEarn dashboard chart">
            {Array.from({ length: 18 }, (_, index) => (
              <span
                key={index}
                style={{ "--bar": index, "--height": `${28 + ((index * 17 + tick * 9) % 58)}%` }}
              />
            ))}
            <i />
          </div>
          <div className="dashboard-actions">
            <motion.button type="button" whileHover={{ y: -3 }} whileTap={{ scale: 0.97 }}>
              <ActiveIcon size={17} /> {active.action}
            </motion.button>
            <motion.a href={WEB_APP_SIGNUP_URL} whileHover={{ y: -3 }} whileTap={{ scale: 0.97 }}>
              <Rocket size={17} /> Create account
            </motion.a>
          </div>
        </motion.div>
        <div className="dashboard-activity-rail">
          {activityEvents.slice(0, 5).map(([city, event, value], index) => (
            <span key={`${city}-dash-${event}`} style={{ "--rail": index }}>
              <i />
              <strong>{value}</strong>
              {city} {event}
            </span>
          ))}
        </div>
      </div>
    </Reveal>
  );
}

function ExaAiFloatingAccess() {
  return (
    <motion.a
      className="exaai-floating"
      href="#exaai"
      aria-label="Open ExaAi intelligence layer"
      initial={{ opacity: 0, y: 18, scale: 0.9 }}
      animate={{ opacity: 1, y: 0, scale: 1 }}
      transition={{ duration: 0.58, ease: cinematicEase, delay: 1.1 }}
      whileHover={{ y: -5, scale: 1.04 }}
      whileTap={{ scale: 0.96 }}
    >
      <BrainCircuit size={22} />
      <span>
        <strong>ExaAi</strong>
        Ask intelligence
      </span>
    </motion.a>
  );
}

function InstitutionalLayer() {
  return (
    <Reveal as={motion.section} className="section-band institutional-section" id="architecture">
      <motion.div className="section-heading split" variants={staggerGroup}>
        <div>
          <p className="eyebrow">Institutional architecture</p>
          <h2>Designed to feel investable, scalable, and operationally serious.</h2>
        </div>
        <p>
          The experience is framed around trust, composability, and clear financial
          utility, so ExaEarn reads like infrastructure rather than a campaign page.
        </p>
      </motion.div>
      <motion.div className="institutional-grid" variants={staggerGroup} initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.22 }}>
        {institutionalSignals.map(([title, text, Icon], index) => (
          <motion.article className="institution-card" key={title} style={{ "--signal": index }} variants={fadeUp} whileHover={{ y: -10, rotateX: 2, rotateY: index - 1 }}>
            <Icon size={25} />
            <h3>{title}</h3>
            <p>{text}</p>
          </motion.article>
        ))}
      </motion.div>
      <motion.div className="trust-rail" variants={staggerGroup} initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.32 }}>
        {trustRails.map(([title, text]) => (
          <motion.article key={title} variants={fadeUp} whileHover={{ backgroundColor: "rgba(14, 24, 42, 0.86)" }}>
            <strong>{title}</strong>
            <span>{text}</span>
          </motion.article>
        ))}
      </motion.div>
    </Reveal>
  );
}

function PhoneMockup({ screen, index }) {
  const Icon = screen.icon;
  const candles = candleSets[index % candleSets.length];
  return (
    <motion.article
      className="phone-shell"
      style={{ "--phone": index }}
      variants={fadeUp}
      whileHover={{ y: -18, rotate: 0, scale: 1.025 }}
      transition={{ type: "spring", stiffness: 260, damping: 24 }}
    >
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
    </motion.article>
  );
}

function MobileShowcase() {
  return (
    <Reveal as={motion.section} className="section-band app-showcase" id="mobile">
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
      <div className="showcase-badges" aria-label="Live ecosystem signals">
        <span><Zap size={15} /> Live yield graph</span>
        <span><Orbit size={15} /> Reward routes</span>
        <span><Fingerprint size={15} /> Wallet pulse</span>
      </div>
      <motion.div className="phone-stage" variants={staggerGroup} initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.18 }}>
        <SectionSignalCanvas className="app-signal-canvas" density={76} drift={0.9} />
        {appScreens.map((screen, index) => (
          <PhoneMockup key={screen.title} screen={screen} index={index} />
        ))}
      </motion.div>
    </Reveal>
  );
}

function WhyExaEarn() {
  return (
    <Reveal as={motion.section} className="section-band" id="why">
      <div className="section-heading">
        <p className="eyebrow">Why ExaEarn</p>
        <h2>A real-world decentralized financial ecosystem, not another crypto exchange.</h2>
      </div>
      <motion.div className="pillar-grid" variants={staggerGroup} initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.16 }}>
        {pillars.map(([title, text, Icon]) => (
          <motion.article className="glass-module" key={title} variants={fadeUp} whileHover={{ y: -8, scale: 1.015 }}>
            <Icon size={25} />
            <h3>{title}</h3>
            <p>{text}</p>
          </motion.article>
        ))}
      </motion.div>
    </Reveal>
  );
}

function FeatureGrid() {
  return (
    <Reveal as={motion.section} className="section-band feature-section" id="features">
      <SectionSignalCanvas className="feature-signal-canvas" density={92} drift={0.72} />
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
      <motion.div className="feature-grid" variants={staggerGroup} initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.14 }}>
        {features.map(([title, text, Icon], index) => (
          <motion.article className="feature-card" key={title} style={{ "--feature": index }} variants={fadeUp} whileHover={{ y: -10, scale: 1.012 }}>
            <div className="feature-icon">
              <Icon size={24} />
            </div>
            <h3>{title}</h3>
            <p>{text}</p>
          </motion.article>
        ))}
      </motion.div>
    </Reveal>
  );
}

function HowItWorks() {
  return (
    <Reveal as={motion.section} className="section-band flow-section" id="how">
      <div className="flow-copy">
        <p className="eyebrow">How ExaEarn works</p>
        <h2>Simple entry. Intelligent participation. Connected value.</h2>
        <p>
          ExaEarn hides protocol complexity behind a clear journey so users can
          move from onboarding to earning without feeling overwhelmed.
        </p>
      </div>
      <motion.div className="flow-chain" variants={staggerGroup} initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.18 }}>
        {flowSteps.map(([title, text], index) => (
          <motion.article className="flow-step" key={title} variants={fadeUp} whileHover={{ x: 8, borderColor: "rgba(56, 232, 255, 0.42)" }}>
            <span>{String(index + 1).padStart(2, "0")}</span>
            <div>
              <h3>{title}</h3>
              <p>{text}</p>
            </div>
          </motion.article>
        ))}
      </motion.div>
    </Reveal>
  );
}

function TrustSecurity() {
  return (
    <Reveal as={motion.section} className="section-band security-section" id="security">
      <div className="security-visual" aria-hidden="true">
        <SectionSignalCanvas className="security-signal-canvas" density={58} drift={0.6} />
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
        <motion.div className="security-list" variants={staggerGroup} initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.3 }}>
          {securityItems.map((item) => (
            <motion.span key={item} variants={fadeUp} whileHover={{ x: 6 }}>
              <CheckCircle2 size={17} /> {item}
            </motion.span>
          ))}
        </motion.div>
      </div>
    </Reveal>
  );
}

function TokenSection() {
  return (
    <Reveal as={motion.section} className="section-band token-section" id="token">
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
      <motion.div className="utility-grid" variants={staggerGroup} initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.2 }}>
        {tokenUtilities.map(([title, text]) => (
          <motion.article key={title} variants={fadeUp} whileHover={{ y: -8 }}>
            <h3>{title}</h3>
            <p>{text}</p>
          </motion.article>
        ))}
      </motion.div>
    </Reveal>
  );
}

function EcosystemMap() {
  return (
    <Reveal as={motion.section} className="section-band ecosystem-map" id="ecosystem">
      <div className="section-heading">
        <p className="eyebrow">ExaEarn ecosystem map</p>
        <h2>An entire digital economy, connected around one intelligent core.</h2>
      </div>
      <motion.div className="map-stage" variants={staggerGroup} initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.18 }}>
        <div className="map-core">
          <img src={logo} alt="ExaEarn" />
          <strong>ExaEarn Core</strong>
        </div>
        {ecosystemNodes.map(([title, Icon], index) => (
          <motion.article className="map-node" key={title} style={{ "--node": index }} variants={fadeUp} whileHover={{ scale: 1.055 }}>
            <Icon size={22} />
            <span>{title}</span>
          </motion.article>
        ))}
      </motion.div>
    </Reveal>
  );
}

function Roadmap() {
  return (
    <Reveal as={motion.section} className="section-band roadmap-section" id="roadmap">
      <div className="section-heading">
        <p className="eyebrow">Roadmap</p>
        <h2>Expansion phases for the future decentralized economy.</h2>
      </div>
      <motion.div className="timeline" variants={staggerGroup} initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.12 }}>
        {roadmap.map(([phase, title, text], index) => (
          <motion.article className="timeline-item" key={phase} style={{ "--phase": index }} variants={fadeUp} whileHover={{ y: -8 }}>
            <span>{phase}</span>
            <h3>{title}</h3>
            <p>{text}</p>
          </motion.article>
        ))}
      </motion.div>
    </Reveal>
  );
}

function Community() {
  return (
    <Reveal as={motion.section} className="section-band community-section" id="community">
      <div>
        <p className="eyebrow">Community + ecosystem</p>
        <h2>Built with a global network of users, builders, and contributors.</h2>
        <p>
          Web3 economies become real through participation. ExaEarn is shaped for
          Telegram communities, Discord contributors, X updates, regional ambassadors,
          ecosystem releases, and builders who want practical utility.
        </p>
      </div>
      <motion.div className="community-grid" variants={staggerGroup} initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.28 }}>
        <motion.a variants={fadeUp} whileHover={{ y: -8 }} href="https://t.me/ExaEarn" target="_blank" rel="noreferrer"><MessageCircle size={22} /> Telegram</motion.a>
        <motion.a variants={fadeUp} whileHover={{ y: -8 }} href="#community"><UsersRound size={22} /> Discord</motion.a>
        <motion.a variants={fadeUp} whileHover={{ y: -8 }} href="#community"><Twitter size={22} /> X / Twitter</motion.a>
        <motion.a variants={fadeUp} whileHover={{ y: -8 }} href="#community"><Radio size={22} /> Updates</motion.a>
      </motion.div>
    </Reveal>
  );
}

function DownloadAccess({ onOpenDownload }) {
  return (
    <Reveal as={motion.section} className="section-band download-section" id="download">
      <motion.div className="download-panel" whileHover={{ borderColor: "rgba(56, 232, 255, 0.34)" }}>
        <div>
          <p className="eyebrow">Download / access</p>
          <h2>Access the future financial system.</h2>
          <p>
            Get the ExaEarn mobile app, web platform, and Web3 wallet experience
            across all your devices as the ecosystem goes live.
          </p>
        </div>
        <div className="download-actions">
          <ButtonLink className="store" href="#download" onClick={onOpenDownload}>
            <Download size={19} /> Download on App Store
          </ButtonLink>
          <ButtonLink className="store" href="#download" onClick={onOpenDownload}>
            <Play size={19} /> Get it on Google Play
          </ButtonLink>
        </div>
      </motion.div>
    </Reveal>
  );
}

function FAQ() {
  return (
    <Reveal as={motion.section} className="section-band faq-section" id="faq">
      <div className="section-heading">
        <p className="eyebrow">FAQ</p>
        <h2>Clear answers for a serious ecosystem.</h2>
      </div>
      <motion.div className="faq-list" variants={staggerGroup} initial="hidden" whileInView="visible" viewport={{ once: true, amount: 0.18 }}>
        {faqs.map(([question, answer]) => (
          <motion.details key={question} variants={fadeUp} whileHover={{ x: 4 }}>
            <summary>
              <span>{question}</span>
              <ChevronDown size={18} />
            </summary>
            <p>{answer}</p>
          </motion.details>
        ))}
      </motion.div>
    </Reveal>
  );
}

function FinalCta({ onOpenDownload, onOpenWallet, isWalletConnected }) {
  return (
    <motion.section
      className="final-cta"
      id="connect"
      initial="hidden"
      whileInView="visible"
      viewport={{ once: true, amount: 0.3 }}
      variants={staggerGroup}
    >
      <BlockchainNetworkBackground />
      <AmbientParticles />
      <motion.div className="final-symbol" aria-hidden="true" variants={fadeUp} whileHover={{ scale: 1.04, rotate: 2 }}>
        <img src={logo} alt="" />
      </motion.div>
      <motion.p className="eyebrow" variants={fadeUp}>Join the economy</motion.p>
      <motion.h2 variants={fadeUp}>The future is not coming. It is being built inside ExaEarn.</motion.h2>
      <motion.p variants={fadeUp}>
        Join the decentralized ecosystem redefining digital finance, rewards,
        ownership, and real-world utility.
      </motion.p>
      <motion.div className="hero-actions" variants={fadeUp}>
        <ButtonLink className="primary" href={WEB_APP_SIGNUP_URL}>
          <Rocket size={18} /> Join the Economy
        </ButtonLink>
        <ButtonLink href="#download" onClick={onOpenDownload}>
          <Download size={18} /> Download App
        </ButtonLink>
        <ButtonLink href="#connect" onClick={onOpenWallet}>
          <Wallet size={18} /> {isWalletConnected ? "Verified Wallet" : "Connect Wallet"}
        </ButtonLink>
      </motion.div>
    </motion.section>
  );
}

function Footer({ onOpenDownload }) {
  return (
    <motion.footer
      className="site-footer"
      initial="hidden"
      whileInView="visible"
      viewport={{ once: true, amount: 0.22 }}
      variants={staggerGroup}
    >
      <SectionSignalCanvas className="footer-signal-canvas" density={54} drift={0.48} />
      <motion.div className="footer-aurora" aria-hidden="true" variants={fadeUp} />
      <motion.div className="footer-topline" variants={fadeUp}>
        <a className="footer-brand" href="#top" aria-label="ExaEarn home">
          <img src={logo} alt="ExaEarn" />
          <span>
            <strong>ExaEarn</strong>
            <small>Intelligent financial infrastructure</small>
          </span>
        </a>
        <div className="footer-actions">
          <ButtonLink className="primary" href={WEB_APP_LOGIN_URL}>
            <Rocket size={17} /> Launch app
          </ButtonLink>
          <ButtonLink href="#download" onClick={onOpenDownload}>
            <Download size={17} /> Get Mobile Access
          </ButtonLink>
        </div>
      </motion.div>

      <div className="footer-main">
        <motion.div className="footer-narrative" variants={fadeUp}>
          <p className="eyebrow">Global Web3 economy</p>
          <h2>Built for serious digital finance, everyday utility, and future-scale participation.</h2>
          <p>
            ExaEarn connects wallets, staking, rewards, education, commerce,
            agriculture, NFTs, and intelligent risk layers into one premium
            financial ecosystem.
          </p>
          <div className="footer-signal-row" aria-label="Footer platform signals">
            {footerSignals.map(([label, value, Icon]) => (
              <span key={label}>
                <Icon size={16} />
                <small>{label}</small>
                <strong>{value}</strong>
              </span>
            ))}
          </div>
        </motion.div>

        <motion.div className="footer-directory" variants={staggerGroup}>
          <motion.div className="footer-highlight-strip" variants={fadeUp} aria-label="ExaEarn ecosystem highlights">
            {footerHighlights.map(([value, label]) => (
              <span key={label}>
                <strong>{value}</strong>
                <small>{label}</small>
              </span>
            ))}
          </motion.div>

          <div className="footer-link-grid">
            {footerLinks.map((group) => (
              <motion.nav key={group.title} variants={fadeUp} aria-label={`${group.title} footer links`}>
                <h3>{group.title}</h3>
                {group.links.map(([label, href]) => (
                  <motion.a key={label} href={href} whileHover={{ x: 5 }}>
                    <ArrowRight size={14} />
                    {label}
                  </motion.a>
                ))}
              </motion.nav>
            ))}
          </div>

          <motion.div className="footer-connect-panel" variants={fadeUp}>
            <div>
              <p className="eyebrow">Stay connected</p>
              <h3>Follow the ecosystem as wallet, token, marketplace, and reward rails come online.</h3>
            </div>
            <div className="footer-channel-grid">
              {footerChannels.map(([label, note, Icon, href]) => (
                <motion.a key={label} href={href} whileHover={{ y: -4 }}>
                  <Icon size={18} />
                  <span>
                    <strong>{label}</strong>
                    <small>{note}</small>
                  </span>
                </motion.a>
              ))}
            </div>
            <div className="footer-trust-notes" aria-label="Trust and security notes">
              {footerTrustNotes.map((note) => (
                <span key={note}>
                  <CheckCircle2 size={15} />
                  {note}
                </span>
              ))}
            </div>
          </motion.div>
        </motion.div>
      </div>

      <motion.div className="footer-bottom" variants={fadeUp}>
        <span>&copy; {new Date().getFullYear()} ExaEarn. All rights reserved.</span>
        <div>
          <a href="#security">Security</a>
          <a href="#faq">Disclosures</a>
          <a href="#community">Community</a>
        </div>
      </motion.div>
    </motion.footer>
  );
}

function DeferredPageSections({ onOpenDownload, onOpenWallet, isWalletConnected }) {
  return (
    <>
      <ExaAiFloatingAccess />
      <ProofOfActivity />
      <InstitutionalLayer />
      <ExaAiShowcase />
      <LivingDashboardPreview />
      <MobileShowcase />
      <WhyExaEarn />
      <FeatureGrid />
      <HowItWorks />
      <TrustSecurity />
      <TokenSection />
      <EcosystemMap />
      <Roadmap />
      <Community />
      <DownloadAccess onOpenDownload={onOpenDownload} />
      <FAQ />
      <FinalCta onOpenDownload={onOpenDownload} onOpenWallet={onOpenWallet} isWalletConnected={isWalletConnected} />
      <Footer onOpenDownload={onOpenDownload} />
    </>
  );
}

function WebsiteLanguageSwitcher() {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [languageCode, setLanguageCode] = useState(() => {
    const stored = localStorage.getItem(LANGUAGE_STORAGE_KEY);
    const browserLanguages = Array.from(navigator.languages || [navigator.language]);
    return resolvePreferredLanguage([stored, ...browserLanguages, DEFAULT_LANGUAGE_CODE]);
  });

  const language = getLanguageByCode(languageCode);
  const results = useMemo(() => searchLanguages(query), [query]);

  useEffect(() => {
    document.documentElement.lang = language.locale;
    document.documentElement.dir = language.direction;
    document.documentElement.dataset.language = language.code;
    localStorage.setItem(LANGUAGE_STORAGE_KEY, language.code);
  }, [language.code, language.direction, language.locale]);

  const selectLanguage = (code) => {
    setLanguageCode(getLanguageByCode(code).code);
    setOpen(false);
    setQuery("");
  };

  return (
    <div className="website-language-switcher">
      <button type="button" className="website-language-trigger" onClick={() => setOpen((value) => !value)} aria-haspopup="dialog" aria-expanded={open}>
        <Globe2 size={16} />
        <span>{language.code.toUpperCase()}</span>
        <ChevronDown size={13} />
      </button>
      {open ? (
        <div className="website-language-panel" role="dialog" aria-label="Select website language">
          <div className="website-language-head">
            <div>
              <strong>Language</strong>
              <span>{formatLanguageLabel(language)}</span>
            </div>
            <button type="button" onClick={() => setOpen(false)} aria-label="Close language selector"><X size={15} /></button>
          </div>
          <label className="website-language-search">
            <Search size={14} />
            <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search language..." />
          </label>
          <div className="website-language-list" role="listbox">
            {results.map((item) => (
              <button key={item.code} type="button" onClick={() => selectLanguage(item.code)} className={item.code === language.code ? "is-selected" : ""} role="option" aria-selected={item.code === language.code}>
                <span><strong>{item.englishName}</strong><small>{item.nativeName} - {item.locale}</small></span>
                {item.code === language.code ? <Check size={14} /> : null}
              </button>
            ))}
          </div>
          <p>English copy is used wherever a localized translation is not ready.</p>
        </div>
      ) : null}
    </div>
  );
}
function App() {
  const [isDownloadModalOpen, setIsDownloadModalOpen] = useState(false);
  const [isWalletModalOpen, setIsWalletModalOpen] = useState(false);
  const [isWalletConnected, setIsWalletConnected] = useState(false);
  useCinematicPageEffects();

  const openDownloadModal = (event) => {
    event?.preventDefault();
    setIsDownloadModalOpen(true);
  };

  const closeDownloadModal = () => {
    setIsDownloadModalOpen(false);
  };

  const openWalletModal = (event) => {
    event?.preventDefault();
    setIsWalletModalOpen(true);
  };

  const closeWalletModal = () => {
    setIsWalletModalOpen(false);
  };

  const disconnectWallet = () => {
    setIsWalletConnected(false);
  };

  return (
    <main className="site-shell">
      <div className="cursor-spotlight" aria-hidden="true" />
      <motion.nav
        className="topbar"
        aria-label="Primary navigation"
        initial={{ y: -18, opacity: 1, filter: "blur(2px)" }}
        animate={{ y: 0, opacity: 1, filter: "blur(0px)" }}
        transition={{ duration: 0.58, ease: cinematicEase, delay: 0.04 }}
      >
        <motion.a className="brand" href="#top" whileHover={{ scale: 1.025 }}>
          <img src={logo} alt="ExaEarn" />
          <span>ExaEarn</span>
        </motion.a>
        <div className="nav-links">
          {["Activity", "ExaAi", "Dashboard", "Features", "Security"].map((item) => (
            <motion.a
              key={item}
              href={`#${item.toLowerCase()}`}
              whileHover={{ y: -2 }}
              whileTap={{ scale: 0.96 }}
            >
              {item}
            </motion.a>
          ))}
        </div>
        <div className="nav-actions">
          <WebsiteLanguageSwitcher />
          <motion.a className="nav-action primary" href={WEB_APP_SIGNUP_URL} whileHover={{ y: -3, scale: 1.02 }} whileTap={{ scale: 0.97 }}>
            <Rocket size={16} /> Launch App
          </motion.a>
          <WalletAccountControl
            isConnected={isWalletConnected}
            onOpenWallet={openWalletModal}
            onDisconnect={disconnectWallet}
          />
        </div>
        <div className="mobile-connect-action">
          <WalletAccountControl
            isConnected={isWalletConnected}
            onOpenWallet={openWalletModal}
            onDisconnect={disconnectWallet}
            label="Connect"
          />
        </div>
        <details className="mobile-menu">
          <summary aria-label="Open navigation menu">
            <Menu size={22} />
          </summary>
          <div className="mobile-menu-panel">
          <div className="mobile-menu-links">
              <a href="#activity">Activity</a>
              <a href="#exaai">ExaAi</a>
              <a href="#dashboard">Dashboard</a>
              <a href="#features">Features</a>
              <a href="#security">Security</a>
            </div>
            <WebsiteLanguageSwitcher />
            <div className="mobile-auth-actions" aria-label="Account actions">
              <a href={WEB_APP_LOGIN_URL}>Sign in</a>
              <a href={WEB_APP_SIGNUP_URL}>Sign up</a>
            </div>
            <div className="mobile-store-actions" aria-label="Download app">
              <a href="#download" onClick={openDownloadModal}>
                <Download size={17} /> App Store
              </a>
              <a href="#download" onClick={openDownloadModal}>
                <Play size={17} /> Play Store
              </a>
            </div>
          </div>
        </details>
      </motion.nav>
      <Hero onOpenDownload={openDownloadModal} onOpenWallet={openWalletModal} isWalletConnected={isWalletConnected} />
      <DeferredPageSections
        onOpenDownload={openDownloadModal}
        onOpenWallet={openWalletModal}
        isWalletConnected={isWalletConnected}
      />
      <DownloadAppModal isOpen={isDownloadModalOpen} onClose={closeDownloadModal} />
      <WalletOnboardingModal
        isOpen={isWalletModalOpen}
        onClose={closeWalletModal}
        isConnected={isWalletConnected}
        onConnected={() => setIsWalletConnected(true)}
        onDisconnect={disconnectWallet}
      />
    </main>
  );
}

createRoot(document.getElementById("root")).render(
  <StrictMode>
    <App />
  </StrictMode>,
);


