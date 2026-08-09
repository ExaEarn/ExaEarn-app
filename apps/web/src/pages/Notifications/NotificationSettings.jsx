import { useMemo, useState } from "react";
import { ArrowLeft, Bell, Check, ChevronRight, Moon, X } from "lucide-react";

const learningItems = [
  { key: "courseUpdates", icon: "Course", title: "Course Updates", desc: "New lessons, course edits, and instructor posts." },
  { key: "quizReminders", icon: "Quiz", title: "Quiz Reminders", desc: "Get notified before assessment deadlines." },
  { key: "exaRewards", icon: "EXA", title: "EXA Reward Notifications", desc: "Track new rewards credited to your wallet." },
  { key: "certificate", icon: "Cert", title: "Certificate Issued", desc: "Instant alert when a certificate is generated." },
  { key: "scholar", icon: "Aid", title: "Scholarship Announcements", desc: "Receive updates on scholarship windows." },
];

const tokenItems = [
  { key: "exaPrice", icon: "Price", title: "EXA Price Change Alerts", desc: "Price move alerts based on threshold." },
  { key: "significantChange", icon: "%", title: "Significant % Change Alerts", desc: "Large market swings and volatility warnings." },
  { key: "walletTransactions", icon: "Wallet", title: "Wallet Transactions", desc: "Deposit, withdrawal, and transfer confirmations." },
  { key: "securityLogin", icon: "Secure", title: "Security Login Alerts", desc: "Notify on new or suspicious login sessions." },
  { key: "marketNews", icon: "News", title: "Market News", desc: "Major updates, listings, and ecosystem news." },
];

const communityItems = [
  { key: "referralBonus", icon: "Team", title: "Referral Bonus Updates", desc: "Track referral earnings and milestones." },
  { key: "announcements", icon: "Info", title: "Announcements", desc: "Platform releases and governance updates." },
  { key: "exaAi", icon: "AI", title: "ExaAI Insights", desc: "Actionable AI-generated market insights." },
  { key: "supportMessages", icon: "Help", title: "Messages from Support", desc: "Case updates and support communication." },
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
    <main className="relative min-h-screen bg-[var(--exa-bg-primary)] text-white">
      <div className="pointer-events-none absolute inset-0 opacity-25 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.22)_1px,transparent_0)] [background-size:24px_24px]" />

      <header
        className="sticky top-0 z-30 border-b border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-3xl px-4 pb-3 pt-3 sm:px-6">
          <div className="flex items-start gap-3">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] p-2 text-[var(--exa-text-secondary)] transition hover:border-[var(--exa-border-active)]/60"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div className="flex-1">
              <h1 className="text-lg font-semibold text-[var(--exa-text-primary)]">Notification Preferences</h1>
              <p className="text-xs text-[var(--exa-text-muted)]">Manage how and when ExaEarn sends you updates.</p>
            </div>
          </div>
        </div>
      </header>

      <section
        className="relative mx-auto w-full max-w-3xl space-y-4 px-4 pb-28 pt-4 sm:px-6"
        style={{ paddingBottom: "calc(96px + env(safe-area-inset-bottom))" }}
      >
        <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)] backdrop-blur">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="text-sm font-semibold text-[var(--exa-text-primary)]">ðŸ”” Enable Notifications</p>
              <p className="mt-1 text-xs text-[var(--exa-text-muted)]">Manage how and when ExaEarn sends you updates.</p>
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
                          ? "bg-gradient-to-r from-[var(--exa-gold-light)] via-[var(--exa-gold)] to-[var(--exa-gold-dark)] text-[var(--exa-gold-contrast)]"
                          : "bg-[var(--exa-surface-hover)] text-[var(--exa-text-secondary)]"
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
                  className="flex w-full items-center justify-between rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2.5 text-left disabled:opacity-45"
                >
                  <span className="text-sm text-[var(--exa-text-secondary)]">{channel.label}</span>
                  <span className={`inline-flex h-5 w-5 items-center justify-center rounded border ${active ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]" : "border-[var(--exa-border)] text-transparent"}`}>
                    <Check className="h-3.5 w-3.5" />
                  </span>
                </button>
              );
            })}
          </div>
        </Section>

        <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
          <button
            type="button"
            onClick={() => setShowQuietHours(true)}
            className="inline-flex items-center gap-1 text-sm font-medium text-[var(--exa-gold-light)] hover:text-[var(--exa-gold-light)]"
          >
            Quiet Hours
            <ChevronRight className="h-4 w-4" />
          </button>
        </article>
      </section>

      <section
        className="fixed inset-x-0 bottom-0 z-40 border-t border-[var(--exa-border)] bg-[var(--exa-surface)] p-3 backdrop-blur"
        style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}
      >
        <div className="mx-auto w-full max-w-3xl">
          <button
            type="button"
            onClick={savePreferences}
            disabled={!hasChanges || saving}
            className="w-full rounded-xl bg-gradient-to-r from-[var(--exa-gold-light)] via-[var(--exa-gold)] to-[var(--exa-gold-dark)] py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] disabled:cursor-not-allowed disabled:opacity-45"
          >
            {saving ? "Saving..." : "Save Preferences"}
          </button>
        </div>
      </section>

      {showQuietHours ? (
        <div className="fixed inset-0 z-50 flex items-end bg-black/50 sm:items-center sm:justify-center">
          <button type="button" className="absolute inset-0" onClick={() => setShowQuietHours(false)} />
          <div className="relative w-full rounded-t-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 sm:max-w-sm sm:rounded-2xl">
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-base font-semibold text-[var(--exa-text-primary)]">Set Quiet Hours</h3>
              <button type="button" onClick={() => setShowQuietHours(false)} className="text-[var(--exa-text-secondary)]">
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
                  className="h-10 w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm outline-none"
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
                  className="h-10 w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm outline-none"
                />
              </Field>
              <div className="flex items-center justify-between rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2.5">
                <span className="text-sm text-[var(--exa-text-secondary)]">Allow Critical Alerts During Quiet Hours</span>
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
    <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)]">
      <h2 className="mb-3 text-sm font-semibold text-[var(--exa-text-primary)]">{title}</h2>
      <div className="space-y-2">{children}</div>
    </article>
  );
}

function Row({ icon, title, desc, enabled, onToggle, disabled }) {
  return (
    <div className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2.5">
      <div className="flex items-start justify-between gap-3">
        <div className="flex-1">
          <p className="text-sm font-medium text-[var(--exa-text-secondary)]">{icon} {title}</p>
          <p className="mt-0.5 text-xs text-[var(--exa-text-muted)]">{desc}</p>
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
        value ? "bg-[var(--exa-gold)]" : "bg-[var(--exa-surface-hover)]"
      } disabled:opacity-45`}
    >
      <span className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all duration-300 ${value ? "left-5" : "left-0.5"}`} />
    </button>
  );
}

function Field({ label, children }) {
  return (
    <label className="block">
      <span className="mb-1 block text-xs text-[var(--exa-text-muted)]">{label}</span>
      {children}
    </label>
  );
}

export default NotificationSettings;

