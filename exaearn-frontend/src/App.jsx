import React, { useEffect, useMemo, useState } from "react";
import { ArrowUp, BarChart3, Bell, Clock3, Gem, Handshake, Settings, Wallet, X } from "lucide-react";
import Image from "./assets/Image";
import Game from "./Game/Game";
import Token from "./Token/Token";
import Giftcard from "./Giftcard/Giftcard";
import BuyGiftcard from "./BuyGiftcard/BuyGiftcard";
import NFTMarketplace from "./NFTMarketplace/NFTMarketplace";
import Campaigns from "./Campaigns/Campaigns";
import Agriculture from "./pages/Agriculture/Agriculture";
import AcquireShare from "./pages/Agriculture/AcquireShare";
import SubscriptionPage from "./pages/Agriculture/SubscriptionPage";
import ExaScout from "./pages/ExaScout/ExaScout";
import CreatePlayerProfile from "./pages/ExaScout/CreatePlayerProfile";
import ScoutTalentPage from "./pages/ExaScout/ScoutTalentPage";
import ViewPlayerProfile from "./pages/ExaScout/ViewPlayerProfile";
import HighlightPreviewPage from "./pages/ExaScout/HighlightPreviewPage";
import InitiateContractPage from "./pages/ExaScout/InitiateContractPage";
import EdTech from "./pages/EdTech/EdTech";
import BecomeEducator from "./pages/EdTech/BecomeEducator";
import ApplyScholarship from "./pages/EdTech/ApplyScholarship";
import CourseUpload from "./pages/EdTech/CourseUpload";
import InstructorWorkshop from "./pages/EdTech/InstructorWorkshop";
import InstructorDashboard from "./pages/EdTech/InstructorDashboard";
import StartLearningEarn from "./pages/EdTech/StartLearningEarn";
import UploadedCourses from "./pages/EdTech/UploadedCourses";
import StakingDashboard from "./pages/staking/StakingDashboard.jsx";
import P2PMarketplace from "./pages/P2PMarketplace/P2PMarketplace";
import Transactions from "./pages/Transactions/Transactions";
import Rewards from "./pages/Rewards/Rewards";
import Assets from "./pages/Assets/Assets";
import Crowdfunding from "./pages/Crowdfunding/Crowdfunding";
import CreateCampaignPage from "./pages/Crowdfunding/CreateCampaignPage";
import SupportCampaignPage from "./pages/Crowdfunding/SupportCampaignPage";
import ViewCampaignPage from "./pages/Crowdfunding/ViewCampaignPage";
import Login from "./pages/auth/Login";
import Register from "./pages/auth/Register";
import ForgotPassword from "./pages/auth/ForgotPassword";
import NeedHelp from "./pages/auth/NeedHelp";
import ForgotAccountAppeal from "./pages/auth/ForgotAccountAppeal";
import ResetPassword from "./pages/auth/ResetPassword";
import AccountRecoveryEntry from "./pages/auth/AccountRecoveryEntry";
import SplashScreen from "./components/SplashScreen";
import ProfilePage from "./pages/Profile/ProfilePage";
import SettingsPage from "./pages/Settings/SettingsPage";
import LanguageRegionPage from "./pages/Settings/LanguageRegionPage";
import CurrencyPreferencePage from "./pages/Settings/CurrencyPreferencePage";
import MarketAnalyticsPage from "./pages/Settings/MarketAnalyticsPage";
import PaymentCurrencyPage from "./pages/Settings/PaymentCurrencyPage";
import PaymentMethodsPage from "./pages/Settings/PaymentMethodsPage";
import AddFundsPage from "./pages/AddFunds/AddFundsPage";
import Send from "./pages/Send/Send";
import Swap from "./pages/Swap/Swap";
import Withdraw from "./pages/Withdraw/Withdraw";
import MorePage from "./pages/More/MorePage";
import SupportCenter from "./pages/Support/SupportCenter";
import LiveSupportChat from "./pages/Support/LiveSupportChat";
import HelpSupportCenter from "./pages/Support/HelpSupportCenter";
import KYCVerification from "./pages/KYC/KYCVerification";
import ReferralProgram from "./pages/Referral/ReferralProgram";
import NotificationSettings from "./pages/Notifications/NotificationSettings";
import AboutExaEarn from "./pages/About/AboutExaEarn";
import ChangePassword from "./pages/Security/ChangePassword";
import LoginDevices from "./pages/Security/LoginDevices";
import Market from "./pages/market/Market";
import CryptoMarkets from "./pages/market/CryptoMarkets";
import Trade from "./pages/trade/Trade";
import Futures from "./pages/futures/Futures";
import Options from "./pages/futures/Options";
import SmartMoney from "./pages/futures/SmartMoney";
import { useAuth } from "./context/AuthContext";
import newsData from "./data/news.json";
import "./App.css";

