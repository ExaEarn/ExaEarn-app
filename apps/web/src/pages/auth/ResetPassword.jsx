import { Eye, EyeOff } from "lucide-react";
import { useState } from "react";
import { useAuth } from "../../context/AuthContext";
import { ExaAuthShell, ExaButton, ExaField } from "../../components/ui";

function ResetPassword({ onBack }) {
  const [email, setEmail] = useState("");
  const [resetToken, setResetToken] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const { resetPassword, authLoading, authError } = useAuth();

  const handleSubmit = async (event) => {
    event.preventDefault();
    setStatusMessage("");
    const result = await resetPassword({ email, token: resetToken, password, passwordConfirmation: confirmPassword });
    if (result.success) setStatusMessage("Password reset successfully. You can log in now.");
  };

  return (
    <ExaAuthShell title="Reset Password" subtitle="Create a new secure password for your ExaEarn account.">
      <form onSubmit={handleSubmit} className="space-y-4">
        <ExaField id="resetEmail" label="Email Address" type="email" value={email} onChange={(event) => setEmail(event.target.value)} placeholder="you@exaearn.com" autoComplete="email" required />
        <ExaField id="resetToken" label="Reset Token" type="text" value={resetToken} onChange={(event) => setResetToken(event.target.value)} placeholder="Paste the token from your email" required />

        <ExaField label="New Password">
          <input id="resetPassword" type={showPassword ? "text" : "password"} value={password} onChange={(event) => setPassword(event.target.value)} placeholder="New password" autoComplete="new-password" className="w-full bg-transparent text-sm text-[var(--exa-text-primary)] outline-none placeholder:text-[var(--exa-text-disabled)]" required />
          <button type="button" onClick={() => setShowPassword((prev) => !prev)} className="ml-3 text-[var(--exa-text-muted)] transition hover:text-[var(--exa-gold-light)] exa-focusable" aria-label={showPassword ? "Hide password" : "Show password"}>
            {showPassword ? <EyeOff className="h-4 w-4" aria-hidden="true" /> : <Eye className="h-4 w-4" aria-hidden="true" />}
          </button>
        </ExaField>

        <ExaField label="Confirm Password">
          <input id="resetConfirmPassword" type={showConfirmPassword ? "text" : "password"} value={confirmPassword} onChange={(event) => setConfirmPassword(event.target.value)} placeholder="Confirm password" autoComplete="new-password" className="w-full bg-transparent text-sm text-[var(--exa-text-primary)] outline-none placeholder:text-[var(--exa-text-disabled)]" required />
          <button type="button" onClick={() => setShowConfirmPassword((prev) => !prev)} className="ml-3 text-[var(--exa-text-muted)] transition hover:text-[var(--exa-gold-light)] exa-focusable" aria-label={showConfirmPassword ? "Hide password" : "Show password"}>
            {showConfirmPassword ? <EyeOff className="h-4 w-4" aria-hidden="true" /> : <Eye className="h-4 w-4" aria-hidden="true" />}
          </button>
        </ExaField>

        <ExaButton type="submit" loading={authLoading} disabled={authLoading} className="w-full">
          {authLoading ? "Resetting..." : "Reset Password"}
        </ExaButton>
      </form>

      {statusMessage ? <p className="mt-3 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-200">{statusMessage}</p> : null}
      {authError ? <p className="mt-3 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-xs text-rose-200">{authError}</p> : null}

      <ExaButton type="button" variant="secondary" onClick={onBack} className="mt-4 w-full">
        Back to Login
      </ExaButton>
      <p className="mt-6 text-center text-xs leading-relaxed text-[var(--exa-text-muted)]">For your security, ExaEarn will never ask for your password, private keys or recovery phrase.</p>
    </ExaAuthShell>
  );
}

export default ResetPassword;