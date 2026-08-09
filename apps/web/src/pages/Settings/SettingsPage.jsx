import { LANGUAGE_STORAGE_KEY, formatLanguageLabel, getLanguageByCode } from "@exaearn/config";
import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  BellRing,
  CircleHelp,
  Clock3,
  Coins,
  CreditCard,
  FileSearch,
  Fingerprint,
  Globe,
  Landmark,
  Languages,
  Lock,
  MoonStar,
  ShieldCheck,
  Smartphone,
  Trash2,
  UserCog,
  Wallet,
} from "lucide-react";
import { useTheme } from "../../context/ThemeContext";

function SettingsPage({ onBack, onOpenLanguageRegion, onOpenCurrencyPreference, onOpenMarketAnalytics, onOpenNotificationPreferences, onOpenPaymentCurrency, onOpenPaymentMethods, onOpenActivityLogs }) {
  const { theme, setTheme } = useTheme();
  const [twoFactorEnabled, setTwoFactorEnabled] = useState(true);
  const [biometricEnabled, setBiometricEnabled] = useState(false);
  const [cacheUsage, setCacheUsage] = useState("Calculating...");
  const [languageRegionSummary, setLanguageRegionSummary] = useState("English (Default), Nigeria");
  const [currencySummary, setCurrencySummary] = useState("Display USD, Transaction USD");

  useEffect(() => {
    let mounted = true;

    const formatMB = (bytes) => `${(bytes / (1024 * 1024)).toFixed(2)} MB`;

    async function resolveCacheUsage() {
      try {
        if (navigator.storage?.estimate) {
          const { usage } = await navigator.storage.estimate();
          if (mounted && typeof usage === "number") {
            setCacheUsage(formatMB(usage));
            return;
          }
        }
      } catch {
        // Fall back to local/session storage estimation below.
      }

      try {
        let total = 0;
        for (let i = 0; i < localStorage.length; i += 1) {
          const key = localStorage.key(i) || "";
          const value = localStorage.getItem(key) || "";
          total += key.length + value.length;
        }
        for (let i = 0; i < sessionStorage.length; i += 1) {
          const key = sessionStorage.key(i) || "";
          const value = sessionStorage.getItem(key) || "";
          total += key.length + value.length;
        }
        if (mounted) {
          setCacheUsage(formatMB(total * 2));
        }
      } catch {
        if (mounted) setCacheUsage("0.00 MB");
      }
    }

    resolveCacheUsage();
    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    try {
      const raw = localStorage.getItem("exaearn-currency-preference");
      const parsed = raw ? JSON.parse(raw) : null;
      if (parsed?.displayCurrency || parsed?.transactionCurrency) {
        const displayCurrency = parsed.displayCurrency || "USD";
        const transactionCurrency = parsed.transactionCurrency || displayCurrency;
        setCurrencySummary(`Display ${displayCurrency}, Transaction ${transactionCurrency}`);
      }
    } catch {
      setCurrencySummary("Display USD, Transaction USD");
    }
  }, []);

  useEffect(() => {
    try {
      const currentCode = localStorage.getItem(LANGUAGE_STORAGE_KEY);
      const raw = localStorage.getItem("exaearn-language-region-settings");
      const parsed = raw ? JSON.parse(raw) : null;
      const language = getLanguageByCode(currentCode || parsed?.language_code || parsed?.language || "en");
      setLanguageRegionSummary(`${formatLanguageLabel(language)}, ${parsed?.region || "Nigeria"}`);
    } catch {
      setLanguageRegionSummary("English, Nigeria");
    }
  }, []);

  const prefItems = useMemo(
    () => [
      { icon: Languages, title: "Language & Region", description: languageRegionSummary, action: onOpenLanguageRegion },
      { icon: Coins, title: "Currency Preference", description: currencySummary, action: onOpenCurrencyPreference },
      { icon: Clock3, title: "Change (%) & Chart Timezone", description: "UTC+01:00", action: onOpenMarketAnalytics },
      { icon: BellRing, title: "Notification Preferences", description: "Push, Email", action: onOpenNotificationPreferences },
    ],
    [currencySummary, languageRegionSummary, onOpenLanguageRegion, onOpenCurrencyPreference, onOpenMarketAnalytics, onOpenNotificationPreferences]
  );

  const paymentWalletItems = [
    { icon: Coins, title: "Payment Currency", action: onOpenPaymentCurrency },
    { icon: CreditCard, title: "Payment Methods", action: onOpenPaymentMethods },
    { icon: Wallet, title: "Linked Wallets" },
    { icon: UserCog, title: "P2P Payment Accounts" },
    { icon: Landmark, title: "Fiat On/Off-Ramp Settings" },
  ];

  const securityItems = [
    { icon: ShieldCheck, title: "Security Center" },
    { icon: Lock, title: "Change Password" },
    { icon: Smartphone, title: "Device Management" },
    { icon: FileSearch, title: "Activity Log", action: onOpenActivityLogs },
    { icon: Globe, title: "Anti-Phishing Code" },
  ];

  const ecosystemItems = [
    { icon: Coins, title: "ExaToken Preferences" },
    { icon: Landmark, title: "Staking Settings" },
    { icon: UserCog, title: "Crowdfunding Participation Settings" },
    { icon: ShieldCheck, title: "DAO Governance Preferences" },
    { icon: BellRing, title: "Referral & Rewards Settings" },
  ];

  const supportItems = [
    { icon: Trash2, title: "Clear Cache", description: cacheUsage },
    { icon: CircleHelp, title: "Help & Support" },
    { icon: Lock, title: "Cookie Settings" },
    { icon: ShieldCheck, title: "Privacy Center" },
    { icon: Globe, title: "About ExaEarn" },
    { icon: Smartphone, title: "Check for Updates", description: "v0.0.0" },
  ];

  return (
    <div className="min-h-screen bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
      <div className="mx-auto w-full max-w-sm px-3 pb-8 pt-4 sm:max-w-lg sm:px-4 sm:pt-6 md:max-w-2xl lg:max-w-4xl">
        <div className="rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)] backdrop-blur-xl sm:p-5">
          <header className="relative mb-5 overflow-hidden rounded-2xl border border-[var(--exa-border)] bg-gradient-to-r from-[var(--exa-surface)] via-[var(--exa-surface-elevated)] to-[var(--exa-bg-tertiary)] p-4 sm:mb-6 sm:p-5">
            <span className="pointer-events-none absolute -right-8 -top-8 h-24 w-24 rounded-full bg-[var(--exa-gold-surface)] blur-2xl" />
            <span className="pointer-events-none absolute -left-8 -bottom-8 h-24 w-24 rounded-full bg-[var(--exa-surface-hover)] blur-2xl" />
            <div className="relative flex items-center gap-3">
              <button
                type="button"
                onClick={onBack}
                className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] p-2 text-[var(--exa-text-secondary)] transition-all duration-300 hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)] active:scale-95"
                aria-label="Back"
              >
                <ArrowLeft className="h-5 w-5" />
              </button>
              <div>
                <p className="text-xs uppercase tracking-[0.18em] text-[var(--exa-text-muted)]">Preferences</p>
                <h1 className="font-['Sora'] text-xl font-semibold text-[var(--exa-text-primary)] sm:text-2xl">Settings</h1>
              </div>
            </div>
          </header>

          <Section title="Preferences">
            {prefItems.map((item) => (
              <SettingsRow key={item.title} icon={item.icon} title={item.title} description={item.description} onClick={item.action} />
            ))}
            <ToggleRow
              icon={MoonStar}
              title="Theme Mode"
              description={theme === "dark" ? "Dark" : "Light"}
              enabled={theme === "dark"}
              onToggle={() => setTheme((prev) => (prev === "dark" ? "light" : "dark"))}
            />
          </Section>

          <Section title="Payment & Wallet">
            {paymentWalletItems.map((item) => (
              <SettingsRow key={item.title} icon={item.icon} title={item.title} onClick={item.action} />
            ))}
          </Section>

          <Section title="Security">
            {securityItems.map((item) => (
              <SettingsRow
                key={item.title}
                icon={item.icon}
                title={item.title}
                securityAccent
                onClick={item.action}
              />
            ))}
            <ToggleRow
              icon={ShieldCheck}
              title="Two-Factor Authentication (2FA)"
              description={twoFactorEnabled ? "Enabled" : "Disabled"}
              enabled={twoFactorEnabled}
              onToggle={() => setTwoFactorEnabled((prev) => !prev)}
              securityAccent
            />
            <ToggleRow
              icon={Fingerprint}
              title="Biometric Login"
              description={biometricEnabled ? "Enabled" : "Disabled"}
              enabled={biometricEnabled}
              onToggle={() => setBiometricEnabled((prev) => !prev)}
              securityAccent
            />
          </Section>

          <Section title="Ecosystem Settings">
            {ecosystemItems.map((item) => (
              <SettingsRow key={item.title} icon={item.icon} title={item.title} />
            ))}
          </Section>

          <Section title="System & Support">
            {supportItems.map((item) => (
              <SettingsRow key={item.title} icon={item.icon} title={item.title} description={item.description} />
            ))}
          </Section>
        </div>
      </div>
    </div>
  );
}

