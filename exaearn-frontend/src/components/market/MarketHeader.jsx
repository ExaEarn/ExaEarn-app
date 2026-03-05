import { BarChart3, Settings2 } from "lucide-react";

const topTabs = ["Favorites", "Market", "Discover", "..."];
const marketTabs = ["Crypto", "Spot", "Futures", "P2P"];

function MarketHeader({ fiat = "USD", onFiatChange, activeSubTab = "Spot", onSelectSubTab = () => {} }) {
  return (
    <header className="border-b border-white/10 bg-[#0b0f18]/95 px-4 pt-4 backdrop-blur">
      <div className="flex items-center justify-between">
        <h1 className="font-['Sora'] text-xl font-semibold text-white">Market</h1>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => onFiatChange(fiat === "USD" ? "NGN" : "USD")}
            className="rounded-lg border border-white/15 bg-[#0f1720] px-2.5 py-1 text-xs font-semibold text-[#9aa4b2] hover:text-white"
            aria-label="Toggle fiat"
          >
            {fiat}
          </button>
          <button
            type="button"
            className="rounded-lg border border-white/15 bg-[#0f1720] p-2 text-[#9aa4b2] hover:text-[#ffb900]"
            aria-label="Open chart tools"
          >
            <BarChart3 className="h-4 w-4" />
          </button>
          <button
            type="button"
            className="rounded-lg border border-white/15 bg-[#0f1720] p-2 text-[#9aa4b2] hover:text-[#ffb900]"
            aria-label="Open market settings"
          >
            <Settings2 className="h-4 w-4" />
          </button>
        </div>
      </div>

      <div className="mt-3 flex gap-5 overflow-x-auto pb-2 text-sm">
        {topTabs.map((tab) => (
          <button
            key={tab}
            type="button"
            className={`relative whitespace-nowrap pb-1.5 font-medium ${
              tab === "Market" ? "text-white" : "text-[#9aa4b2]"
            }`}
            aria-label={`Open ${tab} tab`}
          >
            {tab}
            {tab === "Market" ? (
              <span className="absolute bottom-0 left-0 h-0.5 w-full rounded-full bg-gradient-to-r from-[#6b2cff] to-[#ffb900] transition-all duration-300" />
            ) : null}
          </button>
        ))}
      </div>

      <div className="mt-1 flex gap-2 overflow-x-auto pb-3">
        {marketTabs.map((tab) => (
          <button
            key={tab}
            type="button"
            onClick={() => onSelectSubTab(tab)}
            className={`rounded-full border px-3 py-1 text-xs font-medium ${
              tab === activeSubTab
                ? "border-transparent bg-gradient-to-r from-[#6b2cff] to-[#ffb900] text-white"
                : "border-white/15 bg-[#0f1720] text-[#9aa4b2]"
            }`}
            aria-label={`Open ${tab} market`}
          >
            {tab}
          </button>
        ))}
      </div>
    </header>
  );
}

export default MarketHeader;
