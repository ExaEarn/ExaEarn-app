import { useEffect, useMemo, useState } from "react";
import {
  Bell,
  CandlestickChart,
  ChartNoAxesCombined,
  ChevronDown,
  Gift,
  Menu,
  ShieldAlert,
  SlidersHorizontal,
} from "lucide-react";

const topTabs = ["USDT-M", "COIN-M", "Options", "Smart Money"];
const usdtContracts = ["XRPUSDT Perp", "BTCUSDT Perp", "ETHUSDT Perp", "SOLUSDT Perp"];
const coinMContracts = ["BTCUSD Perp", "ETHUSD Perp", "BNBUSD Perp", "XRPUSD Perp"];
const orderTypes = ["Market", "Limit", "Stop"];
const timeframes = ["1m", "5m", "1h", "1D"];
const percentSteps = [25, 50, 75, 100];

function Futures({ onBack, onOpenOptions, onOpenSmart }) {
  const [activeTab, setActiveTab] = useState("USDT-M");
  const [selectedUsdtContract, setSelectedUsdtContract] = useState(usdtContracts[0]);
  const [selectedCoinMContract, setSelectedCoinMContract] = useState(coinMContracts[0]);
  const [showContracts, setShowContracts] = useState(false);

  const [price, setPrice] = useState(1.3572);
  const [change24h, setChange24h] = useState(-2.14);
  const [fundingRate, setFundingRate] = useState(0.0125);
  const [countdown, setCountdown] = useState(1438);
  const [pricePulse, setPricePulse] = useState(false);

  const [marginType, setMarginType] = useState("Cross");
  const [positionMode, setPositionMode] = useState("One-way");
  const [leverage, setLeverage] = useState(20);
  const [side, setSide] = useState("buy");
  const [orderType, setOrderType] = useState("Market");
  const [priceInput, setPriceInput] = useState("1.3572");
  const [amount, setAmount] = useState("");
  const [selectedPct, setSelectedPct] = useState(0);
  const [currency, setCurrency] = useState("USDT");
  const [enableTpSl, setEnableTpSl] = useState(false);
  const [tpPrice, setTpPrice] = useState("");
  const [slPrice, setSlPrice] = useState("");
  const [reduceOnly, setReduceOnly] = useState(false);
  const [timeInForce, setTimeInForce] = useState("GTC");
  const [showChart, setShowChart] = useState(true);
  const [chartFrame, setChartFrame] = useState("5m");

  const availableBalance = 3280.32;
  const [bookTick, setBookTick] = useState(0);

  const selectedPair = activeTab === "COIN-M" ? selectedCoinMContract : selectedUsdtContract;
  const contracts = activeTab === "COIN-M" ? coinMContracts : usdtContracts;

  useEffect(() => {
    const timer = setInterval(() => {
      setPrice((previous) => {
        const next = Math.max(0.0001, previous + (Math.random() - 0.5) * 0.0035);
        return Number(next.toFixed(4));
      });
      setChange24h((previous) => Number((previous + (Math.random() - 0.5) * 0.12).toFixed(2)));
      setFundingRate((previous) => Number((previous + (Math.random() - 0.5) * 0.0004).toFixed(4)));
      setBookTick((previous) => previous + 1);
      setPricePulse(true);
      setTimeout(() => setPricePulse(false), 240);
    }, 1700);
    return () => clearInterval(timer);
  }, []);

  useEffect(() => {
    const timer = setInterval(() => {
      setCountdown((previous) => (previous <= 0 ? 1438 : previous - 1));
    }, 1000);
    return () => clearInterval(timer);
  }, []);

  useEffect(() => {
    if (orderType === "Market") {
      setPriceInput(price.toFixed(4));
    }
  }, [price, orderType]);

  const askOrders = useMemo(() => generateOrders(price + 0.0007, false, bookTick), [price, bookTick]);
  const bidOrders = useMemo(() => generateOrders(price - 0.0007, true, bookTick), [price, bookTick]);
  const buyVolume = useMemo(() => bidOrders.reduce((acc, row) => acc + row.amount, 0), [bidOrders]);
  const sellVolume = useMemo(() => askOrders.reduce((acc, row) => acc + row.amount, 0), [askOrders]);
  const dominance = useMemo(() => {
    const total = buyVolume + sellVolume;
    if (!total) return 50;
    return Math.round((buyVolume / total) * 100);
  }, [buyVolume, sellVolume]);

  const estimatedCost = useMemo(() => {
    const p = parseFloat(priceInput || "0");
    const a = parseFloat(amount || "0");
    return p * a;
  }, [priceInput, amount]);
  const feePreview = useMemo(() => estimatedCost * 0.0008, [estimatedCost]);
  const liquidationPrice = useMemo(() => {
    const p = parseFloat(priceInput || "0");
    if (!p) return 0;
    return side === "buy" ? p * (1 - 0.8 / leverage) : p * (1 + 0.8 / leverage);
  }, [priceInput, side, leverage]);
  const changePositive = change24h >= 0;

  const applyPercent = (pct) => {
    setSelectedPct(pct);
    const p = parseFloat(priceInput || "0");
    const maxAmount = p ? availableBalance / p : 0;
    setAmount(((maxAmount * pct) / 100).toFixed(4));
  };

  const handleTabClick = (tab) => {
    if (tab === "Options") {
      onOpenOptions?.();
      return;
    }
    if (tab === "Smart" || tab === "Smart Money") {
      onOpenSmart?.();
      return;
    }
    setActiveTab(tab);
    setShowContracts(false);
  };

  return (
    <main className="min-h-screen bg-[#0B0F1A] text-white">
      <div className="mx-auto w-full max-w-md pb-28 sm:max-w-2xl lg:max-w-5xl">
        <header className="sticky top-0 z-20 border-b border-white/10 bg-[#0B0F1A]/95 px-4 pt-4 backdrop-blur">
          <div className="flex items-center justify-between">
            <div className="flex gap-4 overflow-x-auto text-xs font-semibold">
              {topTabs.map((tab) => (
                <button
                  key={tab}
                  type="button"
                  onClick={() => handleTabClick(tab)}
                  className={`relative whitespace-nowrap pb-2 ${activeTab === tab ? "text-white" : "text-[#9CA3AF]"}`}
                >
                  {tab}
                  {activeTab === tab ? <span className="absolute bottom-0 left-0 h-0.5 w-full rounded-full bg-gradient-to-r from-[#6b2cff] to-[#ffb900]" /> : null}
                </button>
              ))}
            </div>
            <div className="flex items-center gap-2">
              <button type="button" className="rounded-lg border border-white/15 bg-[#111827] p-2 text-[#9CA3AF]">
                <Menu className="h-4 w-4" />
              </button>
              <button type="button" className="relative rounded-lg border border-white/15 bg-[#111827] p-2 text-[#9CA3AF]">
                <Bell className="h-4 w-4" />
                <span className="absolute right-1 top-1 h-1.5 w-1.5 rounded-full bg-[#ffb900]" />
              </button>
            </div>
          </div>

          <div className="mt-3 flex items-start justify-between pb-3">
            <div className="relative">
              <button
                type="button"
                onClick={() => setShowContracts((prev) => !prev)}
                className="inline-flex items-center gap-1 rounded-lg border border-white/15 bg-[#111827] px-2.5 py-1 text-sm font-semibold text-[#d7dde8]"
              >
                {selectedPair}
                <ChevronDown className={`h-4 w-4 text-[#9CA3AF] transition ${showContracts ? "rotate-180" : ""}`} />
              </button>
              {showContracts ? (
                <div className="absolute left-0 top-[calc(100%+0.35rem)] z-30 w-44 rounded-xl border border-white/12 bg-[#111827] p-2 shadow-[0_18px_32px_rgba(0,0,0,0.4)]">
                  {contracts.map((contract) => (
                    <button
                      key={contract}
                      type="button"
                      onClick={() => {
                        if (activeTab === "COIN-M") {
                          setSelectedCoinMContract(contract);
                        } else {
                          setSelectedUsdtContract(contract);
                        }
                        setShowContracts(false);
                      }}
                      className="w-full rounded-lg px-2 py-2 text-left text-xs text-[#d7dde8] hover:bg-[#0D1424]"
                    >
                      {contract}
                    </button>
                  ))}
                </div>
              ) : null}
              <p className={`mt-2 text-2xl font-semibold transition ${pricePulse ? "drop-shadow-[0_0_12px_rgba(255,185,0,0.45)]" : ""}`}>
                {price.toFixed(4)}
              </p>
              <p className={`text-xs font-medium ${changePositive ? "text-[#18c06c]" : "text-[#ff5b6b]"}`}>
                {changePositive ? "+" : ""}
                {change24h.toFixed(2)}% (24h)
              </p>
              <p className="mt-1 text-[11px] text-[#9CA3AF]">
                Funding {fundingRate.toFixed(4)}% • {formatCountdown(countdown)}
              </p>
            </div>
            <div className="flex items-center gap-1">
              <IconButton>
                <Gift className="h-4 w-4" />
              </IconButton>
              <IconButton>
                <CandlestickChart className="h-4 w-4" />
              </IconButton>
              <IconButton>
                <ChartNoAxesCombined className="h-4 w-4" />
              </IconButton>
              <IconButton>
                <SlidersHorizontal className="h-4 w-4" />
              </IconButton>
            </div>
          </div>
        </header>

        <section className="px-4 pt-4">
          <div className="rounded-2xl border border-white/10 bg-[#111827] p-3">
            <div className="flex items-center justify-between text-xs">
              <button
                type="button"
                onClick={() => setShowChart((previous) => !previous)}
                className="rounded-lg border border-white/15 bg-[#0D1424] px-2 py-1 text-[#9CA3AF]"
              >
                {showChart ? "Hide Chart" : "Show Chart"}
              </button>
              <div className="flex items-center gap-1">
                {timeframes.map((frame) => (
                  <button
                    key={frame}
                    type="button"
                    onClick={() => setChartFrame(frame)}
                    className={`rounded-md px-2 py-1 ${
                      chartFrame === frame ? "bg-gradient-to-r from-[#6b2cff] to-[#ffb900] text-white" : "bg-[#0D1424] text-[#9CA3AF]"
                    }`}
                  >
                    {frame}
                  </button>
                ))}
              </div>
            </div>

            {showChart ? (
              <div className="mt-3 h-24 rounded-xl border border-white/10 bg-[#0D1424] p-2">
                <div className="flex h-full items-end gap-1">
                  {Array.from({ length: 24 }).map((_, index) => {
                    const height = 18 + ((index * 13 + bookTick * 5) % 72);
                    return <span key={index} className="w-full rounded-t bg-gradient-to-t from-[#6b2cff] to-[#ffb900]" style={{ height: `${height}%` }} />;
                  })}
                </div>
              </div>
            ) : null}
          </div>
        </section>

        <section className="grid gap-4 px-4 pt-4 lg:grid-cols-[1.12fr_0.88fr]">
          <div className="space-y-4">
            <div className="rounded-2xl border border-white/10 bg-[#111827] p-3">
              <div className="grid grid-cols-3 gap-2 text-xs">
                <Segment label="Cross" active={marginType === "Cross"} onClick={() => setMarginType((v) => (v === "Cross" ? "Isolated" : "Cross"))} />
                <button
                  type="button"
                  onClick={() => setLeverage((v) => (v >= 125 ? 1 : v + 1))}
                  className="rounded-lg border border-white/15 bg-[#0D1424] px-2 py-1 font-semibold text-[#d7dde8]"
                >
                  {leverage}x
                </button>
                <Segment label={positionMode} active onClick={() => setPositionMode((v) => (v === "One-way" ? "Hedge" : "One-way"))} />
              </div>

              <div className="relative mt-3 flex rounded-xl bg-[#0D1424] p-1">
                <span
                  className={`absolute bottom-1 top-1 w-[calc(50%-0.35rem)] rounded-lg transition-transform duration-300 ${
                    side === "buy" ? "bg-gradient-to-r from-[#18c06c] to-[#34d98f]" : "translate-x-full bg-gradient-to-r from-[#b43d48] to-[#ff5b6b]"
                  }`}
                />
                <button type="button" onClick={() => setSide("buy")} className="relative z-10 flex-1 py-2 text-sm font-semibold">
                  Buy / Long
                </button>
                <button type="button" onClick={() => setSide("sell")} className="relative z-10 flex-1 py-2 text-sm font-semibold">
                  Sell / Short
                </button>
              </div>

              <div className="mt-3 space-y-3">
                <Field label="Order Type">
                  <select
                    value={orderType}
                    onChange={(e) => setOrderType(e.target.value)}
                    className="w-full rounded-xl border border-white/15 bg-[#0D1424] px-3 py-2 text-sm outline-none focus:border-[#ffb900]"
                  >
                    {orderTypes.map((type) => (
                      <option key={type}>{type}</option>
                    ))}
                  </select>
                </Field>

                <Field label="Price">
                  <div className="flex gap-2">
                    <input
                      value={priceInput}
                      onChange={(e) => setPriceInput(e.target.value)}
                      disabled={orderType === "Market"}
                      className="w-full rounded-xl border border-white/15 bg-[#0D1424] px-3 py-2 text-sm outline-none disabled:opacity-65 focus:border-[#ffb900]"
                    />
                    <button
                      type="button"
                      onClick={() => setPriceInput(price.toFixed(4))}
                      className="rounded-xl border border-white/15 bg-[#0D1424] px-3 py-2 text-xs font-semibold text-[#9CA3AF]"
                    >
                      BBO
                    </button>
                  </div>
                </Field>

                <Field label="Amount">
                  <div className="flex gap-2">
                    <input
                      value={amount}
                      onChange={(e) => setAmount(e.target.value)}
                      placeholder="0.00"
                      className="w-full rounded-xl border border-white/15 bg-[#0D1424] px-3 py-2 text-sm outline-none focus:border-[#ffb900]"
                    />
                    <button
                      type="button"
                      onClick={() => setCurrency((v) => (v === "USDT" ? "XRP" : "USDT"))}
                      className="rounded-xl border border-white/15 bg-[#0D1424] px-3 py-2 text-xs font-semibold text-[#d7dde8]"
                    >
                      {currency}
                    </button>
                  </div>
                </Field>

                <div className="grid grid-cols-4 gap-2">
                  {percentSteps.map((step) => (
                    <button
                      key={step}
                      type="button"
                      onClick={() => applyPercent(step)}
                      className={`rounded-lg px-2 py-1 text-xs font-semibold ${
                        selectedPct === step
                          ? "bg-gradient-to-r from-[#6b2cff] to-[#ffb900] text-white"
                          : "border border-white/15 bg-[#0D1424] text-[#9CA3AF]"
                      }`}
                    >
                      {step}%
                    </button>
                  ))}
                </div>

                <div className="rounded-xl border border-white/10 bg-[#0D1424] p-3 text-xs text-[#9CA3AF]">
                  <p>Max Available: {availableBalance.toFixed(2)} USDT</p>
                  <p className="mt-1">Estimated Cost: {estimatedCost.toFixed(4)} USDT</p>
                  <p className="mt-1">Fee Preview: {feePreview.toFixed(4)} USDT</p>
                  <p className="mt-1 text-[#ffb900]">Liquidation Price: {liquidationPrice.toFixed(4)}</p>
                </div>
              </div>
            </div>

            <div className="rounded-2xl border border-white/10 bg-[#111827] p-3">
              <h3 className="text-sm font-semibold">TP/SL & Advanced</h3>
              <div className="mt-2 space-y-2 text-xs">
                <ToggleRow label="Take Profit / Stop Loss" checked={enableTpSl} onChange={setEnableTpSl} />
                {enableTpSl ? (
                  <div className="space-y-2 rounded-lg border border-white/10 bg-[#0D1424] p-2">
                    <Field label="Take Profit Price">
                      <input
                        value={tpPrice}
                        onChange={(e) => setTpPrice(e.target.value)}
                        placeholder="Set TP price"
                        className="w-full rounded-lg border border-white/15 bg-[#0B0F1A] px-2 py-2 text-xs outline-none focus:border-[#ffb900]"
                      />
                    </Field>
                    <Field label="Stop Loss Price">
                      <input
                        value={slPrice}
                        onChange={(e) => setSlPrice(e.target.value)}
                        placeholder="Set SL price"
                        className="w-full rounded-lg border border-white/15 bg-[#0B0F1A] px-2 py-2 text-xs outline-none focus:border-[#ffb900]"
                      />
                    </Field>
                  </div>
                ) : null}
                <ToggleRow label="Reduce Only" checked={reduceOnly} onChange={setReduceOnly} />
                <div className="flex items-center justify-between rounded-lg border border-white/10 bg-[#0D1424] px-2 py-2">
                  <span className="text-[#9CA3AF]">Time in Force</span>
                  <button
                    type="button"
                    onClick={() => setTimeInForce((v) => (v === "GTC" ? "IOC" : "GTC"))}
                    className="inline-flex items-center gap-1 text-[#d7dde8]"
                  >
                    {timeInForce}
                    <ChevronDown className="h-3.5 w-3.5 text-[#9CA3AF]" />
                  </button>
                </div>
              </div>
            </div>

            <div className="rounded-2xl border border-white/10 bg-[#111827] p-3">
              <h3 className="text-sm font-semibold">Open Position</h3>
              <div className="mt-2 grid grid-cols-2 gap-2 text-xs">
                <InfoCell label="Entry Price" value="1.3400" />
                <InfoCell label="Mark Price" value={price.toFixed(4)} />
                <InfoCell label="Unrealized PNL" value="+48.29 USDT" positive={side === "buy"} negative={side === "sell"} />
                <InfoCell label="Margin Used" value="138.44 USDT" />
                <InfoCell label="Liquidation" value={liquidationPrice.toFixed(4)} />
                <button type="button" className="rounded-lg border border-[#ff5b6b]/35 bg-[#ff5b6b]/15 px-2 py-1.5 font-semibold text-[#ff9aa8]">
                  Close Position
                </button>
              </div>
            </div>
          </div>

          <div className="space-y-4">
            <div className="rounded-2xl border border-white/10 bg-[#111827] p-3">
              <h3 className="text-sm font-semibold">Order Book</h3>
              <div className="mt-2 space-y-1">
                {askOrders.map((row, idx) => (
                  <OrderRow key={`ask-${idx}`} row={row} side="sell" />
                ))}
              </div>
              <div className="my-2 rounded-lg border border-[#ffb900]/30 bg-[#ffb900]/10 px-2 py-1 text-center text-sm font-semibold text-[#ffde7a]">
                {price.toFixed(4)} Mark
              </div>
              <div className="space-y-1">
                {bidOrders.map((row, idx) => (
                  <OrderRow key={`bid-${idx}`} row={row} side="buy" />
                ))}
              </div>
              <div className="mt-3">
                <div className="mb-1 flex items-center justify-between text-[11px] text-[#9CA3AF]">
                  <span>Buy {dominance}%</span>
                  <span>Sell {100 - dominance}%</span>
                </div>
                <div className="h-2 overflow-hidden rounded-full bg-[#0D1424]">
                  <div className="h-full bg-gradient-to-r from-[#18c06c] to-[#34d98f]" style={{ width: `${dominance}%` }} />
                </div>
                <button type="button" className="mt-2 w-full rounded-lg border border-white/15 bg-[#0D1424] py-1.5 text-xs text-[#9CA3AF]">
                  Depth Chart Preview
                </button>
              </div>
            </div>

            <div className="rounded-2xl border border-[#ffb900]/30 bg-[#ffb900]/10 p-3 text-xs text-[#fce6a7]">
              <p className="flex items-start gap-2">
                <ShieldAlert className="mt-0.5 h-4 w-4 text-[#ffb900]" />
                Futures trading involves leverage risk. Ensure you understand margin and liquidation before opening a position.
              </p>
            </div>
          </div>
        </section>
      </div>

      <div className="fixed inset-x-0 bottom-0 border-t border-white/10 bg-[#0B0F1A]/95 p-3 backdrop-blur">
        <div className="mx-auto w-full max-w-md sm:max-w-2xl lg:max-w-5xl">
          <button
            type="button"
            className={`w-full rounded-xl py-3 text-sm font-semibold shadow-[0_0_24px_rgba(255,185,0,0.25)] transition active:scale-[0.99] ${
              side === "buy" ? "bg-gradient-to-r from-[#18c06c] to-[#34d98f] text-[#0B0F1A]" : "bg-gradient-to-r from-[#b43d48] to-[#ff5b6b] text-white"
            }`}
          >
            {side === "buy" ? "Open Long Position" : "Open Short Position"}
          </button>
          <button type="button" onClick={onBack} className="mt-2 w-full rounded-lg border border-white/15 bg-[#111827] py-2 text-xs text-[#9CA3AF]">
            Back to Home
          </button>
        </div>
      </div>
    </main>
  );
}

