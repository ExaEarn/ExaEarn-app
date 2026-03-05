import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  BellRing,
  CircleHelp,
  Clock3,
  Coins,
  CreditCard,
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

function SettingsPage({ onBack, onOpenLanguageRegion, onOpenCurrencyPreference, onOpenMarketAnalytics, onOpenNotificationPreferences, onOpenPaymentCurrency, onOpenPaymentMethods }) {
  const { theme, setTheme } = useTheme();
  const [twoFactorEnabled, setTwoFactorEnabled] = useState(true);
  const [biometricEnabled, setBiometricEnabled] = useState(false);
  const [cacheUsage, setCacheUsage] = useState("Calculating...");

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

  const prefItems = useMemo(
    () => [
      { icon: Languages, title: "Language & Region", description: "English (US)", action: onOpenLanguageRegion },
      { icon: Coins, title: "Currency Preference", description: "USD", action: onOpenCurrencyPreference },
      { icon: Clock3, title: "Change (%) & Chart Timezone", description: "UTC+01:00", action: onOpenMarketAnalytics },
      { icon: BellRing, title: "Notification Preferences", description: "Push, Email", action: onOpenNotificationPreferences },
    ],
    [onOpenLanguageRegion, onOpenCurrencyPreference, onOpenMarketAnalytics, onOpenNotificationPreferences]
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
    <div className="min-h-screen bg-gradient-to-br from-[#07050f] via-[#130a23] to-[#1d1134] text-violet-50">
      <div className="mx-auto w-full max-w-sm px-3 pb-8 pt-4 sm:max-w-lg sm:px-4 sm:pt-6 md:max-w-2xl lg:max-w-4xl">
        <div className="rounded-3xl border border-violet-200/10 bg-cosmic-900/65 p-4 shadow-cosmic-card backdrop-blur-xl sm:p-5">
          <header className="relative mb-5 overflow-hidden rounded-2xl border border-violet-200/15 bg-gradient-to-r from-cosmic-900/95 via-cosmic-800/90 to-cosmic-700/90 p-4 sm:mb-6 sm:p-5">
            <span className="pointer-events-none absolute -right-8 -top-8 h-24 w-24 rounded-full bg-auric-400/10 blur-2xl" />
            <span className="pointer-events-none absolute -left-8 -bottom-8 h-24 w-24 rounded-full bg-cosmic-500/20 blur-2xl" />
            <div className="relative flex items-center gap-3">
              <button
                type="button"
                onClick={onBack}
                className="rounded-xl border border-violet-200/20 bg-cosmic-800/70 p-2 text-violet-100 transition-all duration-300 hover:border-auric-300/65 hover:text-auric-300 active:scale-95"
                aria-label="Back"
              >
                <ArrowLeft className="h-5 w-5" />
              </button>
              <div>
                <p className="text-xs uppercase tracking-[0.18em] text-violet-100/60">Preferences</p>
                <h1 className="font-['Sora'] text-xl font-semibold text-violet-50 sm:text-2xl">Settings</h1>
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
    <section className="mb-5 rounded-2xl border border-violet-200/10 bg-cosmic-800/55 p-2 sm:p-3">
      <h2 className="px-2 pb-2 pt-1 font-['Sora'] text-xs font-semibold uppercase tracking-[0.12em] text-violet-100/65">{title}</h2>
      <div className="space-y-1">{children}</div>
    </section>
  );
}

function SettingsRow({ icon: Icon, title, description, securityAccent = false, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="group flex w-full items-center justify-between rounded-xl px-3 py-3 text-left transition-all duration-300 hover:bg-cosmic-700/65 hover:shadow-[0_0_24px_rgba(127,70,212,0.24)] active:scale-[0.99]"
    >
      <div className="flex items-center gap-3">
        <span
          className={`flex h-9 w-9 items-center justify-center rounded-lg border bg-cosmic-500/15 transition-colors duration-300 ${
            securityAccent
              ? "border-auric-300/45 text-auric-300"
              : "border-cosmic-400/45 text-cosmic-400 group-hover:border-auric-300/50 group-hover:text-auric-300"
          }`}
        >
          <Icon className="h-4 w-4" />
        </span>
        <span>
          <span className="block text-sm font-medium text-violet-100">{title}</span>
          {description ? <span className="block text-xs text-violet-100/55">{description}</span> : null}
        </span>
      </div>
      <ArrowRight className="h-4 w-4 text-violet-200/55 transition-all duration-300 group-hover:translate-x-1 group-hover:text-auric-300" />
    </button>
  );
}

function ToggleRow({ icon: Icon, title, description, enabled, onToggle, securityAccent = false }) {
  return (
    <button
      type="button"
      onClick={onToggle}
      className="group flex w-full items-center justify-between rounded-xl px-3 py-3 text-left transition-all duration-300 hover:bg-cosmic-700/65 hover:shadow-[0_0_24px_rgba(127,70,212,0.24)] active:scale-[0.99]"
    >
      <div className="flex items-center gap-3">
        <span
          className={`flex h-9 w-9 items-center justify-center rounded-lg border bg-cosmic-500/15 ${
            securityAccent ? "border-auric-300/45 text-auric-300" : "border-cosmic-400/45 text-cosmic-400"
          }`}
        >
          <Icon className="h-4 w-4" />
        </span>
        <span>
          <span className="block text-sm font-medium text-violet-100">{title}</span>
          {description ? <span className="block text-xs text-violet-100/55">{description}</span> : null}
        </span>
      </div>
      <span className={`relative h-6 w-11 rounded-full transition-colors duration-300 ${enabled ? "bg-gradient-to-r from-cosmic-500 to-auric-500" : "bg-violet-200/20"}`}>
        <span className={`absolute top-1 h-4 w-4 rounded-full bg-white shadow transition-all duration-300 ${enabled ? "left-6" : "left-1"}`} />
      </span>
    </button>
  );
}

export default SettingsPage;
