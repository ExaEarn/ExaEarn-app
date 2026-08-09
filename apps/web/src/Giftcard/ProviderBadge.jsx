function ProviderBadge({ icon, name }) {
  return (
    <div className="gift-provider inline-flex items-center gap-2 rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 py-2 text-sm text-[var(--exa-text-secondary)]">
      <span className="inline-flex h-6 w-6 items-center justify-center rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] text-[var(--exa-gold-light)]">
        {icon}
      </span>
      <span>{name}</span>
    </div>
  );
}

export default ProviderBadge;
