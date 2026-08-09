import React, { lazy, useCallback, useEffect, useMemo, useState } from "react";
import {
  ArrowUp,
  BarChart3,
  Bell,
  Coins,
  Flame,
  Gamepad2,
  Gem,
  Gift,
  GraduationCap,
  HandCoins,
  Handshake,
  Leaf,
  LogOut,
  ShieldCheck,
  UserRound,
  Loader2,
  MoreHorizontal,
  Plus,
  Settings,
  Sparkles,
  Star,
  Wallet,
  X,
} from "lucide-react";
import Image from "./assets/Image";
import SplashScreen from "./components/SplashScreen";
import LanguageSwitcher from "./components/language/LanguageSwitcher.jsx";
import ProfileIdentity from "./components/profile/ProfileIdentity";
import { useAuth } from "./context/AuthContext";
import { useLanguage } from "./context/LanguageContext.jsx";
import { useWebSocketEvent } from "./services/webSocketService";
import useMarketData from "./components/market/useMarketData";
import newsData from "./data/news.json";
import Register from "./pages/auth/Register";
import "./styles/App.css";

const Game = lazy(() => import("./Game/Game"));
const Token = lazy(() => import("./Token/Token"));
const Giftcard = lazy(() => import("./Giftcard/Giftcard"));
const BuyGiftcard = lazy(() => import("./BuyGiftcard/BuyGiftcard"));
const NFTMarketplace = lazy(() => import("./NFTMarketplace/NFTMarketplace"));
const Campaigns = lazy(() => import("./Campaigns/Campaigns"));
const Agriculture = lazy(() => import("./pages/Agriculture/Agriculture"));
const AcquireShare = lazy(() => import("./pages/Agriculture/AcquireShare"));
const SubscriptionPage = lazy(() => import("./pages/Agriculture/SubscriptionPage"));
const ExaScout = lazy(() => import("./pages/ExaScout/ExaScout"));
const CreatePlayerProfile = lazy(() => import("./pages/ExaScout/CreatePlayerProfile"));
const ScoutTalentPage = lazy(() => import("./pages/ExaScout/ScoutTalentPage"));
const ViewPlayerProfile = lazy(() => import("./pages/ExaScout/ViewPlayerProfile"));
const HighlightPreviewPage = lazy(() => import("./pages/ExaScout/HighlightPreviewPage"));
const InitiateContractPage = lazy(() => import("./pages/ExaScout/InitiateContractPage"));
const EdTech = lazy(() => import("./pages/EdTech/EdTech"));
const BecomeEducator = lazy(() => import("./pages/EdTech/BecomeEducator"));
const ApplyScholarship = lazy(() => import("./pages/EdTech/ApplyScholarship"));
const CourseUpload = lazy(() => import("./pages/EdTech/CourseUpload"));
const InstructorWorkshop = lazy(() => import("./pages/EdTech/InstructorWorkshop"));
const InstructorDashboard = lazy(() => import("./pages/EdTech/InstructorDashboard"));
const StartLearningEarn = lazy(() => import("./pages/EdTech/StartLearningEarn"));
const UploadedCourses = lazy(() => import("./pages/EdTech/UploadedCourses"));
const StakingDashboard = lazy(() => import("./pages/staking/StakingDashboard.jsx"));
const P2PMarketplace = lazy(() => import("./pages/P2PMarketplace/P2PMarketplace"));
const Transactions = lazy(() => import("./pages/Transactions/Transactions"));
const Rewards = lazy(() => import("./pages/Rewards/Rewards"));
const Assets = lazy(() => import("./pages/Assets/Assets"));
const Crowdfunding = lazy(() => import("./pages/Crowdfunding/Crowdfunding"));
const CreateCampaignPage = lazy(() => import("./pages/Crowdfunding/CreateCampaignPage"));
const SupportCampaignPage = lazy(() => import("./pages/Crowdfunding/SupportCampaignPage"));
const ViewCampaignPage = lazy(() => import("./pages/Crowdfunding/ViewCampaignPage"));
const Login = lazy(() => import("./pages/auth/Login"));
const ForgotPassword = lazy(() => import("./pages/auth/ForgotPassword"));
const NeedHelp = lazy(() => import("./pages/auth/NeedHelp"));
const ForgotAccountAppeal = lazy(() => import("./pages/auth/ForgotAccountAppeal"));
const ResetPassword = lazy(() => import("./pages/auth/ResetPassword"));
const AccountRecoveryEntry = lazy(() => import("./pages/auth/AccountRecoveryEntry"));
const ProfilePage = lazy(() => import("./pages/Profile/ProfilePage"));
const ProfileAppearance = lazy(() => import("./pages/Profile/ProfileAppearance"));
const SettingsPage = lazy(() => import("./pages/Settings/SettingsPage"));
const LanguageRegionPage = lazy(() => import("./pages/Settings/LanguageRegionPage"));
const CurrencyPreferencePage = lazy(() => import("./pages/Settings/CurrencyPreferencePage"));
const MarketAnalyticsPage = lazy(() => import("./pages/Settings/MarketAnalyticsPage"));
const PaymentCurrencyPage = lazy(() => import("./pages/Settings/PaymentCurrencyPage"));
const PaymentMethodsPage = lazy(() => import("./pages/Settings/PaymentMethodsPage"));
const AddFundsPage = lazy(() => import("./pages/AddFunds/AddFundsPage"));
const Send = lazy(() => import("./pages/Send/Send"));
const Swap = lazy(() => import("./pages/Swap/Swap"));
const Withdraw = lazy(() => import("./pages/Withdraw/Withdraw"));
const FiatWithdrawalPage = lazy(() => import("./pages/Withdraw/FiatWithdrawalPage"));
const MorePage = lazy(() => import("./pages/More/MorePage"));
const AITradingAssistantPage = lazy(() => import("./pages/AI/AITradingAssistantPage"));
const SupportCenter = lazy(() => import("./pages/Support/SupportCenter"));
const LiveSupportChat = lazy(() => import("./pages/Support/LiveSupportChat"));
const HelpSupportCenter = lazy(() => import("./pages/Support/HelpSupportCenter"));
const KYCVerification = lazy(() => import("./pages/KYC/KYCVerification"));
const ReferralProgram = lazy(() => import("./pages/Referral/ReferralProgram"));
const NotificationSettings = lazy(() => import("./pages/Notifications/NotificationSettings"));
const AboutExaEarn = lazy(() => import("./pages/About/AboutExaEarn"));
const ChangePassword = lazy(() => import("./pages/Security/ChangePassword"));
const LoginDevices = lazy(() => import("./pages/Security/LoginDevices"));
const ActivityLogs = lazy(() => import("./pages/Security/ActivityLogs"));
const Market = lazy(() => import("./pages/market/Market"));
const CryptoMarkets = lazy(() => import("./pages/market/CryptoMarkets"));
const Trade = lazy(() => import("./pages/trade/Trade"));
const Futures = lazy(() => import("./pages/futures/Futures"));
const Options = lazy(() => import("./pages/futures/Options"));
const SmartMoney = lazy(() => import("./pages/futures/SmartMoney"));

