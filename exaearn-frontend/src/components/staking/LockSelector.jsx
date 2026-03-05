function LockSelector({ options, selected, onSelect }) {
  return (
    <section className="rounded-2xl border border-violet-300/20 bg-cosmic-900/60 p-4 shadow-cosmic-card transition-all duration-300 hover:-translate-y-1 hover:border-auric-300/60 hover:shadow-button-glow">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-auric-300/70">Lock Selector</p>
          <h2 className="mt-1 text-lg font-semibold text-violet-50">Choose Lock Duration</h2>
        </div>
        <span className="text-xs text-violet-100/60">Multiplier hint</span>
      </div>

      <div className="mt-4 grid gap-2 sm:grid-cols-2">
        {options.map((option) => {
          const active = option.value === selected;
          return (
            <button
              key={option.value}
              type="button"
              onClick={() => onSelect(option.value)}
              className={`flex items-center justify-between rounded-xl border px-3 py-2 text-xs font-semibold transition-all duration-300 ${
                active
                  ? "border-auric-300/70 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 text-cosmic-900 shadow-button-glow"
                  : "border-violet-300/25 bg-cosmic-900/70 text-violet-100/75 hover:-translate-y-0.5 hover:border-auric-300/60 hover:text-auric-200 hover:shadow-button-glow"
              }`}
            >
              <span>{option.value}</span>
              <span>{option.multiplier}</span>
            </button>
          );
        })}
      </div>
    </section>
  );
}

export default LockSelector;
