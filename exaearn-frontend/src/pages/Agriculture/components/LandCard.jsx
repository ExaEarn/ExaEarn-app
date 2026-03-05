function LandCard({ parcel, onAcquireShare }) {
  const themeClasses = {
    emerald: "from-emerald-500/40 via-emerald-500/10 to-transparent",
    gold: "from-amber-400/40 via-amber-300/10 to-transparent",
    violet: "from-violet-500/40 via-fuchsia-500/10 to-transparent",
  };

  return (
    <article className="w-full rounded-2xl border border-violet-300/20 bg-cosmic-900/70 p-3 shadow-cosmic-card">
      <div className={`h-20 sm:h-24 w-full rounded-xl bg-gradient-to-br ${themeClasses[parcel.theme] || themeClasses.violet}`} />
      <div className="mt-3">
        <p className="text-sm font-semibold text-violet-50">{parcel.name}</p>
        <p className="mt-1 text-xs text-violet-100/70">
          {parcel.size} · {parcel.location}
        </p>
      </div>
      <div className="mt-3 flex items-center justify-between text-xs">
        <span className="text-violet-100/60">Available</span>
        <span className="font-semibold text-auric-300">{parcel.availability}%</span>
      </div>
      <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-cosmic-800/80">
        <div
          className="h-full rounded-full bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500"
          style={{ width: `${parcel.availability}%` }}
        />
      </div>
      <button
        type="button"
        onClick={onAcquireShare}
        className="mt-3 inline-flex w-full items-center justify-center rounded-xl border border-auric-300/60 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 px-3 py-2 text-xs font-semibold text-cosmic-900 transition-all duration-300 hover:scale-[1.01] hover:shadow-button-glow"
      >
        Acquire Share
      </button>
    </article>
  );
}

export default LandCard;