function AnimatedCounter({ end = 125480, duration = 0.8 }) {
  const target = Number(end);
  const [count, setCount] = useState(Number.isFinite(target) ? 0 : 0);

  useEffect(() => {
    if (!Number.isFinite(target)) {
      setCount(0);
      return;
    }

    setCount(0);
    let startTime;
    const animate = (currentTime) => {
      if (!startTime) startTime = currentTime;
      const elapsed = (currentTime - startTime) / 1000;
      const progress = Math.min(elapsed / duration, 1);
      setCount(Math.floor(progress * target));

      if (progress < 1) {
        requestAnimationFrame(animate);
      }
    };

    requestAnimationFrame(animate);
  }, [end, duration, target]);

  if (!Number.isFinite(target)) {
    return String(end);
  }

  return `$${count.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
}

function rewardDeviceFingerprint() {
  const storageKey = "exaearn_reward_device";
  try {
    const existing = localStorage.getItem(storageKey);
    if (existing) return existing;

    const value = crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;
    localStorage.setItem(storageKey, value);
    return value;
  } catch {
    return "browser-session";
  }
}

function DailyRewardCard({ progress, loading, claiming, onClaim, onOpen }) {
  const current = progress?.available_points ?? 0;
  const target = progress?.redemption_target_points ?? 5000;
  const percentage = Math.min(100, Math.max(0, progress?.progress_percentage ?? (current / target) * 100));
  const radius = 15;
  const circumference = 2 * Math.PI * radius;
  const strokeDashoffset = circumference - (percentage / 100) * circumference;

  return (
    <div
      role="button"
      tabIndex={0}
      className="daily-reward-card"
      onClick={onOpen}
      onKeyDown={(event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          onOpen();
        }
      }}
      aria-label="Open daily rewards"
    >
      <span className="reward-streak">
        <Flame className="reward-flame" size={17} aria-hidden="true" />
        <span>{progress?.current_streak ?? 0} Day Streak</span>
      </span>
      <span className="reward-points">{Number(current).toLocaleString()} Points</span>
      <span className="reward-ring" aria-hidden="true">
        <svg viewBox="0 0 40 40">
          <circle cx="20" cy="20" r={radius} />
          <circle cx="20" cy="20" r={radius} style={{ strokeDasharray: circumference, strokeDashoffset }} />
        </svg>
      </span>
      <span className="reward-progress">Progress to 5 USDT</span>
      <span
        role="button"
        tabIndex={0}
        className="reward-claim"
        onClick={(event) => {
          event.stopPropagation();
          onClaim();
        }}
        onKeyDown={(event) => {
          if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            event.stopPropagation();
            onClaim();
          }
        }}
      >
        {claiming ? <Loader2 className="reward-spin" size={14} aria-hidden="true" /> : loading ? "Sync" : "Claim"}
      </span>
    </div>
  );
}

function DailyRewardModal({ progress, history, lastReward, onClose, onClaim, onOpenMystery, onRedeem, busy }) {
  const checkins = history?.checkins ?? [];
  const probabilities = history?.daily_probabilities ?? [];
  const mystery = history?.mystery_probabilities ?? [];

  return (
    <div className="reward-modal-backdrop" role="presentation" onClick={onClose}>
      <section className="reward-modal" role="dialog" aria-modal="true" aria-label="Daily rewards" onClick={(event) => event.stopPropagation()}>
        <div className="reward-modal-head">
          <div>
            <p className="reward-kicker">Daily Check-in</p>
            <h2>Reward Engine</h2>
          </div>
          <button type="button" className="reward-close" onClick={onClose} aria-label="Close rewards">
            <X size={17} aria-hidden="true" />
          </button>
        </div>

        <div className="reward-modal-grid">
          <div className="reward-balance-panel">
            <div className="reward-box-visual">
              <Gift size={30} aria-hidden="true" />
              <span />
            </div>
            <p>{Number(progress?.available_points ?? 0).toLocaleString()} / {Number(progress?.redemption_target_points ?? 5000).toLocaleString()} Points</p>
            <div className="reward-bar"><span style={{ width: `${progress?.progress_percentage ?? 0}%` }} /></div>
            <small>{progress?.estimated_days_to_redeem ?? 0} estimated days to 5 USDT trading credit</small>
            {lastReward ? <strong>+{lastReward} points earned</strong> : null}
          </div>

          <div className="reward-actions-panel">
            <button type="button" className="reward-primary-action" onClick={onClaim} disabled={busy}>
              {busy ? <Loader2 className="reward-spin" size={15} aria-hidden="true" /> : <Flame size={15} aria-hidden="true" />}
              Claim Today
            </button>
            <button type="button" className="reward-secondary-action" onClick={onOpenMystery} disabled={busy || !progress?.mystery_box_available}>
              <Sparkles size={15} aria-hidden="true" />
              Open Mystery Box
            </button>
            <button type="button" className="reward-secondary-action" onClick={onRedeem} disabled={busy || (progress?.available_points ?? 0) < 5000}>
              <Wallet size={15} aria-hidden="true" />
              Redeem Credit
            </button>
          </div>
        </div>

        <div className="reward-history-strip">
          {Array.from({ length: 7 }).map((_, index) => {
            const active = index < (progress?.current_streak ?? 0);
            return <span key={index} className={active ? "active" : ""}>{index + 1}</span>;
          })}
        </div>

        <div className="reward-detail-grid">
          <div>
            <h3>Recent Rewards</h3>
            <div className="reward-list">
              {checkins.slice(0, 5).map((item) => (
                <p key={`${item.checkin_date}-${item.streak_day}`}>
                  <span>Day {item.streak_day}</span>
                  <strong>+{item.reward_points}</strong>
                </p>
              ))}
              {!checkins.length ? <p><span>No claims yet</span><strong>0</strong></p> : null}
            </div>
          </div>
          <div>
            <h3>Reward Odds</h3>
            <div className="reward-odds">
              {probabilities.slice(0, 4).map((item) => <span key={item.points}>{item.points}p</span>)}
              {mystery.slice(0, 3).map((item) => <span key={`m-${item.points}`}>{item.points}p box</span>)}
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}

const features = [
  { name: "Gift Cards", image: Image.giftcard, icon: Gift, tone: "gold" },
  { name: "Earn", image: Image.xrp, icon: Coins, tone: "cyan" },
  { name: "Games", image: Image.games, icon: Gamepad2, tone: "violet" },
  { name: "NFT Market", image: Image.nft, icon: Gem, tone: "blue" },
  { name: "Crowdfund", image: Image.crowdfund, icon: HandCoins, tone: "gold" },
  { name: "Agritech", image: Image.agriculture, icon: Leaf, tone: "green" },
  { name: "ExaSkills", image: Image.edu, icon: GraduationCap, tone: "cyan" },
  { name: "More", image: Image.more, icon: MoreHorizontal, tone: "violet" },
];

const marketAssets = [
  { symbol: "BTC", pair: "BTC/USDT", price: "$102,840", change: "+2.84%", heat: "hot" },
  { symbol: "ETH", pair: "ETH/USDT", price: "$5,418", change: "+1.37%", heat: "warm" },
  { symbol: "XRP", pair: "XRP/USDT", price: "$2.92", change: "+4.61%", heat: "hot" },
  { symbol: "SOL", pair: "SOL/USDT", price: "$238.70", change: "-0.42%", heat: "cool" },
  { symbol: "EXA", pair: "EXA/USDT", price: "$0.84", change: "+8.20%", heat: "hot" },
];

function formatMarketPrice(value) {
  const numericValue = Number(value);
  if (!Number.isFinite(numericValue)) return "--";
  if (numericValue >= 1000) return numericValue.toLocaleString(undefined, { maximumFractionDigits: 2 });
  if (numericValue >= 1) return numericValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 });
  return numericValue.toLocaleString(undefined, { minimumFractionDigits: 4, maximumFractionDigits: 6 });
}

function normalizeNotifications(payload) {
  const source = payload?.data?.data || payload?.data || payload?.notifications || [];
  return Array.isArray(source) ? source : [];
}

function isUnreadNotification(notification) {
  return notification?.status && notification.status !== "read";
}

function formatNotificationTime(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

function getAuthPageFromLocation() {
  try {
    const params = new URLSearchParams(window.location.search);
    const authParam = params.get("auth")?.toLowerCase();
    const pathname = window.location.pathname.toLowerCase().replace(/\/+$/, "");
    const routeSegment = pathname.split("/").filter(Boolean).at(-1);

    if (authParam === "register" || routeSegment === "register" || routeSegment === "signup" || routeSegment === "registe") {
      return "register";
    }

    return "login";
  } catch {
    return "login";
  }
}

function getAuthRoutePrefix() {
  const segments = window.location.pathname.split("/").filter(Boolean);
  const lastSegment = segments.at(-1)?.toLowerCase();
  const authSegments = new Set(["login", "signin", "register", "signup", "registe"]);

  if (authSegments.has(lastSegment)) {
    segments.pop();
  }

  return segments.length ? `/${segments.join("/")}` : "";
}

function getAuthUrl(page) {
  const prefix = getAuthRoutePrefix();

  if (page === "register") {
    return `${prefix}/register`;
  }

  if (page === "login") {
    return `${prefix}/login`;
  }

  return null;
}

export default function App() {
  const { user, setUser, logout, apiBaseUrl, request } = useAuth();
  const { t } = useLanguage();
  const [isInitialLoading, setIsInitialLoading] = useState(true);
  const [authPage, setAuthPageState] = useState(getAuthPageFromLocation);
  const [portfolioValue, setPortfolioValue] = useState("0");
  const [portfolioCurrency, setPortfolioCurrency] = useState("USDT");
  const [showSplash, setShowSplash] = useState(false);
  const [currentPage, setCurrentPage] = useState("home");
  const [p2pInitialSide, setP2pInitialSide] = useState("buy");
  const [showGiftcardChoice, setShowGiftcardChoice] = useState(false);
  const [activeNewsIndex, setActiveNewsIndex] = useState(0);
  const [selectedCourseUpload, setSelectedCourseUpload] = useState("web3-fundamentals");
  const [selectedCampaignId, setSelectedCampaignId] = useState("cmp-1");
  const [selectedPlayerId, setSelectedPlayerId] = useState("st-1");
  const [selectedAgriProjectId, setSelectedAgriProjectId] = useState(null);
  const [sportsReturnPage, setSportsReturnPage] = useState("exascout");
  const [referralReturnPage, setReferralReturnPage] = useState("home");
  const [helpReturnPage, setHelpReturnPage] = useState("home");
  const [chatReturnPage, setChatReturnPage] = useState("supportCenter");
  const [activityLogsReturnPage, setActivityLogsReturnPage] = useState("profile");
  const [rewardProgress, setRewardProgress] = useState(null);
  const [rewardHistory, setRewardHistory] = useState(null);
  const [rewardModalOpen, setRewardModalOpen] = useState(false);
  const [rewardLoading, setRewardLoading] = useState(false);
  const [rewardBusy, setRewardBusy] = useState(false);
  const [lastReward, setLastReward] = useState(null);
  const [notifications, setNotifications] = useState([]);
  const [notificationOpen, setNotificationOpen] = useState(false);
  const [profileMenuOpen, setProfileMenuOpen] = useState(false);
  const [notificationLoading, setNotificationLoading] = useState(false);
  const { pairs: livePairs, setPairs: setLivePairs } = useMarketData();
  const [homeMarketFilter, setHomeMarketFilter] = useState("Top");
  const isAuthenticated = Boolean(user);
  const showAiQuickLaunch = isAuthenticated && ["trade", "futures", "options", "smartMoney"].includes(currentPage);
  const unreadNotificationCount = useMemo(
    () => notifications.filter(isUnreadNotification).length,
    [notifications]
  );

  const openP2PPage = useCallback((side = "buy") => {
    setP2pInitialSide(side === "sell" ? "sell" : "buy");
    setCurrentPage("p2pMarketplace");
  }, []);

  const setAuthPage = useCallback((page) => {
    setAuthPageState(page);

    const nextUrl = getAuthUrl(page);
    if (!nextUrl || window.location.pathname === nextUrl) {
      return;
    }

    window.history.pushState({ authPage: page }, "", nextUrl);
  }, []);

  useEffect(() => {
    const handlePopState = () => {
      setAuthPageState(getAuthPageFromLocation());
    };

    window.addEventListener("popstate", handlePopState);
    return () => window.removeEventListener("popstate", handlePopState);
  }, []);

  const campaignNews = useMemo(() => {
    return [...newsData].sort((a, b) => {
      if (a.featured !== b.featured) {
        return a.featured ? -1 : 1;
      }
      return new Date(b.date).getTime() - new Date(a.date).getTime();
    });
  }, []);

  const homeMarketPairs = useMemo(() => {
    const source = livePairs.length ? livePairs : marketAssets.map((asset) => ({
      pair: asset.pair,
      base: asset.symbol,
      quote: "USDT",
      last: Number(asset.price.replace(/[$,]/g, "")),
      change24h: Number(asset.change.replace("%", "")),
      volume: "Live",
      favorite: asset.symbol === "BTC" || asset.symbol === "XRP",
    }));

    const sorted = [...source].sort((left, right) => {
      if (homeMarketFilter === "Gainers") return right.change24h - left.change24h;
      if (homeMarketFilter === "Fav") return Number(Boolean(right.favorite)) - Number(Boolean(left.favorite));
      return right.last - left.last;
    });

    return sorted.slice(0, 5);
  }, [homeMarketFilter, livePairs]);

  useEffect(() => {
    const splashTimer = setTimeout(() => {
      setIsInitialLoading(false);
    }, 1500);

    return () => clearTimeout(splashTimer);
  }, []);

  useEffect(() => {
    if (!campaignNews.length) {
      return undefined;
    }

    const intervalId = setInterval(() => {
      setActiveNewsIndex((prev) => (prev + 1) % campaignNews.length);
    }, 3500);

    return () => clearInterval(intervalId);
  }, [campaignNews]);

  useEffect(() => {
    let ignore = false;

    const fetchPortfolio = async () => {
      const base = apiBaseUrl?.replace(/\/+$/, "") || "";
      if (!base || !user) {
        return;
      }

      try {
        const response = await fetch(`${base}/api/portfolio`, {
          credentials: "include",
          headers: {
            Accept: "application/json",
          },
        });

        if (!response.ok) {
          return;
        }

        const payload = await response.json();
        if (!ignore && payload?.data) {
          setPortfolioValue(payload.data.total_value ?? "0");
          setPortfolioCurrency(payload.data.currency ?? "USDT");
        }
      } catch {
        // best-effort portfolio bootstrap
      }
    };

    fetchPortfolio();

    return () => {
      ignore = true;
    };
  }, [apiBaseUrl, user]);

  const loadRewards = useCallback(async () => {
    if (!user) return;

    setRewardLoading(true);
    try {
      const payload = await request("/api/points", { method: "GET" });
      setRewardProgress(payload.data ?? null);
    } catch {
      setRewardProgress(null);
    } finally {
      setRewardLoading(false);
    }
  }, [request, user]);

  const loadRewardHistory = useCallback(async () => {
    if (!user) return;

    try {
      const payload = await request("/api/checkin/history", { method: "GET" });
      setRewardHistory(payload.data ?? null);
    } catch {
      setRewardHistory(null);
    }
  }, [request, user]);

  useEffect(() => {
    loadRewards();
  }, [loadRewards]);

  const claimReward = useCallback(async () => {
    if (!user || rewardBusy) return;

    setRewardBusy(true);
    try {
      const payload = await request("/api/checkin", {
        method: "POST",
        headers: { "X-Device-Fingerprint": rewardDeviceFingerprint() },
        body: JSON.stringify({}),
      });
      setLastReward(payload?.data?.reward_points ?? null);
      setRewardProgress(payload?.data?.progress ?? null);
      await loadRewardHistory();
    } catch {
      await loadRewards();
    } finally {
      setRewardBusy(false);
    }
  }, [loadRewardHistory, loadRewards, request, rewardBusy, user]);

  const openMysteryBox = useCallback(async () => {
    if (!user || rewardBusy) return;

    setRewardBusy(true);
    try {
      const payload = await request("/api/mystery-box/open", {
        method: "POST",
        headers: { "X-Device-Fingerprint": rewardDeviceFingerprint() },
        body: JSON.stringify({}),
      });
      setLastReward(payload?.data?.reward_points ?? null);
      setRewardProgress(payload?.data?.progress ?? null);
      await loadRewardHistory();
    } catch {
      await loadRewards();
    } finally {
      setRewardBusy(false);
    }
  }, [loadRewardHistory, loadRewards, request, rewardBusy, user]);

  const redeemReward = useCallback(async () => {
    if (!user || rewardBusy) return;

    setRewardBusy(true);
    try {
      await request("/api/redeem", {
        method: "POST",
        headers: { "X-Device-Fingerprint": rewardDeviceFingerprint() },
        body: JSON.stringify({}),
      });
      await loadRewards();
      await loadRewardHistory();
    } catch {
      await loadRewards();
    } finally {
      setRewardBusy(false);
    }
  }, [loadRewardHistory, loadRewards, request, rewardBusy, user]);

  const loadNotifications = useCallback(async () => {
    if (!user) {
      setNotifications([]);
      return;
    }

    setNotificationLoading(true);
    try {
      const payload = await request("/api/notifications?per_page=8", { method: "GET" });
      setNotifications(normalizeNotifications(payload));
    } catch {
      setNotifications([]);
    } finally {
      setNotificationLoading(false);
    }
  }, [request, user]);

  useEffect(() => {
    loadNotifications();
  }, [loadNotifications]);

  useEffect(() => {
    if (!user) return undefined;

    const intervalId = setInterval(loadNotifications, 45000);
    return () => clearInterval(intervalId);
  }, [loadNotifications, user]);

  const openNotifications = useCallback(async () => {
    setNotificationOpen((open) => !open);
    if (!notificationOpen) {
      await loadNotifications();
    }
  }, [loadNotifications, notificationOpen]);

  const openNotification = useCallback(async (notification) => {
    if (!notification?.id) return;

    try {
      await request(`/api/notifications/${notification.id}/read`, { method: "PUT" });
    } catch {
      // Keep the tray usable even if the read receipt fails.
    }

    setNotifications((items) =>
      items.map((item) =>
        item.id === notification.id
          ? { ...item, status: "read", read_at: item.read_at || new Date().toISOString() }
          : item
      )
    );

    setNotificationOpen(false);
    if (notification?.data?.action_page === "aiAssistant" || notification?.type === "ai_assistant") {
      setCurrentPage("aiAssistant");
    }
  }, [request]);

  useWebSocketEvent("portfolio:update", (payload) => {
    const data = payload?.data ?? payload;
    if (!data) {
      return;
    }

    setPortfolioValue((prev) => data.total_value ?? prev);
    setPortfolioCurrency((prev) => data.currency ?? prev);
  });

  if (isInitialLoading) {
    return <SplashScreen />;
  }

  if (!isAuthenticated) {
    if (authPage === "register") {
      return (
        <Register
          onLogin={() => setAuthPage("login")}
          onSuccess={() => {
            setShowSplash(true);
            setTimeout(() => {
              setShowSplash(false);
            }, 900);
          }}
        />
      );
    }
    if (authPage === "forgotPassword") {
      return <ForgotPassword onLogin={() => setAuthPage("login")} />;
    }
    if (authPage === "needHelp") {
      return (
        <NeedHelp
          onBack={() => setAuthPage("login")}
          onRememberAccount={() => setAuthPage("resetPassword")}
          onForgotAccount={() => setAuthPage("forgotAccountAppeal")}
        />
      );
    }
    if (authPage === "resetPassword") {
      return <ResetPassword onBack={() => setAuthPage("needHelp")} />;
    }
    if (authPage === "forgotAccountAppeal") {
      return (
        <ForgotAccountAppeal
          onBack={() => setAuthPage("needHelp")}
          onSubmitAppeal={() => setAuthPage("accountRecoveryEntry")}
          onCheckPreviousResult={() => setAuthPage("accountRecoveryEntry")}
        />
      );
    }
    if (authPage === "accountRecoveryEntry") {
      return <AccountRecoveryEntry onBack={() => setAuthPage("forgotAccountAppeal")} />;
    }

    return (
      <Login
        onCreateAccount={() => setAuthPage("register")}
        onForgotPassword={() => setAuthPage("forgotPassword")}
        onNeedHelp={() => setAuthPage("needHelp")}
        onSuccess={() => {
          setShowSplash(true);
          setTimeout(() => {
            setShowSplash(false);
          }, 900);
        }}
      />
    );
  }

  if (showSplash) {
    return (
      <div className="min-h-screen w-full bg-linear-to-br from-black via-[#140a24] to-[#220c3d] text-white">
        <div className="flex min-h-screen items-center justify-center">
          <div className="flex h-28 w-28 items-center justify-center rounded-3xl border border-auric-300/60 bg-cosmic-900/70 shadow-button-glow">
            <img src={Image.earn} alt="ExaEarn logo" className="h-14 w-14 object-contain" />
          </div>
        </div>
      </div>
    );
  }

  if (currentPage === "game") {
    return <Game onBack={() => setCurrentPage("home")} />;
  }

  if (currentPage === "token") {
    return <Token onBack={() => setCurrentPage("home")} />;
  }
  if (currentPage === "market") {
    return (
      <Market
        onBack={() => setCurrentPage("home")}
        onOpenTrade={() => setCurrentPage("trade")}
        onOpenFutures={() => setCurrentPage("futures")}
        onOpenP2P={() => openP2PPage()}
        onOpenCrypto={() => setCurrentPage("cryptoMarkets")}
      />
    );
  }
  if (currentPage === "cryptoMarkets") {
    return <CryptoMarkets onBack={() => setCurrentPage("market")} onOpenTrade={() => setCurrentPage("trade")} />;
  }
  if (currentPage === "trade") {
    return (
      <Trade
        onBack={() => setCurrentPage("home")}
        onOpenConvert={() => setCurrentPage("swap")}
        onOpenFutures={() => setCurrentPage("futures")}
        onOpenOptions={() => setCurrentPage("options")}
        onOpenTradFi={() => setCurrentPage("smartMoney")}
      />
    );
  }
  if (currentPage === "futures") {
    return (
      <Futures
        onBack={() => setCurrentPage("home")}
        onOpenConvert={() => setCurrentPage("swap")}
        onOpenSpot={() => setCurrentPage("trade")}
        onOpenOptions={() => setCurrentPage("options")}
        onOpenTradFi={() => setCurrentPage("smartMoney")}
        onOpenSmart={() => setCurrentPage("smartMoney")}
      />
    );
  }
  if (currentPage === "options") {
    return <Options onBack={() => setCurrentPage("futures")} onOpenSmartMoney={() => setCurrentPage("smartMoney")} />;
  }
  if (currentPage === "smartMoney") {
    return <SmartMoney onBack={() => setCurrentPage("futures")} onOpenOptions={() => setCurrentPage("options")} />;
  }

  if (currentPage === "giftcard") {
    return <Giftcard onBack={() => setCurrentPage("home")} />;
  }

  if (currentPage === "buyGiftcard") {
    return <BuyGiftcard onBack={() => setCurrentPage("home")} />;
  }

  if (currentPage === "nftMarketplace") {
    return <NFTMarketplace onBack={() => setCurrentPage("home")} />;
  }

  if (currentPage === "campaigns") {
    return <Campaigns onBack={() => setCurrentPage("home")} />;
  }
  if (currentPage === "agriculture") {
    return (
      <Agriculture
        onBack={() => setCurrentPage("home")}
        onOpenAcquireShare={(projectId) => {
          setSelectedAgriProjectId(projectId ?? null);
          setCurrentPage("acquireShare");
        }}
        onOpenSubscribe={() => setCurrentPage("agriSubscription")}
      />
    );
  }
  if (currentPage === "acquireShare") {
    return <AcquireShare onBack={() => setCurrentPage("agriculture")} initialProjectId={selectedAgriProjectId} />;
  }
  if (currentPage === "agriSubscription") {
    return <SubscriptionPage onBack={() => setCurrentPage("agriculture")} />;
  }
  if (currentPage === "exascout") {
    return (
      <ExaScout
        onBack={() => setCurrentPage("home")}
        onOpenCreatePlayerProfile={() => setCurrentPage("createPlayerProfile")}
        onOpenScoutTalent={() => setCurrentPage("scoutTalent")}
        onOpenViewProfile={(playerId) => {
          setSelectedPlayerId(playerId);
          setCurrentPage("viewPlayerProfile");
        }}
        onOpenHighlightPreview={(playerId) => {
          setSelectedPlayerId(playerId);
          setSportsReturnPage("exascout");
          setCurrentPage("highlightPreview");
        }}
        onOpenInitiateContract={() => {
          setSportsReturnPage("exascout");
          setCurrentPage("initiateContract");
        }}
      />
    );
  }
  if (currentPage === "createPlayerProfile") {
    return <CreatePlayerProfile onBack={() => setCurrentPage("exascout")} />;
  }
  if (currentPage === "scoutTalent") {
    return (
      <ScoutTalentPage
        onBack={() => setCurrentPage("exascout")}
        onViewProfile={(playerId) => {
          setSelectedPlayerId(playerId);
          setCurrentPage("viewPlayerProfile");
        }}
        onOpenHighlightPreview={(playerId) => {
          setSelectedPlayerId(playerId);
          setSportsReturnPage("scoutTalent");
          setCurrentPage("highlightPreview");
        }}
      />
    );
  }
  if (currentPage === "highlightPreview") {
    return (
      <HighlightPreviewPage
        onBack={() => setCurrentPage(sportsReturnPage)}
        playerId={selectedPlayerId}
        onViewProfile={(playerId) => {
          setSelectedPlayerId(playerId);
          setCurrentPage("viewPlayerProfile");
        }}
      />
    );
  }
  if (currentPage === "initiateContract") {
    return <InitiateContractPage onBack={() => setCurrentPage(sportsReturnPage)} />;
  }
  if (currentPage === "viewPlayerProfile") {
    return <ViewPlayerProfile onBack={() => setCurrentPage("scoutTalent")} playerId={selectedPlayerId} />;
  }
  if (currentPage === "edtech") {
    return (
      <EdTech
        onBack={() => setCurrentPage("home")}
        onOpenBecomeEducator={() => setCurrentPage("becomeEducator")}
        onOpenApplyScholarship={() => setCurrentPage("applyScholarship")}
        onOpenStartLearning={() => setCurrentPage("startLearningEarn")}
        onOpenCourseUpload={(courseKey) => {
          setSelectedCourseUpload(courseKey);
          setCurrentPage("courseUpload");
        }}
        onOpenInstructorWorkshop={() => setCurrentPage("instructorWorkshop")}
        onOpenInstructorDashboard={() => setCurrentPage("instructorDashboard")}
      />
    );
  }
  if (currentPage === "startLearningEarn") {
    return (
      <StartLearningEarn
        onBack={() => setCurrentPage("edtech")}
        onExploreCourses={() => setCurrentPage("uploadedCourses")}
        onStartCourse={() => {
          setSelectedCourseUpload("web3-fundamentals");
          setCurrentPage("courseUpload");
        }}
      />
    );
  }
  if (currentPage === "uploadedCourses") {
    return (
      <UploadedCourses
        onBack={() => setCurrentPage("startLearningEarn")}
        onOpenCourse={(courseKey) => {
          setSelectedCourseUpload(courseKey);
          setCurrentPage("courseUpload");
        }}
      />
    );
  }
  if (currentPage === "becomeEducator") {
    return <BecomeEducator onBack={() => setCurrentPage("edtech")} />;
  }
  if (currentPage === "applyScholarship") {
    return <ApplyScholarship onBack={() => setCurrentPage("edtech")} />;
  }
  if (currentPage === "courseUpload") {
    return <CourseUpload onBack={() => setCurrentPage("edtech")} courseKey={selectedCourseUpload} />;
  }
  if (currentPage === "instructorWorkshop") {
    return <InstructorWorkshop onBack={() => setCurrentPage("edtech")} />;
  }
  if (currentPage === "instructorDashboard") {
    return <InstructorDashboard onBack={() => setCurrentPage("edtech")} />;
  }
  if (currentPage === "staking") {
    return <StakingDashboard onBack={() => setCurrentPage("home")} />;
  }
  if (currentPage === "p2pMarketplace") {
    return (
      <P2PMarketplace
        onBack={() => setCurrentPage("home")}
        initialTradeSide={p2pInitialSide}
        onOpenConvert={() => setCurrentPage("swap")}
        onOpenFiatGateway={() => setCurrentPage("addFunds")}
      />
    );
  }
  if (currentPage === "transactions") {
    return <Transactions onBack={() => setCurrentPage("home")} />;
  }
  if (currentPage === "rewards") {
    return <Rewards onBack={() => setCurrentPage("home")} />;
  }
  if (currentPage === "assets") {
    return (
      <Assets
        onBack={() => setCurrentPage("home")}
        onOpenSend={() => setCurrentPage("send")}
        onOpenAddFunds={() => setCurrentPage("addFunds")}
        onOpenSwap={() => setCurrentPage("swap")}
        onOpenWithdraw={() => setCurrentPage("withdraw")}
      />
    );
  }
  if (currentPage === "crowdfunding") {
    return (
      <Crowdfunding
        onBack={() => setCurrentPage("home")}
        onCreateCampaign={() => setCurrentPage("createCampaign")}
        onViewCampaign={(campaignId) => {
          setSelectedCampaignId(campaignId);
          setCurrentPage("viewCampaign");
        }}
        onSupportCampaign={(campaignId) => {
          setSelectedCampaignId(campaignId);
          setCurrentPage("supportCampaign");
        }}
      />
    );
  }
  if (currentPage === "createCampaign") {
    return <CreateCampaignPage onBack={() => setCurrentPage("crowdfunding")} />;
  }
  if (currentPage === "supportCampaign") {
    return <SupportCampaignPage onBack={() => setCurrentPage("crowdfunding")} campaignId={selectedCampaignId} />;
  }
  if (currentPage === "viewCampaign") {
    return (
      <ViewCampaignPage
        onBack={() => setCurrentPage("crowdfunding")}
        campaignId={selectedCampaignId}
        onSupportCampaign={(campaignId) => {
          setSelectedCampaignId(campaignId);
          setCurrentPage("supportCampaign");
        }}
      />
    );
  }
  if (currentPage === "profile") {
    return (
      <ProfilePage
        onBack={() => setCurrentPage("home")}
        onOpenSettings={() => setCurrentPage("settings")}
        onOpenVerification={() => setCurrentPage("kycVerification")}
        onOpenProfileAppearance={() => setCurrentPage("profileAppearance")}
        onOpenReferral={() => {
          setReferralReturnPage("profile");
          setCurrentPage("referralProgram");
        }}
        onOpenNotifications={() => setCurrentPage("notificationSettings")}
        onOpenHelpSupport={() => {
          setHelpReturnPage("profile");
          setCurrentPage("helpSupportCenter");
        }}
        onOpenAbout={() => setCurrentPage("aboutExaEarn")}
        onOpenChangePassword={() => setCurrentPage("changePassword")}
        onOpenLoginDevices={() => setCurrentPage("loginDevices")}
        onOpenActivityLogs={() => {
          setActivityLogsReturnPage("profile");
          setCurrentPage("activityLogs");
        }}
        user={user}
        onLogout={async () => {
          await logout();
          setUser(null);
          setAuthPage("login");
          setCurrentPage("home");
        }}
      />
    );
  }
  if (currentPage === "profileAppearance") {
    return <ProfileAppearance onBack={() => setCurrentPage("profile")} />;
  }

  if (currentPage === "kycVerification") {
    return <KYCVerification onBack={() => setCurrentPage("profile")} />;
  }
  if (currentPage === "referralProgram") {
    return <ReferralProgram onBack={() => setCurrentPage(referralReturnPage)} user={user} />;
  }
  if (currentPage === "notificationSettings") {
    return <NotificationSettings onBack={() => setCurrentPage("profile")} />;
  }
  if (currentPage === "aboutExaEarn") {
    return <AboutExaEarn onBack={() => setCurrentPage("profile")} />;
  }
  if (currentPage === "changePassword") {
    return <ChangePassword onBack={() => setCurrentPage("profile")} onBackToSettings={() => setCurrentPage("settings")} />;
  }
  if (currentPage === "loginDevices") {
    return (
      <LoginDevices
        onBack={() => setCurrentPage("profile")}
        onOpenActivityLogs={() => {
          setActivityLogsReturnPage("profile");
          setCurrentPage("activityLogs");
        }}
      />
    );
  }
  if (currentPage === "activityLogs") {
    return <ActivityLogs onBack={() => setCurrentPage(activityLogsReturnPage)} />;
  }
  if (currentPage === "settings") {
    return (
      <SettingsPage
        onBack={() => setCurrentPage("home")}
        onOpenLanguageRegion={() => setCurrentPage("languageRegion")}
        onOpenCurrencyPreference={() => setCurrentPage("currencyPreference")}
        onOpenMarketAnalytics={() => setCurrentPage("marketAnalyticsSettings")}
        onOpenNotificationPreferences={() => setCurrentPage("notificationSettings")}
        onOpenPaymentCurrency={() => setCurrentPage("paymentCurrency")}
        onOpenPaymentMethods={() => setCurrentPage("paymentMethods")}
        onOpenActivityLogs={() => {
          setActivityLogsReturnPage("settings");
          setCurrentPage("activityLogs");
        }}
      />
    );
  }
  if (currentPage === "languageRegion") {
    return <LanguageRegionPage onBack={() => setCurrentPage("settings")} />;
  }
  if (currentPage === "currencyPreference") {
    return <CurrencyPreferencePage onBack={() => setCurrentPage("settings")} />;
  }
  if (currentPage === "paymentCurrency") {
    return <PaymentCurrencyPage onBack={() => setCurrentPage("settings")} />;
  }
  if (currentPage === "paymentMethods") {
    return <PaymentMethodsPage onBack={() => setCurrentPage("settings")} />;
  }
  if (currentPage === "marketAnalyticsSettings") {
    return <MarketAnalyticsPage onBack={() => setCurrentPage("settings")} />;
  }
  if (currentPage === "addFunds") {
    return (
      <AddFundsPage
        onBack={() => setCurrentPage("home")}
        onOpenSend={() => setCurrentPage("send")}
        onOpenSwap={() => setCurrentPage("swap")}
        onOpenWithdraw={() => setCurrentPage("withdraw")}
        onOpenP2P={() => openP2PPage()}
      />
    );
  }
  if (currentPage === "send") {
    return <Send onBack={() => setCurrentPage("home")} onAddFunds={() => setCurrentPage("addFunds")} />;
  }
  if (currentPage === "swap") {
    return <Swap onBack={() => setCurrentPage("assets")} />;
  }
  if (currentPage === "withdraw") {
    return <Withdraw onBack={() => setCurrentPage("assets")} onOpenP2P={openP2PPage} onOpenFiatWithdrawal={() => setCurrentPage("fiatWithdrawal")} />;
  }
  if (currentPage === "fiatWithdrawal") {
    return <FiatWithdrawalPage onBack={() => setCurrentPage("withdraw")} />;
  }
  if (currentPage === "more") {
    return (
      <MorePage
        onBack={() => setCurrentPage("home")}
        onOpenRewards={() => setCurrentPage("rewards")}
        onOpenReferral={() => {
          setReferralReturnPage("more");
          setCurrentPage("referralProgram");
        }}
        onOpenHelpSupport={() => {
          setHelpReturnPage("more");
          setCurrentPage("helpSupportCenter");
        }}
        onOpenAiAssistant={() => setCurrentPage("aiAssistant")}
        onOpenToken={() => setCurrentPage("token")}
        onOpenTransactions={() => setCurrentPage("transactions")}
        onOpenSports={() => setCurrentPage("exascout")}
      />
    );
  }
  if (currentPage === "aiAssistant") {
    return <AITradingAssistantPage onBack={() => setCurrentPage("more")} />;
  }
  if (currentPage === "helpSupportCenter") {
    return (
      <HelpSupportCenter
        onBack={() => setCurrentPage(helpReturnPage)}
        onOpenLiveChat={() => {
          setChatReturnPage("helpSupportCenter");
          setCurrentPage("liveSupportChat");
        }}
        onOpenTicketCenter={() => setCurrentPage("supportCenter")}
      />
    );
  }
  if (currentPage === "supportCenter") {
    return (
      <SupportCenter
        onBack={() => setCurrentPage("home")}
        onOpenLiveChat={() => {
          setChatReturnPage("supportCenter");
          setCurrentPage("liveSupportChat");
        }}
      />
    );
  }
  if (currentPage === "liveSupportChat") {
    return <LiveSupportChat onBack={() => setCurrentPage(chatReturnPage)} />;
  }

  const activeNews = campaignNews[activeNewsIndex];
  const userDisplayName = user?.name?.trim() || "ExaEarn User";
  const maskedEmail = user?.email ? user.email.replace(/^(.{2}).*(@.*)$/, "$1***$2") : t("dashboard.emailNotSet");
  const verificationLevel = user?.verification?.kyc_level ?? user?.kyc_level ?? 0;
  const securityStatus = user?.two_factor_enabled ? t("dashboard.security2faEnabled") : t("dashboard.security2faNotEnabled");
  const featureLabel = (featureName) => {
    const keys = {
      "Gift Cards": "dashboard.featureGiftCards",
      Earn: "dashboard.featureEarn",
      Games: "dashboard.featureGames",
      "NFT Market": "dashboard.featureNftMarket",
      Crowdfund: "dashboard.featureCrowdfund",
      Agritech: "dashboard.featureAgritech",
      ExaSkills: "dashboard.featureExaSkills",
      More: "dashboard.featureMore",
    };
    return t(keys[featureName] || featureName);
  };

  const marketTabLabel = (tab) => {
    const keys = { Top: "dashboard.top", Gainers: "dashboard.gainers", Fav: "dashboard.favorites" };
    return t(keys[tab] || tab);
  };
  const openFeature = (featureName) => {
    if (featureName === "Games") {
      setCurrentPage("game");
    } else if (featureName === "Gift Cards") {
      setShowGiftcardChoice(true);
    } else if (featureName === "NFT Market") {
      setCurrentPage("nftMarketplace");
    } else if (featureName === "Crowdfund") {
      setCurrentPage("crowdfunding");
    } else if (featureName === "Agritech") {
      setCurrentPage("agriculture");
    } else if (featureName === "ExaSkills") {
      setCurrentPage("edtech");
    } else if (featureName === "Earn") {
      setCurrentPage("staking");
    } else if (featureName === "More") {
      setCurrentPage("more");
    }
  };
  const toggleHomeMarketFavorite = (pairKey) => {
    setLivePairs((previous) =>
      previous.map((item) => (item.pair === pairKey ? { ...item, favorite: !item.favorite } : item))
    );
  };

  return (
    <div className="home-screen text-white exa-bg app-shell">
      <div className="home-scroll-area">
        <div className="w-full px-0 pt-4 pb-6">
          <div className="home-main-card p-4 shadow-xl glass-card rounded-3xl sm:p-5">
          <header className="home-profile-card flex items-center justify-between mb-4 sm:mb-6 campaign-card">
            <div className="flex items-center min-w-0 gap-3 sm:gap-4">
              <div className="profile-menu">
                <button
                  type="button"
                  onClick={() => setProfileMenuOpen((open) => !open)}
                  className="avatar-accent"
                  aria-label={t("dashboard.openProfileMenu")}
                  aria-expanded={profileMenuOpen}
                >
                  <ProfileIdentity user={user} apiBaseUrl={apiBaseUrl} size="md" alt={`${userDisplayName} profile`} />
                </button>
                {profileMenuOpen ? (
                  <div className="profile-dropdown" role="menu" aria-label={t("dashboard.profileMenu")}>
                    <div className="profile-dropdown-head">
                      <ProfileIdentity user={user} apiBaseUrl={apiBaseUrl} size="lg" alt={`${userDisplayName} profile`} />
                      <div className="min-w-0">
                        <strong>{userDisplayName}</strong>
                        <span>UID {user?.unique_user_id || t("dashboard.uidPending")}</span>
                        <small>{maskedEmail}</small>
                      </div>
                    </div>
                    <div className="profile-dropdown-grid">
                      <span>{t("dashboard.verification")} <b>{t("dashboard.level")} {verificationLevel}</b></span>
                      <span>{t("dashboard.tier")} <b>{user?.account_tier || t("dashboard.standard")}</b></span>
                      <span>{t("dashboard.security")} <b>{securityStatus}</b></span>
                    </div>
                    <button type="button" role="menuitem" onClick={() => { setProfileMenuOpen(false); setCurrentPage("profileAppearance"); }}>
                      <UserRound className="h-4 w-4" /> {t("dashboard.profileSettings")}
                    </button>
                    <button type="button" role="menuitem" onClick={() => { setProfileMenuOpen(false); setCurrentPage("settings"); }}>
                      <ShieldCheck className="h-4 w-4" /> {t("dashboard.securitySettings")}
                    </button>
                    <button type="button" role="menuitem" className="danger" onClick={() => { setProfileMenuOpen(false); logout(); }}>
                      <LogOut className="h-4 w-4" /> {t("dashboard.signOut")}
                    </button>
                  </div>
                ) : null}
              </div>
              <div className="min-w-0">
                <div className="text-sm font-semibold tracking-tight truncate gold-text">{t("dashboard.vaultTitle")}</div>
                <div className="text-xs text-gray-300 truncate">{t("dashboard.vaultSubtitle")}</div>
              </div>
            </div>

            <div className="flex items-center gap-2 sm:gap-3">
              <LanguageSwitcher compact />
              <div className="notification-menu">
                <button
                  type="button"
                  onClick={openNotifications}
                  className="notification-trigger"
                  aria-label={t("dashboard.openNotifications")}
                  aria-expanded={notificationOpen}
                >
                  <Bell size={18} className="icon-muted" />
                  {unreadNotificationCount > 0 ? (
                    <span className="notification-count">{unreadNotificationCount > 9 ? "9+" : unreadNotificationCount}</span>
                  ) : null}
                </button>
                {notificationOpen ? (
                  <div className="notification-tray" role="menu" aria-label={t("dashboard.notifications")}>
                    <div className="notification-tray-head">
                      <div>
                        <strong>{t("dashboard.notifications")}</strong>
                        <span>{t("dashboard.unread", { count: unreadNotificationCount })}</span>
                      </div>
                      <button type="button" onClick={() => setNotificationOpen(false)} aria-label={t("dashboard.closeNotifications")}>
                        <X size={15} />
                      </button>
                    </div>
                    <div className="notification-list">
                      {notificationLoading ? (
                        <div className="notification-empty">Loading...</div>
                      ) : notifications.length ? (
                        notifications.map((notification) => {
                          const unread = isUnreadNotification(notification);
                          return (
                            <button
                              key={notification.id}
                              type="button"
                              className={`notification-item ${unread ? "is-unread" : ""}`}
                              onClick={() => openNotification(notification)}
                              role="menuitem"
                            >
                              <span className="notification-dot" aria-hidden="true" />
                              <span className="notification-copy">
                                <strong>{notification.title || t("dashboard.notificationFallbackTitle")}</strong>
                                <small>{notification.message || t("dashboard.notificationFallbackMessage")}</small>
                              </span>
                              <time>{formatNotificationTime(notification.created_at)}</time>
                            </button>
                          );
                        })
                      ) : (
                        <div className="notification-empty">{t("dashboard.noNotifications")}</div>
                      )}
                    </div>
                  </div>
                ) : null}
              </div>
              <button
                type="button"
                onClick={() => setCurrentPage("settings")}
                className="rounded-lg border-0 bg-transparent p-0 text-inherit transition-colors duration-300 hover:text-auric-300 active:scale-95"
                aria-label="Open settings page"
              >
                <Settings size={18} className="icon-muted" />
              </button>
            </div>
          </header>

          <section className="home-campaign-card mb-4 campaign-hero-card" onClick={() => setCurrentPage("campaigns")}>
            <div className="campaign-hero-orbit" aria-hidden="true" />
            <div className="campaign-hero-copy">
              <span className="campaign-hero-kicker">{t("dashboard.liveGrowthEngine")}</span>
              <strong>{t("dashboard.runCampaign")}</strong>
              <p>{activeNews ? activeNews.title : t("dashboard.campaignFallback")}</p>
            </div>
            <div className="campaign-hero-metrics" aria-hidden="true">
              <span>AI</span>
              <small>{campaignNews.length ? activeNewsIndex + 1 : 0}/{campaignNews.length || 1}</small>
            </div>
          </section>

          <section className="home-portfolio-card mb-4 campaign-card">
            <div className="flex flex-col gap-4 sm:items-center sm:flex-row sm:justify-between">
              <div>
                <div className="label-muted">{t("dashboard.portfolioValue")}</div>
                <div className="balance-wrapper">
                  <div className="balance">
                    <AnimatedCounter end={portfolioValue} duration={0.8} />
                  </div>
                  <div className="balance-accent" />
                </div>
                <div className="flex items-center gap-2 mt-1 text-xs">
                  <div className="pill">
                    <ArrowUp size={12} className="arrow-animate" />
                    <span>+4.2% (24h)</span>
                  </div>
                  <div className="text-gray-400">- {t("dashboard.todayPnl")}</div>
                </div>
              </div>

              <div className="flex flex-col items-stretch gap-2 sm:items-end">
                <button type="button" onClick={() => setCurrentPage("addFunds")} className="w-full btn-gold sm:w-auto">{t("dashboard.addFunds")}</button>
                <DailyRewardCard
                  progress={rewardProgress}
                  loading={rewardLoading}
                  claiming={rewardBusy}
                  onClaim={claimReward}
                  onOpen={() => {
                    setRewardModalOpen(true);
                    loadRewardHistory();
                  }}
                />
              </div>
            </div>
          </section>

          <section className="home-features-card mb-4 campaign-card">
            <div className="feature-grid-header">
              <div>
                <span>{t("dashboard.superAppAccess")}</span>
                <h2>{t("dashboard.coreModules")}</h2>
              </div>
              <Sparkles size={17} aria-hidden="true" />
            </div>
            <div className="features-grid">
              {features.map((feature) => (
                <button
                  type="button"
                  key={feature.name}
                  className={`feature-card feature-${feature.tone}`}
                  onClick={() => openFeature(feature.name)}
                >
                  <div className="icon-wrap">
                    <img src={feature.image} alt={feature.name} className="feature-image" />
                    <feature.icon size={17} className="feature-lucide" aria-hidden="true" />
                  </div>
                  <div className="feature-name">{featureLabel(feature.name)}</div>
                </button>
              ))}
            </div>
          </section>

          <section className="market-intel-section">
            <div className="market-section-head">
              <div>
                <span>{t("dashboard.liveMarket")}</span>
                <h2>{t("dashboard.exchangeMarkets")}</h2>
              </div>
              <button type="button" className="market-add-main" onClick={() => setCurrentPage("market")}>
                <Plus size={14} aria-hidden="true" />
                {t("dashboard.addCrypto")}
              </button>
            </div>

            <div className="market-ticker" aria-label="Live market ticker">
              <div className="market-ticker-track">
                {[...marketAssets, ...marketAssets].map((asset, index) => (
                  <div className={`ticker-chip ticker-${asset.heat}`} key={`${asset.symbol}-${index}`}>
                    <strong>{asset.symbol}</strong>
                    <span>{asset.price}</span>
                    <em>{asset.change}</em>
                  </div>
                ))}
              </div>
            </div>

            <div className="home-exchange-card">
              <div className="exchange-toolbar">
                <div className="exchange-tabs">
                  {["Top", "Gainers", "Fav"].map((tab) => (
                    <button
                      type="button"
                      key={tab}
                      className={homeMarketFilter === tab ? "active" : ""}
                      onClick={() => setHomeMarketFilter(tab)}
                    >
                      {marketTabLabel(tab)}
                    </button>
                  ))}
                </div>
                <span className="live-market-badge">{t("dashboard.live")}</span>
              </div>

              <div className="exchange-table-head">
                <span>{t("dashboard.pair")}</span>
                <span>{t("dashboard.lastPrice")}</span>
                <span>24h</span>
              </div>

              <div className="exchange-market-list">
                {homeMarketPairs.map((pair) => {
                  const positive = pair.change24h >= 0;
                  return (
                    <button type="button" className="exchange-row" key={pair.pair} onClick={() => setCurrentPage("market")}>
                      <div className="exchange-pair">
                        <button
                          type="button"
                          className={`exchange-star ${pair.favorite ? "active" : ""}`}
                          onClick={(event) => {
                            event.stopPropagation();
                            toggleHomeMarketFavorite(pair.pair);
                          }}
                          aria-label={`Pin ${pair.pair}`}
                        >
                          <Star size={13} aria-hidden="true" />
                        </button>
                        <span>{pair.base}</span>
                        <small>/{pair.quote}</small>
                      </div>
                      <div className="exchange-price">
                        <strong>${formatMarketPrice(pair.last)}</strong>
                        <small>{t("dashboard.volume")} {pair.volume}</small>
                      </div>
                      <div className={`exchange-change ${positive ? "positive" : "negative"}`}>
                        {positive ? "+" : ""}{Number(pair.change24h).toFixed(2)}%
                      </div>
                      <div className="exchange-spark" aria-hidden="true">
                        {[34, 48, 42, 62, 54, 74, 66].map((height, index) => (
                          <span key={index} style={{ height: `${positive ? height : 92 - height}%` }} />
                        ))}
                      </div>
                    </button>
                  );
                })}
              </div>

              <div className="quick-add-crypto">
                <span>{t("dashboard.quickAdd")}</span>
                <div>
                  {["BTC", "ETH", "XRP", "SOL"].map((symbol) => (
                    <button type="button" key={symbol} onClick={() => setCurrentPage("market")}>
                      <Plus size={11} aria-hidden="true" />
                      {symbol}
                    </button>
                  ))}
                </div>
              </div>
            </div>

            <div className="market-ai-strip">
              <span>{t("dashboard.aiSignal")}</span>
              <strong>{t("dashboard.bullish")}</strong>
              <div>
                <i style={{ width: "87%" }} />
              </div>
            </div>
          </section>

          </div>
        </div>
      </div>

      <div className="bottom-nav-shell">
        <div className="w-full px-0">
          <nav className="bottom-nav bottom-nav-fixed">
            <NavItem
              icon={<img src={Image.earn} alt="Home" className="nav-image nav-image-home" />}
              label={t("dashboard.home")}
              active
              imageIcon
              onClick={() => setCurrentPage("home")}
            />
            <NavItem
              icon={<BarChart3 size={21} />}
              label={t("dashboard.market")}
              onClick={() => setCurrentPage("market")}
            />
            <NavItem icon={<Gem size={21} />} label={t("dashboard.trade")} onClick={() => setCurrentPage("trade")} />
            <NavItem icon={<Handshake size={21} />} label={t("dashboard.p2p")} onClick={() => setCurrentPage("p2pMarketplace")} />
            <NavItem icon={<Wallet size={21} />} label={t("dashboard.assets")} onClick={() => setCurrentPage("assets")} />
          </nav>
        </div>
      </div>

      {showAiQuickLaunch ? (
        <button
          type="button"
          onClick={() => setCurrentPage("aiAssistant")}
          className="fixed bottom-24 right-5 z-40 rounded-full border border-[#D4AF37]/60 bg-[#111827] px-4 py-2 text-xs font-semibold text-[#F3E8C8] shadow-lg"
        >
          {t("dashboard.aiAssistant")}
        </button>
      ) : null}

      {rewardModalOpen ? (
        <DailyRewardModal
          progress={rewardProgress}
          history={rewardHistory}
          lastReward={lastReward}
          busy={rewardBusy}
          onClose={() => setRewardModalOpen(false)}
          onClaim={claimReward}
          onOpenMystery={openMysteryBox}
          onRedeem={redeemReward}
        />
      ) : null}

      {showGiftcardChoice ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4">
          <div className="w-full max-w-md rounded-2xl border border-auric-300/35 bg-linear-to-br from-cosmic-900/95 to-cosmic-800/95 p-5 shadow-cosmic-glow backdrop-blur-xl sm:p-6">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">{t("dashboard.giftcardActions")}</h2>
                <p className="mt-2 text-sm text-violet-100/75">{t("dashboard.giftcardActionsDescription")}</p>
              </div>
              <button
                type="button"
                onClick={() => setShowGiftcardChoice(false)}
                className="rounded-lg border border-violet-300/30 bg-cosmic-900/65 p-2 text-violet-100/80 transition hover:border-auric-300/60 hover:text-auric-300"
              >
                <X className="h-4 w-4" aria-hidden="true" />
              </button>
            </div>

            <div className="mt-5 grid gap-3">
              <button
                type="button"
                onClick={() => {
                  setShowGiftcardChoice(false);
                  setCurrentPage("buyGiftcard");
                }}
                className="rounded-xl border border-auric-300/75 bg-linear-to-r from-auric-300 via-auric-400 to-auric-500 px-4 py-3 text-base font-semibold text-cosmic-900 transition-all duration-300 hover:scale-[1.01] hover:shadow-button-glow active:scale-[0.99]"
              >
                {t("dashboard.buyGiftcard")}
              </button>
              <button
                type="button"
                onClick={() => {
                  setShowGiftcardChoice(false);
                  setCurrentPage("giftcard");
                }}
                className="rounded-xl border border-violet-300/35 bg-cosmic-900/65 px-4 py-3 text-base font-semibold text-violet-50 transition-all duration-300 hover:-translate-y-0.5 hover:border-auric-300/65 hover:text-auric-200"
              >
                {t("dashboard.sellGiftcard")}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}

function NavItem({ icon, label, active, imageIcon = false, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`nav-item border-0 bg-transparent ${active ? "active" : ""}`}
    >
      <div className={`nav-icon ${imageIcon ? "image-icon" : ""}`}>{icon}</div>
      <div className="nav-label">{label}</div>
    </button>
  );
}



