import { CircleHelp, UserRoundCheck } from "lucide-react";
import { ExaAuthShell, ExaButton } from "../../components/ui";
import RecoveryActionCard from "./components/RecoveryActionCard";

function NeedHelp({ onBack, onRememberAccount, onForgotAccount }) {
  return (
    <ExaAuthShell title="Need help to log in?" subtitle="Choose the recovery path that best matches your situation.">
      <div className="space-y-4">
        <RecoveryActionCard title="I Remember My Account" description="Reset your password using your registered email or phone number." icon={<UserRoundCheck className="h-5 w-5" aria-hidden="true" />} onClick={onRememberAccount} />
        <RecoveryActionCard title="I Forgot My Account" description="Start an account appeal if you do not remember the email or phone number linked to ExaEarn." icon={<CircleHelp className="h-5 w-5" aria-hidden="true" />} onClick={onForgotAccount} />
      </div>
      <ExaButton type="button" variant="secondary" onClick={onBack} className="mt-5 w-full">
        Back to Login
      </ExaButton>
      <p className="mt-6 text-center text-xs leading-relaxed text-[var(--exa-text-muted)]">For your security, ExaEarn will never ask for your password, private keys or recovery phrase.</p>
    </ExaAuthShell>
  );
}

export default NeedHelp;