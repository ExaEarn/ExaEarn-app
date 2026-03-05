import { useMemo, useState } from "react";
import { ArrowLeft, Bell, Check, ChevronRight, Moon, X } from "lucide-react";

const learningItems = [
  { key: "courseUpdates", icon: "📚", title: "Course Updates", desc: "New lessons, course edits, and instructor posts." },
  { key: "quizReminders", icon: "📝", title: "Quiz Reminders", desc: "Get notified before assessment deadlines." },
  { key: "exaRewards", icon: "💰", title: "EXA Reward Notifications", desc: "Track new rewards credited to your wallet." },
  { key: "certificate", icon: "🏆", title: "Certificate Issued", desc: "Instant alert when a certificate is generated." },
  { key: "scholarship", icon: "🎓", title: "Scholarship Announcements", desc: "Receive updates on scholarship windows." },
];

const tokenItems = [
  { key: "exaPrice", icon: "📈", title: "EXA Price Change Alerts", desc: "Price move alerts based on threshold." },
  { key: "significantChange", icon: "🔔", title: "Significant % Change Alerts", desc: "Large market swings and volatility warnings." },
  { key: "walletTransactions", icon: "💸", title: "Wallet Transactions", desc: "Deposit, withdrawal, and transfer confirmations." },
  { key: "securityLogin", icon: "🔐", title: "Security Login Alerts", desc: "Notify on new or suspicious login sessions." },
  { key: "marketNews", icon: "📊", title: "Market News", desc: "Major updates, listings, and ecosystem news." },
];

const communityItems = [
  { key: "referralBonus", icon: "👥", title: "Referral Bonus Updates", desc: "Track referral earnings and milestones." },
  { key: "announcements", icon: "📢", title: "Announcements", desc: "Platform releases and governance updates." },
  { key: "exaAi", icon: "🤖", title: "ExaAI Insights", desc: "Actionable AI-generated market insights." },
  { key: "supportMessages", icon: "💬", title: "Messages from Support", desc: "Case updates and support communication." },
];

const baseState = {
  masterEnabled: true,
  channels: {
    push: true,
    email: true,
    sms: false,
  },
  priceThreshold: "5%",
  quietHours: {
    start: "22:00",
    end: "06:00",
    allowCritical: true,
  },
  toggles: {
    courseUpdates: true,
    quizReminders: true,
    exaRewards: true,
    certificate: true,
    scholarship: false,
    exaPrice: true,
    significantChange: true,
    walletTransactions: true,
    securityLogin: true,
    marketNews: false,
    referralBonus: true,
    announcements: true,
    exaAi: false,
    supportMessages: true,
  },
};

