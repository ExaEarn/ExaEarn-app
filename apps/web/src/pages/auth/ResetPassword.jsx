import { ArrowLeft, Eye, EyeOff } from "lucide-react";
import { useState } from "react";
import Image from "../../assets/Image";
import { useAuth } from "../../context/AuthContext";

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
    const result = await resetPassword({
      email,
      token: resetToken,
      password,
      passwordConfirmation: confirmPassword,
    });
    if (result.success) {
      setStatusMessage("Password reset successfully. You can log in now.");
    }
  };

  return (
    <div className="min-h-screen w-full bg-gradient-to-br from-black via-[#140a24] to-[#220c3d] text-white">
      <div className="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-6 sm:px-5 sm:py-8">
        <div className="flex items-center justify-between">
          <button
            type="button"
            onClick={onBack}
            className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-violet-300/25 bg-cosmic-900/65 text-violet-100/80 transition hover:border-auric-300/60 hover:text-auric-200"
          >
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          </button>
          <img src={Image.earn} alt="ExaEarn logo" className="h-7 w-7 object-contain opacity-90" />
        </div>

        <div className="mt-8">
          <h1 className="font-['Sora'] text-3xl font-semibold tracking-tight text-violet-50">Reset Password</h1>
        </div>

        <form onSubmit={handleSubmit} className="mt-6 space-y-4">
          <div>
            <label htmlFor="resetEmail" className="text-xs uppercase tracking-[0.2em] text-auric-300/75">
              Email Address
            </label>
            <input
              id="resetEmail"
              type="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              placeholder="you@exaearn.io"
              className="mt-2 w-full rounded-2xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3.5 text-sm text-violet-100 outline-none transition-all duration-300 focus:border-violet-300/80 focus:shadow-[0_0_0_3px_rgba(168,85,247,0.25)]"
              required
            />
          </div>

          <div>
            <label htmlFor="resetToken" className="text-xs uppercase tracking-[0.2em] text-auric-300/75">
              Reset Token
            </label>
            <input
              id="resetToken"
              type="text"
              value={resetToken}
              onChange={(event) => setResetToken(event.target.value)}
              placeholder="Paste the token from your email"
              className="mt-2 w-full rounded-2xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3.5 text-sm text-violet-100 outline-none transition-all duration-300 focus:border-violet-300/80 focus:shadow-[0_0_0_3px_rgba(168,85,247,0.25)]"
              required
            />
          </div>

          <div>
            <label htmlFor="resetPassword" className="text-xs uppercase tracking-[0.2em] text-auric-300/75">
              New Password
            </label>
            <div className="mt-2 flex items-center rounded-2xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3.5 text-sm text-violet-100 transition-all duration-300 focus-within:border-violet-300/80 focus-within:shadow-[0_0_0_3px_rgba(168,85,247,0.25)]">
              <input
                id="resetPassword"
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

          <div>
            <label htmlFor="resetConfirmPassword" className="text-xs uppercase tracking-[0.2em] text-auric-300/75">
              Confirm Password
            </label>
            <div className="mt-2 flex items-center rounded-2xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3.5 text-sm text-violet-100 transition-all duration-300 focus-within:border-violet-300/80 focus-within:shadow-[0_0_0_3px_rgba(168,85,247,0.25)]">
              <input
                id="resetConfirmPassword"
                type={showConfirmPassword ? "text" : "password"}
                value={confirmPassword}
                onChange={(event) => setConfirmPassword(event.target.value)}
                placeholder="••••••••"
                className="w-full bg-transparent text-sm text-violet-100 outline-none placeholder:text-violet-100/40"
                required
              />
              <button
                type="button"
                onClick={() => setShowConfirmPassword((prev) => !prev)}
                className="ml-2 text-violet-100/60 transition hover:text-auric-300"
              >
                {showConfirmPassword ? <EyeOff className="h-4 w-4" aria-hidden="true" /> : <Eye className="h-4 w-4" aria-hidden="true" />}
              </button>
            </div>
          </div>

          <button
            type="submit"
            disabled={authLoading}
            className="w-full rounded-2xl border border-auric-300/80 bg-gradient-to-r from-violet-500/90 via-fuchsia-500/85 to-auric-400 px-4 py-3 text-sm font-semibold text-white shadow-[0_0_22px_rgba(212,175,55,0.35)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_28px_rgba(212,175,55,0.5)] disabled:cursor-not-allowed disabled:opacity-55 disabled:shadow-none"
          >
            {authLoading ? "Resetting..." : "Reset Password"}
          </button>
        </form>
        {statusMessage ? <p className="mt-3 text-xs text-emerald-300">{statusMessage}</p> : null}
        {authError ? <p className="mt-2 text-xs text-rose-300">{authError}</p> : null}

        <p className="mt-auto pt-8 text-center text-xs leading-relaxed text-violet-100/55">
          For your security, ExaEarn will never ask for your password or private keys.
        </p>
      </div>
    </div>
  );
}

export default ResetPassword;
