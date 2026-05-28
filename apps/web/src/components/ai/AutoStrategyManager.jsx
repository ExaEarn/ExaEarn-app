export default function AutoStrategyManager({ strategies = [], onActivate, onDeactivate }) {
  return (
    <section className="rounded-2xl border border-white/10 bg-[#0f172a] p-4">
      <h3 className="text-sm font-semibold text-white">Auto Strategy Manager</h3>
      <div className="mt-3 space-y-2">
        {strategies.slice(0, 5).map((s) => (
          <div key={s.id} className="flex items-center justify-between rounded-lg border border-white/10 bg-[#111827] p-3 text-xs text-slate-200">
            <div>
              <p className="font-semibold">{s.name || `Strategy ${s.id}`}</p>
              <p>Status: {s.status || "inactive"}</p>
            </div>
            {s.status === "active" ? (
              <button type="button" onClick={() => onDeactivate(s.id)} className="rounded bg-red-500 px-2 py-1 text-white">Stop</button>
            ) : (
              <button type="button" onClick={() => onActivate(s.id)} className="rounded bg-emerald-500 px-2 py-1 text-white">Start</button>
            )}
          </div>
        ))}
        {!strategies.length ? <p className="text-xs text-slate-400">No strategies found.</p> : null}
      </div>
    </section>
  );
}
