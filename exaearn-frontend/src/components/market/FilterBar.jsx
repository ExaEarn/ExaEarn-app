import { ChevronDown, Search, Star } from "lucide-react";

const quickChips = ["XRP", "USDT", "BTC"];

function FilterBar({
  searchTerm,
  onSearchChange,
  activeChip,
  onChipSelect,
  sortBy,
  sortDirection,
  onSortByChange,
  onSortDirectionToggle,
  favoritesOnly,
  onFavoritesToggle,
}) {
  return (
    <section className="border-b border-white/10 bg-[#0b0f18] px-4 pb-3 pt-3">
      <div className="relative">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#9aa4b2]" />
        <input
          value={searchTerm}
          onChange={(event) => onSearchChange(event.target.value)}
          placeholder="Search symbol or name (e.g., XRP, BTC)"
          className="w-full rounded-xl border border-white/15 bg-[#0f1720] py-2.5 pl-9 pr-3 text-sm text-white outline-none placeholder:text-[#9aa4b2] focus:border-[#ffb900]"
          aria-label="Search market pairs"
        />
      </div>

      <div className="mt-3 flex items-center gap-2 overflow-x-auto pb-1">
        {quickChips.map((chip) => (
          <button
            key={chip}
            type="button"
            onClick={() => onChipSelect(activeChip === chip ? "" : chip)}
            className={`rounded-full border px-3 py-1 text-xs font-semibold ${
              activeChip === chip
                ? "border-transparent bg-gradient-to-r from-[#6b2cff] to-[#ffb900] text-white"
                : "border-white/15 bg-[#0f1720] text-[#9aa4b2]"
            }`}
            aria-label={`Filter by ${chip}`}
          >
            {chip}
          </button>
        ))}

        <button
          type="button"
          onClick={onFavoritesToggle}
          className={`inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold transition ${
            favoritesOnly
              ? "border-transparent bg-gradient-to-r from-[#6b2cff] to-[#ffb900] text-white shadow-[0_0_18px_rgba(255,185,0,0.25)]"
              : "border-white/15 bg-[#0f1720] text-[#9aa4b2]"
          }`}
          aria-label="Toggle favorites filter"
        >
          <Star className={`h-3.5 w-3.5 ${favoritesOnly ? "fill-current" : ""}`} />
          Favorites
        </button>
      </div>

      <div className="mt-3 flex items-center gap-2">
        <div className="relative">
          <select
            value={sortBy}
            onChange={(event) => onSortByChange(event.target.value)}
            className="appearance-none rounded-lg border border-white/15 bg-[#0f1720] py-2 pl-3 pr-8 text-xs font-medium text-white outline-none"
            aria-label="Sort market pairs"
          >
            <option value="last">Last Price</option>
            <option value="change24h">24h Change</option>
            <option value="volume">Volume</option>
          </select>
          <ChevronDown className="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#9aa4b2]" />
        </div>

        <button
          type="button"
          onClick={onSortDirectionToggle}
          className="rounded-lg border border-white/15 bg-[#0f1720] px-3 py-2 text-xs font-medium text-[#9aa4b2] hover:text-white"
          aria-label="Toggle sort direction"
        >
          {sortDirection === "desc" ? "Descending" : "Ascending"}
        </button>
      </div>
    </section>
  );
}

export default FilterBar;