function AnimatedCounter({ end = 125480, duration = 0.8 }) {
  const [count, setCount] = useState(0);

  useEffect(() => {
    let startTime;
    const animate = (currentTime) => {
      if (!startTime) startTime = currentTime;
      const elapsed = (currentTime - startTime) / 1000;
      const progress = Math.min(elapsed / duration, 1);
      setCount(Math.floor(progress * end));

      if (progress < 1) {
        requestAnimationFrame(animate);
      }
    };

    requestAnimationFrame(animate);
  }, [end, duration]);

  return `$${count.toLocaleString()}`;
}

const features = [
  { name: "AgriTech", image: Image.agriculture },
  { name: "EdTech", image: Image.edu },
  { name: "Crowdfund", image: Image.crowdfund },
  { name: "Giftcards", image: Image.giftcard },
  { name: "Games", image: Image.games },
  { name: "Stake XRP", image: Image.xrp },
  { name: "NFT Marketplace", image: Image.nft },
  { name: "Sports Talent Pool", image: Image.sports },
  { name: "Customer Support", image: Image.more },
  { name: "Token", image: Image.token },
  { name: "Transactions", image: Image.transactions },
  { name: "More", image: Image.more },
];

export default function App() {
  const { user, setUser } = useAuth();
  const [isInitialLoading, setIsInitialLoading] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [authPage, setAuthPage] = useState("login");
  const [showSplash, setShowSplash] = useState(false);
  const [currentPage, setCurrentPage] = useState("home");
  const [showGiftcardChoice, setShowGiftcardChoice] = useState(false);
  const [activeNewsIndex, setActiveNewsIndex] = useState(0);
  const [selectedCourseUpload, setSelectedCourseUpload] = useState("web3-fundamentals");
  const [selectedCampaignId, setSelectedCampaignId] = useState("cmp-1");
  const [selectedPlayerId, setSelectedPlayerId] = useState("st-1");
  const [sportsReturnPage, setSportsReturnPage] = useState("exascout");
  const [referralReturnPage, setReferralReturnPage] = useState("home");
  const [helpReturnPage, setHelpReturnPage] = useState("home");
  const [chatReturnPage, setChatReturnPage] = useState("supportCenter");

  const campaignNews = useMemo(() => {
    return [...newsData].sort((a, b) => {
      if (a.featured !== b.featured) {
        return a.featured ? -1 : 1;
      }
      return new Date(b.date).getTime() - new Date(a.date).getTime();
    });
  }, []);

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

  if (isInitialLoading) {
    return <SplashScreen />;
  }

  if (!isAuthenticated) {
    if (authPage === "register") {
      return <Register onLogin={() => setAuthPage("login")} />;
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
            setIsAuthenticated(true);
            setShowSplash(false);
          }, 900);
        }}
      />
    );
  }

  if (showSplash) {
    return (
      <div className="min-h-screen w-full bg-gradient-to-br from-black via-[#140a24] to-[#220c3d] text-white">
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
        onOpenP2P={() => setCurrentPage("p2pMarketplace")}
        onOpenCrypto={() => setCurrentPage("cryptoMarkets")}
      />
    );
  }
  if (currentPage === "cryptoMarkets") {
    return <CryptoMarkets onBack={() => setCurrentPage("market")} onOpenTrade={() => setCurrentPage("trade")} />;
  }
  if (currentPage === "trade") {
    return <Trade onBack={() => setCurrentPage("home")} />;
  }
  if (currentPage === "futures") {
    return (
      <Futures
        onBack={() => setCurrentPage("home")}
        onOpenOptions={() => setCurrentPage("options")}
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
        onOpenAcquireShare={() => setCurrentPage("acquireShare")}
        onOpenSubscribe={() => setCurrentPage("agriSubscription")}
      />
    );
  }
  if (currentPage === "acquireShare") {
    return <AcquireShare onBack={() => setCurrentPage("agriculture")} />;
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
    return <P2PMarketplace onBack={() => setCurrentPage("home")} />;
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
        user={user}
        onLogout={() => {
          setUser(null);
          setIsAuthenticated(false);
          setAuthPage("login");
          setCurrentPage("home");
        }}
      />
    );
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
    return <LoginDevices onBack={() => setCurrentPage("profile")} />;
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
    return <Withdraw onBack={() => setCurrentPage("assets")} />;
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
      />
    );
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
  const userInitials = userDisplayName
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() || "")
    .join("") || "EX";

  return (
    <div className="home-screen text-white exa-bg app-shell">
      <div className="home-scroll-area">
        <div className="container w-full max-w-sm px-3 pt-4 pb-6 mx-auto sm:max-w-lg sm:px-4 sm:pt-6 md:max-w-2xl lg:max-w-4xl xl:max-w-5xl">
          <div className="p-4 shadow-xl glass-card rounded-3xl sm:p-5">
          <header className="flex items-center justify-between mb-4 sm:mb-6 campaign-card">
            <div className="flex items-center min-w-0 gap-3 sm:gap-4">
              <button
                type="button"
                onClick={() => setCurrentPage("profile")}
                className="avatar-accent"
                aria-label="Open profile page"
              >
                {user?.picture ? (
                  <img src={user.picture} alt={`${userDisplayName} avatar`} className="avatar-image" />
                ) : (
                  <span className="text-xs font-semibold text-violet-100">{userInitials}</span>
                )}
              </button>
              <div className="min-w-0">
                <div className="text-sm font-semibold tracking-tight truncate gold-text">0x4a...0c51</div>
                <div className="text-xs text-gray-300 truncate">Wallet ID - 41033651</div>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <Bell size={18} className="icon-muted" />
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

          <section className="mb-6 campaign-card">
            <div className="flex flex-col gap-4 sm:items-center sm:flex-row sm:justify-between">
              <div>
                <div className="label-muted">Est. Portfolio Value</div>
                <div className="balance-wrapper">
                  <div className="balance">
                    <AnimatedCounter end={125480} duration={0.8} />
                  </div>
                  <div className="balance-accent" />
                </div>
                <div className="flex items-center gap-2 mt-1 text-xs">
                  <div className="pill">
                    <ArrowUp size={12} className="arrow-animate" />
                    <span>+4.2% (24h)</span>
                  </div>
                  <div className="text-gray-400">- Today&apos;s PNL</div>
                </div>
              </div>

              <div className="flex flex-col items-stretch gap-2 sm:items-end">
                <button type="button" onClick={() => setCurrentPage("addFunds")} className="w-full btn-gold sm:w-auto">Add Funds</button>
              </div>
            </div>
          </section>

          <section className="mb-6 campaign-card">
            <div className="features-grid">
              {features.map((feature) => (
                <div
                  key={feature.name}
                  className="feature-card"
                  onClick={
                    feature.name === "Games"
                      ? () => setCurrentPage("game")
                      : feature.name === "Token"
                      ? () => setCurrentPage("token")
                        : feature.name === "Giftcards"
                          ? () => setShowGiftcardChoice(true)
                          : feature.name === "NFT Marketplace"
                            ? () => setCurrentPage("nftMarketplace")
                            : feature.name === "Crowdfund"
                              ? () => setCurrentPage("crowdfunding")
                            : feature.name === "AgriTech"
                              ? () => setCurrentPage("agriculture")
                              : feature.name === "Sports Talent Pool"
                                ? () => setCurrentPage("exascout")
                                : feature.name === "EdTech"
                                  ? () => setCurrentPage("edtech")
                                : feature.name === "Stake XRP"
                                    ? () => setCurrentPage("staking")
                                    : feature.name === "More"
                                      ? () => setCurrentPage("more")
                                      : feature.name === "Customer Support"
                                        ? () => {
                                            setHelpReturnPage("home");
                                            setCurrentPage("helpSupportCenter");
                                          }
                                        : feature.name === "Transactions"
                                          ? () => setCurrentPage("transactions")
                                : undefined
                  }
                >
                  <div className="icon-wrap">
                    <img src={feature.image} alt={feature.name} className="feature-image" />
                  </div>
                  <div className="feature-name">{feature.name}</div>
                </div>
              ))}
            </div>
          </section>

          <section className="mb-6 campaign-card" onClick={() => setCurrentPage("campaigns")}>
            <div className="campaign-card">
              <div className="flex items-center justify-between gap-4">
                <div className="flex items-center flex-1 gap-4">
                  <div className="campaign-icon-wrap">
                    <img src={Image.campaigns} alt="Campaigns" className="campaign-image" />
                  </div>
                  <div className="flex-1">
                    <div className="campaign-title">Run Campaign</div>
                    <div className="campaign-desc">
                      {activeNews ? `${activeNews.category} - ${activeNews.title}` : "Reach your community with targeted adverts"}
                    </div>
                    <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-cosmic-900/60">
                      <div
                        className="h-full rounded-full bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 transition-all duration-500"
                        style={{ width: `${campaignNews.length ? ((activeNewsIndex + 1) / campaignNews.length) * 100 : 0}%` }}
                      />
                    </div>
                  </div>
                </div>
                <div className="campaign-arrow" />
              </div>
            </div>
          </section>

          </div>
        </div>
      </div>

      <div className="bottom-nav-shell">
        <div className="container w-full max-w-sm px-3 mx-auto sm:max-w-lg sm:px-4 md:max-w-2xl lg:max-w-4xl xl:max-w-5xl">
          <nav className="bottom-nav bottom-nav-fixed">
            <NavItem
              icon={<img src={Image.earn} alt="Home" className="nav-image nav-image-home" />}
              label="Home"
              active
              imageIcon
              onClick={() => setCurrentPage("home")}
            />
            <NavItem
              icon={<BarChart3 size={21} />}
              label="Market"
              onClick={() => setCurrentPage("market")}
            />
            <NavItem icon={<Gem size={21} />} label="Trade" onClick={() => setCurrentPage("trade")} />
            <NavItem icon={<Handshake size={21} />} label="P2P" onClick={() => setCurrentPage("p2pMarketplace")} />
            <NavItem icon={<Wallet size={21} />} label="Assets" onClick={() => setCurrentPage("assets")} />
          </nav>
        </div>
      </div>

      {showGiftcardChoice ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4">
          <div className="w-full max-w-md rounded-2xl border border-auric-300/35 bg-gradient-to-br from-cosmic-900/95 to-cosmic-800/95 p-5 shadow-cosmic-glow backdrop-blur-xl sm:p-6">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">Giftcard Actions</h2>
                <p className="mt-2 text-sm text-violet-100/75">Choose what you want to do with giftcards.</p>
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
                className="rounded-xl border border-auric-300/75 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 px-4 py-3 text-base font-semibold text-cosmic-900 transition-all duration-300 hover:scale-[1.01] hover:shadow-button-glow active:scale-[0.99]"
              >
                Buy Giftcard
              </button>
              <button
                type="button"
                onClick={() => {
                  setShowGiftcardChoice(false);
                  setCurrentPage("giftcard");
                }}
                className="rounded-xl border border-violet-300/35 bg-cosmic-900/65 px-4 py-3 text-base font-semibold text-violet-50 transition-all duration-300 hover:-translate-y-0.5 hover:border-auric-300/65 hover:text-auric-200"
              >
                Sell Giftcard
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
