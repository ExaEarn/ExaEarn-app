import { useCallback, useEffect, useRef, useState } from "react";
import { AlertCircle, Loader2, LockKeyhole, LogOut } from "lucide-react";

import { useAuth } from "../context/AuthContext";
import { readSecurityPreferences, SECURITY_PREFERENCES_EVENT } from "../security/securityPreferences";

export default function SecurityLifecycleGuard({ children }) {
  const { user, request, logout } = useAuth();
  const [preferences, setPreferences] = useState(() => readSecurityPreferences().preferences);
  const [locked, setLocked] = useState(false);
  const [password, setPassword] = useState("");
  const [twoFactorCode, setTwoFactorCode] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const lastActivityAt = useRef(Date.now());
  const backgroundedAt = useRef(null);
  const lockedRef = useRef(false);

  const lock = useCallback(() => {
    if (!user || lockedRef.current) return;
    lockedRef.current = true;
    setLocked(true);
    setPassword("");
    setTwoFactorCode("");
    setError("");
  }, [user]);

  useEffect(() => {
    const updatePreferences = (event) => {
      setPreferences(event.detail || readSecurityPreferences().preferences);
    };
    const updateStoredPreferences = (event) => {
      if (event.key === "exaearn-security-preferences-v1") {
        setPreferences(readSecurityPreferences().preferences);
      }
    };
    window.addEventListener(SECURITY_PREFERENCES_EVENT, updatePreferences);
    window.addEventListener("storage", updateStoredPreferences);
    return () => {
      window.removeEventListener(SECURITY_PREFERENCES_EVENT, updatePreferences);
      window.removeEventListener("storage", updateStoredPreferences);
    };
  }, []);

  useEffect(() => {
    if (!user) {
      lockedRef.current = false;
      setLocked(false);
      return undefined;
    }
    lastActivityAt.current = Date.now();
    backgroundedAt.current = null;

    const handleVisibility = () => {
      if (document.visibilityState !== "visible") {
        if (backgroundedAt.current === null) backgroundedAt.current = Date.now();
        return;
      }

      const backgrounded = backgroundedAt.current;
      backgroundedAt.current = null;
      if (backgrounded === null) return;
      const elapsedSeconds = (Date.now() - backgrounded) / 1000;
      if (preferences.autoLockSeconds === 0 || elapsedSeconds >= preferences.autoLockSeconds) {
        lock();
      } else {
        lastActivityAt.current = Date.now();
      }
    };

    const recordActivity = () => {
      if (!lockedRef.current && document.visibilityState === "visible") lastActivityAt.current = Date.now();
    };

    document.addEventListener("visibilitychange", handleVisibility);
    window.addEventListener("pointerdown", recordActivity, true);
    window.addEventListener("keydown", recordActivity, true);
    const timer = window.setInterval(() => {
      if (
        document.visibilityState === "visible"
        && preferences.autoLockSeconds > 0
        && (Date.now() - lastActivityAt.current) / 1000 >= preferences.autoLockSeconds
      ) {
        lock();
      }
    }, 1000);

    return () => {
      document.removeEventListener("visibilitychange", handleVisibility);
      window.removeEventListener("pointerdown", recordActivity, true);
      window.removeEventListener("keydown", recordActivity, true);
      window.clearInterval(timer);
    };
  }, [lock, preferences.autoLockSeconds, user]);

  const unlock = async (event) => {
    event.preventDefault();
    if (!password || busy) {
      setError("Enter your account password to unlock.");
      return;
    }

    setBusy(true);
    setError("");
    try {
      await request("/api/auth/reauthenticate", {
        method: "POST",
        body: JSON.stringify({ password, two_factor_code: twoFactorCode || undefined }),
      });
      lockedRef.current = false;
      setLocked(false);
      setPassword("");
      setTwoFactorCode("");
      lastActivityAt.current = Date.now();
    } catch (requestError) {
      setError(requestError?.message || "Account reauthentication failed.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <div aria-hidden={locked} className={locked ? "invisible pointer-events-none" : ""}>
        {children}
      </div>
      {locked && user ? (
        <div className="fixed inset-0 z-[10000] grid min-h-screen place-items-center bg-[#080a0e] p-4" role="dialog" aria-modal="true" aria-labelledby="security-lock-title">
          <form onSubmit={unlock} className="w-full max-w-md rounded-2xl border border-white/10 bg-[#111720] p-6 shadow-2xl">
            <span className="grid h-11 w-11 place-items-center rounded-xl border border-emerald-300/20 bg-emerald-300/5 text-emerald-300">
              <LockKeyhole size={19} aria-hidden="true" />
            </span>
            <h1 id="security-lock-title" className="mt-4 font-['Sora'] text-xl font-semibold text-white">ExaEarn is locked</h1>
            <p className="mt-2 text-sm leading-6 text-slate-400">Reauthenticate with your account password to continue. App lock does not authorize financial transactions.</p>
            <label className="mt-5 block text-xs font-medium text-slate-300">
              Account password
              <input
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                className="mt-2 h-11 w-full rounded-xl border border-white/10 bg-[#0b1017] px-3 text-sm text-white outline-none focus:border-slate-400/50"
              />
            </label>
            {user?.two_factor_enabled ? (
              <label className="mt-3 block text-xs font-medium text-slate-300">
                Authenticator code
                <input
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  maxLength={6}
                  value={twoFactorCode}
                  onChange={(event) => setTwoFactorCode(event.target.value.replace(/\D/g, "").slice(0, 6))}
                  className="mt-2 h-11 w-full rounded-xl border border-white/10 bg-[#0b1017] px-3 text-sm text-white outline-none focus:border-slate-400/50"
                />
              </label>
            ) : null}
            {error ? <p role="alert" className="mt-3 flex items-start gap-2 text-sm text-rose-300"><AlertCircle size={16} className="mt-0.5 shrink-0" />{error}</p> : null}
            <button type="submit" disabled={busy} className="mt-5 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 text-sm font-semibold text-slate-950 disabled:opacity-60">
              {busy ? <Loader2 size={16} className="animate-spin" /> : <LockKeyhole size={16} />}
              Unlock securely
            </button>
            <button type="button" onClick={() => void logout()} className="mt-3 inline-flex w-full items-center justify-center gap-2 py-2 text-sm text-slate-400 hover:text-white">
              <LogOut size={15} /> Sign out instead
            </button>
          </form>
        </div>
      ) : null}
    </>
  );
}
