import { ArrowLeft, ClipboardCheck, FilePlus2 } from "lucide-react";
import Image from "../../assets/Image";
import RecoveryActionCard from "./components/RecoveryActionCard";

function ForgotAccountAppeal({ onBack, onSubmitAppeal, onCheckPreviousResult }) {
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
          <h1 className="font-['Sora'] text-3xl font-semibold tracking-tight text-violet-50">Forgot Account Appeal</h1>
          <p className="mt-2 text-sm text-violet-100/70">Please select the operation you want to perform.</p>
        </div>

        <div className="mt-6 space-y-4">
          <RecoveryActionCard
            title="Submit a New Appeal"
            icon={<FilePlus2 className="h-5 w-5" aria-hidden="true" />}
            onClick={onSubmitAppeal}
          />
          <RecoveryActionCard
            title="Check Previous Result"
            description="Only choose this if you have submitted an appeal before"
            icon={<ClipboardCheck className="h-5 w-5" aria-hidden="true" />}
            onClick={onCheckPreviousResult}
          />
        </div>

        <p className="mt-auto pt-8 text-center text-xs leading-relaxed text-violet-100/55">
          For your security, ExaEarn will never ask for your password or private keys.
        </p>
      </div>
    </div>
  );
}

export default ForgotAccountAppeal;
