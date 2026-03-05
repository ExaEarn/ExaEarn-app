import { useState } from "react";
import { Mail } from "lucide-react";
import Image from "../../assets/Image";

function ForgotPassword({ onLogin }) {
  const [email, setEmail] = useState("");

  const handleSubmit = (event) => {
    event.preventDefault();
  };

  return (
    <div className="min-h-screen w-full bg-gradient-to-br from-black via-[#140a24] to-[#220c3d] text-white">
      <div className="flex min-h-screen items-center justify-center px-4 py-10">
        <div className="w-full max-w-md rounded-3xl border border-violet-300/20 bg-cosmic-900/70 p-6 shadow-cosmic-glow backdrop-blur-xl sm:p-8">
          <div className="flex flex-col items-center text-center">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl border border-auric-300/60 bg-cosmic-900/70">
              <img src={Image.earn} alt="ExaEarn logo" className="h-8 w-8 object-contain" />
            </div>
            <h1 className="mt-4 font-['Sora'] text-3xl font-semibold text-violet-50">Forgot Password</h1>
            <p className="mt-2 text-sm text-violet-100/70">Reset your account access securely</p>
          </div>

          <form onSubmit={handleSubmit} className="mt-6 space-y-4">
            <div className="space-y-2">
              <label htmlFor="forgotEmail" className="text-xs uppercase tracking-[0.2em] text-auric-300/70">
                Email Address
              </label>
              <div className="flex items-center rounded-2xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3 text-sm text-violet-100 transition-all duration-300 focus-within:border-violet-300/80 focus-within:shadow-[0_0_0_3px_rgba(168,85,247,0.25)]">
                <Mail className="h-4 w-4 text-violet-200/70" aria-hidden="true" />
                <input
                  id="forgotEmail"
                  type="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  placeholder="Enter your email"
                  className="ml-3 w-full bg-transparent text-sm text-violet-100 outline-none placeholder:text-violet-100/40"
                  required
                />
              </div>
            </div>

            <button
              type="submit"
              className="w-full rounded-2xl border border-auric-300/80 bg-gradient-to-r from-violet-500/90 via-fuchsia-500/85 to-auric-400 px-4 py-3 text-sm font-semibold text-white shadow-[0_0_24px_rgba(212,175,55,0.35)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_30px_rgba(212,175,55,0.5)]"
            >
              Send Reset Link
            </button>
          </form>

          <button
            type="button"
            onClick={onLogin}
            className="mt-4 w-full rounded-2xl border border-violet-300/30 bg-transparent px-4 py-3 text-sm font-semibold text-violet-100/80 transition-all duration-300 hover:-translate-y-0.5 hover:border-auric-300/60 hover:text-auric-200"
          >
            Return to Login
          </button>

          <p className="mt-6 text-center text-xs text-violet-100/55">Never share your password with anyone.</p>
        </div>
      </div>
    </div>
  );
}

export default ForgotPassword;
