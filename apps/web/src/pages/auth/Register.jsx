import React, { useMemo, useState } from "react";
import {
  BarChart3,
  Check,
  ChevronRight,
  Coins,
  Eye,
  EyeOff,
  Gamepad2,
  Gem,
  Gift,
  GraduationCap,
  HandCoins,
  Layers,
  Leaf,
  LockKeyhole,
  Network,
  Rocket,
  ShieldCheck,
  Sparkles,
  Trophy,
  Wallet,
} from "lucide-react";
import Image from "../../assets/Image";
import LanguageSwitcher from "../../components/language/LanguageSwitcher.jsx";
import { useAuth } from "../../context/AuthContext";
import { useLanguage } from "../../context/LanguageContext.jsx";
import "./Register.css";

const ONBOARDING_STORAGE_KEY = "exaearn_onboarding_preferences";

const interestOptions = [
  { id: "trading", label: "Trading & Analytics", icon: BarChart3, text: "Market tools, signals and digital asset opportunities." },
  { id: "giftcards", label: "Giftcard Exchange", icon: Gift, text: "Secure conversion flows for supported giftcards." },
  { id: "passive", label: "Passive Earnings", icon: Coins, text: "Staking and reward paths built for steady discovery." },
  { id: "agritech", label: "Agritech Investment", icon: Leaf, text: "Real-world agriculture powered by community participation." },
  { id: "nft", label: "NFT Marketplace", icon: Gem, text: "Digital ownership, creator assets and marketplace access." },
  { id: "education", label: "Learning Web3", icon: GraduationCap, text: "Guided finance and blockchain education inside the app." },
  { id: "gaming", label: "Gaming Rewards", icon: Gamepad2, text: "Play, compete and earn ecosystem rewards." },
  { id: "crowdfunding", label: "Crowdfunding Opportunities", icon: HandCoins, text: "Support innovative projects and community ideas." },
];

const experienceOptions = [
  { id: "beginner", label: "Beginner", description: "Perfect. ExaEarn is designed to simplify Web3 for everyone." },
  { id: "learning", label: "Learning", description: "Great timing. ExaEarn will help you connect concepts to real product experiences." },
  { id: "intermediate", label: "Intermediate", description: "Excellent. You will find guided tools plus deeper market and ecosystem modules." },
  { id: "advanced", label: "Advanced", description: "Great. Advanced trading tools and ecosystem features will be available for you." },
];

const goalOptions = [
  { id: "wealth", label: "Build Wealth", icon: Trophy },
  { id: "passive-income", label: "Earn Passive Income", icon: Coins },
  { id: "trade", label: "Trade Digital Assets", icon: BarChart3 },
  { id: "convert", label: "Convert Giftcards", icon: Gift },
  { id: "learn", label: "Learn Blockchain", icon: GraduationCap },
  { id: "invest", label: "Invest in Real Opportunities", icon: Leaf },
  { id: "discover", label: "Discover New Technologies", icon: Sparkles },
  { id: "community", label: "Join Web3 Communities", icon: Network },
];

const featureCards = [
  {
    id: "trading",
    label: "Trading",
    icon: BarChart3,
    description: "Access market opportunities, analytics and trading tools built for both beginners and professionals.",
  },
  { id: "giftcards", label: "Giftcards", icon: Gift, description: "Convert supported giftcards securely and efficiently." },
  { id: "staking", label: "Staking", icon: Coins, description: "Earn rewards through supported staking opportunities." },
  { id: "agritech", label: "Agritech", icon: Leaf, description: "Technology-powered agricultural opportunities backed by community participation." },
  { id: "crowdfunding", label: "Crowdfunding", icon: HandCoins, description: "Support innovative projects and community-driven ideas." },
  { id: "edtech", label: "EdTech", icon: GraduationCap, description: "Learn blockchain, finance and emerging technologies directly inside ExaEarn." },
  { id: "nft", label: "NFT Marketplace", icon: Gem, description: "Explore digital ownership and creator ecosystems." },
];

