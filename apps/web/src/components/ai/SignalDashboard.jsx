export default function SignalDashboard({ signals = [] }) {
  return (
    <section className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
      <h3 className="text-sm font-semibold text-[var(--exa-text-primary)]">Signal Dashboard</h3>
      <div className="mt-3 space-y-2">
        {signals.slice(0, 5).map((s, i) => (
          <div key={s.id || i} className="rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3 text-xs text-[var(--exa-text-secondary)]">
            <div className="font-semibold">{s.symbol || s.pair || "Market"} - {s.signal || s.direction || "N/A"}</div>
            <div>Confidence: {s.confidence ?? s.confidence_score ?? "-"}%</div>
          </div>
        ))}
        {!signals.length ? <p className="text-xs text-[var(--exa-text-muted)]">No signals yet.</p> : null}
      </div>
    </section>
  );
}
