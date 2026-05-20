export default function TradeSuggestionCard({ suggestion }) {
  if (!suggestion) {
    return (
      <section className="rounded-2xl border border-white/10 bg-[#0f172a] p-4">
        <h3 className="text-sm font-semibold text-white">Trade Suggestion</h3>
        <p className="mt-2 text-xs text-slate-400">No suggestion generated yet.</p>
      </section>
    );
  }

  return (
    <section className="rounded-2xl border border-white/10 bg-[#0f172a] p-4">
      <h3 className="text-sm font-semibold text-white">Trade Suggestion</h3>
      <p className="mt-2 text-xs text-slate-200">Entry: {suggestion.entry ?? "-"}</p>
      <p className="text-xs text-slate-200">Stop: {suggestion.stop ?? "-"}</p>
      <p className="text-xs text-slate-200">Targets: {(suggestion.targets || []).join(", ") || "-"}</p>
      <p className="mt-2 text-[11px] text-yellow-300">AI suggestions are not financial advice.</p>
    </section>
  );
}
