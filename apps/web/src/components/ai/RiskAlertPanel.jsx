export default function RiskAlertPanel({ risk }) {
  const score = Number(risk?.risk_score ?? 0);
  const tone = score > 70 ? "text-red-300" : score >= 30 ? "text-yellow-300" : "text-emerald-300";

  return (
    <section className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
      <h3 className="text-sm font-semibold text-[var(--exa-text-primary)]">Risk Alert Panel</h3>
      <p className={`mt-2 text-sm font-semibold ${tone}`}>Portfolio Risk Score: {score}/100</p>
      <p className="mt-1 text-xs text-[var(--exa-text-secondary)]">{risk?.message || "Risk checks and warnings appear here."}</p>
    </section>
  );
}
