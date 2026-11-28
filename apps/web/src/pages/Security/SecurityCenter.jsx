import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Activity,
  AlertCircle,
  ArrowLeft,
  ArrowLeftRight,
  ArrowRight,
  BellRing,
  CheckCircle2,
  Clipboard,
  Clock3,
  CreditCard,
  Eye,
  EyeOff,
  Fingerprint,
  KeyRound,
  Laptop,
  Loader2,
  LockKeyhole,
  MapPin,
  MonitorSmartphone,
  RefreshCw,
  ShieldAlert,
  ShieldCheck,
  Smartphone,
  Wallet,
  WifiOff,
} from "lucide-react";

import { useAuth } from "../../context/AuthContext";
import {
  readSecurityPreferences,
  saveSecurityPreferences,
} from "../../security/securityPreferences";

const autoLockChoices = [
  { label: "Immediately", seconds: 0 },
  { label: "30 sec", seconds: 30 },
  { label: "1 min", seconds: 60 },
  { label: "5 min", seconds: 300 },
];

function messageFrom(error, fallback) {
  return error instanceof Error ? error.message : fallback;
}

function formatWhen(value) {
  if (!value) return "Not available";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "Not available" : date.toLocaleString();
}

function Section({ title, children }) {
  return (
    <section className="mt-6">
      <h2 className="mb-2 px-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">{title}</h2>
      <div className="overflow-hidden rounded-2xl border border-white/[.07] bg-[#111720]">{children}</div>
    </section>
  );
}

function SecurityRow({
  icon: Icon,
  title,
  description,
  status,
  tone = "muted",
  action,
  onClick,
  last = false,
  disabled = false,
  right,
}) {
  const content = (
    <>
      <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/[.04] text-slate-400">
        <Icon size={17} strokeWidth={1.7} aria-hidden="true" />
      </span>
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-medium text-slate-100">{title}</span>
        <span className="mt-1 block text-xs leading-5 text-slate-400">{description}</span>
        {status ? <span className={`mt-1 block text-[11px] font-medium ${toneClass[tone]}`}>{status}</span> : null}
      </span>
      {right || (onClick ? (
        <span className="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-slate-300">
          {action || "Open"} <ArrowRight size={14} aria-hidden="true" />
        </span>
      ) : null)}
    </>
  );
  const className = `flex min-h-[72px] w-full items-center gap-3 px-3 py-3 text-left ${!last ? "border-b border-white/[.06]" : ""} ${disabled ? "opacity-60" : ""}`;

  return onClick ? (
    <button type="button" onClick={onClick} disabled={disabled} className={`${className} transition-colors hover:bg-white/[.025] disabled:cursor-not-allowed`} aria-label={`${title}${action ? `, ${action}` : ""}`}>
      {content}
    </button>
  ) : (
    <div className={className}>{content}</div>
  );
}

const toneClass = {
  green: "text-emerald-300",
  amber: "text-amber-300",
  red: "text-rose-300",
  muted: "text-slate-500",
};

function PreferenceSwitch({ icon, title, description, value, onChange, disabled = false, last = false }) {
  return (
    <div className={`flex min-h-[76px] items-center gap-3 px-3 py-3 ${!last ? "border-b border-white/[.06]" : ""} ${disabled ? "opacity-55" : ""}`}>
      <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/[.04] text-slate-400">
        {icon}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-medium text-slate-100">{title}</span>
        <span className="mt-1 block text-xs leading-5 text-slate-400">{description}</span>
      </span>
      <button
        type="button"
        role="switch"
        aria-checked={value}
        aria-label={title}
        disabled={disabled}
        onClick={() => onChange(!value)}
        className={`relative h-6 w-11 shrink-0 rounded-full transition-colors disabled:cursor-not-allowed ${value ? "bg-emerald-700" : "bg-slate-700"}`}
      >
        <span className={`absolute top-1 h-4 w-4 rounded-full bg-white transition-transform ${value ? "translate-x-6" : "translate-x-1"}`} />
      </button>
    </div>
  );
}

