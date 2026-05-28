import { useEffect, useMemo, useState } from "react";
import {
  Filter,
  Flame,
  LineChart,
  Search,
  ShieldCheck,
  Star,
  TrendingDown,
  TrendingUp,
  X,
} from "lucide-react";

const topTabs = ["All", "Favorites", "Spot", "Futures", "Trending", "Gainers", "Losers", "New Listings"];
const marketFilters = ["USDT", "BTC", "ETH", "DeFi", "AI", "Layer 1", "Meme", "Gaming", "RWA", "Stablecoins"];

const marketSeed = [
  { symbol: "BTC", name: "Bitcoin", pair: "BTC/USDT", price: 68054.45, change24h: 2.18, high24h: 69200.12, low24h: 66840.6, volume: "1.70B", marketCap: "1.34T", type: "Spot", tags: ["USDT", "Layer 1"], trending: true, newListing: false, spark: [42, 45, 44, 49, 53, 51, 58] },
  { symbol: "ETH", name: "Ethereum", pair: "ETH/USDT", price: 1967.82, change24h: -1.34, high24h: 2015.1, low24h: 1930.3, volume: "973.49M", marketCap: "236.9B", type: "Spot", tags: ["USDT", "Layer 1"], trending: true, newListing: false, spark: [62, 61, 59, 58, 56, 53, 54] },
  { symbol: "XRP", name: "XRP", pair: "XRP/USDT", price: 1.3572, change24h: 4.64, high24h: 1.395, low24h: 1.289, volume: "181.08M", marketCap: "76.2B", type: "Futures", tags: ["USDT", "RWA"], trending: true, newListing: false, spark: [30, 33, 35, 36, 40, 44, 48] },
  { symbol: "SOL", name: "Solana", pair: "SOL/USDT", price: 154.27, change24h: 5.12, high24h: 156.0, low24h: 147.2, volume: "642.25M", marketCap: "71.0B", type: "Spot", tags: ["USDT", "Layer 1"], trending: true, newListing: false, spark: [35, 38, 39, 44, 46, 50, 55] },
  { symbol: "TON", name: "Toncoin", pair: "TON/USDT", price: 6.44, change24h: -2.65, high24h: 6.71, low24h: 6.31, volume: "188.03M", marketCap: "22.3B", type: "Futures", tags: ["USDT", "Layer 1"], trending: false, newListing: false, spark: [70, 67, 65, 62, 58, 54, 52] },
  { symbol: "ONDO", name: "Ondo", pair: "ONDO/USDT", price: 1.03, change24h: 7.8, high24h: 1.06, low24h: 0.95, volume: "96.72M", marketCap: "3.1B", type: "Spot", tags: ["USDT", "RWA", "DeFi"], trending: false, newListing: true, spark: [22, 24, 23, 30, 34, 37, 43] },
  { symbol: "PEPE", name: "Pepe", pair: "PEPE/USDT", price: 0.000012, change24h: -4.92, high24h: 0.000013, low24h: 0.000011, volume: "314.11M", marketCap: "4.7B", type: "Spot", tags: ["USDT", "Meme"], trending: false, newListing: false, spark: [68, 66, 62, 58, 54, 52, 49] },
  { symbol: "AI16Z", name: "AI16Z", pair: "AI16Z/USDT", price: 0.312, change24h: 9.2, high24h: 0.34, low24h: 0.27, volume: "41.83M", marketCap: "410M", type: "Futures", tags: ["USDT", "AI"], trending: false, newListing: true, spark: [15, 20, 22, 24, 28, 35, 42] },
];

