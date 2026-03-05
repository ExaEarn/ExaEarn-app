import { useMemo, useState } from "react";
import { ArrowLeft, BadgeCheck, ChevronDown, CircleAlert, ShieldCheck } from "lucide-react";

const assets = [
  { symbol: "XRP", name: "Ripple", tone: "from-sky-400 to-blue-500" },
  { symbol: "USDT", name: "Tether", tone: "from-emerald-400 to-green-500" },
  { symbol: "BTC", name: "Bitcoin", tone: "from-amber-300 to-orange-500" },
  { symbol: "ETH", name: "Ethereum", tone: "from-indigo-300 to-violet-500" },
];

const paymentOptions = ["All Methods", "Bank Transfer", "Opay", "Airtel Money", "PalmPay"];

const p2pListings = [
  {
    id: "l1",
    merchant: "CryptoVendor01",
    successRate: 98,
    online: true,
    asset: "USDT",
    price: "₦1,642.50",
    available: "15,230 USDT",
    limits: "₦20,000 - ₦3,500,000",
    paymentMethods: ["Bank Transfer", "Opay"],
  },
  {
    id: "l2",
    merchant: "PrimeNodeNG",
    successRate: 96,
    online: true,
    asset: "BTC",
    price: "₦168,400,000",
    available: "0.92 BTC",
    limits: "₦50,000 - ₦8,000,000",
    paymentMethods: ["Bank Transfer", "Airtel Money"],
  },
  {
    id: "l3",
    merchant: "SwiftDeskP2P",
    successRate: 99,
    online: false,
    asset: "XRP",
    price: "₦1,360.70",
    available: "89,000 XRP",
    limits: "₦10,000 - ₦2,400,000",
    paymentMethods: ["Opay", "PalmPay", "Bank Transfer"],
  },
  {
    id: "l4",
    merchant: "AtlasLiquidity",
    successRate: 97,
    online: true,
    asset: "ETH",
    price: "₦7,940,000",
    available: "13.2 ETH",
    limits: "₦75,000 - ₦12,000,000",
    paymentMethods: ["Bank Transfer"],
  },
];

function TokenLogo({ symbol, tone }) {
  return (
    <span className={`inline-flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br ${tone} text-[11px] font-bold text-white shadow-[0_0_18px_rgba(168,85,247,0.35)]`}>
      {symbol.slice(0, 2)}
    </span>
  );
}

