export default function TradeSuggestionCard({ suggestion }) {
  if (!suggestion) {
    return (
      <section className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
        <h3 className="text-sm font-semibold text-[var(--exa-text-primary)]">Trade Suggestion</h3>
        <p className="mt-2 text-xs text-[var(--exa-text-muted)]">No suggestion generated yet.</p>
      </section>
    );
  }

  return (
    <section className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
      <h3 className="text-sm font-semibold text-[var(--exa-text-primary)]">Trade Suggestion</h3>
      <p className="mt-2 text-xs text-[var(--exa-text-secondary)]">Entry: {suggestion.entry ?? "-"}</p>
      <p className="text-xs text-[var(--exa-text-secondary)]">Stop: {suggestion.stop ?? "-"}</p>
      <p className="text-xs text-[var(--exa-text-secondary)]">Targets: {(suggestion.targets || []).join(", ") || "-"}</p>
      <p className="mt-2 text-[11px] text-yellow-300">AI suggestions are not financial advice.</p>
    </section>
  );
}
