import React, { useMemo, useState } from "react";
import { BarChart3, Check, ChevronLeft, ChevronRight, Compass, Eye, EyeOff, Gauge, Landmark, LockKeyhole, Send, Sparkles, TrendingUp, UserRound, Wallet } from "lucide-react";
import LanguageSwitcher from "../../components/language/LanguageSwitcher.jsx";
import { useAuth } from "../../context/AuthContext";
import { useLanguage } from "../../context/LanguageContext.jsx";
import { buildDashboardPreferences, experienceOptions, goalOptions, inferInterests, interestOptions, recommendExperienceMode } from "../../features/onboarding/experienceRecommendation.js";
import "./Register.css";

const goalIcons = { buy_trade: BarChart3, send_pay: Send, grow_assets: TrendingUp, trade_smarter: Sparkles, p2p: Landmark, explore: Compass };

export default function Register({ onLogin, onSuccess }) {
  const { register, checkAccountAvailability, authLoading, authError } = useAuth();
  const { t } = useLanguage();
  const [stage, setStage] = useState(0);
  const [account, setAccount] = useState({ name: "", email: "", referral: "", password: "", confirmation: "" });
  const [showPassword, setShowPassword] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [experience, setExperience] = useState("");
  const [goal, setGoal] = useState("");
  const [interests, setInterests] = useState([]);
  const [modeOverride, setModeOverride] = useState("");
  const passwordsMatch = !account.password || !account.confirmation || account.password === account.confirmation;
  const effectiveInterests = interests.length ? interests : inferInterests(goal);
  const recommendedMode = useMemo(() => recommendExperienceMode(experience, goal, effectiveInterests), [experience, goal, effectiveInterests]);
  const selectedMode = modeOverride || recommendedMode;
  const orderedInterests = useMemo(() => {
    const preferred = inferInterests(goal);
    return [...interestOptions].sort((a, b) => Number(preferred.includes(b.id)) - Number(preferred.includes(a.id)));
  }, [goal]);

  const updateAccount = (key) => (event) => setAccount((current) => ({ ...current, [key]: event.target.value }));
  const begin = async (event) => {
    event.preventDefault(); setSubmitted(true);
    if (!passwordsMatch) return;
    const result = await checkAccountAvailability({ name: account.name, email: account.email, password: account.password, passwordConfirmation: account.confirmation, referralCode: account.referral, validateCredentials: true });
    if (result.success && !result.exists) setStage(1);
  };
  const finish = async () => {
    const dashboardPreferences = buildDashboardPreferences({ experience, goal, interests, selectedMode });
    const result = await register({ name: account.name, email: account.email, password: account.password, passwordConfirmation: account.confirmation, referralCode: account.referral, dashboardPreferences });
    if (result.success) onSuccess?.();
  };
  const toggleInterest = (id) => setInterests((current) => current.includes(id) ? current.filter((item) => item !== id) : current.length < 3 ? [...current, id] : current);

  return <main className="register-onboarding-shell">
    <div className="register-language-switcher"><LanguageSwitcher compact /></div>
    <section className="onboarding-modal" aria-label="Create your ExaEarn account">
      {stage > 0 ? <header className="onboarding-progress"><div><span>Personalize your experience</span><b>Step {stage} of 4</b></div><i><span style={{ width: `${stage * 25}%` }} /></i></header> : null}
      <div className="onboarding-stage">
        {stage === 0 ? <AccountForm account={account} update={updateAccount} showPassword={showPassword} setShowPassword={setShowPassword} passwordsMatch={passwordsMatch} submitted={submitted} busy={authLoading} error={authError} onSubmit={begin} onLogin={onLogin} t={t} /> : null}
        {stage === 1 ? <ChoiceStep eyebrow="Step 1" title="How familiar are you with crypto?" description="We’ll use this to set the right level of detail."><div className="onboarding-option-grid three">{experienceOptions.map((item) => <Option key={item.id} active={experience === item.id} onClick={() => setExperience(item.id)} icon={UserRound} title={item.label} text={item.description} />)}</div></ChoiceStep> : null}
        {stage === 2 ? <ChoiceStep eyebrow="Step 2" title="What do you mainly want to do?" description="Choose one primary goal. You can change it later."><div className="onboarding-option-grid goals">{goalOptions.map((item) => <Option key={item.id} active={goal === item.id} onClick={() => { setGoal(item.id); setInterests([]); }} icon={goalIcons[item.id]} title={item.label} text={item.description} />)}</div></ChoiceStep> : null}
        {stage === 3 ? <ChoiceStep eyebrow="Step 3" title="What are you interested in?" description="Choose up to three, or skip and we’ll use your main goal."><div className="interest-pills">{orderedInterests.map((item) => <button type="button" key={item.id} className={interests.includes(item.id) ? "active" : ""} onClick={() => toggleInterest(item.id)} aria-pressed={interests.includes(item.id)}>{interests.includes(item.id) ? <Check size={14} /> : null}{item.label}</button>)}</div><p className="selection-count">{interests.length}/3 selected</p></ChoiceStep> : null}
        {stage === 4 ? <ChoiceStep eyebrow="Step 4" title="Your ExaEarn experience is ready" description="Your recommendation controls layout and discovery, never financial access or eligibility."><div className="mode-result"><span className="mode-icon">{selectedMode === "pro" ? <Gauge /> : <Sparkles />}</span><div><small>Recommended experience</small><h2>{recommendedMode === "pro" ? "Pro" : "Lite"}</h2><p>{recommendedMode === "pro" ? "Markets and advanced trading tools are prioritized." : "Essential actions and guided product discovery are prioritized."}</p></div></div><fieldset className="mode-override"><legend>Experience mode</legend>{["lite", "pro"].map((mode) => <button type="button" key={mode} className={selectedMode === mode ? "active" : ""} onClick={() => setModeOverride(mode)}>{mode === "lite" ? "Lite" : "Pro"}{recommendedMode === mode ? <small>Recommended</small> : null}</button>)}</fieldset><div className="priority-list"><strong>We’ll prioritize</strong>{effectiveInterests.slice(0, 3).map((id) => <span key={id}><Check size={14} />{interestOptions.find((item) => item.id === id)?.label}</span>)}</div></ChoiceStep> : null}
      </div>
      {stage > 0 ? <footer className="onboarding-nav"><button type="button" className="onboarding-secondary" onClick={() => setStage((value) => value - 1)} disabled={authLoading}><ChevronLeft />Back</button>{stage === 3 ? <button type="button" className="onboarding-skip" onClick={() => { setInterests([]); setStage(4); }}>Skip</button> : null}<button type="button" className="onboarding-primary" disabled={authLoading || (stage === 1 && !experience) || (stage === 2 && !goal)} onClick={stage === 4 ? finish : () => setStage((value) => value + 1)}>{authLoading ? "Creating account..." : stage === 4 ? "Enter ExaEarn" : "Continue"}<ChevronRight /></button></footer> : null}
      {stage > 0 && authError ? <p className="auth-error">{authError}</p> : null}
    </section>
  </main>;
}

