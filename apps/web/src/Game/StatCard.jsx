function StatCard({ label, value }) {
  return (
    <article className="game-stat-card rounded-3xl p-5 sm:p-6">
      <p className="text-sm font-medium tracking-wide text-[var(--exa-text-secondary)] sm:text-base">{label}</p>
      <p className="mt-3 text-4xl font-semibold text-[var(--exa-gold)] sm:text-5xl">{value}</p>
    </article>
  );
}

export default StatCard;
