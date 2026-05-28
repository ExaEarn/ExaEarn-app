import { ArrowLeft, CircleHelp, UserRoundCheck } from "lucide-react";
import Image from "../../assets/Image";
import RecoveryActionCard from "./components/RecoveryActionCard";

function NeedHelp({ onBack, onRememberAccount, onForgotAccount }) {
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
          <h1 className="font-['Sora'] text-3xl font-semibold tracking-tight text-violet-50">Need help to log in?</h1>
        </div>

        <div className="mt-6 space-y-4">
          <RecoveryActionCard
            title="I Remember My Account"
            description="Reset your password using your registered email or phone number"
            icon={<UserRoundCheck className="h-5 w-5" aria-hidden="true" />}
            onClick={onRememberAccount}
          />
          <RecoveryActionCard
            title="I Forgot My Account"
            description="I don't remember the email or phone number linked to my ExaEarn account"
            icon={<CircleHelp className="h-5 w-5" aria-hidden="true" />}
            onClick={onForgotAccount}
          />
        </div>

        <p className="mt-auto pt-8 text-center text-xs leading-relaxed text-violet-100/55">
          For your security, ExaEarn will never ask for your password or private keys.
        </p>
      </div>
    </div>
  );
}

export default NeedHelp;
