import { useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRightLeft,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  Settings2,
  ShieldCheck,
  Sparkles,
  TrendingUp,
  X,
} from "lucide-react";

const GOLD_GRADIENT = "from-[#f8e08e] via-[#d7b25f] to-[#b88a2a]";

const CRYPTO_ASSETS = [
  { symbol: "XRP", name: "Ripple", icon: "XR", balanceLabel: "Available balance", balance: "2,840.55 XRP" },
  { symbol: "ETH", name: "Ethereum", icon: "ET", balanceLabel: "Available balance", balance: "0.820 ETH" },
  { symbol: "BTC", name: "Bitcoin", icon: "BT", balanceLabel: "Available balance", balance: "0.146 BTC" },
  { symbol: "USDT", name: "Tether", icon: "US", balanceLabel: "Available balance", balance: "1,920.00 USDT" },
  { symbol: "ADA", name: "Cardano", icon: "AD", balanceLabel: "Available balance", balance: "5,128.40 ADA" },
];

const FIAT_ASSETS = [
  { symbol: "NGN", name: "Nigerian Naira", icon: "NG", balanceLabel: "Bank balance", balance: "₦2,450,000.00" },
  { symbol: "USD", name: "US Dollar", icon: "US", balanceLabel: "Bank balance", balance: "$3,400.00" },
  { symbol: "EUR", name: "Euro", icon: "EU", balanceLabel: "Bank balance", balance: "€1,910.00" },
  { symbol: "GBP", name: "British Pound", icon: "GB", balanceLabel: "Bank balance", balance: "£1,420.00" },
];

function AssetTypeTabs({ value, onChange }) {
  return (
    <div className="inline-flex rounded-lg border border-[#c9a451]/30 bg-black/30 p-1">
      {["crypto", "fiat"].map((type) => (
        <button
          key={type}
          type="button"
          onClick={() => onChange(type)}
          className={`rounded-md px-3 py-1.5 text-xs font-semibold tracking-wide transition ${
            value === type
              ? "bg-gradient-to-r from-[#f8e08e]/20 via-[#d7b25f]/20 to-[#b88a2a]/20 text-[#f5d780] ring-1 ring-[#d7b25f]/40"
              : "text-neutral-400 hover:text-[#e5c573]"
          }`}
        >
          {type === "crypto" ? "Crypto" : "Fiat"}
        </button>
      ))}
    </div>
  );
}

