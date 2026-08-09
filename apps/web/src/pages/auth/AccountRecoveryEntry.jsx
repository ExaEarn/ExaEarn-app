import { useState } from "react";
import { ExaAuthShell, ExaButton, ExaField } from "../../components/ui";

function AccountRecoveryEntry({ onBack }) {
  const [identifier, setIdentifier] = useState("");

  const handleSubmit = (event) => {
    event.preventDefault();
  };

  return (
    <ExaAuthShell title="Recover Account" subtitle="Enter the email or phone number linked to your ExaEarn account.">
      <form onSubmit={handleSubmit} className="space-y-4">
        <ExaField type="text" value={identifier} onChange={(event) => setIdentifier(event.target.value)} placeholder="Email / Phone number" autoComplete="username" />
        <ExaButton type="submit" disabled={!identifier.trim()} className="w-full">
          Next
        </ExaButton>
      </form>
      <ExaButton type="button" variant="secondary" onClick={onBack} className="mt-4 w-full">
        Back
      </ExaButton>
      <p className="mt-6 text-center text-xs leading-relaxed text-[var(--exa-text-muted)]">We use this only to locate your account recovery request and protect your access.</p>
    </ExaAuthShell>
  );
}

export default AccountRecoveryEntry;