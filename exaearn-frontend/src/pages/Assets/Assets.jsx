import { useState } from "react";
import {
  ArrowLeft,
  ArrowUpFromLine,
  Eye,
  EyeOff,
  Plus,
  Repeat2,
  SendHorizontal,
  Settings2,
  Wallet,
} from "lucide-react";

const holdings = [
  {
    id: "asset-1",
    symbol: "XRP",
    name: "Ripple",
    balance: "2,840.55 XRP",
    fiat: "₦3,905,880",
    change: "+2.8%",
    tone: "from-sky-400 to-blue-500",
  },
  {
    id: "asset-2",
    symbol: "USDT",
    name: "Tether",
    balance: "1,920.00 USDT",
    fiat: "₦3,153,600",
    change: "+0.1%",
    tone: "from-emerald-400 to-green-500",
  },
  {
    id: "asset-3",
    symbol: "EXA",
    name: "ExaToken",
    balance: "45,200 EXA",
    fiat: "₦2,904,200",
    change: "+5.9%",
    tone: "from-amber-300 to-yellow-500",
  },
  {
    id: "asset-4",
    symbol: "ETH",
    name: "Ethereum",
    balance: "0.820 ETH",
    fiat: "₦6,420,000",
    change: "-1.2%",
    tone: "from-indigo-300 to-violet-500",
  },
];

function ActionButton({ icon: Icon, label, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex min-h-20 flex-col items-center justify-center rounded-xl border border-violet-300/20 bg-violet-500/10 px-3 py-3 text-violet-100 transition duration-200 hover:-translate-y-0.5 hover:border-amber-300/50 hover:text-amber-200 active:scale-[0.98]"
    >
      <Icon className="h-5 w-5" />
      <span className="mt-2 text-xs font-semibold">{label}</span>
    </button>
  );
}