function AssetSelect({ label, type, setType, value, setValue }) {
  const options = type === "crypto" ? CRYPTO_ASSETS : FIAT_ASSETS;
  const selected = options.find((item) => item.symbol === value) || options[0];

  return (
    <div className="rounded-2xl border border-[#c9a451]/20 bg-[#121212]/90 p-4 sm:p-5">
      <div className="mb-4 flex items-center justify-between gap-3">
        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">{label}</p>
        <AssetTypeTabs
          value={type}
          onChange={(nextType) => {
            setType(nextType);
            const nextPool = nextType === "crypto" ? CRYPTO_ASSETS : FIAT_ASSETS;
            setValue(nextPool[0].symbol);
          }}
        />
      </div>

      <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
        <label className="group flex items-center justify-between rounded-xl border border-[#c9a451]/25 bg-black/25 px-3 py-2.5">
          <div className="flex items-center gap-3">
            <span className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-neutral-800 to-neutral-950 text-[10px] font-bold text-[#f5d780] ring-1 ring-[#d7b25f]/30">
              {selected.icon}
            </span>
            <div>
              <p className="text-sm font-semibold text-neutral-100">{selected.symbol}</p>
              <p className="text-xs text-neutral-400">{selected.name}</p>
            </div>
          </div>
          <select
            value={selected.symbol}
            onChange={(event) => setValue(event.target.value)}
            className="cursor-pointer appearance-none bg-transparent pl-6 pr-0 text-right text-xs font-semibold text-[#ebcc7d] outline-none"
          >
            {options.map((item) => (
              <option key={item.symbol} value={item.symbol} className="bg-[#111111] text-neutral-100">
                {item.symbol}
              </option>
            ))}
          </select>
          <ChevronDown className="pointer-events-none ml-1 h-4 w-4 text-neutral-500 transition group-focus-within:text-[#e5c573]" />
        </label>

        <button
          type="button"
          className="rounded-xl border border-[#c9a451]/40 bg-black/20 px-4 py-2 text-xs font-semibold text-[#f2d27e] transition hover:border-[#c9a451]/70 hover:bg-[#c9a451]/10"
        >
          Max
        </button>
      </div>

      <div className="mt-3 rounded-xl border border-neutral-800 bg-[#0b0b0b] p-3">
        <p className="text-xs text-neutral-500">{selected.balanceLabel}</p>
        <p className="mt-1 text-sm font-semibold text-neutral-100">{selected.balance}</p>
        {type === "fiat" ? (
          <p className="mt-2 inline-flex rounded-md border border-[#c9a451]/25 bg-[#c9a451]/10 px-2 py-1 text-[10px] font-medium text-[#f0cf79]">
            Powered by ExaEarn Gateway
          </p>
        ) : null}
      </div>
    </div>
  );
}

function Swap({ onBack }) {
  const [fromType, setFromType] = useState("crypto");
  const [toType, setToType] = useState("fiat");
  const [fromAsset, setFromAsset] = useState("XRP");
  const [toAsset, setToAsset] = useState("NGN");
  const [amount, setAmount] = useState("");
  const [isRotated, setIsRotated] = useState(false);
  const [showSettings, setShowSettings] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [loading, setLoading] = useState(false);
  const [expandedRates, setExpandedRates] = useState(false);
  const [slippage, setSlippage] = useState("0.5%");
  const [payoutMethod, setPayoutMethod] = useState("Bank Transfer");
  const [autoRoute, setAutoRoute] = useState(true);
  const [deadline, setDeadline] = useState("20");

  const fromPool = fromType === "crypto" ? CRYPTO_ASSETS : FIAT_ASSETS;
  const toPool = toType === "crypto" ? CRYPTO_ASSETS : FIAT_ASSETS;
  const selectedFrom = fromPool.find((item) => item.symbol === fromAsset) || fromPool[0];
  const selectedTo = toPool.find((item) => item.symbol === toAsset) || toPool[0];
  const parsedAmount = Number.parseFloat(amount || "0");
  const cleanAmount = Number.isNaN(parsedAmount) ? 0 : parsedAmount;

  const receivedAmount = useMemo(() => {
    if (!cleanAmount) return "0.00";
    const mockRate = fromType === "crypto" && toType === "fiat" ? 1540 : fromType === "fiat" && toType === "crypto" ? 0.00063 : 0.984;
    return (cleanAmount * mockRate).toLocaleString(undefined, { maximumFractionDigits: 6 });
  }, [cleanAmount, fromType, toType]);

  const ctaLabel = useMemo(() => {
    if (fromType === "crypto" && toType === "crypto") return "Swap Crypto";
    if (fromType === "crypto" && toAsset === "NGN") return "Convert to Naira";
    if (fromType === "fiat" && toType === "crypto") return "Buy Crypto";
    if (fromType === "crypto" && toType === "fiat") return "Sell Crypto";
    return "Swap";
  }, [fromType, toType, toAsset]);

  const isCryptoToNgn = fromType === "crypto" && toType === "fiat" && toAsset === "NGN";
  const isNgnToCrypto = fromType === "fiat" && fromAsset === "NGN" && toType === "crypto";
  const showGatewayFee = fromType === "fiat" || toType === "fiat";
  const showNetworkFee = fromType === "crypto" || toType === "crypto";
  const isDisabled = !amount || Number.isNaN(Number(amount)) || Number(amount) <= 0;

  const handleReverse = () => {
    setIsRotated((prev) => !prev);
    setFromType(toType);
    setToType(fromType);
    setFromAsset(toAsset);
    setToAsset(fromAsset);
  };

  const handleSwapClick = () => {
    if (isDisabled) return;
    setShowConfirm(true);
  };

  const handleConfirm = () => {
    setLoading(true);
    setTimeout(() => {
      setLoading(false);
      setShowConfirm(false);
    }, 1200);
  };

  return (
    <main className="min-h-screen bg-gradient-to-br from-[#050505] via-[#0d0d0d] to-[#17120b] px-4 pb-28 pt-7 text-neutral-100 sm:px-6 lg:px-8">
      <div className="mx-auto w-full max-w-5xl">
        <header className="mb-6">
          <div className="mb-4 flex items-start justify-between gap-4">
            <div>
              {onBack ? (
                <button
                  type="button"
                  onClick={onBack}
                  className="mb-3 inline-flex items-center gap-2 rounded-lg border border-[#c9a451]/30 bg-black/25 px-3 py-2 text-xs font-semibold text-[#f1d486] transition hover:border-[#c9a451]/60 hover:bg-[#c9a451]/10"
                >
                  <ArrowLeft className="h-4 w-4" />
                  Back
                </button>
              ) : null}
              <h1 className="font-['Sora'] text-3xl font-semibold tracking-tight text-neutral-50 sm:text-4xl">Smart Swap</h1>
              <p className="mt-1 max-w-xl text-sm text-neutral-400">
                Instantly convert crypto and local currencies in one secure interface.
              </p>
            </div>
            <div className="inline-flex items-center gap-2 rounded-xl border border-[#c9a451]/35 bg-[#c9a451]/10 px-3 py-2 text-xs font-semibold text-[#f1d486]">
              <Sparkles className="h-4 w-4" />
              Multi-Chain & Fiat Enabled
            </div>
          </div>
          <div className="h-px w-full bg-gradient-to-r from-transparent via-[#c9a451]/30 to-transparent" />
        </header>

        <section className="mx-auto max-w-2xl rounded-2xl border border-[#c9a451]/25 bg-[linear-gradient(160deg,rgba(20,20,20,.98),rgba(12,12,12,.95))] p-4 shadow-[0_30px_80px_rgba(0,0,0,.55)] backdrop-blur-xl sm:p-6">
          <div className="mb-4 flex items-center justify-between">
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">Hybrid Swap Engine</p>
            <button
              type="button"
              onClick={() => setShowSettings(true)}
              className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#c9a451]/30 bg-black/30 text-[#e2be6d] transition hover:border-[#c9a451]/55 hover:bg-[#c9a451]/10"
              aria-label="Open swap settings"
            >
              <Settings2 className="h-4 w-4" />
            </button>
          </div>

          <AssetSelect
            label="From"
            type={fromType}
            setType={setFromType}
            value={fromAsset}
            setValue={setFromAsset}
          />

          <div className="my-3 flex justify-center">
            <button
              type="button"
              onClick={handleReverse}
              className="inline-flex h-11 w-11 items-center justify-center rounded-full border border-[#d7b25f]/65 bg-gradient-to-r from-[#f8e08e]/90 via-[#d7b25f]/90 to-[#b88a2a]/90 text-[#1a1409] shadow-[0_0_30px_rgba(215,178,95,0.4)] transition hover:scale-[1.04]"
            >
              <ArrowRightLeft className={`h-4 w-4 transition-transform duration-500 ${isRotated ? "rotate-180" : ""}`} />
            </button>
          </div>

          <div className="rounded-2xl border border-[#c9a451]/20 bg-[#121212]/90 p-4 sm:p-5">
            <div className="mb-4 flex items-center justify-between gap-3">
              <p className="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">To</p>
              <div className="rounded-lg border border-[#c9a451]/30 bg-black/30 px-2 py-1 text-[10px] font-medium uppercase tracking-wide text-[#f1d486]">
                Estimated Output
              </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
              <label className="group flex items-center justify-between rounded-xl border border-[#c9a451]/25 bg-black/25 px-3 py-2.5">
                <div className="flex items-center gap-3">
                  <span className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-neutral-800 to-neutral-950 text-[10px] font-bold text-[#f5d780] ring-1 ring-[#d7b25f]/30">
                    {selectedTo.icon}
                  </span>
                  <div>
                    <p className="text-sm font-semibold text-neutral-100">{selectedTo.symbol}</p>
                    <p className="text-xs text-neutral-400">{selectedTo.name}</p>
                  </div>
                </div>
                <select
                  value={selectedTo.symbol}
                  onChange={(event) => setToAsset(event.target.value)}
                  className="cursor-pointer appearance-none bg-transparent pl-6 pr-0 text-right text-xs font-semibold text-[#ebcc7d] outline-none"
                >
                  {toPool.map((item) => (
                    <option key={item.symbol} value={item.symbol} className="bg-[#111111] text-neutral-100">
                      {item.symbol}
                    </option>
                  ))}
                </select>
                <ChevronDown className="pointer-events-none ml-1 h-4 w-4 text-neutral-500 transition group-focus-within:text-[#e5c573]" />
              </label>

              <div className="rounded-xl border border-[#c9a451]/25 bg-black/20 px-4 py-2 text-right">
                <p className="text-[10px] uppercase tracking-[0.14em] text-neutral-500">Arrival</p>
                <p className="mt-1 text-xs font-semibold text-neutral-200">{showGatewayFee ? "~2 - 6 mins" : "~30 sec"}</p>
              </div>
            </div>

            <div className="mt-3 rounded-xl border border-neutral-800 bg-[#0b0b0b] p-3">
              <p className="text-xs text-neutral-500">Estimated received</p>
              <p className="mt-1 text-xl font-semibold text-[#f4d682]">
                {receivedAmount} {selectedTo.symbol}
              </p>
              <p className="mt-1 text-xs text-neutral-400">1 {selectedFrom.symbol} = 1,540.22 NGN (indicative)</p>
            </div>
          </div>

          <div className="mt-4 rounded-xl border border-[#c9a451]/20 bg-[#0f0f0f] p-3">
            <label className="mb-2 block text-xs text-neutral-500">Amount</label>
            <input
              type="number"
              value={amount}
              onChange={(event) => setAmount(event.target.value)}
              placeholder="0.00"
              className="w-full rounded-lg border border-[#c9a451]/25 bg-black/40 px-3 py-3 text-base font-semibold text-neutral-100 outline-none placeholder:text-neutral-500 focus:border-[#d7b25f]/70"
            />
            <p className="mt-2 text-xs text-neutral-400">USD estimate: ${cleanAmount ? (cleanAmount * 2.15).toFixed(2) : "0.00"}</p>
          </div>

          {(isCryptoToNgn || isNgnToCrypto) && (
            <div className="mt-4 rounded-xl border border-[#c9a451]/25 bg-black/35 p-3">
              {isCryptoToNgn ? (
                <div className="grid gap-2 text-xs sm:grid-cols-3">
                  <div>
                    <p className="text-neutral-500">Estimated bank payout</p>
                    <p className="font-semibold text-neutral-100">₦{cleanAmount ? (cleanAmount * 1540).toLocaleString() : "0.00"}</p>
                  </div>
                  <div>
                    <p className="text-neutral-500">Processing time</p>
                    <p className="font-semibold text-neutral-100">3 - 5 mins</p>
                  </div>
                  <div>
                    <p className="text-neutral-500">Gateway fee</p>
                    <p className="font-semibold text-neutral-100">0.45%</p>
                  </div>
                </div>
              ) : (
                <div className="space-y-2 text-xs">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-neutral-500">Payment method</p>
                    <div className="flex gap-2">
                      {["Bank Transfer", "Card"].map((method) => (
                        <button
                          key={method}
                          type="button"
                          onClick={() => setPayoutMethod(method)}
                          className={`rounded-md px-3 py-1 font-semibold transition ${
                            payoutMethod === method
                              ? "border border-[#d7b25f]/60 bg-[#d7b25f]/20 text-[#f2d27e]"
                              : "border border-neutral-700 bg-black/30 text-neutral-300 hover:border-[#d7b25f]/35"
                          }`}
                        >
                          {method}
                        </button>
                      ))}
                    </div>
                  </div>
                  <p className="text-neutral-300">Payment processing begins after transfer confirmation.</p>
                  <p className="inline-flex items-center gap-1 rounded-md border border-[#d7b25f]/30 bg-[#d7b25f]/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-[#edce7a]">
                    <CheckCircle2 className="h-3 w-3" />
                    Compliance Verified
                  </p>
                </div>
              )}
              <p className="mt-3 text-[11px] text-neutral-500">
                All fiat transactions comply with regional financial regulations.
              </p>
            </div>
          )}

          <div className="mt-4 overflow-hidden rounded-xl border border-[#c9a451]/20 bg-black/25">
            <button
              type="button"
              onClick={() => setExpandedRates((prev) => !prev)}
              className="flex w-full items-center justify-between px-3 py-3 text-left text-sm font-semibold text-neutral-200 md:cursor-default"
            >
              Rate & Fee Breakdown
              <span className="md:hidden">{expandedRates ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}</span>
            </button>
            <div className={`${expandedRates ? "block" : "hidden"} border-t border-[#c9a451]/15 p-3 md:block`}>
              <div className="grid gap-2 text-xs">
                <FeeRow label="Exchange Rate" value={`1 ${selectedFrom.symbol} = 1,540.22 ${selectedTo.symbol}`} />
                {showNetworkFee ? <FeeRow label="Network Fee" value="~0.00012 ETH" /> : null}
                {showGatewayFee ? <FeeRow label="Gateway Fee" value="0.45%" /> : null}
                <FeeRow label="Slippage" value={slippage} />
                <FeeRow label="Price Impact" value="0.08%" />
                <FeeRow label="Total Deduction" value={showGatewayFee ? "0.57%" : "0.12%"} />
                <FeeRow label="Minimum Received" value={`${cleanAmount ? (cleanAmount * 0.97).toFixed(4) : "0.00"} ${selectedTo.symbol}`} />
              </div>
            </div>
          </div>

          <button
            type="button"
            onClick={handleSwapClick}
            disabled={isDisabled || loading}
            className={`mt-4 hidden w-full rounded-xl bg-gradient-to-r ${GOLD_GRADIENT} px-4 py-3 text-sm font-semibold text-[#19150c] shadow-[0_0_26px_rgba(215,178,95,.35)] transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-45 sm:block`}
          >
            {loading ? "Processing..." : ctaLabel}
          </button>
        </section>

        <section className="mx-auto mt-6 hidden max-w-5xl rounded-2xl border border-[#c9a451]/15 bg-[#0f0f0f]/80 p-5 lg:block">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="text-base font-semibold text-neutral-100">Market Snapshot</h2>
            <span className="inline-flex items-center gap-1 text-xs text-[#e4c070]">
              <TrendingUp className="h-4 w-4" />
              Live trend placeholder
            </span>
          </div>
          <div className="grid grid-cols-4 gap-4">
            <StatCard title="24H Volume" value="$1.82B" />
            <StatCard title="Liquidity" value="$624M" />
            <StatCard title="Price Change" value="+2.41%" />
            <StatCard title="Avg Route Time" value="41 sec" />
          </div>
          <div className="mt-4 h-28 rounded-xl border border-neutral-800 bg-gradient-to-r from-[#131313] via-[#1a1a1a] to-[#101010] p-3">
            <div className="h-full w-full rounded-lg bg-[radial-gradient(circle_at_10%_80%,rgba(215,178,95,.22),transparent_28%),radial-gradient(circle_at_35%_35%,rgba(215,178,95,.08),transparent_30%),linear-gradient(180deg,rgba(255,255,255,.04),transparent)]" />
          </div>
        </section>
      </div>

      <div className="fixed inset-x-0 bottom-0 z-30 border-t border-[#c9a451]/25 bg-[#080808]/95 p-3 backdrop-blur md:hidden">
        <button
          type="button"
          onClick={handleSwapClick}
          disabled={isDisabled || loading}
          className={`w-full rounded-xl bg-gradient-to-r ${GOLD_GRADIENT} px-4 py-3 text-sm font-semibold text-[#19150c] shadow-[0_0_26px_rgba(215,178,95,.35)] transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-45`}
        >
          {loading ? "Processing..." : ctaLabel}
        </button>
      </div>

      {showSettings ? (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/70 p-4">
          <div className="w-full max-w-md rounded-xl border border-[#c9a451]/30 bg-[#101010] p-5 shadow-[0_24px_70px_rgba(0,0,0,.6)]">
            <div className="mb-4 flex items-center justify-between">
              <h3 className="text-lg font-semibold text-neutral-50">Swap Settings</h3>
              <button
                type="button"
                onClick={() => setShowSettings(false)}
                className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-700 bg-black/30 text-neutral-300 hover:border-[#c9a451]/45 hover:text-[#f1d486]"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="space-y-4 text-sm">
              <div>
                <p className="mb-2 text-neutral-400">Slippage tolerance</p>
                <div className="flex flex-wrap gap-2">
                  {["0.1%", "0.5%", "1.0%"].map((item) => (
                    <button
                      key={item}
                      type="button"
                      onClick={() => setSlippage(item)}
                      className={`rounded-md px-3 py-1.5 text-xs font-semibold transition ${
                        slippage === item
                          ? "border border-[#d7b25f]/70 bg-[#d7b25f]/20 text-[#f1d486]"
                          : "border border-neutral-700 bg-black/30 text-neutral-300 hover:border-[#c9a451]/35"
                      }`}
                    >
                      {item}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <p className="mb-2 text-neutral-400">Preferred payout method</p>
                <select
                  value={payoutMethod}
                  onChange={(event) => setPayoutMethod(event.target.value)}
                  className="w-full rounded-lg border border-neutral-700 bg-black/40 px-3 py-2 text-sm text-neutral-100 outline-none focus:border-[#c9a451]/60"
                >
                  <option>Bank Transfer</option>
                  <option>Card</option>
                  <option>Wallet Settlement</option>
                </select>
              </div>

              <div className="flex items-center justify-between rounded-lg border border-neutral-800 bg-black/35 px-3 py-2">
                <div>
                  <p className="text-neutral-200">Auto-route optimization</p>
                  <p className="text-xs text-neutral-500">Use best liquidity paths</p>
                </div>
                <button
                  type="button"
                  onClick={() => setAutoRoute((prev) => !prev)}
                  className={`relative h-6 w-11 rounded-full transition ${autoRoute ? "bg-[#c9a451]/50" : "bg-neutral-700"}`}
                >
                  <span
                    className={`absolute top-0.5 h-5 w-5 rounded-full bg-neutral-100 transition ${
                      autoRoute ? "left-[22px]" : "left-0.5"
                    }`}
                  />
                </button>
              </div>

              <div>
                <p className="mb-2 text-neutral-400">Transaction deadline</p>
                <div className="flex items-center gap-2">
                  <input
                    value={deadline}
                    onChange={(event) => setDeadline(event.target.value)}
                    className="w-24 rounded-lg border border-neutral-700 bg-black/40 px-3 py-2 text-sm text-neutral-100 outline-none focus:border-[#c9a451]/60"
                  />
                  <span className="text-neutral-400">minutes</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      ) : null}

      {showConfirm ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4">
          <div className="w-full max-w-md rounded-xl border border-[#c9a451]/35 bg-[#101010] p-5 shadow-[0_26px_80px_rgba(0,0,0,.65)] animate-[fadeIn_.2s_ease-out]">
            <h3 className="text-lg font-semibold text-neutral-50">Confirm Swap</h3>
            <p className="mt-1 text-xs text-neutral-400">Review transaction details before final confirmation.</p>

            <div className="mt-4 space-y-2 rounded-xl border border-neutral-800 bg-black/35 p-3 text-xs">
              <FeeRow label="From" value={`${amount || "0.00"} ${selectedFrom.symbol}`} />
              <FeeRow label="To" value={`${receivedAmount} ${selectedTo.symbol}`} />
              <FeeRow label="Rate" value={`1 ${selectedFrom.symbol} = 1,540.22 ${selectedTo.symbol}`} />
              <FeeRow label="Fees" value={showGatewayFee ? "Network + Gateway" : "Network"} />
              <FeeRow label="Estimated delivery" value={showGatewayFee ? "2 - 6 mins" : "Under 1 min"} />
            </div>

            <div className="mt-3 rounded-lg border border-[#c9a451]/20 bg-[#c9a451]/10 p-3 text-xs text-[#f2d27e]">
              <p className="flex items-start gap-2">
                <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0" />
                All swaps are executed through secure smart contract routing and regulated fiat gateways.
              </p>
            </div>

            <div className="mt-5 flex gap-2">
              <button
                type="button"
                onClick={handleConfirm}
                className={`flex-1 rounded-lg bg-gradient-to-r ${GOLD_GRADIENT} px-3 py-2.5 text-sm font-semibold text-[#1a1409]`}
              >
                Confirm
              </button>
              <button
                type="button"
                onClick={() => setShowConfirm(false)}
                className="flex-1 rounded-lg border border-neutral-700 bg-black/40 px-3 py-2.5 text-sm font-semibold text-neutral-200 hover:border-neutral-500"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </main>
  );
}

function FeeRow({ label, value }) {
  return (
    <div className="flex items-center justify-between border-b border-neutral-800/80 pb-2 last:border-0 last:pb-0">
      <span className="text-neutral-500">{label}</span>
      <span className="font-medium text-neutral-100">{value}</span>
    </div>
  );
}

function StatCard({ title, value }) {
  return (
    <article className="rounded-xl border border-neutral-800 bg-black/35 p-3">
      <p className="text-xs text-neutral-500">{title}</p>
      <p className="mt-1 text-sm font-semibold text-neutral-100">{value}</p>
    </article>
  );
}

export default Swap;
