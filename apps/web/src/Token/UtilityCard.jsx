function UtilityCard({ icon, title, description }) {
  return (
    <article className="border border-[var(--exa-border)] bg-[var(--exa-surface)] shadow-[var(--exa-shadow-soft)] group rounded-2xl p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-[var(--exa-shadow-soft)]">
      <div className="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] text-[var(--exa-gold)] transition-colors duration-300 group-hover:border-[var(--exa-border-active)] group-hover:text-[var(--exa-gold-light)]">
        {icon}
      </div>
      <h3 className="mt-4 text-lg font-semibold text-[var(--exa-text-primary)]">{title}</h3>
      <p className="mt-2 text-sm leading-relaxed text-[var(--exa-text-muted)]">{description}</p>
    </article>
  );
}

export default UtilityCard;