function NotificationSettings({ onBack }) {
  const [state, setState] = useState(baseState);
  const [saved, setSaved] = useState(baseState);
  const [showQuietHours, setShowQuietHours] = useState(false);
  const [toast, setToast] = useState("");
  const [saving, setSaving] = useState(false);

  const hasChanges = useMemo(() => JSON.stringify(state) !== JSON.stringify(saved), [state, saved]);

  const toggleItem = (key) => {
    setState((prev) => ({
      ...prev,
      toggles: {
        ...prev.toggles,
        [key]: !prev.toggles[key],
      },
    }));
  };

  const savePreferences = async () => {
    if (!hasChanges || saving) return;
    setSaving(true);
    await new Promise((resolve) => setTimeout(resolve, 700));
    setSaved(state);
    setSaving(false);
    setToast("Your notification preferences have been updated.");
    setTimeout(() => setToast(""), 2200);
  };

  return (
    <main className="relative min-h-screen bg-gradient-to-br from-[#0B0F1F] via-[#140B2D] to-[#1C0F3F] text-white">
      <div className="pointer-events-none absolute inset-0 opacity-25 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.22)_1px,transparent_0)] [background-size:24px_24px]" />

      <header
        className="sticky top-0 z-30 border-b border-violet-300/20 bg-cosmic-900/85 backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-3xl px-4 pb-3 pt-3 sm:px-6">
          <div className="flex items-start gap-3">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-violet-300/25 bg-cosmic-800/70 p-2 text-violet-100 transition hover:border-auric-300/60"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div className="flex-1">
              <h1 className="text-lg font-semibold text-violet-50">Notification Preferences</h1>
              <p className="text-xs text-violet-100/65">Manage how and when ExaEarn sends you updates.</p>
            </div>
          </div>
        </div>
      </header>

      <section
        className="relative mx-auto w-full max-w-3xl space-y-4 px-4 pb-28 pt-4 sm:px-6"
        style={{ paddingBottom: "calc(96px + env(safe-area-inset-bottom))" }}
      >
        <article className="rounded-2xl border border-violet-300/20 bg-cosmic-900/70 p-4 shadow-cosmic-card backdrop-blur">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="text-sm font-semibold text-violet-50">🔔 Enable Notifications</p>
              <p className="mt-1 text-xs text-violet-100/65">Manage how and when ExaEarn sends you updates.</p>
            </div>
            <Toggle value={state.masterEnabled} onChange={() => setState((prev) => ({ ...prev, masterEnabled: !prev.masterEnabled }))} />
          </div>
        </article>

        <Section title="Learn & Earn Alerts">
          {learningItems.map((item) => (
            <Row
              key={item.key}
              icon={item.icon}
              title={item.title}
              desc={item.desc}
              enabled={state.toggles[item.key]}
              disabled={!state.masterEnabled}
              onToggle={() => toggleItem(item.key)}
            />
          ))}
        </Section>

        <Section title="Token & Market Alerts">
          {tokenItems.map((item) => (
            <div key={item.key}>
              <Row
                icon={item.icon}
                title={item.title}
                desc={item.desc}
                enabled={state.toggles[item.key]}
                disabled={!state.masterEnabled}
                onToggle={() => toggleItem(item.key)}
              />
              {item.key === "exaPrice" && state.toggles.exaPrice ? (
                <div className="mt-2 ml-3 flex flex-wrap gap-2">
                  {["3%", "5%", "10%"].map((threshold) => (
                    <button
                      key={threshold}
                      type="button"
                      onClick={() => setState((prev) => ({ ...prev, priceThreshold: threshold }))}
                      className={`rounded-full px-3 py-1 text-xs font-semibold transition ${
                        state.priceThreshold === threshold
                          ? "bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 text-cosmic-900"
                          : "bg-cosmic-800/75 text-violet-100"
                      }`}
                    >
                      {threshold}
                    </button>
                  ))}
                </div>
              ) : null}
            </div>
          ))}
        </Section>

        <Section title="Community & Platform">
          {communityItems.map((item) => (
            <Row
              key={item.key}
              icon={item.icon}
              title={item.title}
              desc={item.desc}
              enabled={state.toggles[item.key]}
              disabled={!state.masterEnabled}
              onToggle={() => toggleItem(item.key)}
            />
          ))}
        </Section>

        <Section title="Notification Channels">
          <div className="space-y-2">
            {[
              { key: "push", label: "Push Notifications" },
              { key: "email", label: "Email Notifications" },
              { key: "sms", label: "SMS Notifications" },
            ].map((channel) => {
              const active = state.channels[channel.key];
              return (
                <button
                  key={channel.key}
                  type="button"
                  disabled={!state.masterEnabled}
                  onClick={() =>
                    setState((prev) => ({
                      ...prev,
                      channels: {
                        ...prev.channels,
                        [channel.key]: !prev.channels[channel.key],
                      },
                    }))
                  }
                  className="flex w-full items-center justify-between rounded-xl border border-violet-300/15 bg-cosmic-800/60 px-3 py-2.5 text-left disabled:opacity-45"
                >
                  <span className="text-sm text-violet-100">{channel.label}</span>
                  <span className={`inline-flex h-5 w-5 items-center justify-center rounded border ${active ? "border-auric-300 bg-auric-300/20 text-auric-300" : "border-violet-300/35 text-transparent"}`}>
                    <Check className="h-3.5 w-3.5" />
                  </span>
                </button>
              );
            })}
          </div>
        </Section>

        <article className="rounded-2xl border border-violet-300/20 bg-cosmic-900/70 p-4">
          <button
            type="button"
            onClick={() => setShowQuietHours(true)}
            className="inline-flex items-center gap-1 text-sm font-medium text-auric-300 hover:text-auric-200"
          >
            Quiet Hours
            <ChevronRight className="h-4 w-4" />
          </button>
        </article>
      </section>

      <section
        className="fixed inset-x-0 bottom-0 z-40 border-t border-violet-300/20 bg-cosmic-900/90 p-3 backdrop-blur"
        style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}
      >
        <div className="mx-auto w-full max-w-3xl">
          <button
            type="button"
            onClick={savePreferences}
            disabled={!hasChanges || saving}
            className="w-full rounded-xl bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 py-3 text-sm font-semibold text-cosmic-900 shadow-[0_10px_24px_rgba(212,175,55,0.3)] disabled:cursor-not-allowed disabled:opacity-45"
          >
            {saving ? "Saving..." : "Save Preferences"}
          </button>
        </div>
      </section>

      {showQuietHours ? (
        <div className="fixed inset-0 z-50 flex items-end bg-black/50 sm:items-center sm:justify-center">
          <button type="button" className="absolute inset-0" onClick={() => setShowQuietHours(false)} />
          <div className="relative w-full rounded-t-2xl border border-violet-300/20 bg-cosmic-900 p-4 sm:max-w-sm sm:rounded-2xl">
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-base font-semibold text-violet-50">Set Quiet Hours</h3>
              <button type="button" onClick={() => setShowQuietHours(false)} className="text-violet-100/70">
                <X className="h-4 w-4" />
              </button>
            </div>
            <div className="space-y-3">
              <Field label="Start Time">
                <input
                  type="time"
                  value={state.quietHours.start}
                  onChange={(e) =>
                    setState((prev) => ({
                      ...prev,
                      quietHours: { ...prev.quietHours, start: e.target.value },
                    }))
                  }
                  className="h-10 w-full rounded-xl border border-violet-300/20 bg-cosmic-800 px-3 text-sm outline-none"
                />
              </Field>
              <Field label="End Time">
                <input
                  type="time"
                  value={state.quietHours.end}
                  onChange={(e) =>
                    setState((prev) => ({
                      ...prev,
                      quietHours: { ...prev.quietHours, end: e.target.value },
                    }))
                  }
                  className="h-10 w-full rounded-xl border border-violet-300/20 bg-cosmic-800 px-3 text-sm outline-none"
                />
              </Field>
              <div className="flex items-center justify-between rounded-xl border border-violet-300/20 bg-cosmic-800/70 px-3 py-2.5">
                <span className="text-sm text-violet-100">Allow Critical Alerts During Quiet Hours</span>
                <Toggle
                  value={state.quietHours.allowCritical}
                  onChange={() =>
                    setState((prev) => ({
                      ...prev,
                      quietHours: { ...prev.quietHours, allowCritical: !prev.quietHours.allowCritical },
                    }))
                  }
                />
              </div>
            </div>
          </div>
        </div>
      ) : null}

      {toast ? (
        <div className="fixed right-4 top-24 z-50 rounded-xl border border-[#16C784]/35 bg-[#16C784]/15 px-3 py-2 text-xs text-[#BBF7D0] shadow-lg">
          {toast}
        </div>
      ) : null}
    </main>
  );
}

