function LandCard({ parcel, onAcquireShare }) {
  const themeClasses = {
    emerald: "from-emerald-500/40 via-emerald-500/10 to-transparent",
    gold: "from-amber-400/40 via-amber-300/10 to-transparent",
    violet: "from-violet-500/40 via-fuchsia-500/10 to-transparent",
  };

  return (
    <article className="w-full rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3 shadow-[var(--exa-shadow-soft)]">
      <div className={`h-20 sm:h-24 w-full rounded-xl bg-gradient-to-br ${themeClasses[parcel.theme] || themeClasses.violet}`} />
      <div className="mt-3">
        <p className="text-sm font-semibold text-[var(--exa-text-primary)]">{parcel.name}</p>
        <p className="mt-1 text-xs text-[var(--exa-text-muted)]">
          {parcel.size} · {parcel.location}
        </p>
      </div>
      <div className="mt-3 flex items-center justify-between text-xs">
        <span className="text-[var(--exa-text-secondary)]">Available</span>
        <span className="font-semibold text-[var(--exa-gold)]">{parcel.availability}%</span>
      </div>
      <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-[var(--exa-surface-elevated)]">
        <div
          className="h-full rounded-full bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)]"
          style={{ width: `${parcel.availability}%` }}
        />
      </div>
      <button
        type="button"
        onClick={() => onAcquireShare?.(parcel.id)}
        className="mt-3 inline-flex w-full items-center justify-center rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-3 py-2 text-xs font-semibold text-[var(--exa-gold-contrast)] transition-all duration-300 hover:scale-[1.01] hover:shadow-[var(--exa-shadow-gold)]"
      >
        Acquire Share
      </button>
    </article>
  );
}

export default LandCard;
