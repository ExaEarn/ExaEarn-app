import { useMemo, useState } from "react";
import { ArrowDownRight, ArrowLeft, ArrowUpRight, Check, ChevronDown, MapPin } from "lucide-react";

const BRAND = {
  positive: "#16C784",
  negative: "#EA3943",
};

const timeframes = ["1H", "24H", "7D", "1M", "3M", "1Y", "ALL"];

const chartSeries = {
  "1H": [52, 53, 55, 54, 56, 57, 59, 58, 60, 61, 62],
  "24H": [42, 44, 46, 45, 47, 50, 48, 49, 51, 53, 55],
  "7D": [62, 60, 58, 56, 54, 52, 50, 49, 48, 47, 46],
  "1M": [38, 39, 42, 45, 47, 49, 51, 54, 56, 58, 61],
  "3M": [30, 33, 35, 37, 39, 42, 45, 46, 47, 50, 53],
  "1Y": [20, 24, 28, 30, 34, 38, 41, 43, 47, 52, 58],
  ALL: [12, 14, 17, 20, 24, 29, 34, 39, 45, 52, 60],
};

const changeByFrame = {
  "1H": 1.24,
  "24H": 5.42,
  "7D": -3.18,
  "1M": 7.33,
  "3M": 11.49,
  "1Y": 34.85,
  ALL: 124.12,
};

const timezoneOptions = ["Auto Detect", "UTC", "UTC +1 (WAT)", "UTC +2", "UTC +3", "GMT"];

