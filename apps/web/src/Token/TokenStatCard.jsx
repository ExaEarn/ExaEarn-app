function TokenStatCard({ label, value, hint }) {
  return (
    <article className="border border-[var(--exa-border)] bg-[var(--exa-surface)] shadow-[var(--exa-shadow-soft)] rounded-2xl p-5 sm:p-6">
      <p className="text-sm font-medium tracking-wide text-[var(--exa-text-secondary)]">{label}</p>
      <p className="mt-3 text-3xl font-semibold text-[var(--exa-gold)] sm:text-4xl">{value}</p>
      {hint ? <p className="mt-2 text-sm text-[var(--exa-text-secondary)]">{hint}</p> : null}
    </article>
  );
}

export default TokenStatCard;