function CryptoMarkets({ onBack, onOpenTrade }) {
  const [activeTab, setActiveTab] = useState("All");
  const [activeFilter, setActiveFilter] = useState("USDT");
  const [searchTerm, setSearchTerm] = useState("");
  const [loading, setLoading] = useState(true);
  const [coins, setCoins] = useState(marketSeed);
  const [flashMap, setFlashMap] = useState({});
  const [tradeCoin, setTradeCoin] = useState(null);
  const [suggestionsOpen, setSuggestionsOpen] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => setLoading(false), 700);
    return () => clearTimeout(timer);
  }, []);

  useEffect(() => {
    const timer = setInterval(() => {
      setCoins((previous) =>
        previous.map((coin) => {
          const drift = (Math.random() - 0.5) * (coin.price > 100 ? 0.004 : 0.02);
          const nextPrice = Math.max(0.000001, coin.price * (1 + drift));
          const direction = nextPrice >= coin.price ? "up" : "down";
          setFlashMap((existing) => ({ ...existing, [coin.pair]: direction }));
          setTimeout(() => {
            setFlashMap((existing) => ({ ...existing, [coin.pair]: "" }));
          }, 260);
          return {
            ...coin,
            price: Number(nextPrice.toFixed(nextPrice > 1 ? 4 : 6)),
            change24h: Number((coin.change24h + (Math.random() - 0.5) * 0.28).toFixed(2)),
            spark: coin.spark.map((point, index) => {
              if (index !== coin.spark.length - 1) return point;
              return Math.max(10, Math.min(90, point + (Math.random() - 0.5) * 10));
            }),
          };
        })
      );
    }, 2200);
    return () => clearInterval(timer);
  }, []);

  const suggestions = useMemo(() => {
    if (!searchTerm.trim()) return [];
    const text = searchTerm.trim().toLowerCase();
    return coins
      .filter((coin) => coin.symbol.toLowerCase().includes(text) || coin.name.toLowerCase().includes(text) || coin.pair.toLowerCase().includes(text))
      .slice(0, 6);
  }, [coins, searchTerm]);

  const trending = useMemo(() => [...coins].sort((a, b) => Math.abs(b.change24h) - Math.abs(a.change24h)).slice(0, 5), [coins]);
  const gainers = useMemo(() => [...coins].filter((coin) => coin.change24h > 0).sort((a, b) => b.change24h - a.change24h).slice(0, 5), [coins]);
  const losers = useMemo(() => [...coins].filter((coin) => coin.change24h < 0).sort((a, b) => a.change24h - b.change24h).slice(0, 5), [coins]);

  const filteredCoins = useMemo(() => {
    const term = searchTerm.trim().toLowerCase();
    return coins.filter((coin) => {
      const bySearch =
        !term ||
        coin.name.toLowerCase().includes(term) ||
        coin.symbol.toLowerCase().includes(term) ||
        coin.pair.toLowerCase().includes(term);
      const byChip = !activeFilter || coin.tags.includes(activeFilter);
      const byTab =
        activeTab === "All" ||
        (activeTab === "Favorites" && coin.favorite) ||
        (activeTab === "Spot" && coin.type === "Spot") ||
        (activeTab === "Futures" && coin.type === "Futures") ||
        (activeTab === "Trending" && coin.trending) ||
        (activeTab === "Gainers" && coin.change24h > 0) ||
        (activeTab === "Losers" && coin.change24h < 0) ||
        (activeTab === "New Listings" && coin.newListing);
      return bySearch && byChip && byTab;
    });
  }, [coins, searchTerm, activeFilter, activeTab]);

  const toggleFavorite = (pair) => {
    setCoins((previous) => previous.map((coin) => (coin.pair === pair ? { ...coin, favorite: !coin.favorite } : coin)));
  };

  return (
    <main className="min-h-screen bg-[#0B0F1A] text-white">
      <div className="mx-auto w-full max-w-md pb-24 sm:max-w-2xl lg:max-w-6xl">
        <header className="sticky top-0 z-30 border-b border-white/10 bg-[#0B0F1A]/95 px-4 pt-4 backdrop-blur">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <button type="button" onClick={onBack} className="rounded-lg border border-white/15 bg-[#111827] px-2 py-1 text-xs text-[#9CA3AF]">
                Back
              </button>
              <h1 className="font-['Sora'] text-xl font-semibold">Markets</h1>
            </div>
            <div className="flex items-center gap-2">
              <button type="button" className="rounded-lg border border-white/15 bg-[#111827] p-2 text-[#9CA3AF]">
                <Search className="h-4 w-4" />
              </button>
              <button type="button" className="rounded-lg border border-white/15 bg-[#111827] p-2 text-[#9CA3AF]">
                <Filter className="h-4 w-4" />
              </button>
            </div>
          </div>

          <div className="mt-3 flex gap-5 overflow-x-auto pb-3 text-xs font-semibold">
            {topTabs.map((tab) => (
              <button
                key={tab}
                type="button"
                onClick={() => setActiveTab(tab)}
                className={`relative whitespace-nowrap pb-1.5 ${activeTab === tab ? "text-white" : "text-[#9CA3AF]"}`}
              >
                {tab}
                {activeTab === tab ? <span className="absolute bottom-0 left-0 h-0.5 w-full rounded-full bg-gradient-to-r from-[#6b2cff] to-[#ffb900]" /> : null}
              </button>
            ))}
          </div>
        </header>

        <section className="sticky top-[95px] z-20 bg-[#0B0F1A]/97 px-4 pb-3 pt-3 backdrop-blur">
          <div className="relative">
            <input
              value={searchTerm}
              onChange={(event) => {
                setSearchTerm(event.target.value);
                setSuggestionsOpen(true);
              }}
              onFocus={() => setSuggestionsOpen(true)}
              placeholder="Search crypto by name or symbol (e.g., BTC, ETH)"
              className="w-full rounded-xl border border-white/15 bg-[#111827] px-3 py-2.5 text-sm text-white outline-none placeholder:text-[#9CA3AF] focus:border-[#ffb900] focus:shadow-[0_0_16px_rgba(255,185,0,0.2)]"
            />
            {suggestionsOpen && suggestions.length ? (
              <div className="absolute left-0 right-0 top-[calc(100%+0.4rem)] rounded-xl border border-white/10 bg-[#111827] p-2 shadow-[0_18px_40px_rgba(0,0,0,0.45)]">
                {suggestions.map((coin) => (
                  <button
                    key={`suggestion-${coin.pair}`}
                    type="button"
                    onClick={() => {
                      setSearchTerm(coin.symbol);
                      setSuggestionsOpen(false);
                    }}
                    className="flex w-full items-center justify-between rounded-lg px-2 py-2 text-left text-xs hover:bg-[#1A2538]"
                  >
                    <span className="text-[#d5dbe7]">{coin.name}</span>
                    <span className="text-[#9CA3AF]">{coin.pair}</span>
                  </button>
                ))}
              </div>
            ) : null}
          </div>

          <div className="mt-3 flex gap-2 overflow-x-auto pb-1">
            {marketFilters.map((chip) => (
              <button
                key={chip}
                type="button"
                onClick={() => setActiveFilter(chip)}
                className={`rounded-full border px-3 py-1 text-xs font-semibold ${
                  activeFilter === chip
                    ? "border-transparent bg-gradient-to-r from-[#6b2cff] to-[#ffb900] text-white"
                    : "border-white/15 bg-[#111827] text-[#9CA3AF]"
                }`}
              >
                {chip}
              </button>
            ))}
          </div>
        </section>

        <section className="px-4 pt-3">
          <div className="grid gap-2 sm:grid-cols-3">
            <MiniBoard title="Trending Coins" icon={<Flame className="h-4 w-4 text-[#ffb900]" />} items={trending} />
            <MiniBoard title="Top Gainers" icon={<TrendingUp className="h-4 w-4 text-[#18c06c]" />} items={gainers} />
            <MiniBoard title="Top Losers" icon={<TrendingDown className="h-4 w-4 text-[#ff5b6b]" />} items={losers} />
          </div>
        </section>

        <section className="mt-3 px-4">
          <div className="hidden rounded-xl border border-white/10 bg-[#111827] px-3 py-2 text-[11px] text-[#9CA3AF] lg:grid lg:grid-cols-[1.45fr_1fr_0.9fr_0.9fr_1fr_1fr_0.7fr]">
            <span>Pair</span>
            <span>Price</span>
            <span>24h Change</span>
            <span>24h High</span>
            <span>24h Low</span>
            <span>Volume / Market Cap</span>
            <span className="text-right">Action</span>
          </div>

          <div className="mt-2 space-y-1.5">
            {loading
              ? Array.from({ length: 7 }).map((_, index) => <MarketSkeleton key={index} />)
              : filteredCoins.map((coin) => (
                  <MarketRow
                    key={coin.pair}
                    coin={coin}
                    flash={flashMap[coin.pair]}
                    onTrade={() => setTradeCoin(coin)}
                    onToggleFavorite={() => toggleFavorite(coin.pair)}
                    onOpenTradePage={onOpenTrade}
                  />
                ))}
            {!loading && !filteredCoins.length ? (
              <div className="rounded-xl border border-white/10 bg-[#111827] px-3 py-8 text-center text-sm text-[#9CA3AF]">
                No markets found. Try a different filter or search term.
              </div>
            ) : null}
          </div>
        </section>

        <section className="px-4 pb-6 pt-4">
          <div className="rounded-2xl border border-[#ffb900]/30 bg-[#ffb900]/10 p-3 text-xs text-[#f9e8b4]">
            <p className="flex items-start gap-2">
              <ShieldCheck className="mt-0.5 h-4 w-4 text-[#ffb900]" />
              ExaEarn provides real-time market data and deep liquidity across all supported crypto pairs.
            </p>
          </div>
        </section>
      </div>

      <button
        type="button"
        className="fixed bottom-5 right-5 z-40 inline-flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-r from-[#6b2cff] to-[#ffb900] text-white shadow-[0_0_26px_rgba(255,185,0,0.35)]"
      >
        <Filter className="h-5 w-5" />
      </button>

      {tradeCoin ? (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-4 sm:items-center">
          <div className="w-full max-w-sm rounded-2xl border border-white/15 bg-[#111827] p-4">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-semibold">{tradeCoin.pair}</h3>
              <button type="button" onClick={() => setTradeCoin(null)} className="rounded-md border border-white/15 p-1 text-[#9CA3AF]">
                <X className="h-4 w-4" />
              </button>
            </div>
            <div className="mt-3 grid grid-cols-2 gap-2 text-sm">
              <button type="button" onClick={onOpenTrade} className="rounded-lg border border-[#18c06c]/35 bg-[#18c06c]/15 py-2 font-semibold text-[#84f0b8]">
                Buy
              </button>
              <button type="button" onClick={onOpenTrade} className="rounded-lg border border-[#ff5b6b]/35 bg-[#ff5b6b]/15 py-2 font-semibold text-[#ff9aa8]">
                Sell
              </button>
              <button type="button" className="rounded-lg border border-white/15 bg-[#0E1627] py-2 text-[#d0d7e5]">
                View Chart
              </button>
              <button
                type="button"
                onClick={() => toggleFavorite(tradeCoin.pair)}
                className="rounded-lg border border-white/15 bg-[#0E1627] py-2 text-[#d0d7e5]"
              >
                Add to Watchlist
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </main>
  );
}

function MiniBoard({ title, icon, items }) {
  return (
    <article className="rounded-xl border border-white/10 bg-[#111827] p-2.5">
      <div className="mb-2 flex items-center gap-1 text-xs font-semibold text-[#d8deea]">
        {icon}
        {title}
      </div>
      <div className="flex gap-2 overflow-x-auto pb-1">
        {items.map((item) => (
          <div key={`${title}-${item.pair}`} className="min-w-[110px] rounded-lg border border-white/10 bg-[#0E1627] px-2 py-1.5">
            <p className="text-[11px] font-semibold">{item.symbol}</p>
            <p className={`text-[11px] ${item.change24h >= 0 ? "text-[#18c06c]" : "text-[#ff5b6b]"}`}>{item.change24h.toFixed(2)}%</p>
          </div>
        ))}
      </div>
    </article>
  );
}

function MarketRow({ coin, flash, onTrade, onToggleFavorite, onOpenTradePage }) {
  const up = coin.change24h >= 0;
  return (
    <article className="group rounded-xl border border-white/10 bg-[#111827] px-3 py-3 transition-all duration-150 hover:-translate-y-[1px] hover:border-[#6b2cff]/35 hover:shadow-[0_0_20px_rgba(107,44,255,0.2)]">
      <div className="lg:hidden">
        <div className="flex items-center justify-between gap-2">
          <div className="min-w-0 flex items-center gap-2">
            <button type="button" onClick={onToggleFavorite} className={`${coin.favorite ? "text-[#ffb900]" : "text-[#6b7280]"} transition`}>
              <Star className={`h-4 w-4 ${coin.favorite ? "fill-current drop-shadow-[0_0_6px_rgba(255,185,0,0.5)]" : ""}`} />
            </button>
            <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-[#1A2538] text-[11px] font-bold text-[#d7dff0]">
              {coin.symbol.slice(0, 2)}
            </span>
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold">{coin.name}</p>
              <p className="text-xs text-[#9CA3AF]">{coin.pair}</p>
            </div>
          </div>
          <button
            type="button"
            onClick={onTrade}
            className="rounded-lg border border-[#6b2cff]/40 bg-gradient-to-r from-[#6b2cff]/10 to-[#ffb900]/10 px-3 py-1.5 text-xs font-semibold text-[#e7dbff]"
          >
            Trade
          </button>
        </div>
        <div className="mt-2 flex items-center justify-between">
          <p
            className={`text-base font-semibold transition ${
              flash === "up" ? "text-[#18c06c]" : flash === "down" ? "text-[#ff5b6b]" : "text-white"
            }`}
          >
            {formatPrice(coin.price)}
          </p>
          <p className={`rounded-full px-2 py-0.5 text-xs font-semibold ${up ? "bg-[#18c06c]/20 text-[#85efb8]" : "bg-[#ff5b6b]/20 text-[#ff9ba8]"}`}>
            {up ? "+" : ""}
            {coin.change24h.toFixed(2)}%
          </p>
        </div>
        <div className="mt-2 flex items-center justify-between">
          <p className="text-xs text-[#9CA3AF]">Vol {coin.volume}</p>
          <Sparkline points={coin.spark} positive={up} />
        </div>
      </div>

      <div className="hidden items-center gap-3 lg:grid lg:grid-cols-[1.45fr_1fr_0.9fr_0.9fr_1fr_1fr_0.7fr]">
        <div className="flex items-center gap-2">
          <button type="button" onClick={onToggleFavorite} className={`${coin.favorite ? "text-[#ffb900]" : "text-[#6b7280]"} transition`}>
            <Star className={`h-4 w-4 ${coin.favorite ? "fill-current drop-shadow-[0_0_6px_rgba(255,185,0,0.5)]" : ""}`} />
          </button>
          <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-[#1A2538] text-[11px] font-bold">{coin.symbol.slice(0, 2)}</span>
          <div>
            <p className="text-sm font-semibold">{coin.name}</p>
            <p className="text-xs text-[#9CA3AF]">{coin.pair}</p>
          </div>
        </div>
        <p className={`text-sm font-semibold ${flash === "up" ? "text-[#18c06c]" : flash === "down" ? "text-[#ff5b6b]" : "text-white"}`}>{formatPrice(coin.price)}</p>
        <p className={`text-sm font-semibold ${up ? "text-[#18c06c]" : "text-[#ff5b6b]"}`}>{up ? "+" : ""}{coin.change24h.toFixed(2)}%</p>
        <p className="text-xs text-[#9CA3AF]">{formatPrice(coin.high24h)}</p>
        <p className="text-xs text-[#9CA3AF]">{formatPrice(coin.low24h)}</p>
        <div>
          <p className="text-xs text-[#9CA3AF]">{coin.volume}</p>
          <p className="text-[11px] text-[#6b7280]">{coin.marketCap}</p>
        </div>
        <div className="flex items-center justify-end gap-2">
          <Sparkline points={coin.spark} positive={up} />
          <button
            type="button"
            onClick={() => {
              onTrade();
              onOpenTradePage?.();
            }}
            className="rounded-lg border border-[#6b2cff]/40 bg-gradient-to-r from-[#6b2cff]/10 to-[#ffb900]/10 px-3 py-1.5 text-xs font-semibold text-[#ece2ff]"
          >
            Trade
          </button>
        </div>
      </div>
    </article>
  );
}

function Sparkline({ points, positive }) {
  const max = Math.max(...points);
  return (
    <div className="flex h-6 w-16 items-end gap-[2px]">
      {points.map((point, index) => (
        <span
          key={`${point}-${index}`}
          className={`w-full rounded-t ${positive ? "bg-[#18c06c]/75" : "bg-[#ff5b6b]/75"}`}
          style={{ height: `${Math.max(15, (point / max) * 100)}%` }}
        />
      ))}
    </div>
  );
}

function MarketSkeleton() {
  return (
    <div className="rounded-xl border border-white/10 bg-[#111827] px-3 py-3">
      <div className="animate-pulse">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="h-8 w-8 rounded-full bg-white/10" />
            <div>
              <div className="h-2.5 w-20 rounded bg-white/10" />
              <div className="mt-1.5 h-2 w-14 rounded bg-white/10" />
            </div>
          </div>
          <div className="h-6 w-14 rounded-lg bg-white/10" />
        </div>
        <div className="mt-2 h-2.5 w-24 rounded bg-white/10" />
      </div>
    </div>
  );
}

function formatPrice(value) {
  if (value >= 1000) return value.toLocaleString(undefined, { maximumFractionDigits: 2 });
  if (value >= 1) return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 });
  return value.toLocaleString(undefined, { minimumFractionDigits: 6, maximumFractionDigits: 6 });
}

export default CryptoMarkets;
