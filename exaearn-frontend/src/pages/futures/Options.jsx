import { ArrowLeft, ChartCandlestick, CircleDollarSign, ShieldCheck } from "lucide-react";

const optionsPairs = [
  { symbol: "BTC", expiry: "29 Mar 2026", strike: "68,000", type: "Call", bid: "2,120", ask: "2,248", iv: "57.2%" },
  { symbol: "ETH", expiry: "29 Mar 2026", strike: "2,000", type: "Call", bid: "84.1", ask: "89.7", iv: "62.9%" },
  { symbol: "XRP", expiry: "29 Mar 2026", strike: "1.40", type: "Put", bid: "0.062", ask: "0.067", iv: "71.4%" },
  { symbol: "SOL", expiry: "29 Mar 2026", strike: "160", type: "Call", bid: "10.8", ask: "11.5", iv: "66.1%" },
];

function Options({ onBack, onOpenSmartMoney }) {
  return (
    <main className="min-h-screen bg-[#0B0F1A] px-4 pb-8 text-white">
      <div className="mx-auto w-full max-w-5xl pt-4">
        <header className="sticky top-0 z-20 mb-4 rounded-2xl border border-white/10 bg-[#0B0F1A]/95 p-3 backdrop-blur">
          <div className="flex items-center justify-between gap-3">
            <button
              type="button"
              onClick={onBack}
              className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/15 bg-[#111827] text-[#d7dde8]"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div className="text-center">
              <h1 className="text-lg font-semibold">Options Market</h1>
              <p className="text-xs text-[#9CA3AF]">Trade calls and puts with live premiums</p>
            </div>
            <button
              type="button"
              onClick={onOpenSmartMoney}
              className="rounded-lg border border-[#ffb900]/35 bg-[#ffb900]/10 px-3 py-2 text-xs font-semibold text-[#ffde7a]"
            >
              Smart Money
            </button>
          </div>
        </header>

        <section className="grid gap-4 md:grid-cols-3">
          <MetricCard icon={ChartCandlestick} label="Open Interest" value="$1.42B" />
          <MetricCard icon={CircleDollarSign} label="24h Premium Volume" value="$386.4M" />
          <MetricCard icon={ShieldCheck} label="Put/Call Ratio" value="0.74" />
        </section>

        <section className="mt-4 rounded-2xl border border-white/10 bg-[#111827] p-4">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-sm font-semibold">Top Contracts</h2>
            <span className="text-xs text-[#9CA3AF]">Updated every few seconds</span>
          </div>

          <div className="space-y-2">
            {optionsPairs.map((item) => (
              <article
                key={`${item.symbol}-${item.expiry}-${item.strike}-${item.type}`}
                className="rounded-xl border border-white/10 bg-[#0D1424] p-3"
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p className="text-sm font-semibold">{item.symbol} {item.type}</p>
                    <p className="text-xs text-[#9CA3AF]">{item.expiry} - Strike {item.strike}</p>
                  </div>
                  <div className="rounded-lg border border-white/15 bg-[#111827] px-2.5 py-1 text-xs text-[#d7dde8]">
                    IV {item.iv}
                  </div>
                </div>
                <div className="mt-3 grid grid-cols-2 gap-2 text-xs">
                  <div className="rounded-lg border border-[#18c06c]/35 bg-[#18c06c]/10 px-2 py-1.5">
                    <p className="text-[#9CA3AF]">Bid</p>
                    <p className="font-semibold text-[#79f2b4]">{item.bid}</p>
                  </div>
                  <div className="rounded-lg border border-[#ff5b6b]/35 bg-[#ff5b6b]/10 px-2 py-1.5">
                    <p className="text-[#9CA3AF]">Ask</p>
                    <p className="font-semibold text-[#ff9aa8]">{item.ask}</p>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </section>
      </div>
    </main>
  );
}

function MetricCard({ icon: Icon, label, value }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-[#111827] p-4">
      <div className="mb-2 inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-[#0D1424] text-[#ffde7a]">
        <Icon className="h-4 w-4" />
      </div>
      <p className="text-xs text-[#9CA3AF]">{label}</p>
      <p className="mt-1 text-xl font-semibold">{value}</p>
    </div>
  );
}

export default Options;