function P2PMarketplace({ onBack }) {
  const [tradeMode, setTradeMode] = useState("p2p");
  const [tradeSide, setTradeSide] = useState("buy");
  const [selectedAsset, setSelectedAsset] = useState("USDT");
  const [fiatCurrency, setFiatCurrency] = useState("NGN");
  const [paymentMethod, setPaymentMethod] = useState("All Methods");
  const [priceRange, setPriceRange] = useState("");
  const [amount, setAmount] = useState("");
  const [expressAmount, setExpressAmount] = useState("");
  const [expressPayment, setExpressPayment] = useState("Bank Transfer");

  const filteredListings = useMemo(() => {
    return p2pListings.filter((listing) => {
      const assetMatch = listing.asset === selectedAsset;
      const paymentMatch = paymentMethod === "All Methods" || listing.paymentMethods.includes(paymentMethod);
      const rangeMatch = !priceRange || listing.price.toLowerCase().includes(priceRange.toLowerCase());
      return assetMatch && paymentMatch && rangeMatch;
    });
  }, [selectedAsset, paymentMethod, priceRange]);

  const actionLabel = tradeSide === "buy" ? "Buy Now" : "Sell Now";

  return (
    <main className="relative min-h-screen overflow-hidden bg-gradient-to-br from-[#050509] via-[#13071f] to-[#1a0d2f] px-4 py-8 text-violet-50 sm:px-6 sm:py-10">
      <div className="pointer-events-none absolute -left-24 top-24 h-56 w-56 rounded-full bg-purple-500/20 blur-3xl" />
      <div className="pointer-events-none absolute right-0 top-1/3 h-64 w-64 rounded-full bg-amber-400/10 blur-3xl" />

      <section className="mx-auto w-full max-w-7xl rounded-3xl border border-violet-300/15 bg-[#100a1e]/65 p-4 shadow-[0_20px_80px_rgba(0,0,0,0.55)] backdrop-blur-xl [animation:exaFade_0.5s_ease-out] sm:p-6 lg:p-8">
        <header className="rounded-2xl border border-violet-300/15 bg-[#120b22]/80 p-4 shadow-[0_12px_35px_rgba(0,0,0,0.45)] sm:p-6">
          <div className="mb-5 flex flex-wrap items-start justify-between gap-4">
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
              <h1 className="font-['Sora'] text-3xl font-semibold tracking-tight text-white sm:text-4xl">P2P Marketplace</h1>
              <p className="mt-1 text-sm text-violet-100/75">Buy & Sell Crypto Securely</p>
            </div>
            <div className="inline-flex items-center gap-2 rounded-full border border-amber-300/40 bg-amber-300/10 px-3 py-1.5 text-xs font-semibold text-amber-200">
              <ShieldCheck className="h-4 w-4" />
              Escrow Protected
            </div>
          </div>

          <div className="rounded-2xl border border-violet-300/20 bg-[#1b112d]/70 p-1.5">
            <div className="relative grid grid-cols-2 gap-1">
              <span
                className={`absolute bottom-1.5 top-1.5 w-[calc(50%-0.25rem)] rounded-xl bg-gradient-to-r from-amber-300 via-amber-400 to-yellow-500 shadow-[0_0_20px_rgba(250,204,21,0.45)] transition-transform duration-300 ${tradeMode === "p2p" ? "translate-x-[calc(100%+0.25rem)]" : "translate-x-0"}`}
              />
              <button
                type="button"
                onClick={() => setTradeMode("express")}
                className={`relative z-10 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${tradeMode === "express" ? "text-black" : "text-violet-100/80 hover:text-white"}`}
              >
                Express
              </button>
              <button
                type="button"
                onClick={() => setTradeMode("p2p")}
                className={`relative z-10 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${tradeMode === "p2p" ? "text-black" : "text-violet-100/80 hover:text-white"}`}
              >
                P2P
              </button>
            </div>
          </div>
        </header>

        {tradeMode === "p2p" ? (
          <section className="mt-5 space-y-5">
            <div className="rounded-2xl border border-violet-300/15 bg-[#120c22]/70 p-4">
              <div className="inline-flex rounded-full border border-violet-300/20 bg-[#0f0a1b] p-1">
                <button
                  type="button"
                  onClick={() => setTradeSide("buy")}
                  className={`rounded-full px-5 py-2 text-sm font-semibold transition ${tradeSide === "buy" ? "bg-gradient-to-r from-amber-300 to-yellow-500 text-black shadow-[0_0_14px_rgba(245,158,11,0.45)]" : "text-violet-100/80 hover:text-white"}`}
                >
                  Buy
                </button>
                <button
                  type="button"
                  onClick={() => setTradeSide("sell")}
                  className={`rounded-full px-5 py-2 text-sm font-semibold transition ${tradeSide === "sell" ? "bg-gradient-to-r from-purple-500 to-violet-600 text-white shadow-[0_0_14px_rgba(139,92,246,0.45)]" : "text-violet-100/80 hover:text-white"}`}
                >
                  Sell
                </button>
              </div>

              <div className="mt-4 flex gap-3 overflow-x-auto pb-1">
                {assets.map((asset) => (
                  <button
                    key={asset.symbol}
                    type="button"
                    onClick={() => setSelectedAsset(asset.symbol)}
                    className={`inline-flex min-w-fit items-center gap-2 rounded-xl border px-3 py-2 transition ${selectedAsset === asset.symbol ? "border-amber-300/70 bg-amber-300/12 shadow-[0_0_22px_rgba(245,158,11,0.18)]" : "border-violet-300/20 bg-violet-950/35 hover:border-violet-200/45"}`}
                  >
                    <TokenLogo symbol={asset.symbol} tone={asset.tone} />
                    <span className="text-sm font-semibold text-violet-50">{asset.symbol}</span>
                  </button>
                ))}
              </div>
            </div>

            <div className="rounded-2xl border border-violet-300/15 bg-[#120c22]/70 p-4">
              <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label className="space-y-1">
                  <span className="text-xs font-medium text-violet-100/65">Fiat Currency</span>
                  <input
                    value={fiatCurrency}
                    onChange={(event) => setFiatCurrency(event.target.value)}
                    className="w-full rounded-xl border border-violet-300/20 bg-[#100a1c] px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/70"
                  />
                </label>
                <label className="space-y-1">
                  <span className="text-xs font-medium text-violet-100/65">Payment Method</span>
                  <div className="relative">
                    <select
                      value={paymentMethod}
                      onChange={(event) => setPaymentMethod(event.target.value)}
                      className="w-full appearance-none rounded-xl border border-violet-300/20 bg-[#100a1c] px-3 py-2.5 pr-10 text-sm text-white outline-none transition focus:border-amber-300/70"
                    >
                      {paymentOptions.map((method) => (
                        <option key={method} value={method}>
                          {method}
                        </option>
                      ))}
                    </select>
                    <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-violet-200/80" />
                  </div>
                </label>
                <label className="space-y-1">
                  <span className="text-xs font-medium text-violet-100/65">Price Range</span>
                  <input
                    value={priceRange}
                    onChange={(event) => setPriceRange(event.target.value)}
                    placeholder="e.g. 1,600"
                    className="w-full rounded-xl border border-violet-300/20 bg-[#100a1c] px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/70"
                  />
                </label>
                <label className="space-y-1">
                  <span className="text-xs font-medium text-violet-100/65">Amount</span>
                  <input
                    value={amount}
                    onChange={(event) => setAmount(event.target.value)}
                    placeholder="Enter amount"
                    className="w-full rounded-xl border border-violet-300/20 bg-[#100a1c] px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/70"
                  />
                </label>
              </div>
            </div>

            <section className="space-y-3">
              {filteredListings.length ? (
                filteredListings.map((listing) => (
                  <article
                    key={listing.id}
                    className="rounded-2xl border border-violet-300/15 bg-[#120c20]/85 p-4 shadow-[0_10px_28px_rgba(0,0,0,0.35)] transition duration-300 hover:-translate-y-1 hover:border-amber-300/40 sm:p-5"
                  >
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                      <div className="space-y-3">
                        <div className="flex items-center gap-2">
                          <span className="text-sm font-semibold text-white">{listing.merchant}</span>
                          <span className="inline-flex items-center gap-1 rounded-full border border-emerald-300/35 bg-emerald-400/10 px-2 py-0.5 text-[11px] font-semibold text-emerald-200">
                            <BadgeCheck className="h-3.5 w-3.5" />
                            Verified
                          </span>
                          <span className="text-xs text-violet-100/60">{listing.successRate}% Success</span>
                          {listing.online ? <span className="h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-[0_0_12px_rgba(74,222,128,0.8)]" /> : null}
                        </div>

                        <div className="grid gap-2 text-sm text-violet-100/85 sm:grid-cols-3">
                          <p>
                            <span className="block text-xs text-violet-100/55">Price</span>
                            <span className="font-semibold text-amber-200">{listing.price}</span>
                          </p>
                          <p>
                            <span className="block text-xs text-violet-100/55">Available</span>
                            <span className="font-medium text-violet-50">{listing.available}</span>
                          </p>
                          <p>
                            <span className="block text-xs text-violet-100/55">Limits</span>
                            <span className="font-medium text-violet-50">{listing.limits}</span>
                          </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                          {listing.paymentMethods.map((method) => (
                            <span key={method} className="rounded-full border border-violet-300/25 bg-violet-400/10 px-2.5 py-1 text-xs text-violet-100/85">
                              {method}
                            </span>
                          ))}
                        </div>
                      </div>

                      <button
                        type="button"
                        className={`h-11 rounded-xl px-6 text-sm font-semibold text-white transition hover:scale-[1.02] active:scale-[0.99] ${tradeSide === "buy" ? "bg-gradient-to-r from-amber-400 to-yellow-500 text-black shadow-[0_8px_20px_rgba(245,158,11,0.3)]" : "bg-gradient-to-r from-purple-500 to-violet-600 shadow-[0_8px_20px_rgba(139,92,246,0.3)]"}`}
                      >
                        {actionLabel}
                      </button>
                    </div>
                  </article>
                ))
              ) : (
                <div className="rounded-2xl border border-violet-300/15 bg-[#120c20]/85 p-10 text-center">
                  <CircleAlert className="mx-auto h-10 w-10 text-violet-200/70" />
                  <p className="mt-3 text-base font-semibold text-violet-50">No listings available</p>
                  <p className="mt-1 text-sm text-violet-100/65">Try adjusting filters or check back later.</p>
                </div>
              )}
            </section>

            <div className="rounded-xl border border-emerald-300/20 bg-emerald-500/5 px-4 py-3 text-sm text-emerald-100/85">
              <p className="flex items-center gap-2">
                <ShieldCheck className="h-4 w-4 text-emerald-300" />
                Trade only with verified merchants. ExaEarn escrow protects your transactions.
              </p>
            </div>
          </section>
        ) : (
          <section className="mt-5 rounded-2xl border border-violet-300/15 bg-[#120c22]/80 p-4 shadow-[0_10px_30px_rgba(0,0,0,0.35)] sm:p-6">
            <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">I Want to Buy</h2>
            <p className="mt-1 text-sm text-violet-100/70">Instant settlement with curated liquidity and secure payment rails.</p>

            <div className="mt-4 grid gap-3 lg:grid-cols-2">
              <label className="space-y-1">
                <span className="text-xs font-medium text-violet-100/65">Enter Amount (NGN)</span>
                <input
                  value={expressAmount}
                  onChange={(event) => setExpressAmount(event.target.value)}
                  placeholder="e.g. 100000"
                  className="w-full rounded-xl border border-violet-300/20 bg-[#100a1c] px-4 py-3 text-base text-white outline-none transition focus:border-amber-300/70"
                />
              </label>
              <label className="space-y-1">
                <span className="text-xs font-medium text-violet-100/65">Payment Method</span>
                <div className="relative">
                  <select
                    value={expressPayment}
                    onChange={(event) => setExpressPayment(event.target.value)}
                    className="w-full appearance-none rounded-xl border border-violet-300/20 bg-[#100a1c] px-4 py-3 pr-10 text-base text-white outline-none transition focus:border-amber-300/70"
                  >
                    <option>Bank Transfer</option>
                    <option>Opay</option>
                    <option>Airtel Money</option>
                  </select>
                  <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-violet-200/80" />
                </div>
              </label>
            </div>

            <div className="mt-4 flex flex-wrap gap-2">
              {["₦50K", "₦100K", "₦500K", "₦1M"].map((preset) => (
                <button
                  key={preset}
                  type="button"
                  onClick={() => setExpressAmount(preset)}
                  className="rounded-full border border-violet-300/25 bg-violet-500/10 px-3 py-1.5 text-sm font-medium text-violet-100/90 transition hover:border-amber-300/50 hover:text-amber-200"
                >
                  {preset}
                </button>
              ))}
            </div>

            <button
              type="button"
              className="mt-6 h-12 w-full rounded-xl bg-gradient-to-r from-amber-300 via-amber-400 to-yellow-500 text-base font-bold text-black shadow-[0_0_26px_rgba(245,158,11,0.35)] transition hover:brightness-105 active:scale-[0.99]"
            >
              Continue
            </button>
          </section>
        )}
      </section>

      <style>{`
        @keyframes exaFade {
          from {
            opacity: 0;
            transform: translateY(8px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
      `}</style>
    </main>
  );
}

export default P2PMarketplace;
