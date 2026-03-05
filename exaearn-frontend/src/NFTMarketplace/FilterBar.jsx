import { Search } from "lucide-react";

function FilterBar({ search, onSearch, category, onCategory, priceCap, onPriceCap, sortBy, onSortBy }) {
  return (
    <section className="nft-card rounded-2xl p-5 sm:p-6">
      <div className="grid gap-4 lg:grid-cols-[1.2fr_0.8fr_0.8fr_0.8fr]">
        <div>
          <label className="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-violet-100/75">Search</label>
          <div className="nft-input-wrap flex items-center rounded-xl px-4 py-3">
            <Search className="h-4 w-4 text-violet-200/70" aria-hidden="true" />
            <input
              type="text"
              value={search}
              onChange={(event) => onSearch(event.target.value)}
              placeholder="Search by name, collection, creator"
              className="ml-2 w-full bg-transparent text-sm text-violet-50 outline-none placeholder:text-violet-200/45"
            />
          </div>
        </div>

        <SelectField
          label="Category"
          value={category}
          onChange={onCategory}
          options={["All", "Art", "Gaming", "Music", "Utility", "Collectible"]}
        />

        <SelectField
          label="Price Filter"
          value={priceCap}
          onChange={onPriceCap}
          options={["All", "<= 0.50 ETH", "<= 1.00 ETH", "<= 2.00 ETH", "<= 5.00 ETH"]}
        />

        <SelectField
          label="Sort By"
          value={sortBy}
          onChange={onSortBy}
          options={["Newest", "Price: Low to High", "Price: High to Low", "Popular"]}
        />
      </div>
    </section>
  );
}

function SelectField({ label, value, onChange, options }) {
  return (
    <div>
      <label className="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-violet-100/75">{label}</label>
      <div className="nft-input-wrap rounded-xl px-4 py-3">
        <select value={value} onChange={(event) => onChange(event.target.value)} className="w-full bg-transparent text-sm text-violet-50 outline-none">
          {options.map((option) => (
            <option key={option} value={option} className="bg-cosmic-900 text-violet-50">
              {option}
            </option>
          ))}
        </select>
      </div>
    </div>
  );
}

export default FilterBar;