function Section({ title, children }) {
  return (
    <article className="rounded-2xl border border-violet-300/20 bg-cosmic-900/70 p-4 shadow-cosmic-card">
      <h2 className="mb-3 text-sm font-semibold text-violet-50">{title}</h2>
      <div className="space-y-2">{children}</div>
    </article>
  );
}

function Row({ icon, title, desc, enabled, onToggle, disabled }) {
  return (
    <div className="rounded-xl border border-violet-300/15 bg-cosmic-800/60 px-3 py-2.5">
      <div className="flex items-start justify-between gap-3">
        <div className="flex-1">
          <p className="text-sm font-medium text-violet-100">{icon} {title}</p>
          <p className="mt-0.5 text-xs text-violet-100/60">{desc}</p>
        </div>
        <Toggle value={enabled} onChange={onToggle} disabled={disabled} />
      </div>
    </div>
  );
}

function Toggle({ value, onChange, disabled = false }) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onChange}
      className={`relative h-6 w-11 rounded-full transition-colors duration-300 ${
        value ? "bg-auric-400" : "bg-violet-200/25"
      } disabled:opacity-45`}
    >
      <span className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all duration-300 ${value ? "left-5" : "left-0.5"}`} />
    </button>
  );
}

function Field({ label, children }) {
  return (
    <label className="block">
      <span className="mb-1 block text-xs text-violet-100/65">{label}</span>
      {children}
    </label>
  );
}

export default NotificationSettings;
