import { useEffect, useRef, useState } from "react";
import { Eye, EyeOff, LoaderCircle } from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import { ExaAuthShell, ExaButton, ExaField } from "../../components/ui";

function Login({ onSuccess, onCreateAccount, onForgotPassword, onNeedHelp }) {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loginMessage, setLoginMessage] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [remember, setRemember] = useState(false);
  const googleLoginStarted = useRef(false);
  const {
    user,
    login,
    authLoading,
    authError,
    isGoogleAuthLoading,
    googleAuthError,
    isGoogleConfigured,
    startGoogleLogin,
  } = useAuth();

  useEffect(() => {
    if (!user || !googleLoginStarted.current) return;
    googleLoginStarted.current = false;
    onSuccess?.();
  }, [user, onSuccess]);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setLoginMessage("");

    const result = await login({ email, password });
    if (result.success) onSuccess?.();
  };

  return (
    <ExaAuthShell title="Welcome to ExaEarn" subtitle="Secure access to your global digital finance account.">
      <form onSubmit={handleSubmit} className="space-y-4">
        <ExaField
          label="Email"
          type="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          placeholder="you@exaearn.com"
          autoComplete="email"
          required
        />

        <ExaField label="Password">
          <input
            type={showPassword ? "text" : "password"}
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            placeholder="Password"
            autoComplete="current-password"
            className="w-full bg-transparent text-sm text-[var(--exa-text-primary)] outline-none placeholder:text-[var(--exa-text-disabled)]"
            required
          />
          <button
            type="button"
            onClick={() => setShowPassword((prev) => !prev)}
            className="ml-3 text-[var(--exa-text-muted)] transition hover:text-[var(--exa-gold-light)] exa-focusable"
            aria-label={showPassword ? "Hide password" : "Show password"}
          >
            {showPassword ? <EyeOff className="h-4 w-4" aria-hidden="true" /> : <Eye className="h-4 w-4" aria-hidden="true" />}
          </button>
        </ExaField>

        <div className="flex items-center justify-between gap-3 text-xs text-[var(--exa-text-muted)]">
          <label className="flex items-center gap-2">
            <input
              type="checkbox"
              checked={remember}
              onChange={() => setRemember((prev) => !prev)}
              className="h-4 w-4 rounded border-[var(--exa-border)] bg-transparent accent-[var(--exa-gold)]"
            />
            Remember me
          </label>
          <button
            type="button"
            onClick={onForgotPassword}
            className="font-semibold text-[var(--exa-gold-light)] underline decoration-[var(--exa-gold)]/40 underline-offset-4 transition hover:text-white exa-focusable"
          >
            Forgot password?
          </button>
        </div>

        <ExaButton type="submit" loading={authLoading} disabled={authLoading} className="w-full">
          Login
        </ExaButton>
      </form>

      {loginMessage ? <p className="mt-3 text-xs text-[var(--exa-text-muted)]">{loginMessage}</p> : null}
      {authError ? <p className="mt-3 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-xs text-rose-200">{authError}</p> : null}

      <div className="my-6 flex items-center gap-3 text-xs text-[var(--exa-text-disabled)]">
        <div className="h-px flex-1 bg-[var(--exa-border-subtle)]" />
        OR
        <div className="h-px flex-1 bg-[var(--exa-border-subtle)]" />
      </div>

      <div className="space-y-3">
        <ExaButton
          type="button"
          variant="secondary"
          onClick={() => {
            googleLoginStarted.current = true;
            startGoogleLogin();
          }}
          disabled={isGoogleAuthLoading}
          className="w-full"
        >
          {isGoogleAuthLoading ? (
            <span className="inline-flex items-center gap-2">
              <LoaderCircle className="h-4 w-4 animate-spin" aria-hidden="true" />
              Connecting Google...
            </span>
          ) : (
            "Continue with Google"
          )}
        </ExaButton>
        {googleAuthError ? <p className="text-xs text-rose-300">{googleAuthError}</p> : null}
        {!isGoogleConfigured ? <p className="text-xs text-[var(--exa-text-muted)]">Google sign-in requires VITE_GOOGLE_CLIENT_ID.</p> : null}
        <ExaButton type="button" variant="secondary" onClick={onCreateAccount} className="w-full">
          Create an ExaEarn Account
        </ExaButton>
        <p className="text-center text-xs text-[var(--exa-text-muted)]">
          <button
            type="button"
            onClick={onNeedHelp}
            className="font-semibold text-[var(--exa-gold-light)] underline decoration-[var(--exa-gold)]/40 underline-offset-4 transition hover:text-white exa-focusable"
          >
            Need help?
          </button>
        </p>
      </div>
    </ExaAuthShell>
  );
}

export default Login;