function Section({ title, children }) {
  return (
    <section className="mb-5 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-2 sm:p-3">
      <h2 className="px-2 pb-2 pt-1 font-['Sora'] text-xs font-semibold uppercase tracking-[0.12em] text-[var(--exa-text-muted)]">{title}</h2>
      <div className="space-y-1">{children}</div>
    </section>
  );
}

function SettingsRow({ icon: Icon, title, description, securityAccent = false, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="group flex w-full items-center justify-between rounded-xl px-3 py-3 text-left transition-all duration-300 hover:bg-[var(--exa-surface-hover)] hover:shadow-[var(--exa-shadow-soft)] active:scale-[0.99]"
    >
      <div className="flex items-center gap-3">
        <span
          className={`flex h-9 w-9 items-center justify-center rounded-lg border bg-[var(--exa-surface-hover)] transition-colors duration-300 ${
            securityAccent
              ? "border-[var(--exa-border-active)] text-[var(--exa-gold-light)]"
              : "border-[var(--exa-border)] text-[var(--exa-text-secondary)] group-hover:border-[var(--exa-border-active)] group-hover:text-[var(--exa-gold-light)]"
          }`}
        >
          <Icon className="h-4 w-4" />
        </span>
        <span>
          <span className="block text-sm font-medium text-[var(--exa-text-secondary)]">{title}</span>
          {description ? <span className="block text-xs text-[var(--exa-text-muted)]">{description}</span> : null}
        </span>
      </div>
      <ArrowRight className="h-4 w-4 text-[var(--exa-text-muted)] transition-all duration-300 group-hover:translate-x-1 group-hover:text-[var(--exa-gold-light)]" />
    </button>
  );
}

