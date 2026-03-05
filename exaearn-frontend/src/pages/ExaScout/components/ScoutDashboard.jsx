import { Filter, Search } from "lucide-react";

function ScoutDashboard({
  search,
  onSearch,
  position,
  onPosition,
  country,
  onCountry,
  ranking,
  onRanking,
  positions,
  countries,
}) {
  return (
    <section className="rounded-2xl border border-violet-300/20 bg-cosmic-900/60 p-4 shadow-cosmic-card">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-auric-300/70">Scout Dashboard</p>
          <h2 className="mt-1 text-lg font-semibold text-violet-50">Talent Filters</h2>
        </div>
        <button className="btn-outline text-xs">Request Trial</button>
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-2">
        <div className="flex items-center gap-2 rounded-xl border border-violet-300/20 bg-cosmic-900/70 px-3 py-2 text-xs text-violet-100/70">
          <Search className="h-4 w-4 text-auric-300" aria-hidden="true" />
          <input
            value={search}
            onChange={(event) => onSearch(event.target.value)}
            placeholder="Search player name..."
            className="w-full bg-transparent text-xs text-violet-100/80 outline-none placeholder:text-violet-100/40"
          />
        </div>
        <div className="flex items-center gap-2 rounded-xl border border-violet-300/20 bg-cosmic-900/70 px-3 py-2 text-xs text-violet-100/70">
          <Filter className="h-4 w-4 text-auric-300" aria-hidden="true" />
          <select
            value={ranking}
            onChange={(event) => onRanking(event.target.value)}
            className="w-full bg-transparent text-xs text-violet-100/80 outline-none"
          >
            <option className="text-cosmic-900" value="Top Rated">Top Rated</option>
            <option className="text-cosmic-900" value="Most Viewed">Most Viewed</option>
            <option className="text-cosmic-900" value="A-Z">A-Z</option>
          </select>
        </div>
      </div>

      <div className="mt-3 grid gap-2 sm:grid-cols-2">
        <div className="rounded-xl border border-violet-300/20 bg-cosmic-900/70 px-3 py-2">
          <p className="text-xs text-violet-100/60">Position</p>
          <div className="mt-2 flex flex-wrap gap-2">
            {positions.map((item) => (
              <button
                key={item}
                type="button"
                onClick={() => onPosition(item)}
                className={`rounded-full border px-2.5 py-1 text-xs font-semibold transition-all duration-300 ${
                  position === item
                    ? "border-auric-300/70 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 text-cosmic-900"
                    : "border-violet-300/25 bg-cosmic-900/60 text-violet-100/70 hover:border-auric-300/60 hover:text-auric-200"
                }`}
              >
                {item}
              </button>
            ))}
          </div>
        </div>
        <div className="rounded-xl border border-violet-300/20 bg-cosmic-900/70 px-3 py-2">
          <p className="text-xs text-violet-100/60">Country</p>
          <div className="mt-2 flex flex-wrap gap-2">
            {countries.map((item) => (
              <button
                key={item}
                type="button"
                onClick={() => onCountry(item)}
                className={`rounded-full border px-2.5 py-1 text-xs font-semibold transition-all duration-300 ${
                  country === item
                    ? "border-auric-300/70 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 text-cosmic-900"
                    : "border-violet-300/25 bg-cosmic-900/60 text-violet-100/70 hover:border-auric-300/60 hover:text-auric-200"
                }`}
              >
                {item}
              </button>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}

export default ScoutDashboard;
