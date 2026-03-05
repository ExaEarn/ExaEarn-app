import { useMemo, useState } from "react";
import { RefreshCw, WifiOff, X } from "lucide-react";
import MarketHeader from "../../components/market/MarketHeader";
import FilterBar from "../../components/market/FilterBar";
import PairList from "../../components/market/PairList";
import SkeletonRow from "../../components/market/SkeletonRow";
import BottomNav from "../../components/market/BottomNav";
import useMarketData from "../../components/market/useMarketData";

function parseVolumeToNumber(value) {
  if (!value) return 0;
  const cleaned = String(value).toUpperCase();
  const amount = parseFloat(cleaned.replace(/[^\d.]/g, ""));
  if (cleaned.endsWith("B")) return amount * 1_000_000_000;
  if (cleaned.endsWith("M")) return amount * 1_000_000;
  if (cleaned.endsWith("K")) return amount * 1_000;
  return amount;
}

function Market({ onBack, onOpenTrade, onOpenFutures, onOpenP2P, onOpenCrypto }) {
  const { loading, offline, pairs, setPairs } = useMarketData();
  const [fiat, setFiat] = useState("USD");
  const [searchTerm, setSearchTerm] = useState("");
  const [activeChip, setActiveChip] = useState("");
  const [sortBy, setSortBy] = useState("last");
  const [sortDirection, setSortDirection] = useState("desc");
  const [favoritesOnly, setFavoritesOnly] = useState(false);
  const [selectedPair, setSelectedPair] = useState(null);
  const [chartPair, setChartPair] = useState(null);
  const [activeSubTab, setActiveSubTab] = useState("Spot");

  const handleSubTabChange = (tab) => {
    setActiveSubTab(tab);
    if (tab === "Futures") {
      onOpenFutures?.();
      return;
    }
    if (tab === "P2P") {
      onOpenP2P?.();
      return;
    }
    if (tab === "Crypto") {
      onOpenCrypto?.();
    }
  };

  const favoritesMap = useMemo(() => {
    return pairs.reduce((accumulator, pair) => {
      accumulator[pair.pair] = Boolean(pair.favorite);
      return accumulator;
    }, {});
  }, [pairs]);

  const filteredPairs = useMemo(() => {
    const normalizedSearch = searchTerm.trim().toLowerCase();

    const result = pairs
      .filter((item) => {
        if (favoritesOnly && !item.favorite) return false;
        if (activeChip && !item.pair.includes(activeChip)) return false;
        if (!normalizedSearch) return true;
        return (
          item.pair.toLowerCase().includes(normalizedSearch) ||
          item.base.toLowerCase().includes(normalizedSearch) ||
          item.quote.toLowerCase().includes(normalizedSearch)
        );
      })
      .sort((left, right) => {
        const direction = sortDirection === "asc" ? 1 : -1;
        if (sortBy === "change24h") return (left.change24h - right.change24h) * direction;
        if (sortBy === "volume") return (parseVolumeToNumber(left.volume) - parseVolumeToNumber(right.volume)) * direction;
        return (left.last - right.last) * direction;
      });

    return result;
  }, [pairs, favoritesOnly, activeChip, searchTerm, sortBy, sortDirection]);

  const toggleFavorite = (pairKey) => {
    setPairs((previous) =>
      previous.map((item) => (item.pair === pairKey ? { ...item, favorite: !item.favorite } : item))
    );
  };

  const copyPair = async (pairKey) => {
    try {
      await navigator.clipboard.writeText(pairKey);
    } catch (error) {
      console.error("Copy failed:", error);
    }
  };

  return (
    <main className="flex min-h-screen flex-col bg-[#0b0f18] text-white">
      {/* Replace useMarketData internals with API + websocket in one place when backend is ready. */}
      <MarketHeader
        fiat={fiat}
        onFiatChange={setFiat}
        activeSubTab={activeSubTab}
        onSelectSubTab={handleSubTabChange}
      />

      {offline ? (
        <div className="mx-4 mt-3 flex items-center gap-2 rounded-xl border border-[#ffb900]/30 bg-[#ffb900]/10 px-3 py-2 text-xs text-[#ffde7a]">
          <WifiOff className="h-3.5 w-3.5" />
          Offline - using cached data
        </div>
      ) : null}

      <FilterBar
        searchTerm={searchTerm}
        onSearchChange={setSearchTerm}
        activeChip={activeChip}
        onChipSelect={setActiveChip}
        sortBy={sortBy}
        sortDirection={sortDirection}
        onSortByChange={setSortBy}
        onSortDirectionToggle={() => setSortDirection((previous) => (previous === "desc" ? "asc" : "desc"))}
        favoritesOnly={favoritesOnly}
        onFavoritesToggle={() => setFavoritesOnly((previous) => !previous)}
      />

      <section className="flex-1 overflow-hidden pb-2 pt-2">
        {loading ? (
          <div className="space-y-1">
            <div className="mx-4 flex items-center gap-2 text-xs text-[#9aa4b2]">
              <RefreshCw className="h-3.5 w-3.5 animate-spin text-[#ffb900]" />
              Loading markets...
            </div>
            {Array.from({ length: 6 }).map((_, index) => (
              <SkeletonRow key={index} />
            ))}
          </div>
        ) : filteredPairs.length ? (
          <PairList
            items={filteredPairs}
            favoritesMap={favoritesMap}
            onOpenDetails={setSelectedPair}
            onToggleFavorite={toggleFavorite}
            onOpenChart={setChartPair}
            onCopyPair={copyPair}
            fiat={fiat}
            viewportHeight={460}
          />
        ) : (
          <div className="mx-4 mt-4 rounded-2xl border border-white/10 bg-[#0f1720] p-6 text-center text-sm text-[#9aa4b2]">
            No markets found. Try clearing filters.
          </div>
        )}
      </section>

      <BottomNav onHome={onBack} onTrade={onOpenTrade} onFutures={onOpenFutures} />

      {selectedPair ? (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-black/60 p-4 sm:items-center">
          <div className="w-full max-w-md rounded-2xl border border-white/15 bg-[#0f1720] p-4 shadow-[0_20px_40px_rgba(0,0,0,0.45)]">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-semibold text-white">{selectedPair.pair}</h3>
              <button
                type="button"
                onClick={() => setSelectedPair(null)}
                className="rounded-md border border-white/15 p-1.5 text-[#9aa4b2] hover:text-white"
                aria-label="Close pair details"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
            <p className="mt-2 text-sm text-[#9aa4b2]">Choose an action for this market.</p>
            <div className="mt-4 grid grid-cols-3 gap-2">
              <button type="button" className="rounded-lg bg-gradient-to-r from-[#6b2cff] to-[#ffb900] py-2 text-sm font-semibold text-white">
                Trade
              </button>
              <button type="button" className="rounded-lg border border-[#18c06c]/35 bg-[#18c06c]/10 py-2 text-sm font-semibold text-[#79f2b4]">
                Buy
              </button>
              <button type="button" className="rounded-lg border border-[#ff5b6b]/35 bg-[#ff5b6b]/10 py-2 text-sm font-semibold text-[#ff9aa8]">
                Sell
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {chartPair ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/65 p-4">
          <div className="w-full max-w-md rounded-2xl border border-white/15 bg-[#0f1720] p-4">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-semibold text-white">{chartPair.pair} Mini Chart</h3>
              <button
                type="button"
                onClick={() => setChartPair(null)}
                className="rounded-md border border-white/15 p-1.5 text-[#9aa4b2] hover:text-white"
                aria-label="Close chart modal"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
            <div className="mt-4 h-36 rounded-xl border border-white/10 bg-[#111d2b] p-3">
              <div className="flex h-full items-end gap-1.5">
                {[35, 42, 30, 55, 46, 60, 50, 63, 52, 70, 58, 66].map((height, index) => (
                  <span
                    key={index}
                    className="w-full rounded-t bg-gradient-to-t from-[#6b2cff] to-[#ffb900]"
                    style={{ height: `${height}%` }}
                  />
                ))}
              </div>
            </div>
          </div>
        </div>
      ) : null}
    </main>
  );
}

export default Market;
