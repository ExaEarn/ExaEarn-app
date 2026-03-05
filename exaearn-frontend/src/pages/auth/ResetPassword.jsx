import { ArrowLeft } from "lucide-react";
import { useState } from "react";
import Image from "../../assets/Image";

function ResetPassword({ onBack }) {
  const [identifier, setIdentifier] = useState("");

  const handleSubmit = (event) => {
    event.preventDefault();
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

        <form onSubmit={handleSubmit} className="mt-6">
          <label htmlFor="resetIdentifier" className="text-xs uppercase tracking-[0.2em] text-auric-300/75">
            Email / Phone Number
          </label>

          <input
            id="resetIdentifier"
            type="text"
            value={identifier}
            onChange={(event) => setIdentifier(event.target.value)}
            placeholder="Enter your email or phone number"
            className="mt-2 w-full rounded-2xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3.5 text-sm text-violet-100 outline-none transition-all duration-300 focus:border-violet-300/80 focus:shadow-[0_0_0_3px_rgba(168,85,247,0.25)]"
          />

          <button
            type="submit"
            disabled={!identifier.trim()}
            className="mt-5 w-full rounded-2xl border border-auric-300/80 bg-gradient-to-r from-violet-500/90 via-fuchsia-500/85 to-auric-400 px-4 py-3 text-sm font-semibold text-white shadow-[0_0_22px_rgba(212,175,55,0.35)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_28px_rgba(212,175,55,0.5)] disabled:cursor-not-allowed disabled:opacity-55 disabled:shadow-none"
          >
            Next
          </button>
        </form>

        <p className="mt-auto pt-8 text-center text-xs leading-relaxed text-violet-100/55">
          For your security, ExaEarn will never ask for your password or private keys.
        </p>
      </div>
    </div>
  );
}

export default ResetPassword;