function IconButton({ children }) {
  return <button type="button" className="rounded-lg border border-white/15 bg-[#111827] p-2 text-[#9CA3AF]">{children}</button>;
}

function Segment({ label, active, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-lg px-2 py-1 font-semibold ${
        active ? "bg-gradient-to-r from-[#6b2cff] to-[#ffb900] text-white shadow-[0_0_14px_rgba(255,185,0,0.25)]" : "border border-white/15 bg-[#0D1424] text-[#9CA3AF]"
      }`}
    >
      {label}
    </button>
  );
}

function Field({ label, children }) {
  return (
    <label className="block">
      <span className="mb-1 block text-xs text-[#9CA3AF]">{label}</span>
      {children}
    </label>
  );
}

function ToggleRow({ label, checked, onChange }) {
  return (
    <label className="flex items-center justify-between rounded-lg border border-white/10 bg-[#0D1424] px-2 py-2">
      <span className="text-[#9CA3AF]">{label}</span>
      <button
        type="button"
        onClick={() => onChange(!checked)}
        className={`relative h-5 w-10 rounded-full transition ${checked ? "bg-gradient-to-r from-[#6b2cff] to-[#ffb900]" : "bg-[#1f2937]"}`}
      >
        <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition ${checked ? "left-5" : "left-0.5"}`} />
      </button>
    </label>
  );
}