const personalizationOptions = [
  { id: "simple", label: "Simple Mode", text: "A clean dashboard with the essentials up front." },
  { id: "trading", label: "Trading Focused", text: "Markets, analytics and exchange actions stay within reach." },
  { id: "investment", label: "Investment Focused", text: "Agritech, staking and crowdfunding recommendations are prioritized." },
  { id: "full", label: "Full Ecosystem Access", text: "Every ExaEarn module is visible from day one." },
  { id: "guided", label: "Beginner Guided Mode", text: "Education, safety tips and simple explanations appear as you explore." },
];

const securityItems = [
  "Secure Wallet Protection",
  "Strong Authentication Systems",
  "Encrypted Transactions",
  "Scam Awareness Protection",
  "Privacy Focused Infrastructure",
];

const onboardingSteps = ["Welcome", "Interests", "Experience", "Goals", "Ecosystem", "Personalize", "Security", "Ready"];

function toggleValue(values, id) {
  return values.includes(id) ? values.filter((value) => value !== id) : [...values, id];
}

function Register({ onLogin, onSuccess }) {
  const [step, setStep] = useState(0);
  const [fullName, setFullName] = useState("");
  const [email, setEmail] = useState("");
  const [referralCode, setReferralCode] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [selectedInterests, setSelectedInterests] = useState([]);
  const [experienceLevel, setExperienceLevel] = useState("beginner");
  const [selectedGoals, setSelectedGoals] = useState([]);
  const [expandedFeature, setExpandedFeature] = useState("trading");
  const [personalization, setPersonalization] = useState("guided");
  const { register, checkAccountAvailability, authLoading, authError } = useAuth();
  const { t } = useLanguage();

  const passwordsMatch = useMemo(() => {
    if (!password || !confirmPassword) {
      return true;
    }
    return password === confirmPassword;
  }, [password, confirmPassword]);

  const selectedInterestLabels = useMemo(() => {
    const labels = interestOptions
      .filter((option) => selectedInterests.includes(option.id))
      .map((option) => option.label.replace(" & Analytics", "").replace(" Exchange", ""));
    return labels.length ? labels : ["Web3 Education", "Secure Wallet System", "Token Ecosystem"];
  }, [selectedInterests]);

  const onboardingStep = Math.max(0, step - 1);
  const progress = step > 0 ? ((onboardingStep + 1) / onboardingSteps.length) * 100 : 0;

  const savePreferences = () => {
    try {
      localStorage.setItem(
        ONBOARDING_STORAGE_KEY,
        JSON.stringify({
          interests: selectedInterests,
          experienceLevel,
          goals: selectedGoals,
          personalization,
          completedAt: new Date().toISOString(),
        })
      );
    } catch {
      // Preference persistence is best-effort.
    }
  };

  const goToStep = (nextStep) => {
    setStep(Math.max(0, Math.min(onboardingSteps.length, nextStep)));
  };

  const skipOnboarding = () => {
    completeRegistration();
  };

  const handleFormSubmit = async (event) => {
    event.preventDefault();
    setIsSubmitted(true);
    if (!passwordsMatch) {
      return;
    }

    const result = await checkAccountAvailability({
      name: fullName,
      email,
      password,
      passwordConfirmation: confirmPassword,
      referralCode,
      validateCredentials: true,
    });
    if (!result.success || result.exists) {
      return;
    }

    setStep(1);
  };

  const completeRegistration = async () => {
    savePreferences();
    const result = await register({
      name: fullName,
      email,
      password,
      passwordConfirmation: confirmPassword,
      referralCode,
    });

    if (result.success && onSuccess) {
      onSuccess();
    }
  };

  const activeExperience = experienceOptions.find((option) => option.id === experienceLevel);

  return (
    <div className="register-onboarding-shell">
      <div className="register-language-switcher"><LanguageSwitcher compact /></div>
      <div className="onboarding-chain-bg" aria-hidden="true">
        {Array.from({ length: 18 }).map((_, index) => (
          <span key={index} style={{ "--i": index }} />
        ))}
      </div>

      <section className="onboarding-modal" aria-label="ExaEarn account creation onboarding">
        {step > 0 ? (
          <div className="onboarding-progress-wrap">
            <div className="onboarding-progress-top">
              <span>{onboardingSteps[onboardingStep]}</span>
              <span>{onboardingStep + 1}/{onboardingSteps.length}</span>
            </div>
            <div className="onboarding-progress-track">
              <i style={{ width: `${progress}%` }} />
            </div>
          </div>
        ) : null}

        <div className="onboarding-stage" key={step}>
          {step === 0 ? (
            <AccountForm
              fullName={fullName}
              setFullName={setFullName}
              email={email}
              setEmail={setEmail}
              referralCode={referralCode}
              setReferralCode={setReferralCode}
              password={password}
              setPassword={setPassword}
              confirmPassword={confirmPassword}
              setConfirmPassword={setConfirmPassword}
              showPassword={showPassword}
              setShowPassword={setShowPassword}
              showConfirmPassword={showConfirmPassword}
              setShowConfirmPassword={setShowConfirmPassword}
              passwordsMatch={passwordsMatch}
              isSubmitted={isSubmitted}
              authLoading={authLoading}
              authError={authError}
              onSubmit={handleFormSubmit}
              onLogin={onLogin}
              t={t}
            />
          ) : null}

          {step === 1 ? (
            <WelcomeScreen onStart={() => goToStep(2)} onExplore={() => goToStep(5)} />
          ) : null}

          {step === 2 ? (
            <div>
              <ScreenHeading
                eyebrow="Personalization Scan"
                title="What brings you to ExaEarn?"
                description="Choose the areas you're most interested in. Your dashboard experience will be personalized for you."
              />
              <div className="onboarding-card-grid interests-grid">
                {interestOptions.map((option) => (
                  <SelectableCard
                    key={option.id}
                    option={option}
                    active={selectedInterests.includes(option.id)}
                    onClick={() => setSelectedInterests((values) => toggleValue(values, option.id))}
                  />
                ))}
              </div>
            </div>
          ) : null}

          {step === 3 ? (
            <div>
              <ScreenHeading eyebrow="Experience Level" title="How experienced are you with crypto and digital finance?" />
              <div className="experience-selector" role="list">
                {experienceOptions.map((option) => (
                  <button
                    type="button"
                    key={option.id}
                    className={experienceLevel === option.id ? "active" : ""}
                    onClick={() => setExperienceLevel(option.id)}
                  >
                    {option.label}
                  </button>
                ))}
              </div>
              <div className="dynamic-insight">
                <Sparkles size={18} aria-hidden="true" />
                <p>{activeExperience?.description}</p>
              </div>
            </div>
          ) : null}

          {step === 4 ? (
            <div>
              <ScreenHeading eyebrow="Goal Discovery" title="What would you like to achieve with ExaEarn?" />
              <div className="goal-cloud">
                {goalOptions.map((option) => {
                  const Icon = option.icon;
                  const active = selectedGoals.includes(option.id);
                  return (
                    <button
                      type="button"
                      key={option.id}
                      className={active ? "active" : ""}
                      onClick={() => setSelectedGoals((values) => toggleValue(values, option.id))}
                    >
                      <Icon size={17} aria-hidden="true" />
                      {option.label}
                    </button>
                  );
                })}
              </div>
            </div>
          ) : null}

          {step === 5 ? (
            <div>
              <ScreenHeading
                eyebrow="Ecosystem Briefing"
                title="Did you know ExaEarn combines multiple ecosystems into one platform?"
                description="Open a module to learn how trading, rewards, education and real-world opportunities connect."
              />
              <div className="feature-briefing-grid">
                {featureCards.map((feature) => {
                  const Icon = feature.icon;
                  const active = expandedFeature === feature.id;
                  return (
                    <button
                      type="button"
                      key={feature.id}
                      className={active ? "active" : ""}
                      onClick={() => setExpandedFeature(active ? "" : feature.id)}
                    >
                      <span className="feature-orb"><Icon size={18} aria-hidden="true" /></span>
                      <strong>{feature.label}</strong>
                      <small>{active ? feature.description : "Learn More"}</small>
                    </button>
                  );
                })}
              </div>
            </div>
          ) : null}

          {step === 6 ? (
            <div>
              <ScreenHeading
                eyebrow="Smart Personalization"
                title="How would you like your ExaEarn experience personalized?"
                description="Your dashboard layout and recommendations will adapt to your preferences."
              />
              <div className="personalization-list">
                {personalizationOptions.map((option) => (
                  <button
                    type="button"
                    key={option.id}
                    className={personalization === option.id ? "active" : ""}
                    onClick={() => setPersonalization(option.id)}
                  >
                    <span>{option.label}</span>
                    <small>{option.text}</small>
                  </button>
                ))}
              </div>
            </div>
          ) : null}

          {step === 7 ? (
            <div>
              <ScreenHeading
                eyebrow="Security & Trust"
                title="Security Comes First"
                description="ExaEarn is designed with modern security practices to help protect user activity and digital assets."
              />
              <div className="security-panel">
                <div className="security-core" aria-hidden="true">
                  <ShieldCheck size={34} />
                  <span />
                </div>
                <div className="security-checklist">
                  {securityItems.map((item) => (
                    <p key={item}>
                      <Check size={16} aria-hidden="true" />
                      {item}
                    </p>
                  ))}
                </div>
              </div>
            </div>
          ) : null}

          {step === 8 ? (
            <div>
              <ScreenHeading eyebrow="Dashboard Ready" title="You're Ready to Explore ExaEarn" />
              <div className="ready-preview">
                <div className="success-ring" aria-hidden="true">
                  <Check size={28} />
                </div>
                <p>
                  Your experience has been optimized for {selectedInterestLabels.slice(0, 3).join(", ")}.
                </p>
                <div className="dashboard-preview" aria-hidden="true">
                  <span />
                  <span />
                  <span />
                  <span />
                </div>
              </div>
              {authError ? <p className="auth-error">{authError}</p> : null}
              <div className="ready-actions">
                <button type="button" className="onboarding-primary" onClick={completeRegistration} disabled={authLoading}>
                  {authLoading ? "Creating Account..." : "Enter Dashboard"}
                </button>
                <button type="button" className="onboarding-secondary" onClick={() => goToStep(5)} disabled={authLoading}>
                  Explore Features
                </button>
                <button type="button" className="onboarding-secondary" onClick={completeRegistration} disabled={authLoading}>
                  Continue Setup
                </button>
              </div>
            </div>
          ) : null}
        </div>

        {step > 0 && step < 8 ? (
          <div className="onboarding-nav">
            {step > 1 ? (
              <button type="button" className="onboarding-secondary" onClick={() => goToStep(step - 1)}>
                Back
              </button>
            ) : (
              <button type="button" className="onboarding-secondary" onClick={() => goToStep(0)}>
                Back to Form
              </button>
            )}
            <button type="button" className="onboarding-skip-action" onClick={skipOnboarding}>
              Skip onboarding
            </button>
            <button type="button" className="onboarding-primary" onClick={() => goToStep(step + 1)}>
              Continue
              <ChevronRight size={17} aria-hidden="true" />
            </button>
          </div>
        ) : null}

        {step === 8 ? (
          <button type="button" className="onboarding-back-link" onClick={() => goToStep(7)} disabled={authLoading}>
            Back to security
          </button>
        ) : null}
      </section>
    </div>
  );
}