function Assets({ onBack, onOpenSend, onOpenAddFunds, onOpenSwap, onOpenWithdraw }) {
  const [showBalance, setShowBalance] = useState(true);
  const [selectedAsset, setSelectedAsset] = useState(null);

  return (
    <main className="relative min-h-screen overflow-hidden bg-gradient-to-br from-[#050509] via-[#140822] to-[#1c0d32] px-4 py-8 text-violet-50 sm:px-6 sm:py-10">
      <div className="pointer-events-none absolute -left-24 top-20 h-56 w-56 rounded-full bg-purple-500/25 blur-3xl" />
      <div className="pointer-events-none absolute right-0 top-1/3 h-64 w-64 rounded-full bg-amber-300/10 blur-3xl" />

      <section className="mx-auto w-full max-w-7xl rounded-3xl border border-violet-300/15 bg-[#110a20]/70 p-4 shadow-[0_20px_80px_rgba(0,0,0,0.55)] backdrop-blur-xl sm:p-6 lg:p-8">
        <header className="rounded-2xl border border-violet-300/15 bg-[#140c24]/85 p-4 sm:p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              {onBack ? (
                <button
                  type="button"
                  onClick={onBack}
                  className="mb-3 inline-flex items-center gap-2 rounded-xl border border-violet-300/25 bg-violet-950/35 px-3 py-2 text-xs font-semibold text-violet-100 transition hover:border-amber-300/60 hover:text-amber-200"
                >
                  <ArrowLeft className="h-4 w-4" />
                  Back
                </button>
              ) : null}
              <h1 className="font-['Sora'] text-3xl font-semibold tracking-tight text-white sm:text-4xl">Assets</h1>
              <p className="mt-1 text-sm text-violet-100/70">Manage your portfolio</p>
            </div>
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setShowBalance((prev) => !prev)}
                className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-violet-300/25 bg-violet-500/10 text-violet-100 transition hover:border-amber-300/60 hover:text-amber-200"
              >
                {showBalance ? <Eye className="h-4 w-4" /> : <EyeOff className="h-4 w-4" />}
              </button>
              <button className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-violet-300/25 bg-violet-500/10 text-violet-100 transition hover:border-amber-300/60 hover:text-amber-200">
                <Settings2 className="h-4 w-4" />
              </button>
            </div>
          </div>
        </header>

        <section className="mt-5 rounded-2xl border border-violet-300/15 bg-gradient-to-br from-[#24123e] via-[#1b112f] to-[#2a1c1a] p-5 shadow-[0_14px_35px_rgba(0,0,0,0.35)] sm:p-6">
          <p className="text-xs text-violet-100/65">Total Balance</p>
          <p className="mt-1 text-3xl font-semibold text-amber-200 drop-shadow-[0_0_14px_rgba(251,191,36,0.35)] sm:text-4xl">
            {showBalance ? "6,125.70 XRP" : "••••••••"}
          </p>
          <p className="mt-2 text-sm text-violet-100/70">{showBalance ? "≈ ₦16,383,680" : "≈ ₦••••••••"}</p>
        </section>

        <section className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
          <ActionButton icon={Plus} label="Add Funds" onClick={onOpenAddFunds} />
          <ActionButton icon={SendHorizontal} label="Send" onClick={onOpenSend} />
          <ActionButton icon={Repeat2} label="Swap" onClick={onOpenSwap} />
          <ActionButton icon={ArrowUpFromLine} label="Withdraw" onClick={onOpenWithdraw} />
        </section>

        <section className="mt-5 rounded-2xl border border-violet-300/15 bg-[#140c24]/85 p-4 sm:p-5">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="font-['Sora'] text-lg font-semibold text-violet-50">Your Assets</h2>
            <span className="text-xs text-violet-100/65">{holdings.length} tokens</span>
          </div>

          {holdings.length ? (
            <div className="space-y-3">
              {holdings.map((asset) => (
                <article key={asset.id} className="rounded-xl border border-violet-300/15 bg-[#110a1e]/80 p-3 shadow-[0_8px_20px_rgba(0,0,0,0.3)] transition hover:border-amber-300/40">
                  <button
                    type="button"
                    onClick={() => setSelectedAsset((prev) => (prev === asset.id ? null : asset.id))}
                    className="flex w-full items-center justify-between gap-3 text-left"
                  >
                    <div className="flex min-w-0 items-center gap-3">
                      <span className={`inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br ${asset.tone} text-xs font-bold text-white shadow-[0_0_14px_rgba(168,85,247,0.25)]`}>
                        {asset.symbol.slice(0, 2)}
                      </span>
                      <div className="min-w-0">
                        <p className="text-sm font-semibold text-white">{asset.symbol}</p>
                        <p className="text-xs text-violet-100/65">{asset.name}</p>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-semibold text-violet-50">{asset.balance}</p>
                      <p className="text-xs text-violet-100/65">{asset.fiat}</p>
                      <p className={`mt-0.5 text-xs font-semibold ${asset.change.startsWith("+") ? "text-emerald-300" : "text-rose-300"}`}>{asset.change}</p>
                    </div>
                  </button>

                  {selectedAsset === asset.id ? (
                    <div className="mt-3 grid grid-cols-2 gap-2 border-t border-violet-300/15 pt-3 sm:grid-cols-4">
                      <button className="rounded-lg border border-violet-300/20 bg-violet-500/12 px-3 py-2 text-xs font-semibold text-violet-100 transition hover:border-amber-300/50 hover:text-amber-200">
                        Send
                      </button>
                      <button className="rounded-lg border border-violet-300/20 bg-violet-500/12 px-3 py-2 text-xs font-semibold text-violet-100 transition hover:border-amber-300/50 hover:text-amber-200">
                        Receive
                      </button>
                      <button
                        type="button"
                        onClick={onOpenSwap}
                        className="rounded-lg border border-violet-300/20 bg-violet-500/12 px-3 py-2 text-xs font-semibold text-violet-100 transition hover:border-amber-300/50 hover:text-amber-200"
                      >
                        Swap
                      </button>
                      <button
                        type="button"
                        onClick={onOpenWithdraw}
                        className="rounded-lg border border-violet-300/20 bg-violet-500/12 px-3 py-2 text-xs font-semibold text-violet-100 transition hover:border-amber-300/50 hover:text-amber-200"
                      >
                        Withdraw
                      </button>
                    </div>
                  ) : null}
                </article>
              ))}
            </div>
          ) : (
            <div className="rounded-xl border border-violet-300/15 bg-[#110a1e]/80 p-10 text-center">
              <Wallet className="mx-auto h-9 w-9 text-violet-200/65" />
              <p className="mt-3 text-base font-semibold text-violet-50">No Assets Yet</p>
              <p className="mt-1 text-sm text-violet-100/65">Add funds to start your journey</p>
            </div>
          )}
        </section>

        <p className="mt-5 text-center text-xs text-violet-100/60">
          Your assets are securely managed within the ExaEarn ecosystem.
        </p>
      </section>
    </main>
  );
}

export default Assets;
