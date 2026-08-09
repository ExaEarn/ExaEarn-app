export default function UserProfileForm({ profile, onSave }) {
  return (
    <section className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
      <h3 className="text-sm font-semibold text-[var(--exa-text-primary)]">User Profile</h3>
      <p className="mt-2 text-xs text-[var(--exa-text-secondary)]">Skill: {profile?.skill_level || "unknown"} | Risk: {profile?.risk_tolerance || "unknown"}</p>
      <button type="button" onClick={onSave} className="mt-3 rounded bg-[var(--exa-gold)] px-3 py-2 text-xs font-semibold text-[var(--exa-gold-contrast)]">Sync Profile</button>
    </section>
  );
}
