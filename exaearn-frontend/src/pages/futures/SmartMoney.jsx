import { ArrowLeft, BarChart3, Flame, TrendingDown, TrendingUp, Wallet } from "lucide-react";

const walletFlows = [
  { wallet: "0x8f...a21d", token: "BTC", action: "Accumulating", amount: "+154.2 BTC", usd: "$10.4M", bullish: true },
  { wallet: "0x22...f0c9", token: "ETH", action: "Distribution", amount: "-3,820 ETH", usd: "$7.5M", bullish: false },
  { wallet: "0x9c...44be", token: "XRP", action: "Accumulating", amount: "+8.1M XRP", usd: "$11.0M", bullish: true },
  { wallet: "0xaa...19f3", token: "SOL", action: "Distribution", amount: "-42,700 SOL", usd: "$6.6M", bullish: false },
];

function SmartMoney({ onBack, onOpenOptions }) {
  const bullishCount = walletFlows.filter((item) => item.bullish).length;
  const bearishCount = walletFlows.length - bullishCount;

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
              <h1 className="text-lg font-semibold">Smart Money</h1>
              <p className="text-xs text-[#9CA3AF]">Track whale flow and sentiment shifts</p>
            </div>
            <button
              type="button"
              onClick={onOpenOptions}
              className="rounded-lg border border-[#ffb900]/35 bg-[#ffb900]/10 px-3 py-2 text-xs font-semibold text-[#ffde7a]"
            >
              Options
            </button>
          </div>
        </header>

        <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard icon={Wallet} label="Watched Wallets" value="2,480" />
          <StatCard icon={BarChart3} label="24h Whale Volume" value="$1.93B" />
          <StatCard icon={TrendingUp} label="Bullish Wallets" value={String(bullishCount)} positive />
          <StatCard icon={TrendingDown} label="Bearish Wallets" value={String(bearishCount)} negative />
        </section>

        <section className="mt-4 rounded-2xl border border-white/10 bg-[#111827] p-4">
          <div className="mb-3 flex items-center gap-2">
            <Flame className="h-4 w-4 text-[#ffb900]" />
            <h2 className="text-sm font-semibold">Latest Whale Activity</h2>
          </div>

          <div className="space-y-2">
            {walletFlows.map((item) => (
              <article key={`${item.wallet}-${item.token}`} className="rounded-xl border border-white/10 bg-[#0D1424] p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p className="text-sm font-semibold">{item.wallet} - {item.token}</p>
                    <p className={`text-xs ${item.bullish ? "text-[#79f2b4]" : "text-[#ff9aa8]"}`}>{item.action}</p>
                  </div>
                  <div className="text-right">
                    <p className={`text-sm font-semibold ${item.bullish ? "text-[#79f2b4]" : "text-[#ff9aa8]"}`}>{item.amount}</p>
                    <p className="text-xs text-[#9CA3AF]">{item.usd}</p>
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

function StatCard({ icon: Icon, label, value, positive = false, negative = false }) {
  const textColor = positive ? "text-[#79f2b4]" : negative ? "text-[#ff9aa8]" : "text-white";
  return (
    <div className="rounded-2xl border border-white/10 bg-[#111827] p-4">
      <div className="mb-2 inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-[#0D1424] text-[#ffde7a]">
        <Icon className="h-4 w-4" />
      </div>
      <p className="text-xs text-[#9CA3AF]">{label}</p>
      <p className={`mt-1 text-xl font-semibold ${textColor}`}>{value}</p>
    </div>
  );
}

export default SmartMoney;
