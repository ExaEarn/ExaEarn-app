import { useEffect, useMemo, useState } from "react";
import { BarChart3, CandlestickChart, ChevronDown, SlidersHorizontal } from "lucide-react";

const pairs = [
  { pair: "XRP/USDT", base: "XRP", quote: "USDT", name: "XRP Ledger", seedPrice: 1.3572, color: "from-[#3b82f6] to-[#60a5fa]" },
  { pair: "BTC/USDT", base: "BTC", quote: "USDT", name: "Bitcoin", seedPrice: 68320.2, color: "from-[#f59e0b] to-[#fbbf24]" },
  { pair: "ETH/USDT", base: "ETH", quote: "USDT", name: "Ethereum", seedPrice: 1964.8, color: "from-[#6366f1] to-[#818cf8]" },
  { pair: "SOL/USDT", base: "SOL", quote: "USDT", name: "Solana", seedPrice: 154.7, color: "from-[#a855f7] to-[#22d3ee]" },
];
const modes = ["Spot", "Margin", "Futures"];
const orderTypes = ["Market", "Limit", "Stop-Limit"];
const percentages = [25, 50, 75, 100];

function Trade({ onBack }) {
  const [pair, setPair] = useState("XRP/USDT");
  const [showPairMenu, setShowPairMenu] = useState(false);
  const [mode, setMode] = useState("Spot");
  const [leverage, setLeverage] = useState(20);
  const [marginType, setMarginType] = useState("Cross");
  const [side, setSide] = useState("buy");
  const [orderType, setOrderType] = useState("Market");
  const [price, setPrice] = useState("1.3572");
  const [amount, setAmount] = useState("");
  const [selectedPct, setSelectedPct] = useState(0);
  const [showConfirm, setShowConfirm] = useState(false);
  const [showChartModal, setShowChartModal] = useState(false);
  const [pricePulse, setPricePulse] = useState(false);
  const [bookTick, setBookTick] = useState(0);

  const [midPrice, setMidPrice] = useState(1.3572);
  const [change24h, setChange24h] = useState(-2.6);
  const [availableBalance] = useState(3240.25);

  useEffect(() => {
    const timer = setInterval(() => {
      setMidPrice((previous) => {
        const next = Math.max(0.0001, previous + (Math.random() - 0.5) * 0.004);
        return Number(next.toFixed(4));
      });
      setChange24h((previous) => Number((previous + (Math.random() - 0.5) * 0.16).toFixed(2)));
      setPricePulse(true);
      setTimeout(() => setPricePulse(false), 260);
      setBookTick((previous) => previous + 1);
    }, 1800);

    return () => clearInterval(timer);
  }, []);

  useEffect(() => {
    if (orderType === "Market") {
      setPrice(midPrice.toFixed(4));
    }
  }, [midPrice, orderType]);

  const estimatedCost = useMemo(() => {
    const numericPrice = parseFloat(price || "0");
    const numericAmount = parseFloat(amount || "0");
    return numericPrice * numericAmount;
  }, [price, amount]);

  const fee = useMemo(() => estimatedCost * 0.001, [estimatedCost]);
  const liquidationPrice = useMemo(() => {
    if (mode !== "Futures") return null;
    const numericPrice = parseFloat(price || "0");
    if (!numericPrice) return null;
    const adjustment = side === "buy" ? 0.88 : 1.12;
    return numericPrice * adjustment;
  }, [mode, side, price]);

  const askOrders = useMemo(() => generateOrders(midPrice + 0.0006, false, bookTick), [midPrice, bookTick]);
  const bidOrders = useMemo(() => generateOrders(midPrice - 0.0006, true, bookTick), [midPrice, bookTick]);
  const changePositive = change24h >= 0;
  const selectedPair = useMemo(() => pairs.find((item) => item.pair === pair) || pairs[0], [pair]);

  useEffect(() => {
    const nextPrice = selectedPair.seedPrice;
    setMidPrice(nextPrice);
    if (orderType === "Market") {
      setPrice(nextPrice.toFixed(4));
    }
  }, [selectedPair, orderType]);

  return (
    <main className="min-h-screen bg-[#0B0F1A] text-white">
      <div className="mx-auto w-full max-w-md pb-24 sm:max-w-2xl lg:max-w-5xl">
        <header className="sticky top-0 z-20 border-b border-white/10 bg-[#0B0F1A]/95 px-4 pb-3 pt-4 backdrop-blur">
          <div className="flex items-center justify-between gap-2">
            <button
              type="button"
              onClick={onBack}
              className="rounded-lg border border-white/15 bg-[#111827] px-2 py-1 text-xs text-[#9aa4b2]"
            >
              Back
            </button>
            <div className="relative">
              <button
                type="button"
                onClick={() => setShowPairMenu((prev) => !prev)}
                className="inline-flex items-center gap-2 rounded-lg border border-white/15 bg-[#111827] px-2.5 py-1.5 text-sm font-semibold"
              >
                <PairBadge pair={selectedPair} compact />
                <span>{selectedPair.pair}</span>
                <ChevronDown className={`h-4 w-4 text-[#9aa4b2] transition ${showPairMenu ? "rotate-180" : ""}`} />
              </button>
              {showPairMenu ? (
                <div className="absolute left-0 top-[calc(100%+0.4rem)] z-30 w-60 rounded-xl border border-white/12 bg-[#111827] p-2 shadow-[0_18px_32px_rgba(0,0,0,0.42)]">
                  {pairs.map((item) => (
                    <button
                      key={item.pair}
                      type="button"
                      onClick={() => {
                        setPair(item.pair);
                        setShowPairMenu(false);
                      }}
                      className={`flex w-full items-center justify-between rounded-lg px-2 py-2 text-left transition ${
                        item.pair === pair ? "bg-[#6b2cff]/18" : "hover:bg-[#0D1424]"
                      }`}
                    >
                      <div className="flex items-center gap-2">
                        <PairBadge pair={item} />
                        <div>
                          <p className="text-sm font-semibold text-white">{item.pair}</p>
                          <p className="text-[11px] text-[#9aa4b2]">{item.name}</p>
                        </div>
                      </div>
                      <span className="text-[11px] text-[#9aa4b2]">{item.base}</span>
                    </button>
                  ))}
                </div>
              ) : null}
            </div>
            <div className="flex items-center gap-1">
              <button type="button" className="rounded-lg border border-white/15 bg-[#111827] p-2 text-[#9aa4b2]">
                <CandlestickChart className="h-4 w-4" />
              </button>
              <button type="button" className="rounded-lg border border-white/15 bg-[#111827] p-2 text-[#9aa4b2]">
                <BarChart3 className="h-4 w-4" />
              </button>
              <button type="button" className="rounded-lg border border-white/15 bg-[#111827] p-2 text-[#9aa4b2]">
                <SlidersHorizontal className="h-4 w-4" />
              </button>
            </div>
          </div>

          <div className="mt-3 flex items-end justify-between">
            <div>
              <p className={`text-2xl font-semibold transition ${pricePulse ? "drop-shadow-[0_0_12px_rgba(255,185,0,0.35)]" : ""}`}>
                {midPrice.toFixed(4)}
              </p>
              <p className={`text-xs font-medium ${changePositive ? "text-[#18c06c]" : "text-[#ff5b6b]"}`}>
                {changePositive ? "+" : ""}
                {change24h.toFixed(2)}% (24h)
              </p>
            </div>
            <button
              type="button"
              onClick={() => setShowChartModal(true)}
              className="rounded-lg bg-gradient-to-r from-[#6b2cff] to-[#ffb900] px-3 py-1.5 text-xs font-semibold"
            >
              Expand Chart
            </button>
          </div>
        </header>

        <section className="px-4 pt-4">
          <div className="rounded-2xl border border-white/10 bg-[#111827] p-3">
            <div className="h-24 rounded-xl bg-[#0d1424] p-2">
              <div className="flex h-full items-end gap-1">
                {[28, 39, 44, 30, 52, 47, 62, 57, 50, 68, 61, 74].map((height, index) => (
                  <span
                    key={index}
                    className="w-full rounded-t bg-gradient-to-t from-[#6b2cff] to-[#ffb900]"
                    style={{ height: `${height}%` }}
                  />
                ))}
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 pt-4">
          <div className="rounded-2xl border border-white/10 bg-[#111827] p-3">
            <div className="relative flex rounded-xl bg-[#0d1424] p-1">
              <span
                className="absolute bottom-1 top-1 w-[calc(33.333%-0.34rem)] rounded-lg bg-gradient-to-r from-[#6b2cff] to-[#ffb900] transition-transform duration-300"
                style={{
                  transform: `translateX(${modes.indexOf(mode) * 100}%)`,
                }}
              />
              {modes.map((item) => (
                <button
                  key={item}
                  type="button"
                  onClick={() => setMode(item)}
                  className="relative z-10 flex-1 rounded-lg py-2 text-xs font-semibold"
                >
                  {item}
                </button>
              ))}
            </div>

            {mode === "Futures" ? (
              <div className="mt-3 flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setLeverage((value) => Math.max(1, value - 1))}
                  className="rounded-lg border border-white/15 bg-[#0d1424] px-2 py-1 text-xs"
                >
                  -
                </button>
                <span className="rounded-lg bg-[#0d1424] px-3 py-1 text-xs font-semibold">{leverage}x</span>
                <button
                  type="button"
                  onClick={() => setLeverage((value) => Math.min(125, value + 1))}
                  className="rounded-lg border border-white/15 bg-[#0d1424] px-2 py-1 text-xs"
                >
                  +
                </button>
                <div className="ml-auto flex items-center gap-1 rounded-lg bg-[#0d1424] p-1">
                  {["Cross", "Isolated"].map((item) => (
                    <button
                      key={item}
                      type="button"
                      onClick={() => setMarginType(item)}
                      className={`rounded-md px-2 py-1 text-xs ${marginType === item ? "bg-[#6b2cff]/30 text-white" : "text-[#9aa4b2]"}`}
                    >
                      {item}
                    </button>
                  ))}
                </div>
              </div>
            ) : null}
          </div>
        </section>

        <section className="grid gap-4 px-4 pt-4 lg:grid-cols-[1.12fr_0.88fr]">
          <div className="space-y-4">
            <div className="rounded-2xl border border-white/10 bg-[#111827] p-3">
              <div className="relative flex rounded-xl bg-[#0d1424] p-1">
                <span
                  className={`absolute bottom-1 top-1 w-[calc(50%-0.34rem)] rounded-lg transition-transform duration-300 ${
                    side === "buy" ? "bg-gradient-to-r from-[#18c06c] to-[#38d982]" : "translate-x-full bg-[#ff5b6b]/90"
                  }`}
                />
                <button
                  type="button"
                  onClick={() => setSide("buy")}
                  className="relative z-10 flex-1 rounded-lg py-2 text-sm font-semibold"
                >
                  Buy
                </button>
                <button
                  type="button"
                  onClick={() => setSide("sell")}
                  className="relative z-10 flex-1 rounded-lg py-2 text-sm font-semibold"
                >
                  Sell
                </button>
              </div>

              <div className="mt-3">
                <label className="mb-1 block text-xs text-[#9aa4b2]">Order Type</label>
                <select
                  value={orderType}
                  onChange={(event) => setOrderType(event.target.value)}
                  className="w-full rounded-xl border border-white/15 bg-[#0d1424] px-3 py-2 text-sm outline-none focus:border-[#ffb900]"
                >
                  {orderTypes.map((item) => (
                    <option key={item} value={item}>
                      {item}
                    </option>
                  ))}
                </select>
              </div>

              <div className="mt-3">
                <label className="mb-1 block text-xs text-[#9aa4b2]">Price</label>
                <input
                  value={price}
                  onChange={(event) => setPrice(event.target.value)}
                  disabled={orderType === "Market"}
                  className="w-full rounded-xl border border-white/15 bg-[#0d1424] px-3 py-2 text-sm outline-none disabled:cursor-not-allowed disabled:opacity-70 focus:border-[#ffb900]"
                />
              </div>

              <div className="mt-3">
                <label className="mb-1 block text-xs text-[#9aa4b2]">Amount</label>
                <input
                  value={amount}
                  onChange={(event) => setAmount(event.target.value)}
                  placeholder="0.00"
                  className="w-full rounded-xl border border-white/15 bg-[#0d1424] px-3 py-2 text-sm outline-none focus:border-[#ffb900]"
                />
              </div>

              <div className="mt-3 grid grid-cols-4 gap-2">
                {percentages.map((percent) => (
                  <button
                    key={percent}
                    type="button"
                    onClick={() => {
                      setSelectedPct(percent);
                      const numericPrice = parseFloat(price || "0");
                      const maxAmount = numericPrice ? availableBalance / numericPrice : 0;
                      setAmount(((maxAmount * percent) / 100).toFixed(4));
                    }}
                    className={`rounded-lg border px-2 py-1 text-xs font-semibold ${
                      selectedPct === percent
                        ? "border-transparent bg-gradient-to-r from-[#6b2cff] to-[#ffb900]"
                        : "border-white/15 bg-[#0d1424] text-[#9aa4b2]"
                    }`}
                  >
                    {percent}%
                  </button>
                ))}
              </div>

              <div className="mt-3 rounded-xl border border-white/10 bg-[#0d1424] p-3 text-xs">
                <p className="text-[#9aa4b2]">Available Balance: {availableBalance.toFixed(2)} USDT</p>
                <p className="mt-1 text-[#9aa4b2]">Estimated Cost: {estimatedCost.toFixed(4)} USDT</p>
                <p className="mt-1 text-[#9aa4b2]">Fee Preview: {fee.toFixed(4)} USDT</p>
                {liquidationPrice ? <p className="mt-1 text-[#ffb900]">Liquidation Price: {liquidationPrice.toFixed(4)}</p> : null}
              </div>

              <button
                type="button"
                onClick={() => setShowConfirm(true)}
                className={`mt-4 w-full rounded-xl py-3 text-sm font-semibold transition active:scale-[0.99] ${
                  side === "buy"
                    ? "bg-gradient-to-r from-[#18c06c] to-[#38d982] text-[#0B0F1A]"
                    : "bg-gradient-to-r from-[#b43340] to-[#ff5b6b] text-white"
                }`}
              >
                {side === "buy" ? "Buy / Long" : "Sell / Short"}
              </button>
            </div>

            <div className="rounded-2xl border border-white/10 bg-[#111827] p-3">
              <h3 className="text-sm font-semibold">Open Position</h3>
              <div className="mt-2 grid grid-cols-2 gap-2 text-xs">
                <InfoCell label="Entry Price" value="1.3400" />
                <InfoCell label="Mark Price" value={midPrice.toFixed(4)} />
                <InfoCell label="Unrealized PNL" value="+43.21 USDT" positive />
                <InfoCell label="Margin Used" value="122.40 USDT" />
                <InfoCell label="Liquidation" value="1.1750" />
                <button
                  type="button"
                  className="rounded-lg border border-[#ff5b6b]/35 bg-[#ff5b6b]/15 px-2 py-1.5 text-xs font-semibold text-[#ff9aa8]"
                >
                  Close Position
                </button>
              </div>
            </div>
          </div>

          <div className="rounded-2xl border border-white/10 bg-[#111827] p-3">
            <h3 className="text-sm font-semibold">Order Book</h3>

            <div className="mt-2 space-y-1">
              {askOrders.map((item, index) => (
                <BookRow key={`ask-${index}`} item={item} side="sell" />
              ))}
            </div>

            <div className="my-2 rounded-lg border border-[#ffb900]/30 bg-[#ffb900]/10 px-2 py-1 text-center text-sm font-semibold text-[#ffde7a]">
              {midPrice.toFixed(4)}
            </div>

            <div className="space-y-1">
              {bidOrders.map((item, index) => (
                <BookRow key={`bid-${index}`} item={item} side="buy" />
              ))}
            </div>
          </div>
        </section>

        <p className="px-4 pt-4 text-center text-xs text-[#9aa4b2]">
          Trading involves risk. Ensure you understand leverage and liquidation before placing an order.
        </p>
      </div>

      {showConfirm ? (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-black/60 p-4 sm:items-center">
          <div className="w-full max-w-sm rounded-2xl border border-white/15 bg-[#111827] p-4">
            <h3 className="text-base font-semibold">Confirm Order</h3>
            <div className="mt-3 space-y-1 text-sm text-[#9aa4b2]">
              <p>Pair: {pair}</p>
              <p>Side: {side === "buy" ? "Buy / Long" : "Sell / Short"}</p>
              <p>Type: {orderType}</p>
              <p>Price: {price}</p>
              <p>Amount: {amount || "0"}</p>
              <p>Fee: {fee.toFixed(4)} USDT</p>
              <p className="text-[#ffde7a]">Total: {(estimatedCost + fee).toFixed(4)} USDT</p>
            </div>
            <div className="mt-4 grid grid-cols-2 gap-2">
              <button
                type="button"
                onClick={() => setShowConfirm(false)}
                className="rounded-lg border border-white/15 bg-[#0d1424] py-2 text-sm"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={() => setShowConfirm(false)}
                className="rounded-lg bg-gradient-to-r from-[#6b2cff] to-[#ffb900] py-2 text-sm font-semibold"
              >
                Confirm
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {showChartModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-3">
          <div className="h-[78vh] w-full max-w-3xl rounded-2xl border border-white/15 bg-[#111827] p-4">
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-base font-semibold">{pair} Advanced Chart</h3>
              <button
                type="button"
                onClick={() => setShowChartModal(false)}
                className="rounded-lg border border-white/15 bg-[#0d1424] px-3 py-1 text-xs"
              >
                Close
              </button>
            </div>
            <div className="h-[calc(100%-2.25rem)] rounded-xl border border-white/10 bg-[#0d1424] p-3">
              <div className="flex h-full items-end gap-1">
                {Array.from({ length: 42 }).map((_, index) => {
                  const height = 18 + ((index * 19 + bookTick * 7) % 66);
                  return (
                    <span
                      key={index}
                      className="w-full rounded-t bg-gradient-to-t from-[#6b2cff] to-[#ffb900]"
                      style={{ height: `${height}%` }}
                    />
                  );
                })}
              </div>
            </div>
          </div>
        </div>
      ) : null}
    </main>
  );
}

function InfoCell({ label, value, positive }) {
  return (
    <div className="rounded-lg border border-white/10 bg-[#0d1424] px-2 py-1.5">
      <p className="text-[11px] text-[#9aa4b2]">{label}</p>
      <p className={`mt-0.5 text-xs font-semibold ${positive ? "text-[#18c06c]" : "text-white"}`}>{value}</p>
    </div>
  );
}

function BookRow({ item, side }) {
  const tone = side === "buy" ? "text-[#18c06c]" : "text-[#ff7b89]";
  const barTone = side === "buy" ? "bg-[#18c06c]/20" : "bg-[#ff5b6b]/20";
  return (
    <div className="relative overflow-hidden rounded-md border border-white/5 px-2 py-1 text-xs">
      <span
        className={`pointer-events-none absolute inset-y-0 right-0 ${barTone}`}
        style={{ width: `${Math.min(100, item.depth)}%` }}
      />
      <div className="relative z-10 flex justify-between">
        <span className={tone}>{item.price.toFixed(4)}</span>
        <span className="text-[#9aa4b2]">{item.amount.toFixed(3)}</span>
      </div>
    </div>
  );
}

function PairBadge({ pair, compact = false }) {
  return (
    <span
      className={`inline-flex items-center justify-center rounded-full bg-gradient-to-r ${pair.color} font-semibold text-[#0B0F1A] ${
        compact ? "h-5 w-5 text-[10px]" : "h-6 w-6 text-[11px]"
      }`}
    >
      {pair.base[0]}
    </span>
  );
}

function generateOrders(startPrice, isBid, tick) {
  return Array.from({ length: 8 }).map((_, index) => {
    const step = 0.0002 * (index + 1);
    const price = isBid ? startPrice - step : startPrice + step;
    const amount = 400 + (((index + 1) * 91 + tick * 13) % 680) + Math.random() * 8;
    return {
      price: Number(price.toFixed(4)),
      amount,
      depth: 18 + ((index * 12 + tick * 3) % 72),
    };
  });
}

export default Trade;
