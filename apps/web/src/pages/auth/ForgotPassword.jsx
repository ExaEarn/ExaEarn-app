import { useState } from "react";
import { Mail } from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import { ExaAuthShell, ExaButton, ExaField } from "../../components/ui";

function ForgotPassword({ onLogin }) {
  const [email, setEmail] = useState("");
  const [statusMessage, setStatusMessage] = useState("");
  const { forgotPassword, authLoading, authError } = useAuth();

  const handleSubmit = async (event) => {
    event.preventDefault();
    setStatusMessage("");
    const result = await forgotPassword(email);
    if (result.success) setStatusMessage("Reset link sent. Check your email.");
  };

  return (
    <ExaAuthShell title="Recover Account" subtitle="Reset access securely using your ExaEarn account email.">
      <form onSubmit={handleSubmit} className="space-y-4">
        <ExaField
          label="Email Address"
          icon={Mail}
          id="forgotEmail"
          type="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          placeholder="Enter your email"
          autoComplete="email"
          required
        />

        <ExaButton type="submit" loading={authLoading} disabled={authLoading} className="w-full">
          {authLoading ? "Sending..." : "Send Reset Link"}
        </ExaButton>
      </form>

      {statusMessage ? <p className="mt-3 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-200">{statusMessage}</p> : null}
      {authError ? <p className="mt-3 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-xs text-rose-200">{authError}</p> : null}

      <ExaButton type="button" variant="secondary" onClick={onLogin} className="mt-4 w-full">
        Return to Login
      </ExaButton>

      <p className="mt-6 text-center text-xs text-[var(--exa-text-muted)]">Never share your password, 2FA code or recovery details with anyone.</p>
    </ExaAuthShell>
  );
}

export default ForgotPassword;