function InfoCell({ label, value, positive, negative }) {
  return (
    <div className="rounded-lg border border-white/10 bg-[#0D1424] px-2 py-1.5">
      <p className="text-[11px] text-[#9CA3AF]">{label}</p>
      <p className={`mt-0.5 text-xs font-semibold ${positive ? "text-[#18c06c]" : negative ? "text-[#ff5b6b]" : "text-white"}`}>{value}</p>
    </div>
  );
}

function OrderRow({ row, side }) {
  const tone = side === "buy" ? "text-[#18c06c]" : "text-[#ff7b89]";
  const barTone = side === "buy" ? "bg-[#18c06c]/18" : "bg-[#ff5b6b]/18";
  return (
    <div className="relative overflow-hidden rounded-md border border-white/5 px-2 py-1 text-xs">
      <span className={`pointer-events-none absolute inset-y-0 right-0 ${barTone}`} style={{ width: `${Math.min(100, row.depth)}%` }} />
      <div className="relative z-10 flex justify-between">
        <span className={tone}>{row.price.toFixed(4)}</span>
        <span className="text-[#9CA3AF]">{row.amount.toFixed(2)}</span>
      </div>
    </div>
  );
}

function generateOrders(startPrice, isBid, tick) {
  return Array.from({ length: 8 }).map((_, index) => {
    const step = 0.0002 * (index + 1);
    const price = isBid ? startPrice - step : startPrice + step;
    const amount = 180 + (((index + 3) * 43 + tick * 11) % 420) + Math.random() * 5;
    return {
      price: Number(price.toFixed(4)),
      amount,
      depth: 16 + ((index * 14 + tick * 4) % 74),
    };
  });
}

function formatCountdown(seconds) {
  const min = Math.floor(seconds / 60);
  const sec = seconds % 60;
  return `${String(min).padStart(2, "0")}:${String(sec).padStart(2, "0")}`;
}

export default Futures;