function ChoiceStep({ eyebrow, title, description, children }) { return <section className="choice-step"><div className="screen-heading"><span>{eyebrow}</span><h1>{title}</h1><p>{description}</p></div>{children}</section>; }
function Option({ active, onClick, icon: Icon, title, text }) { return <button type="button" className={`onboarding-option ${active ? "active" : ""}`} onClick={onClick} aria-pressed={active}><span><Icon size={19} /></span><strong>{title}</strong><small>{text}</small>{active ? <Check className="option-check" size={15} /> : null}</button>; }

function AccountForm({ account, update, showPassword, setShowPassword, passwordsMatch, submitted, busy, error, onSubmit, onLogin, t }) {
  const pattern = "^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^\\w\\s]).{10,}$";
  return <div className="account-setup-screen"><div className="screen-heading"><span>{t("auth.registerEyebrow")}</span><h1>{t("auth.registerTitle")}</h1><p>{t("auth.registerDescription")}</p></div><form onSubmit={onSubmit} className="premium-register-form">
    <label><span>{t("auth.fullName")}</span><input value={account.name} onChange={update("name")} placeholder={t("auth.fullNamePlaceholder")} required /></label>
    <label><span>{t("auth.emailAddress")}</span><input type="email" value={account.email} onChange={update("email")} placeholder="you@exaearn.io" required /></label>
    <label><span>{t("auth.referralCode")} <em>{t("common.optional")}</em></span><input value={account.referral} onChange={update("referral")} placeholder={t("auth.referralPlaceholder")} /></label>
    <label><span>{t("auth.password")}</span><div className="password-field"><LockKeyhole size={16} /><input type={showPassword ? "text" : "password"} value={account.password} onChange={update("password")} placeholder="********" minLength={10} pattern={pattern} title={t("auth.passwordTitle")} required /><button type="button" onClick={() => setShowPassword((value) => !value)} aria-label={showPassword ? t("auth.hidePassword") : t("auth.showPassword")}>{showPassword ? <EyeOff /> : <Eye />}</button></div></label>
    <label><span>{t("auth.confirmPassword")}</span><div className="password-field"><LockKeyhole size={16} /><input type={showPassword ? "text" : "password"} value={account.confirmation} onChange={update("confirmation")} placeholder="********" minLength={10} pattern={pattern} required /></div><small className={submitted && !passwordsMatch ? "form-error" : ""}>{submitted && !passwordsMatch ? t("auth.passwordMismatch") : t("auth.passwordHelp")}</small></label>
    <button className="onboarding-primary submit-account" disabled={busy}>{busy ? t("auth.creating") : "Continue"}<Wallet size={17} /></button>
  </form>{error ? <p className="auth-error">{error}</p> : null}<p className="login-switch">{t("auth.alreadyAccount")} <button type="button" onClick={onLogin}>{t("auth.login")}</button></p></div>;
}