export default function SecurityCenter({ onBack, onOpenExaPay, onOpenExaCard }) {
  const { user, request, logout } = useAuth();
  const loadedPreferences = useMemo(() => readSecurityPreferences(), []);
  const [preferences, setPreferences] = useState(loadedPreferences.preferences);
  const [preferencesError, setPreferencesError] = useState(loadedPreferences.error);
  const [page, setPage] = useState("center");
  const [sessions, setSessions] = useState([]);
  const [devices, setDevices] = useState([]);
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [info, setInfo] = useState(null);
  const [pendingAction, setPendingAction] = useState(null);
  const [reauthPassword, setReauthPassword] = useState("");
  const [reauthCode, setReauthCode] = useState("");
  const [actionError, setActionError] = useState("");
  const [saving, setSaving] = useState(false);

  const twoFactorEnabled = user?.two_factor_enabled === true || user?.two_factor_enabled === 1;
  const autoLockLabel = autoLockChoices.find((item) => item.seconds === preferences.autoLockSeconds)?.label || "Immediately";
  const pageTitle = page === "sessions" ? "Devices & sessions" : page === "history" ? "Security activity" : "Security";

  const loadSessions = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const payload = await request("/api/auth/sessions", { method: "GET" });
      const data = payload?.data && !Array.isArray(payload.data) ? payload.data : {};
      setSessions(Array.isArray(data.sessions) ? data.sessions : Array.isArray(payload.sessions) ? payload.sessions : []);
      setDevices(Array.isArray(data.devices) ? data.devices : Array.isArray(payload.devices) ? payload.devices : []);
    } catch (requestError) {
      setError(messageFrom(requestError, "Active sessions could not be loaded."));
      setSessions([]);
      setDevices([]);
    } finally {
      setLoading(false);
    }
  }, [request]);

  const loadHistory = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const payload = await request("/api/logs/my-activity?type=security&per_page=20", { method: "GET" });
      const data = payload?.data;
      setEvents(Array.isArray(data) ? data : Array.isArray(data?.data) ? data.data : []);
    } catch (requestError) {
      setError(messageFrom(requestError, "Security activity could not be loaded."));
      setEvents([]);
    } finally {
      setLoading(false);
    }
  }, [request]);

  useEffect(() => {
    if (page === "sessions") void loadSessions();
    if (page === "history") void loadHistory();
  }, [loadHistory, loadSessions, page]);

  const updatePreference = (key, value) => {
    setError("");
    setSuccess("");
    try {
      const next = saveSecurityPreferences({ ...preferences, [key]: value });
      setPreferences(next);
      setPreferencesError("");
      setSuccess("Security preference saved in this browser.");
    } catch {
      setPreferencesError("This browser could not save the security preference. No change was applied.");
    }
  };

  const beginSensitiveAction = (action) => {
    setPendingAction(action);
    setReauthPassword("");
    setReauthCode("");
    setActionError("");
    setError("");
  };

  const cancelSensitiveAction = () => {
    if (saving) return;
    setPendingAction(null);
    setReauthPassword("");
    setReauthCode("");
    setActionError("");
  };

  const confirmSensitiveAction = async (event) => {
    event.preventDefault();
    if (!pendingAction || !reauthPassword || saving) {
      setActionError("Enter your account password to continue.");
      return;
    }

    setSaving(true);
    setActionError("");
    try {
      await request("/api/auth/reauthenticate", {
        method: "POST",
        body: JSON.stringify({ password: reauthPassword, two_factor_code: reauthCode || undefined }),
      });

      if (pendingAction.kind === "revoke-session") {
        await request(`/api/auth/sessions/${encodeURIComponent(String(pendingAction.session.id))}`, { method: "DELETE" });
        if (pendingAction.session.is_current) {
          await logout();
          return;
        }
        setSuccess("Session revoked.");
        await loadSessions();
      } else {
        const response = await request("/api/auth/logout-others", { method: "POST" });
        setSuccess(`${Number(response?.revoked_count ?? 0)} other session(s) revoked.`);
        await loadSessions();
      }

      setPendingAction(null);
      setReauthPassword("");
      setReauthCode("");
    } catch (requestError) {
      setActionError(messageFrom(requestError, "The sensitive security action could not be completed."));
    } finally {
      setSaving(false);
    }
  };

  const revealInfo = (title, description) => setInfo({ title, description });
  const refreshPage = page === "sessions" ? loadSessions : loadHistory;

  return (
    <main className="min-h-screen bg-[#090b12] text-slate-100">
      <div className="mx-auto w-full max-w-3xl px-4 pb-12 pt-4 sm:px-6 sm:pt-6">
        <header className="mb-5 flex items-center gap-3">
          <button type="button" onClick={page === "center" ? onBack : () => { setPage("center"); setError(""); setSuccess(""); }} className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-white/10 bg-[#111720] text-slate-300 transition hover:bg-white/[.06]" aria-label="Back">
            <ArrowLeft size={18} />
          </button>
          <div className="min-w-0 flex-1">
            <h1 className="font-['Sora'] text-xl font-semibold text-white sm:text-2xl">{pageTitle}</h1>
            <p className="mt-1 text-xs text-slate-400">Protect your ExaEarn account and devices.</p>
          </div>
          <span className="grid h-10 w-10 place-items-center rounded-xl border border-emerald-300/15 bg-emerald-300/[.05] text-emerald-300" aria-hidden="true">
            <ShieldCheck size={19} />
          </span>
        </header>

        {error || preferencesError ? (
          <div role="alert" className="mb-3 flex items-start gap-2 rounded-xl border border-rose-300/20 bg-rose-300/[.06] p-3 text-sm text-rose-200">
            <AlertCircle size={17} className="mt-0.5 shrink-0" /> {error || preferencesError}
          </div>
        ) : null}
        {success ? (
          <div role="status" className="mb-3 flex items-start gap-2 rounded-xl border border-emerald-300/20 bg-emerald-300/[.05] p-3 text-sm text-emerald-200">
            <CheckCircle2 size={17} className="mt-0.5 shrink-0" /> {success}
          </div>
        ) : null}

        {page === "center" ? (
          <>
            <div className="flex items-start gap-3 rounded-2xl border border-amber-300/15 bg-[#111720] p-3.5">
              <ShieldAlert size={18} className="mt-0.5 shrink-0 text-amber-300" />
              <div>
                <p className="text-sm font-semibold text-slate-100">Browser security capabilities</p>
                <p className="mt-1 text-xs leading-5 text-slate-400">Web pages cannot access the operating system keychain, enforce a secure window, or reliably detect screenshots. Browser preferences are not secure storage for passwords, PINs, keys, or recovery phrases.</p>
              </div>
            </div>

            <Section title="Vault & encryption">
              <SecurityRow icon={LockKeyhole} title="Financial vault encryption" description="No browser-based financial credential vault is configured. The web app does not expose or store wallet signing keys or recovery phrases here." status="No local credential vault configured" tone="amber" />
              <SecurityRow icon={KeyRound} title="Browser session storage" description="The existing web auth flow persists its bearer token in JavaScript-readable localStorage. HttpOnly, Secure, SameSite session-cookie auth is not integrated." status="Browser token storage requires backend migration" tone="amber" />
              <SecurityRow icon={ShieldCheck} title="Account recovery" description="Use ExaEarn account recovery. Recovery codes, phrases, and signing secrets are never displayed here." status="No recovery secret stored in this page" last />
            </Section>

            <Section title="Authentication">
              <SecurityRow icon={KeyRound} title="ExaEarn Passcode" description="A separate 7-digit account-access credential. Enrollment and recovery are not connected to the backend; it remains independent from your account password, device PIN, and Transaction PIN." status="Not enrolled" tone="amber" />
              <SecurityRow icon={WifiOff} title="Offline Smart Access" description="Requires a previously enrolled account session. Offline ExaPay remains SIMUSDT simulation only and requires later reconciliation." status="Limited · simulation only" tone="muted" />
              <SecurityRow icon={Fingerprint} title="Biometric unlock" description="Native browser biometric unlock is not integrated with this account API. Passkey or WebAuthn enrollment is not exposed here." status="Unavailable in this web client" tone="muted" />
              <div className="border-b border-white/[.06] px-3 pb-3 pt-3">
                <div className="flex items-center gap-3 px-0">
                  <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/[.04] text-slate-400"><Clock3 size={17} /></span>
                  <span className="min-w-0 flex-1">
                    <span className="block text-sm font-medium text-slate-100">App auto-lock</span>
                    <span className="mt-1 block text-xs leading-5 text-slate-400">Lock this web app after inactivity or when its tab is backgrounded; unlock requires account reauthentication.</span>
                  </span>
                  <span className="text-xs font-medium text-slate-300">{autoLockLabel}</span>
                </div>
                <div className="mt-3 grid grid-cols-2 gap-1.5 sm:grid-cols-4">
                  {autoLockChoices.map((choice) => (
                    <button
                      key={choice.seconds}
                      type="button"
                      onClick={() => updatePreference("autoLockSeconds", choice.seconds)}
                      aria-pressed={preferences.autoLockSeconds === choice.seconds}
                      className={`min-h-9 rounded-lg border px-2 text-[11px] font-medium transition ${preferences.autoLockSeconds === choice.seconds ? "border-emerald-300/35 bg-emerald-300/10 text-emerald-200" : "border-white/[.07] bg-[#0b1017] text-slate-400 hover:text-white"}`}
                    >
                      {choice.label}
                    </button>
                  ))}
                </div>
              </div>
              <SecurityRow icon={KeyRound} title="Change password" description="Password changes require a supported server endpoint. No local or simulated password update is offered." status="Unavailable: backend endpoint required" tone="amber" />
              <SecurityRow icon={LockKeyhole} title="App PIN" description="A server-backed PIN enrollment and secure recovery flow is not available in this web client." status="Unavailable" tone="muted" />
              <SecurityRow
                icon={ShieldCheck}
                title="Two-factor authentication"
                description={twoFactorEnabled ? "The account API flag is enabled, but this client cannot confirm authenticator enrollment." : "Authenticator setup and verification are not available in this web client."}
                status={twoFactorEnabled ? "Reported enabled by API; enrollment unverified" : "Unavailable"}
                tone="amber"
                onClick={() => revealInfo("Two-factor authentication", twoFactorEnabled
                  ? "The account API reports its 2FA flag as enabled, but this client cannot confirm that an authenticator is enrolled. The backend verification endpoint currently returns HTTP 501. Do not rely on this flag as proof of working MFA."
                  : "This client has no MFA enrollment endpoint. The backend verification endpoint currently returns HTTP 501, so this control remains unavailable rather than simulating enrollment.")}
                action="Details"
                last
              />
            </Section>

            <Section title="Privacy">
              <PreferenceSwitch
                icon={preferences.hideBalances ? <EyeOff size={17} /> : <Eye size={17} />}
                title="Hide sensitive balances"
                description="Mask the dashboard portfolio value. Use the dashboard balance control to reveal it temporarily."
                value={preferences.hideBalances}
                onChange={(value) => updatePreference("hideBalances", value)}
              />
              <SecurityRow icon={Eye} title="Tap to reveal" description={preferences.hideBalances ? "Balance masking is enabled on the dashboard." : "Balance masking is currently off."} status={preferences.hideBalances ? "Available on dashboard" : "Enable Hide sensitive balances"} tone={preferences.hideBalances ? "green" : "muted"} />
              <SecurityRow icon={MonitorSmartphone} title="Screenshot protection" description="Web browsers do not provide reliable screenshot prevention or capture detection. This cannot be enabled from a web page." status="Unsupported by browser platform" tone="amber" />
              <PreferenceSwitch icon={<BellRing size={17} />} title="Notification privacy" description="Hide notification titles and previews inside ExaEarn Activity Center. Browser and operating-system notification delivery is managed separately." value={preferences.privateNotifications} onChange={(value) => updatePreference("privateNotifications", value)} />
              <SecurityRow icon={Clipboard} title="Clipboard privacy" description="The web app does not inspect clipboard history. Browsers cannot reliably clear clipboard data after another page or application writes it." status="External clipboard clearing unsupported" tone="muted" last />
            </Section>

            <Section title="Account & device security">
              <SecurityRow icon={Smartphone} title="Devices & active sessions" description="Review account API sessions and login-device records. Session revocation is enforced by the server." action="Manage" onClick={() => setPage("sessions")} />
              <SecurityRow icon={Activity} title="Security activity" description="View security events recorded by the account API." action="View" onClick={() => setPage("history")} />
              <SecurityRow icon={BellRing} title="New login alerts" description="Alert delivery preferences are not exposed by the current account API." status="Unavailable: backend configuration required" tone="amber" />
              <SecurityRow icon={MonitorSmartphone} title="Trusted devices" description="The API provides login-device records but no independent trusted-device enrollment or revocation endpoint." status="Device records are read-only" tone="amber" last />
            </Section>

            <Section title="Financial security">
              <SecurityRow icon={ArrowLeftRight} title="ExaPay security" description="Online transfers and experimental offline payments remain in the ExaPay module. Offline device enrollment is not proof of hardware-secured value." action="Open" onClick={onOpenExaPay} />
              <SecurityRow icon={CreditCard} title="ExaCard security" description="Card controls and freeze remain in the ExaCard module." action="Open" onClick={onOpenExaCard} />
              <SecurityRow icon={Wallet} title="Withdrawals, transfers & spending limits" description="Confirmation requirements and limits must be enforced by the financial API." status="No mobile/web preference endpoint" tone="amber" />
              <SecurityRow icon={MapPin} title="Address allowlisting" description="Address allowlist management is not exposed by this client." status="Unavailable" tone="muted" last />
            </Section>
          </>
        ) : page === "sessions" ? (
          <>
            <Section title="Active API sessions">
              {loading ? <div className="flex items-center justify-center gap-2 p-6 text-sm text-slate-400"><Loader2 size={16} className="animate-spin" />Loading active sessions...</div> : null}
              {!loading && sessions.length === 0 ? <p className="p-4 text-sm text-slate-400">No session records were returned by the account API.</p> : null}
              {sessions.map((session, index) => (
                <SecurityRow
                  key={String(session.id)}
                  icon={session.is_current ? Smartphone : Laptop}
                  title={session.name || "ExaEarn session"}
                  description={`${session.is_current ? "This device · " : ""}Last active ${formatWhen(session.last_used_at || session.created_at)}`}
                  status={session.risk_state || "Status not reported"}
                  tone={session.risk_state === "NORMAL" ? "green" : "muted"}
                  action={session.is_current ? "This device" : "Revoke"}
                  onClick={() => beginSensitiveAction({ kind: "revoke-session", session })}
                  disabled={Boolean(session.is_current)}
                  last={index === sessions.length - 1 && devices.length === 0}
                />
              ))}
            </Section>
            <Section title="Login-device records">
              {devices.length ? devices.map((device, index) => (
                <SecurityRow
                  key={String(device.id)}
                  icon={Smartphone}
                  title={device.device_name || "Device"}
                  description={`${device.approximate_region || "Region unavailable"} · Last login ${formatWhen(device.last_active)}`}
                  status="Recorded by account API"
                  last={index === devices.length - 1}
                />
              )) : <p className="p-4 text-sm text-slate-400">No login-device records were returned.</p>}
            </Section>
            <button type="button" onClick={() => beginSensitiveAction({ kind: "revoke-others" })} className="mt-4 flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-rose-300/20 bg-rose-300/[.05] px-4 text-sm font-semibold text-rose-200 hover:bg-rose-300/10">
              <ShieldAlert size={16} /> Sign out other sessions
            </button>
            <p className="mt-3 text-xs leading-5 text-slate-500">Revocation requires recent account authentication. Device history cannot be revoked independently.</p>
          </>
        ) : (
          <Section title="Recent security events">
            {loading ? <div className="flex items-center justify-center gap-2 p-6 text-sm text-slate-400"><Loader2 size={16} className="animate-spin" />Loading security activity...</div> : null}
            {!loading && events.length === 0 ? <p className="p-4 text-sm text-slate-400">No security activity records were returned.</p> : null}
            {events.map((event, index) => (
              <SecurityRow
                key={String(event.id)}
                icon={event.status === "failed" ? AlertCircle : ShieldCheck}
                title={String(event.action || "Security event").replace(/[_-]/g, " ")}
                description={`${formatWhen(event.created_at)}${event.device ? ` · ${event.device}` : ""}`}
                status={event.status || "Recorded"}
                tone={event.status === "failed" ? "red" : "muted"}
                last={index === events.length - 1}
              />
            ))}
          </Section>
        )}

        {page !== "center" ? (
          <button type="button" onClick={() => void refreshPage()} disabled={loading} className="mx-auto mt-4 flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-slate-300 disabled:opacity-50">
            {loading ? <Loader2 size={15} className="animate-spin" /> : <RefreshCw size={15} />}
            Refresh
          </button>
        ) : null}
      </div>

      {pendingAction ? (
        <div className="fixed inset-0 z-[100] grid place-items-center bg-black/75 p-4" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) cancelSensitiveAction(); }}>
          <form onSubmit={confirmSensitiveAction} className="w-full max-w-md rounded-2xl border border-white/10 bg-[#111720] p-5" role="dialog" aria-modal="true" aria-labelledby="reauth-title">
            <h2 id="reauth-title" className="font-['Sora'] text-lg font-semibold text-white">Confirm it&apos;s you</h2>
            <p className="mt-2 text-sm leading-6 text-slate-400">
              {pendingAction.kind === "revoke-others"
                ? "Sign out all other API sessions? This device will remain signed in. Recent account authentication is required."
                : `Revoke the ${pendingAction.session.name || "selected"} API session? Recent account authentication is required.`}
            </p>
            <label className="mt-4 block text-xs font-medium text-slate-300">
              Account password
              <input type="password" autoComplete="current-password" value={reauthPassword} onChange={(event) => setReauthPassword(event.target.value)} className="mt-2 h-11 w-full rounded-xl border border-white/10 bg-[#0b1017] px-3 text-sm text-white outline-none focus:border-slate-400/50" />
            </label>
            {twoFactorEnabled ? (
              <label className="mt-3 block text-xs font-medium text-slate-300">
                Two-factor code (if configured)
                <input inputMode="numeric" autoComplete="one-time-code" maxLength={6} value={reauthCode} onChange={(event) => setReauthCode(event.target.value.replace(/\D/g, "").slice(0, 6))} className="mt-2 h-11 w-full rounded-xl border border-white/10 bg-[#0b1017] px-3 text-sm text-white outline-none focus:border-slate-400/50" />
              </label>
            ) : null}
            {actionError ? <p role="alert" className="mt-3 flex items-start gap-2 text-xs text-rose-300"><AlertCircle size={15} className="mt-0.5 shrink-0" />{actionError}</p> : null}
            <div className="mt-5 grid grid-cols-2 gap-2">
              <button type="button" onClick={cancelSensitiveAction} disabled={saving} className="min-h-10 rounded-xl border border-white/10 text-sm text-slate-300 disabled:opacity-50">Cancel</button>
              <button type="submit" disabled={saving} className="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-slate-100 px-3 text-sm font-semibold text-slate-950 disabled:opacity-50">
                {saving ? <Loader2 size={15} className="animate-spin" /> : <LockKeyhole size={15} />}
                Continue
              </button>
            </div>
          </form>
        </div>
      ) : null}

      {info ? (
        <div className="fixed inset-0 z-[100] grid place-items-center bg-black/75 p-4" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setInfo(null); }}>
          <section className="w-full max-w-md rounded-2xl border border-white/10 bg-[#111720] p-5" role="dialog" aria-modal="true" aria-labelledby="security-info-title">
            <h2 id="security-info-title" className="font-['Sora'] text-lg font-semibold text-white">{info.title}</h2>
            <p className="mt-2 text-sm leading-6 text-slate-400">{info.description}</p>
            <button type="button" onClick={() => setInfo(null)} className="mt-5 min-h-10 w-full rounded-xl bg-slate-100 text-sm font-semibold text-slate-950">Understood</button>
          </section>
        </div>
      ) : null}
    </main>
  );
}
