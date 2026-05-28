export default function SignalDashboard({ signals = [] }) {
  return (
    <section className="rounded-2xl border border-white/10 bg-[#0f172a] p-4">
      <h3 className="text-sm font-semibold text-white">Signal Dashboard</h3>
      <div className="mt-3 space-y-2">
        {signals.slice(0, 5).map((s, i) => (
          <div key={s.id || i} className="rounded-lg border border-white/10 bg-[#111827] p-3 text-xs text-slate-200">
            <div className="font-semibold">{s.symbol || s.pair || "Market"} - {s.signal || s.direction || "N/A"}</div>
            <div>Confidence: {s.confidence ?? s.confidence_score ?? "-"}%</div>
          </div>
        ))}
        {!signals.length ? <p className="text-xs text-slate-400">No signals yet.</p> : null}
      </div>
    </section>
  );
}
