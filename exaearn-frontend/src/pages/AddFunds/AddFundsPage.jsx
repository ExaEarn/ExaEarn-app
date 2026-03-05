import { useMemo, useState } from "react";
import {
  ArrowDownToLine,
  ArrowLeft,
  ArrowRight,
  BarChart3,
  Eye,
  EyeOff,
  Landmark,
  Lock,
  QrCode,
  ReceiptText,
  Send,
  Users,
} from "lucide-react";

function AddFundsPage({ onBack, onOpenSend, onOpenSwap, onOpenWithdraw }) {
  const [showBalance, setShowBalance] = useState(true);
  const [currency, setCurrency] = useState("USD");

  const pnl = useMemo(() => ({ value: "+4.2%", positive: true }), []);

  const methods = [
    {
      id: "deposit-crypto",
      title: "Deposit Crypto",
      description: "Deposit crypto from external wallets or exchanges into ExaEarn.",
      icon: ArrowDownToLine,
      featured: false,
    },
    {
      id: "exa-pay",
      title: "Receive via ExaEarn Pay",
      description: "Instantly receive crypto from other ExaEarn users.",
      icon: QrCode,
      featured: false,
    },
    {
      id: "deposit-fiat",
      title: "Deposit Fiat (USD / Local Currency)",
      description: "Fund your account via bank transfer, card payment, or supported gateways.",
      icon: Landmark,
      featured: false,
    },
    {
      id: "p2p",
      title: "P2P Marketplace",
      description: "Buy crypto directly from verified users using local payment methods.",
      icon: Users,
      featured: true,
    },
  ];

  return (
    <main className="relative min-h-screen overflow-hidden bg-gradient-to-br from-[#0b0f1a] via-[#120a20] to-[#1e1240] px-4 py-8 text-violet-50 sm:px-6 sm:py-10">
      <div className="pointer-events-none absolute -left-24 top-12 h-64 w-64 rounded-full bg-cosmic-500/20 blur-3xl" />
      <div className="pointer-events-none absolute right-0 top-1/3 h-72 w-72 rounded-full bg-auric-400/10 blur-3xl" />

      <section className="mx-auto w-full max-w-7xl rounded-3xl border border-violet-300/15 bg-cosmic-900/65 p-4 shadow-[0_20px_70px_rgba(0,0,0,0.55)] backdrop-blur-xl sm:p-6 lg:p-8">
        <header className="relative overflow-hidden rounded-2xl border border-violet-300/20 bg-gradient-to-r from-cosmic-900/95 via-cosmic-800/90 to-cosmic-700/90 p-4 sm:p-6">
          <div className="pointer-events-none absolute -right-8 -top-8 h-24 w-24 rounded-full bg-auric-400/15 blur-2xl" />
          <div className="pointer-events-none absolute -left-8 -bottom-8 h-20 w-20 rounded-full bg-cosmic-500/20 blur-2xl" />
          <div className="relative flex items-start justify-between gap-3">
            <div>
              <button
                type="button"
                onClick={onBack}
                className="mb-3 inline-flex items-center gap-2 rounded-xl border border-violet-300/30 bg-cosmic-800/70 px-3 py-2 text-xs font-semibold text-violet-100 transition hover:border-auric-300/65 hover:text-auric-300 active:scale-95"
              >
                <ArrowLeft className="h-4 w-4" />
                Back
              </button>
              <h1 className="font-['Sora'] text-2xl font-semibold tracking-tight text-violet-50 sm:text-3xl">Add Funds</h1>
              <p className="mt-1 text-sm text-violet-100/70">Fund your ExaEarn wallet securely</p>
            </div>
          </div>
        </header>

        <section className="mt-5 rounded-2xl border border-violet-300/15 bg-gradient-to-br from-[#23123f] via-[#1a1030] to-[#281a1d] p-5 shadow-[0_14px_35px_rgba(0,0,0,0.35)] sm:p-6">
          <div className="flex items-start justify-between gap-2 sm:gap-3">
            <div>
              <p className="text-xs text-violet-100/65">Estimated Total Value</p>
              <div className="mt-1 flex items-center gap-2">
                <p className="font-['Sora'] text-3xl font-semibold text-violet-50 sm:text-4xl">
                  {showBalance ? "$125,480.20" : "******"}
                </p>
                <select
                  value={currency}
                  onChange={(event) => setCurrency(event.target.value)}
                  className="rounded-lg border border-violet-300/25 bg-cosmic-900/60 px-2 py-1 text-xs font-semibold text-violet-100 outline-none transition focus:border-auric-300/70"
                >
                  <option value="USD">USD</option>
                  <option value="NGN">NGN</option>
                  <option value="EUR">EUR</option>
                </select>
              </div>
              <p className={`mt-2 text-xs font-semibold ${pnl.positive ? "text-emerald-300" : "text-rose-300"}`}>
                {pnl.value} Today&apos;s PNL
              </p>
            </div>
            <div className="flex shrink-0 items-center gap-1.5 sm:gap-2">
              <button
                type="button"
                onClick={() => setShowBalance((prev) => !prev)}
                className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-violet-300/25 bg-violet-500/10 text-violet-100 transition hover:border-auric-300/65 hover:text-auric-300 sm:h-10 sm:w-10 sm:rounded-xl"
                aria-label="Toggle balance visibility"
              >
                {showBalance ? <Eye className="h-4 w-4" /> : <EyeOff className="h-4 w-4" />}
              </button>
              <button
                type="button"
                className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-violet-300/25 bg-violet-500/10 text-violet-100 transition hover:border-auric-300/65 hover:text-auric-300 sm:h-10 sm:w-10 sm:rounded-xl"
                aria-label="Portfolio analytics"
              >
                <BarChart3 className="h-4 w-4" />
              </button>
            </div>
          </div>

          <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
            <button
              type="button"
              className="rounded-xl border border-auric-300/70 bg-gradient-to-r from-cosmic-500 via-cosmic-400 to-auric-500 px-3 py-2.5 text-sm font-semibold text-cosmic-900 shadow-button-glow transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]"
            >
              Add Funds
            </button>
            <button
              type="button"
              onClick={onOpenSend}
              className="rounded-xl border border-violet-300/25 bg-violet-500/10 px-3 py-2.5 text-sm font-semibold text-violet-100 transition-all duration-300 hover:border-auric-300/65 hover:text-auric-300 active:scale-[0.98]"
            >
              <span className="inline-flex items-center gap-1">
                <Send className="h-4 w-4" />
                Send
              </span>
            </button>
            <button
              type="button"
              onClick={onOpenSwap}
              className="rounded-xl border border-violet-300/25 bg-violet-500/10 px-3 py-2.5 text-sm font-semibold text-violet-100 transition-all duration-300 hover:border-auric-300/65 hover:text-auric-300 active:scale-[0.98]"
            >
              <span className="inline-flex items-center gap-1">
                <ArrowRight className="h-4 w-4" />
                Swap
              </span>
            </button>
            <button
              type="button"
              onClick={onOpenWithdraw}
              className="rounded-xl border border-violet-300/25 bg-violet-500/10 px-3 py-2.5 text-sm font-semibold text-violet-100 transition-all duration-300 hover:border-auric-300/65 hover:text-auric-300 active:scale-[0.98]"
            >
              <span className="inline-flex items-center gap-1">
                <ReceiptText className="h-4 w-4" />
                Withdraw
              </span>
            </button>
          </div>
        </section>

        <section className="mt-5 rounded-2xl border border-violet-300/15 bg-cosmic-900/45 p-4 sm:p-5">
          <h2 className="font-['Sora'] text-lg font-semibold text-violet-50">Select Funding Method</h2>
          <div className="mt-3 space-y-3">
            {methods.map((method) => {
              const Icon = method.icon;
              return (
                <button
                  type="button"
                  key={method.id}
                  className={`group flex w-full items-center justify-between rounded-2xl border p-3 text-left transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_30px_rgba(127,70,212,0.25)] active:scale-[0.99] sm:p-4 ${
                    method.featured
                      ? "border-auric-300/55 bg-gradient-to-r from-cosmic-800/85 to-cosmic-700/70 shadow-[0_0_26px_rgba(234,185,95,0.18)]"
                      : "border-violet-300/20 bg-cosmic-800/65 hover:border-cosmic-400/55"
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border ${
                      method.featured ? "border-auric-300/60 bg-auric-300/10 text-auric-300" : "border-violet-300/25 bg-violet-500/10 text-violet-100"
                    }`}>
                      <Icon className="h-5 w-5" />
                    </span>
                    <span className="min-w-0">
                      <span className="block text-sm font-semibold text-violet-50">{method.title}</span>
                      <span className="mt-0.5 block text-xs text-violet-100/65">{method.description}</span>
                    </span>
                  </div>
                  <ArrowRight className={`h-5 w-5 shrink-0 transition-all duration-300 group-hover:translate-x-1 ${
                    method.featured ? "text-auric-300" : "text-violet-200/60 group-hover:text-auric-300"
                  }`} />
                </button>
              );
            })}
          </div>
        </section>

        <p className="mt-5 flex items-center justify-center gap-2 text-center text-xs text-violet-100/65">
          <Lock className="h-3.5 w-3.5 text-auric-300" />
          ExaEarn protects your assets using decentralized security and verified transactions.
        </p>
      </section>
    </main>
  );
}

export default AddFundsPage;