function WelcomeScreen({ onStart, onExplore }) {
  return (
    <div className="welcome-screen">
      <div className="logo-orbit" aria-hidden="true">
        <span />
        <img src={Image.earn} alt="" />
      </div>
      <ScreenHeading
        eyebrow="ExaEarn Genesis"
        title="Welcome to ExaEarn"
        description="Where Digital Finance, Real-World Opportunities and Web3 Innovation Meet."
      />
      <p className="welcome-description">
        ExaEarn combines trading, rewards, education, agriculture, crowdfunding and blockchain-powered opportunities into one intelligent ecosystem.
      </p>
      <div className="welcome-actions">
        <button type="button" className="onboarding-primary" onClick={onStart}>
          Get Started
          <Rocket size={17} aria-hidden="true" />
        </button>
        <button type="button" className="onboarding-secondary" onClick={onExplore}>
          Explore Platform
          <Layers size={17} aria-hidden="true" />
        </button>
      </div>
    </div>
  );
}

function ScreenHeading({ eyebrow, title, description }) {
  return (
    <div className="screen-heading">
      <span>{eyebrow}</span>
      <h1>{title}</h1>
      {description ? <p>{description}</p> : null}
    </div>
  );
}

function SelectableCard({ option, active, onClick }) {
  const Icon = option.icon;
  return (
    <button type="button" className={active ? "selectable-card active" : "selectable-card"} onClick={onClick}>
      <span className="selectable-icon">
        <Icon size={19} aria-hidden="true" />
      </span>
      <strong>{option.label}</strong>
      <small>{option.text}</small>
    </button>
  );
}

