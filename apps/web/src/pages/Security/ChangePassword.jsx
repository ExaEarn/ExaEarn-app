import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  Eye,
  EyeOff,
  Loader2,
  LockKeyhole,
  ShieldAlert,
  ShieldCheck,
} from "lucide-react";

const lastPasswordKey = "exaearn-last-password-hash";
const securityLogKey = "exaearn-security-log";

function ChangePassword({ onBack, onBackToSettings }) {
  const [form, setForm] = useState({
    currentPassword: "",
    newPassword: "",
    confirmPassword: "",
    twoFactorCode: "",
  });
  const [showPassword, setShowPassword] = useState({
    currentPassword: false,
    newPassword: false,
    confirmPassword: false,
  });
  const [twoFactorEnabled] = useState(true);
  const [autoLogoutAllSessions, setAutoLogoutAllSessions] = useState(true);
  const [codeCountdown, setCodeCountdown] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState({});
  const [strength, setStrength] = useState({ score: 0, label: "Weak" });
  const [showSuccess, setShowSuccess] = useState(false);

  useEffect(() => {
    if (!codeCountdown) return undefined;
    const timer = setInterval(() => {
      setCodeCountdown((prev) => (prev > 0 ? prev - 1 : 0));
    }, 1000);
    return () => clearInterval(timer);
  }, [codeCountdown]);

  useEffect(() => {
    const timer = setTimeout(() => {
      setStrength(calculateStrength(form.newPassword));
      setErrors((prev) => ({ ...prev, ...validateForm(form, twoFactorEnabled) }));
    }, 260);
    return () => clearTimeout(timer);
  }, [form, twoFactorEnabled]);

  const requirements = useMemo(() => {
    const value = form.newPassword;
    return {
      minLength: value.length >= 8,
      upperCase: /[A-Z]/.test(value),
      number: /\d/.test(value),
      special: /[^A-Za-z0-9]/.test(value),
    };
  }, [form.newPassword]);

  const hasAnyError = Object.values(errors).some(Boolean);
  const canSubmit =
    form.currentPassword &&
    form.newPassword &&
    form.confirmPassword &&
    (!twoFactorEnabled || form.twoFactorCode.length >= 6) &&
    !hasAnyError &&
    !submitting;

  const updateField = (field, value) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const sendTwoFactorCode = () => {
    if (codeCountdown > 0) return;
    setCodeCountdown(60);
  };

  const submitChange = async (event) => {
    event.preventDefault();
    const nextErrors = validateForm(form, twoFactorEnabled);
    setErrors(nextErrors);
    if (Object.values(nextErrors).some(Boolean)) return;

    setSubmitting(true);
    try {
      if (form.currentPassword === form.newPassword) {
        setErrors((prev) => ({ ...prev, newPassword: "New password must be different from your current password." }));
        return;
      }

      const newHash = await hashString(form.newPassword);
      const lastHash = localStorage.getItem(lastPasswordKey);
      if (lastHash && newHash === lastHash) {
        setErrors((prev) => ({ ...prev, newPassword: "You cannot reuse your last password." }));
        return;
      }

      await new Promise((resolve) => setTimeout(resolve, 1200));

      localStorage.setItem(lastPasswordKey, newHash);
      addSecurityLog({
        event: "Password changed",
        at: new Date().toISOString(),
        autoLogoutAllSessions,
      });

      setShowSuccess(true);
      setForm({
        currentPassword: "",
        newPassword: "",
        confirmPassword: "",
        twoFactorCode: "",
      });
    } catch (error) {
      setErrors((prev) => ({ ...prev, form: "Failed to update password. Please try again." }));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <main className="min-h-screen bg-[#070B14] text-white">
      <header
        className="sticky top-0 z-30 border-b border-[#D4AF37]/20 bg-gradient-to-r from-[#121A2A]/95 via-[#0E1524]/95 to-[#0A0F1D]/95 backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-2xl px-4 pb-3 pt-3 sm:px-6">
          <div className="flex items-start gap-3">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-white/15 bg-[#111827] p-2 text-[#E6EAF2] hover:border-[#D4AF37]/60"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div>
              <h1 className="text-lg font-semibold text-[#F8F1DE] sm:text-xl">Change Password</h1>
              <p className="text-xs text-[#B8C0CF] sm:text-sm">Update your account password securely</p>
            </div>
          </div>
        </div>
      </header>

      <section className="mx-auto w-full max-w-2xl px-4 pb-8 pt-5 sm:px-6">
        <div className="mb-4 space-y-2">
          <div className="rounded-2xl border border-[#D4AF37]/25 bg-[#0C1424] p-3 text-sm text-[#D7DDEA]">
            <p className="flex items-center gap-2">
              <ShieldCheck className="h-4 w-4 text-[#D4AF37]" />
              For your security, choose a strong and unique password.
            </p>
          </div>
          <div className="rounded-2xl border border-[#EF4444]/35 bg-[#EF4444]/10 p-3 text-sm text-[#FECACA]">
            <p className="flex items-center gap-2">
              <ShieldAlert className="h-4 w-4 text-[#F87171]" />
              ExaEarn will never ask for your password.
            </p>
          </div>
        </div>

        <form onSubmit={submitChange} className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4 shadow-[0_14px_28px_rgba(0,0,0,0.3)]">
          <PasswordField
            label="Current Password"
            value={form.currentPassword}
            onChange={(value) => updateField("currentPassword", value)}
            visible={showPassword.currentPassword}
            onToggle={() => setShowPassword((prev) => ({ ...prev, currentPassword: !prev.currentPassword }))}
            error={errors.currentPassword}
          />
          <PasswordField
            label="New Password"
            value={form.newPassword}
            onChange={(value) => updateField("newPassword", value)}
            visible={showPassword.newPassword}
            onToggle={() => setShowPassword((prev) => ({ ...prev, newPassword: !prev.newPassword }))}
            error={errors.newPassword}
          />
          <PasswordField
            label="Confirm New Password"
            value={form.confirmPassword}
            onChange={(value) => updateField("confirmPassword", value)}
            visible={showPassword.confirmPassword}
            onToggle={() => setShowPassword((prev) => ({ ...prev, confirmPassword: !prev.confirmPassword }))}
            error={errors.confirmPassword}
          />

          <section className="mb-4 rounded-xl border border-white/10 bg-[#0C1424] p-3">
            <div className="mb-2 flex items-center justify-between text-sm">
              <p className="text-[#D7DDEA]">Password Strength</p>
              <p className={`${strength.score >= 3 ? "text-[#86EFAC]" : strength.score >= 2 ? "text-[#FDE68A]" : "text-[#FCA5A5]"}`}>
                {strength.label}
              </p>
            </div>
            <div className="mb-3 h-2 overflow-hidden rounded-full bg-[#1F2937]">
              <div
                className={`h-full transition-all ${
                  strength.score >= 3 ? "bg-[#22C55E]" : strength.score >= 2 ? "bg-[#F59E0B]" : "bg-[#EF4444]"
                }`}
                style={{ width: `${(strength.score / 4) * 100}%` }}
              />
            </div>
            <ul className="space-y-1 text-xs text-[#AEB7C7]">
              <RequirementRow ok={requirements.minLength} label="Minimum 8 characters" />
              <RequirementRow ok={requirements.upperCase} label="At least one uppercase letter" />
              <RequirementRow ok={requirements.number} label="At least one number" />
              <RequirementRow ok={requirements.special} label="At least one special character" />
            </ul>
          </section>

          {twoFactorEnabled ? (
            <section className="mb-4 rounded-xl border border-white/10 bg-[#0C1424] p-3">
              <p className="mb-2 text-sm text-[#D7DDEA]">2FA Verification</p>
              <div className="flex gap-2">
                <input
                  value={form.twoFactorCode}
                  onChange={(e) => updateField("twoFactorCode", e.target.value.replace(/\D/g, "").slice(0, 6))}
                  placeholder="Enter 6-digit code"
                  className={`h-10 flex-1 rounded-xl border bg-[#111827] px-3 text-sm outline-none ${
                    errors.twoFactorCode ? "border-[#EF4444]/65" : "border-white/15 focus:border-[#D4AF37]/60"
                  }`}
                />
                <button
                  type="button"
                  onClick={sendTwoFactorCode}
                  disabled={codeCountdown > 0}
                  className="rounded-xl border border-white/15 bg-[#111827] px-3 text-xs text-[#D7DDEA] disabled:opacity-50"
                >
                  {codeCountdown > 0 ? `Resend ${codeCountdown}s` : "Send Code"}
                </button>
              </div>
              {errors.twoFactorCode ? <p className="mt-1 text-xs text-[#FCA5A5]">{errors.twoFactorCode}</p> : null}
            </section>
          ) : null}

          <label className="mb-4 flex items-center gap-2 text-sm text-[#C4CCD9]">
            <input
              type="checkbox"
              checked={autoLogoutAllSessions}
              onChange={(e) => setAutoLogoutAllSessions(e.target.checked)}
            />
            Auto logout all active sessions after password change
          </label>

          {errors.form ? <p className="mb-3 text-sm text-[#FCA5A5]">{errors.form}</p> : null}

          <button
            type="submit"
            disabled={!canSubmit}
            className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] py-3 text-sm font-semibold text-[#111827] shadow-[0_10px_24px_rgba(212,175,55,0.28)] disabled:cursor-not-allowed disabled:opacity-45"
          >
            {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <LockKeyhole className="h-4 w-4" />}
            {submitting ? "Updating..." : "Save Changes"}
          </button>
        </form>
      </section>

      {showSuccess ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div className="w-full max-w-sm rounded-2xl border border-[#D4AF37]/35 bg-[#0E1524] p-5 text-center">
            <ShieldCheck className="mx-auto h-8 w-8 text-[#D4AF37]" />
            <h3 className="mt-2 text-lg font-semibold text-[#F8F1DE]">Password Updated Successfully</h3>
            <p className="mt-1 text-sm text-[#C4CCD9]">If this wasn&apos;t you, contact support immediately.</p>
            <button
              type="button"
              onClick={() => {
                setShowSuccess(false);
                onBackToSettings?.();
              }}
              className="mt-4 w-full rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] py-2.5 text-sm font-semibold text-[#111827]"
            >
              Back to Settings
            </button>
          </div>
        </div>
      ) : null}
    </main>
  );
}

function PasswordField({ label, value, onChange, visible, onToggle, error }) {
  return (
    <label className="mb-3 block">
      <span className="mb-1.5 block text-sm text-[#D7DDEA]">{label}</span>
      <div className={`flex items-center rounded-xl border bg-[#111827] px-3 ${error ? "border-[#EF4444]/65" : "border-white/15 focus-within:border-[#D4AF37]/60"}`}>
        <input
          type={visible ? "text" : "password"}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="h-10 flex-1 bg-transparent text-sm outline-none"
        />
        <button type="button" onClick={onToggle} className="text-[#9BA5B7] hover:text-[#E6EAF2]">
          {visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        </button>
      </div>
      {error ? <p className="mt-1 text-xs text-[#FCA5A5]">{error}</p> : null}
    </label>
  );
}

function RequirementRow({ ok, label }) {
  return (
    <li className={`flex items-center gap-1 ${ok ? "text-[#86EFAC]" : "text-[#AEB7C7]"}`}>
      <span className={`h-1.5 w-1.5 rounded-full ${ok ? "bg-[#22C55E]" : "bg-[#6B7280]"}`} />
      {label}
    </li>
  );
}

function calculateStrength(password) {
  let score = 0;
  if (password.length >= 8) score += 1;
  if (/[A-Z]/.test(password)) score += 1;
  if (/\d/.test(password)) score += 1;
  if (/[^A-Za-z0-9]/.test(password)) score += 1;

  if (score <= 1) return { score, label: "Weak" };
  if (score <= 2) return { score, label: "Medium" };
  return { score, label: "Strong" };
}

function validateForm(form, twoFactorEnabled) {
  const next = {};
  next.currentPassword = form.currentPassword ? "" : "Current password is required.";
  next.newPassword = "";
  next.confirmPassword = "";
  next.twoFactorCode = "";

  if (!form.newPassword) next.newPassword = "New password is required.";
  if (form.newPassword && form.newPassword.length < 8) next.newPassword = "Password must be at least 8 characters.";
  if (form.newPassword && !/[A-Z]/.test(form.newPassword)) next.newPassword = "Include at least one uppercase letter.";
  if (form.newPassword && !/\d/.test(form.newPassword)) next.newPassword = "Include at least one number.";
  if (form.newPassword && !/[^A-Za-z0-9]/.test(form.newPassword)) next.newPassword = "Include at least one special character.";

  if (!form.confirmPassword) next.confirmPassword = "Please confirm your new password.";
  if (form.confirmPassword && form.confirmPassword !== form.newPassword) {
    next.confirmPassword = "Passwords do not match.";
  }

  if (twoFactorEnabled && form.twoFactorCode.length < 6) {
    next.twoFactorCode = "A valid 6-digit 2FA code is required.";
  }

  return next;
}

async function hashString(value) {
  if (!window.crypto?.subtle) return btoa(value);
  const bytes = new TextEncoder().encode(value);
  const hashBuffer = await window.crypto.subtle.digest("SHA-256", bytes);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  return hashArray.map((item) => item.toString(16).padStart(2, "0")).join("");
}

function addSecurityLog(entry) {
  try {
    const existing = JSON.parse(localStorage.getItem(securityLogKey) || "[]");
    localStorage.setItem(securityLogKey, JSON.stringify([entry, ...existing].slice(0, 100)));
  } catch (error) {
    console.error("Unable to persist security log", error);
  }
}

export default ChangePassword;