function ToggleRow({ icon: Icon, title, description, enabled, onToggle, securityAccent = false }) {
  return (
    <button
      type="button"
      onClick={onToggle}
      className="group flex w-full items-center justify-between rounded-xl px-3 py-3 text-left transition-all duration-300 hover:bg-[var(--exa-surface-hover)] hover:shadow-[var(--exa-shadow-soft)] active:scale-[0.99]"
    >
      <div className="flex items-center gap-3">
        <span
          className={`flex h-9 w-9 items-center justify-center rounded-lg border bg-[var(--exa-surface-hover)] ${
            securityAccent ? "border-[var(--exa-border-active)] text-[var(--exa-gold-light)]" : "border-[var(--exa-border)] text-[var(--exa-text-secondary)]"
          }`}
        >
          <Icon className="h-4 w-4" />
        </span>
        <span>
          <span className="block text-sm font-medium text-[var(--exa-text-secondary)]">{title}</span>
          {description ? <span className="block text-xs text-[var(--exa-text-muted)]">{description}</span> : null}
        </span>
      </div>
      <span className={`relative h-6 w-11 rounded-full transition-colors duration-300 ${enabled ? "bg-gradient-to-r from-[var(--exa-gold-dark)] to-[var(--exa-gold)]" : "bg-[var(--exa-surface-hover)]"}`}>
        <span className={`absolute top-1 h-4 w-4 rounded-full bg-white shadow transition-all duration-300 ${enabled ? "left-6" : "left-1"}`} />
      </span>
    </button>
  );
}

export default SettingsPage;


