export default function AutoStrategyManager({ strategies = [], onActivate, onDeactivate }) {
  return (
    <section className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
      <h3 className="text-sm font-semibold text-[var(--exa-text-primary)]">Auto Strategy Manager</h3>
      <div className="mt-3 space-y-2">
        {strategies.slice(0, 5).map((s) => (
          <div key={s.id} className="flex items-center justify-between rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3 text-xs text-[var(--exa-text-secondary)]">
            <div>
              <p className="font-semibold">{s.name || `Strategy ${s.id}`}</p>
              <p>Status: {s.status || "inactive"}</p>
            </div>
            {s.status === "active" ? (
              <button type="button" onClick={() => onDeactivate(s.id)} className="rounded bg-red-500 px-2 py-1 text-[var(--exa-text-primary)]">Stop</button>
            ) : (
              <button type="button" onClick={() => onActivate(s.id)} className="rounded bg-emerald-500 px-2 py-1 text-[var(--exa-text-primary)]">Start</button>
            )}
          </div>
        ))}
        {!strategies.length ? <p className="text-xs text-[var(--exa-text-muted)]">No strategies found.</p> : null}
      </div>
    </section>
  );
}
