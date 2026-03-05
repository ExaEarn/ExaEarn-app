import { useMemo, useState } from "react";
import { Eye, EyeOff } from "lucide-react";
import Image from "../../assets/Image";

function Register({ onLogin }) {
  const [fullName, setFullName] = useState("");
  const [email, setEmail] = useState("");
  const [referralCode, setReferralCode] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);

  const passwordsMatch = useMemo(() => {
    if (!password || !confirmPassword) {
      return true;
    }
    return password === confirmPassword;
  }, [password, confirmPassword]);

  const handleSubmit = (event) => {
    event.preventDefault();
    setIsSubmitted(true);
    if (!passwordsMatch) {
      return;
    }
  };

  return (
    <div className="min-h-screen w-full bg-gradient-to-br from-black via-[#140a24] to-[#220c3d] text-white">
      <div className="flex min-h-screen items-center justify-center px-4 py-10">
        <div className="w-full max-w-lg rounded-3xl border border-violet-300/20 bg-cosmic-900/70 p-6 shadow-cosmic-glow backdrop-blur-xl sm:p-8">
          <div className="flex flex-col items-center text-center">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl border border-auric-300/60 bg-cosmic-900/70">
              <img src={Image.earn} alt="ExaEarn logo" className="h-8 w-8 object-contain" />
            </div>
            <h1 className="mt-4 font-['Sora'] text-2xl font-semibold text-violet-50 sm:text-3xl">
              Create Your ExaEarn Account
            </h1>
            <p className="mt-2 text-sm text-violet-100/70">Join the decentralized rewards ecosystem</p>
          </div>

          <form onSubmit={handleSubmit} className="mt-6 space-y-4">
            <div className="space-y-2">
              <label htmlFor="registerName" className="text-xs uppercase tracking-[0.2em] text-auric-300/70">
                Full Name
              </label>
              <input
                id="registerName"
                type="text"
                value={fullName}
                onChange={(event) => setFullName(event.target.value)}
                placeholder="Satoshi Nakamoto"
                className="w-full rounded-2xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3 text-sm text-violet-100 outline-none transition-all duration-300 focus:border-violet-300/80 focus:shadow-[0_0_0_3px_rgba(168,85,247,0.25)]"
                required
              />
            </div>

            <div className="space-y-2">
              <label htmlFor="registerEmail" className="text-xs uppercase tracking-[0.2em] text-auric-300/70">
                Email Address
              </label>
              <input
                id="registerEmail"
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                placeholder="you@exaearn.io"
                className="w-full rounded-2xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3 text-sm text-violet-100 outline-none transition-all duration-300 focus:border-violet-300/80 focus:shadow-[0_0_0_3px_rgba(168,85,247,0.25)]"
                required
              />
            </div>

            <div className="space-y-2">
              <label htmlFor="registerReferral" className="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-auric-300/70">
                Referral Code <span className="rounded-full border border-violet-300/30 px-2 py-0.5 text-[10px] text-violet-100/70">Optional</span>
              </label>
              <input
                id="registerReferral"
                type="text"
                value={referralCode}
                onChange={(event) => setReferralCode(event.target.value)}
                placeholder="EXA-REF-2026"
                className="w-full rounded-2xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3 text-sm text-violet-100 outline-none transition-all duration-300 focus:border-violet-300/80 focus:shadow-[0_0_0_3px_rgba(168,85,247,0.25)]"
              />
            </div>

            <div className="space-y-2">
              <label htmlFor="registerPassword" className="text-xs uppercase tracking-[0.2em] text-auric-300/70">
                Password
              </label>
              <div className="flex items-center rounded-2xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3 text-sm text-violet-100 transition-all duration-300 focus-within:border-violet-300/80 focus-within:shadow-[0_0_0_3px_rgba(168,85,247,0.25)]">
                <input
                  id="registerPassword"
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

            <div className="space-y-2">
              <label htmlFor="registerConfirmPassword" className="text-xs uppercase tracking-[0.2em] text-auric-300/70">
                Confirm Password
              </label>
              <div className="flex items-center rounded-2xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3 text-sm text-violet-100 transition-all duration-300 focus-within:border-violet-300/80 focus-within:shadow-[0_0_0_3px_rgba(168,85,247,0.25)]">
                <input
                  id="registerConfirmPassword"
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
              <p className={`text-xs ${isSubmitted && !passwordsMatch ? "text-rose-300" : "text-violet-100/60"}`}>
                {isSubmitted && !passwordsMatch ? "Passwords do not match." : "Use the same password in both fields."}
              </p>
            </div>

            <div className="pt-2">
              <button
                type="submit"
                className="w-full rounded-2xl border border-auric-300/80 bg-gradient-to-r from-violet-500/90 via-fuchsia-500/85 to-auric-400 px-4 py-3 text-sm font-semibold text-white shadow-[0_0_24px_rgba(212,175,55,0.35)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_30px_rgba(212,175,55,0.5)]"
              >
                Create Account
              </button>
            </div>
          </form>

          <div className="my-5 flex items-center gap-3 text-xs text-violet-100/50">
            <div className="h-px flex-1 bg-violet-300/20" />
            OR
            <div className="h-px flex-1 bg-violet-300/20" />
          </div>

          <p className="text-center text-sm text-violet-100/75">
            Already have an account?{" "}
            <button
              type="button"
              onClick={onLogin}
              className="font-semibold text-auric-300 underline decoration-auric-300/70 underline-offset-4 transition hover:text-auric-200"
            >
              Login
            </button>
          </p>
        </div>
      </div>
    </div>
  );
}

export default Register;