function MarketAnalyticsPage({ onBack }) {
  const [frame, setFrame] = useState("24H");
  const [timezone, setTimezone] = useState("UTC +1 (WAT)");
  const [showTzSheet, setShowTzSheet] = useState(false);

  const series = chartSeries[frame];
  const change = changeByFrame[frame];
  const isPositive = change >= 0;
  const lineColor = isPositive ? BRAND.positive : BRAND.negative;

  const price = 0.0456;
  const totalSupply = 1_000_000_000;
  const circulatingSupply = 620_000_000;
  const delta = price * Math.abs(change / 100);
  const displayDelta = `${isPositive ? "+" : "-"}$${delta.toFixed(4)}`;
  const displayPct = `${isPositive ? "+" : ""}${change.toFixed(2)}%`;
  const marketCap = circulatingSupply * price;
  const fdv = totalSupply * price;
  const volume24h = marketCap * 0.081;

  const points = useMemo(() => {
    const width = 340;
    const height = 170;
    const min = Math.min(...series);
    const max = Math.max(...series);
    const span = Math.max(1, max - min);
    return series
      .map((value, idx) => {
        const x = (idx / (series.length - 1)) * width;
        const y = height - ((value - min) / span) * height;
        return `${x},${y}`;
      })
      .join(" ");
  }, [series]);

  return (
    <main className="min-h-screen bg-gradient-to-br from-[#07050f] via-[#130a23] to-[#1d1134] text-violet-50">
      <header className="sticky top-0 z-30 border-b border-violet-200/15 bg-gradient-to-r from-cosmic-900/95 via-cosmic-800/95 to-cosmic-700/95 backdrop-blur">
        <div className="mx-auto w-full max-w-3xl px-4 pb-4 pt-3 sm:px-6">
          <div className="mb-2 flex items-center justify-between">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-violet-200/20 bg-cosmic-800/70 px-3 py-1.5 text-sm text-violet-100"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <button
              type="button"
              onClick={() => setShowTzSheet(true)}
              className="inline-flex items-center gap-1 rounded-xl border border-violet-200/20 bg-cosmic-800/70 px-3 py-1.5 text-xs font-medium text-violet-100"
            >
              <MapPin className="h-3.5 w-3.5" />
              Time Zone: {timezone}
              <ChevronDown className="h-3.5 w-3.5 text-auric-300" />
            </button>
          </div>
          <h1 className="text-xl font-semibold text-violet-50">Market Analytics</h1>
          <p className="text-sm text-violet-100/65">Change (%) & Chart Timezone</p>
        </div>
      </header>

      <section className="mx-auto w-full max-w-3xl space-y-4 px-4 pb-8 pt-4 sm:px-6">
        <article className="rounded-2xl border border-violet-200/12 bg-cosmic-900/75 p-4 shadow-cosmic-card">
          <p className="text-sm font-medium text-violet-100">EXA Token</p>
          <div className="mt-1 flex items-center gap-2">
            <p className="text-3xl font-bold text-violet-50">${price.toFixed(4)}</p>
            <span className="h-2.5 w-2.5 rounded-full bg-auric-300 animate-pulse" />
          </div>
          <p className={`mt-1 text-sm font-medium ${isPositive ? "text-[#16C784]" : "text-[#EA3943]"}`}>
            {displayPct} ({displayDelta})
          </p>
          <p className="mt-1 text-xs text-violet-100/60">
            Total Supply: {(totalSupply / 1_000_000_000).toFixed(1)}B EXA • Circulating: {(circulatingSupply / 1_000_000).toFixed(0)}M EXA
          </p>
        </article>

        <div className="flex flex-wrap gap-2">
          {timeframes.map((item) => (
            <button
              key={item}
              type="button"
              onClick={() => setFrame(item)}
              className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${
                frame === item
                  ? "bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 text-cosmic-900 shadow-[0_6px_14px_rgba(212,175,55,0.35)]"
                  : "bg-cosmic-800/75 text-violet-100 hover:bg-cosmic-700/80"
              }`}
            >
              {item}
            </button>
          ))}
        </div>

        <article className="rounded-2xl border border-violet-200/12 bg-cosmic-900/75 p-4 shadow-cosmic-card">
          <div className="mb-2 flex items-center justify-between">
            <p className="text-sm font-medium text-violet-100">EXA Price Chart</p>
            <p className="text-xs text-violet-100/60">{frame} • {timezone}</p>
          </div>
          <div className="relative h-48 w-full overflow-hidden rounded-xl bg-cosmic-800/70">
            <svg viewBox="0 0 340 180" className="h-full w-full">
              <defs>
                <linearGradient id="exaArea" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#7f46d4" stopOpacity="0.35" />
                  <stop offset="100%" stopColor="#1a0f2e" stopOpacity="0.05" />
                </linearGradient>
              </defs>
              <polyline fill="url(#exaArea)" stroke="transparent" points={`${points} 340,180 0,180`} />
              <polyline fill="none" stroke={lineColor} strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" points={points} />
            </svg>
            <div className="pointer-events-none absolute inset-x-2 bottom-1 flex justify-between text-[10px] text-violet-100/55">
              <span>00:00</span>
              <span>06:00</span>
              <span>12:00</span>
              <span>18:00</span>
              <span>24:00</span>
            </div>
          </div>
        </article>

        <article className="grid grid-cols-2 gap-3 rounded-2xl border border-violet-200/12 bg-cosmic-900/75 p-4 shadow-cosmic-card">
          <StatItem label="Market Cap" value={`$${(marketCap / 1_000_000).toFixed(2)}M`} positive />
          <StatItem label="24H Volume" value={`$${(volume24h / 1_000_000).toFixed(2)}M`} positive />
          <StatItem label="Circulating Supply" value={`${(circulatingSupply / 1_000_000).toFixed(0)}M EXA`} />
          <StatItem label="Total Supply" value={`${(totalSupply / 1_000_000_000).toFixed(1)}B EXA`} />
          <StatItem label="Fully Diluted Valuation" value={`$${(fdv / 1_000_000).toFixed(2)}M`} positive />
        </article>
      </section>

      {showTzSheet ? (
        <div className="fixed inset-0 z-50 flex items-end bg-black/40">
          <button type="button" className="absolute inset-0" onClick={() => setShowTzSheet(false)} />
          <div className="relative w-full rounded-t-2xl border border-violet-200/15 bg-cosmic-900 p-4 shadow-[0_-10px_30px_rgba(10,31,68,0.35)] animate-[slideUp_.22s_ease-out]">
            <h3 className="mb-3 text-base font-semibold text-violet-50">Select Time Zone</h3>
            <div className="space-y-2">
              {timezoneOptions.map((item) => (
                <button
                  key={item}
                  type="button"
                  onClick={() => {
                    setTimezone(item);
                    setShowTzSheet(false);
                  }}
                  className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm ${
                    timezone === item ? "bg-auric-300/15 text-violet-50" : "bg-cosmic-800 text-violet-100"
                  }`}
                >
                  <span>{item}</span>
                  {timezone === item ? <Check className="h-4 w-4 text-auric-300" /> : null}
                </button>
              ))}
            </div>
          </div>
        </div>
      ) : null}
    </main>
  );
}

function StatItem({ label, value, positive = false, negative = false }) {
  return (
    <div className="rounded-xl border border-violet-200/10 bg-cosmic-800/65 p-3">
      <p className="text-xs text-violet-100/60">{label}</p>
      <p className="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-violet-50">
        {value}
        {positive ? <ArrowUpRight className="h-3.5 w-3.5 text-[#16C784]" /> : null}
        {negative ? <ArrowDownRight className="h-3.5 w-3.5 text-[#EA3943]" /> : null}
      </p>
    </div>
  );
}

export default MarketAnalyticsPage;
