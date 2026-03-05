import { useEffect, useRef, useState } from "react";
import { Eye, EyeOff, LoaderCircle } from "lucide-react";
import Image from "../../assets/Image";
import { useAuth } from "../../context/AuthContext";

function Login({ onSuccess, onCreateAccount, onForgotPassword, onNeedHelp }) {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [remember, setRemember] = useState(false);
  const hasHandledGoogleSuccess = useRef(false);
  const { user, isGoogleAuthLoading, googleAuthError, isGoogleConfigured, startGoogleLogin } = useAuth();

  useEffect(() => {
    if (!user || hasHandledGoogleSuccess.current) {
      return;
    }

    hasHandledGoogleSuccess.current = true;
    if (onSuccess) {
      onSuccess();
    }
  }, [user, onSuccess]);

  const handleSubmit = (event) => {
    event.preventDefault();
    if (onSuccess) {
      onSuccess();
    }
  };

  return (
    <div className="min-h-screen w-full bg-gradient-to-br from-black via-[#140a24] to-[#220c3d] text-white">
      <div className="flex min-h-screen items-center justify-center px-4 py-10">
        <div className="w-full max-w-md rounded-3xl border border-violet-300/20 bg-cosmic-900/70 p-6 shadow-cosmic-glow backdrop-blur-xl sm:p-8">
          <div className="flex flex-col items-center text-center">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl border border-auric-300/60 bg-cosmic-900/70">
              <img src={Image.earn} alt="ExaEarn logo" className="h-8 w-8 object-contain" />
            </div>
            <h1 className="mt-4 font-['Sora'] text-3xl font-semibold text-violet-50">Welcome to ExaEarn</h1>
            <p className="mt-2 text-sm text-violet-100/70">Secure access to your Web3 finance ecosystem.</p>
          </div>

          <form onSubmit={handleSubmit} className="mt-6 space-y-4">
            <div className="space-y-2">
              <label className="text-xs uppercase tracking-[0.2em] text-auric-300/70">Email</label>
              <input
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                placeholder="you@exaearn.io"
                className="w-full rounded-2xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3 text-sm text-violet-100 outline-none transition-all duration-300 focus:border-auric-300/70 focus:shadow-button-glow"
                required
              />
            </div>

            <div className="space-y-2">
              <label className="text-xs uppercase tracking-[0.2em] text-auric-300/70">Password</label>
              <div className="flex items-center rounded-2xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3 text-sm text-violet-100 transition-all duration-300 focus-within:border-auric-300/70 focus-within:shadow-button-glow">
                <input
                  type={showPassword ? "text" : "password"}
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  placeholder="••••••••"
                  className="w-full bg-transparent text-sm text-violet-100 outline-none placeholder:text-violet-100/40"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((prev) => !prev)}
                  className="ml-2 text-violet-100/60 transition hover:text-auric-300"
                >
                  {showPassword ? <EyeOff className="h-4 w-4" aria-hidden="true" /> : <Eye className="h-4 w-4" aria-hidden="true" />}
                </button>
              </div>
            </div>

            <div className="flex items-center justify-between text-xs text-violet-100/70">
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={remember}
                  onChange={() => setRemember((prev) => !prev)}
                  className="h-4 w-4 rounded border-violet-300/40 bg-cosmic-900/70 text-auric-300"
                />
                Remember me
              </label>
              <button
                type="button"
                onClick={onForgotPassword}
                className="text-auric-300 underline decoration-auric-300/70 decoration-2 underline-offset-4 transition hover:text-auric-200 hover:decoration-auric-300"
              >
                Forgot password?
              </button>
            </div>

            <button
              type="submit"
              className="w-full rounded-2xl border border-auric-300/70 bg-gradient-to-r from-violet-500/90 via-fuchsia-500/80 to-auric-400 px-4 py-3 text-sm font-semibold text-white shadow-button-glow transition-all duration-300 hover:scale-[1.01]"
            >
              Login
            </button>
          </form>

          <div className="my-5 flex items-center gap-3 text-xs text-violet-100/50">
            <div className="h-px flex-1 bg-violet-300/20" />
            OR
            <div className="h-px flex-1 bg-violet-300/20" />
          </div>

          <div className="space-y-3">
            <button
              type="button"
              onClick={startGoogleLogin}
              disabled={isGoogleAuthLoading}
              className="w-full rounded-2xl border border-violet-300/30 bg-cosmic-900/70 px-4 py-3 text-sm font-semibold text-violet-100/80 transition-all duration-300 hover:-translate-y-0.5 hover:border-auric-300/60 hover:text-auric-200"
            >
              {isGoogleAuthLoading ? (
                <span className="inline-flex items-center gap-2">
                  <LoaderCircle className="h-4 w-4 animate-spin" aria-hidden="true" />
                  Connecting Google...
                </span>
              ) : (
                "Continue with Google"
              )}
            </button>
            {googleAuthError ? <p className="text-xs text-rose-300">{googleAuthError}</p> : null}
            {!isGoogleConfigured ? (
              <p className="text-xs text-violet-100/55">Google sign-in requires `VITE_GOOGLE_CLIENT_ID` in `.env`.</p>
            ) : null}
            <button
              type="button"
              onClick={onCreateAccount}
              className="w-full rounded-2xl border border-auric-300/60 bg-cosmic-900/70 px-4 py-3 text-sm font-semibold text-auric-300 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-button-glow"
            >
              Create an ExaEarn Account
            </button>
            <p className="text-center text-xs text-auric-300/80">
              <button
                type="button"
                onClick={onNeedHelp}
                className="text-auric-300 underline decoration-auric-300/70 decoration-2 underline-offset-4 transition hover:text-auric-200 hover:decoration-auric-300"
              >
                Need help?
              </button>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

export default Login;