function AccountForm({
  fullName,
  setFullName,
  email,
  setEmail,
  referralCode,
  setReferralCode,
  password,
  setPassword,
  confirmPassword,
  setConfirmPassword,
  showPassword,
  setShowPassword,
  showConfirmPassword,
  setShowConfirmPassword,
  passwordsMatch,
  isSubmitted,
  authLoading,
  authError,
  onSubmit,
  onLogin,
  t,
}) {
  const strongPasswordPattern = "^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^\\w\\s]).{10,}$";

  return (
    <div className="account-setup-screen">
      <ScreenHeading
        eyebrow={t("auth.registerEyebrow")}
        title={t("auth.registerTitle")}
        description={t("auth.registerDescription")}
      />
      <form onSubmit={onSubmit} className="premium-register-form">
        <label>
          <span>{t("auth.fullName")}</span>
          <input type="text" value={fullName} onChange={(event) => setFullName(event.target.value)} placeholder={t("auth.fullNamePlaceholder")} required />
        </label>

        <label>
          <span>{t("auth.emailAddress")}</span>
          <input type="email" value={email} onChange={(event) => setEmail(event.target.value)} placeholder="you@exaearn.io" required />
        </label>

        <label>
          <span>{t("auth.referralCode")} <em>{t("common.optional")}</em></span>
          <input type="text" value={referralCode} onChange={(event) => setReferralCode(event.target.value)} placeholder={t("auth.referralPlaceholder")} />
        </label>

        <label>
          <span>{t("auth.password")}</span>
          <div className="password-field">
            <LockKeyhole size={16} aria-hidden="true" />
            <input
              type={showPassword ? "text" : "password"}
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              placeholder="********"
              minLength={10}
              pattern={strongPasswordPattern}
              title={t("auth.passwordTitle")}
              required
            />
            <button type="button" onClick={() => setShowPassword((prev) => !prev)} aria-label={showPassword ? t("auth.hidePassword") : t("auth.showPassword")}>
              {showPassword ? <EyeOff size={17} aria-hidden="true" /> : <Eye size={17} aria-hidden="true" />}
            </button>
          </div>
        </label>

        <label>
          <span>{t("auth.confirmPassword")}</span>
          <div className="password-field">
            <LockKeyhole size={16} aria-hidden="true" />
            <input
              type={showConfirmPassword ? "text" : "password"}
              value={confirmPassword}
              onChange={(event) => setConfirmPassword(event.target.value)}
              placeholder="********"
              minLength={10}
              pattern={strongPasswordPattern}
              title={t("auth.passwordTitle")}
              required
            />
            <button
              type="button"
              onClick={() => setShowConfirmPassword((prev) => !prev)}
              aria-label={showConfirmPassword ? t("auth.hidePassword") : t("auth.showPassword")}
            >
              {showConfirmPassword ? <EyeOff size={17} aria-hidden="true" /> : <Eye size={17} aria-hidden="true" />}
            </button>
          </div>
          <small className={isSubmitted && !passwordsMatch ? "form-error" : ""}>
            {isSubmitted && !passwordsMatch ? t("auth.passwordMismatch") : t("auth.passwordHelp")}
          </small>
        </label>

        <button type="submit" className="onboarding-primary submit-account" disabled={authLoading}>
          {authLoading ? t("auth.creating") : t("auth.createAccount")}
          <Wallet size={17} aria-hidden="true" />
        </button>
      </form>

      {authError ? <p className="auth-error">{authError}</p> : null}

      <p className="login-switch">
        {t("auth.alreadyAccount")}{" "}
        <button type="button" onClick={onLogin}>
          {t("auth.login")}
        </button>
      </p>
    </div>
  );
}

export default Register;


