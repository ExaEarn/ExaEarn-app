import React, { useEffect, useMemo, useState } from "react";
import Decimal from "decimal.js";
import {
  ArrowLeft,
  AreaChart,
  CandlestickChart,
  ChartNoAxesCombined,
  ChevronDown,
  MoreHorizontal,
  RefreshCcw,
  Search,
  ShieldAlert,
  SlidersHorizontal,
  Star,
  Wallet,
  X,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import TradingChart from '../../components/market/TradingChart';

const PRODUCT_TABS = ["Convert", "Spot", "Futures", "Options", "TradFi"];
const MOBILE_MODES = ["trade", "chart"];
const ACCOUNT_TABS = ["positions", "openOrders", "assets", "tools"];
const TIMEFRAMES = ["1m", "3m", "5m", "15m", "30m", "1h", "4h", "1d"];
const PERCENTAGES = [25, 50, 75, 100];
const PUBLIC_FUTURES_URL = "https://fapi.binance.com";
const DEFAULT_SYMBOLS = ["BTCUSDT", "ETHUSDT", "SOLUSDT", "XRPUSDT", "BNBUSDT", "ADAUSDT"];
const FAVORITES_KEY = "exaearn_futures_favorites";

const decimal = (value) => {
  try {
    return new Decimal(value || 0);
  } catch {
    return new Decimal(0);
  }
};

const toNumber = (value, fallback = 0) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const readFavorites = () => {
  try {
    const raw = localStorage.getItem(FAVORITES_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
};

const formatPrice = (value) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return "--";
  if (Math.abs(number) >= 1000) return number.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (Math.abs(number) >= 1) return number.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 });
  return number.toLocaleString(undefined, { minimumFractionDigits: 4, maximumFractionDigits: 6 });
};

const formatCompact = (value) => new Intl.NumberFormat(undefined, { notation: "compact", maximumFractionDigits: 2 }).format(Number(value || 0));
const formatPct = (value) => `${Number(value || 0) >= 0 ? "+" : ""}${Number(value || 0).toFixed(2)}%`;
const formatCountdown = (timestamp) => {
  if (!timestamp) return "--:--:--";
  const diff = Math.max(0, Math.floor((timestamp - Date.now()) / 1000));
  const hours = String(Math.floor(diff / 3600)).padStart(2, "0");
  const minutes = String(Math.floor((diff % 3600) / 60)).padStart(2, "0");
  const seconds = String(diff % 60).padStart(2, "0");
  return `${hours}:${minutes}:${seconds}`;
};

const fetchPublic = async (path) => {
  const response = await fetch(`${PUBLIC_FUTURES_URL}${path}`, { method: "GET", headers: { Accept: "application/json" } });
  if (!response.ok) throw new Error(`Public futures data request failed (${response.status})`);
  return response.json();
};

const normalizeMarket = (item) => ({
  id: item.id,
  symbol: String(item.symbol || "").toUpperCase(),
  displaySymbol: `${String(item.symbol || "").toUpperCase()} PERP`,
  status: item.status || "active",
  last_price: item.last_price || item.lastPrice || 0,
  price_change_percent: item.price_change_percent || item.priceChangePercent || 0,
  quote_volume: item.quote_volume || item.quoteVolume || 0,
  min_leverage: Number(item.min_leverage || 1),
  max_leverage: Number(item.max_leverage || 100),
  maintenance_margin_rate: item.maintenance_margin_rate || 0.005,
});

function Futures({ onBack, onOpenConvert, onOpenSpot, onOpenOptions, onOpenTradFi, onOpenSmart }) {
  const { request, user } = useAuth();
  const [markets, setMarkets] = useState([]);
  const [selectedSymbol, setSelectedSymbol] = useState("BTCUSDT");
  const [marketSearch, setMarketSearch] = useState("");
  const [showMarketSelector, setShowMarketSelector] = useState(false);
  const [mobileMode, setMobileMode] = useState("trade");
  const [accountTab, setAccountTab] = useState("positions");
  const [chartType, setChartType] = useState("candles");
  const [timeframe, setTimeframe] = useState("15m");
  const [favorites, setFavorites] = useState(readFavorites);
  const [ticker, setTicker] = useState(null);
  const [premium, setPremium] = useState(null);
  const [orderBook, setOrderBook] = useState({ bids: [], asks: [] });
  const [recentTrades, setRecentTrades] = useState([]);
  const [candles, setCandles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadingBook, setLoadingBook] = useState(true);
  const [error, setError] = useState("");
  const [marginMode, setMarginMode] = useState("cross");
  const [showMarginMenu, setShowMarginMenu] = useState(false);
  const [leverage, setLeverage] = useState(20);
  const [showLeverage, setShowLeverage] = useState(false);
  const [orderType, setOrderType] = useState("market");
  const [side, setSide] = useState("long");
  const [quantity, setQuantity] = useState("");
  const [priceInput, setPriceInput] = useState("");
  const [stopPrice, setStopPrice] = useState("");
  const [tpEnabled, setTpEnabled] = useState(false);
  const [tpPrice, setTpPrice] = useState("");
  const [slPrice, setSlPrice] = useState("");
  const [selectedPct, setSelectedPct] = useState(0);
  const [marginStatus, setMarginStatus] = useState(null);
  const [positions, setPositions] = useState([]);
  const [openOrders, setOpenOrders] = useState([]);
  const [validation, setValidation] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [notice, setNotice] = useState("");
  const [formError, setFormError] = useState("");
  const [chartRefreshToken, setChartRefreshToken] = useState(0);

  const selectedMarket = useMemo(() => markets.find((item) => item.symbol === selectedSymbol) || null, [markets, selectedSymbol]);
  const activePrice = toNumber(ticker?.lastPrice ?? premium?.markPrice ?? selectedMarket?.last_price, 0);
  const pricePrecision = activePrice >= 1000 ? 2 : activePrice >= 1 ? 3 : 5;
  const availableMargin = decimal(marginStatus?.available_margin);
  const quantityDecimal = decimal(quantity);
  const summaryData = validation?.data?.data || null;
  const filteredMarkets = useMemo(() => {
    const term = marketSearch.trim().toUpperCase();
    return markets.filter((market) => !term || market.symbol.includes(term));
  }, [marketSearch, markets]);
  const currentPosition = useMemo(() => positions.find((position) => String(position.symbol).toUpperCase() === selectedSymbol && position.status === "open") || null, [positions, selectedSymbol]);

  useEffect(() => {
    localStorage.setItem(FAVORITES_KEY, JSON.stringify(favorites));
  }, [favorites]);

  useEffect(() => {
    let cancelled = false;
    const loadMarkets = async () => {
      setLoading(true);
      try {
        const payload = await request("/api/futures/markets", { method: "GET" });
        const publicTickers = await fetchPublic("/fapi/v1/ticker/24hr").catch(() => []);
        const tickerMap = new Map((Array.isArray(publicTickers) ? publicTickers : []).map((item) => [String(item.symbol || "").toUpperCase(), item]));
        const backendMarkets = (Array.isArray(payload?.data) ? payload.data : []).map((item) => normalizeMarket({ ...item, ...(tickerMap.get(String(item.symbol || "").toUpperCase()) || {}) }));
        const fallbackMarkets = backendMarkets.length
          ? backendMarkets
          : DEFAULT_SYMBOLS.map((symbol) => normalizeMarket({ symbol, ...(tickerMap.get(symbol) || {}), status: "active", min_leverage: 1, max_leverage: 100, last_price: 0 }));
        if (!cancelled) {
          setMarkets(fallbackMarkets);
          if (!fallbackMarkets.some((item) => item.symbol === selectedSymbol) && fallbackMarkets[0]) setSelectedSymbol(fallbackMarkets[0].symbol);
          setError("");
        }
      } catch (nextError) {
        if (!cancelled) {
          setMarkets(DEFAULT_SYMBOLS.map((symbol) => normalizeMarket({ symbol, status: "active", min_leverage: 1, max_leverage: 100, last_price: 0 })));
          setError(cleanError(nextError?.message || "Unable to load futures markets"));
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    loadMarkets();
    return () => { cancelled = true; };
  }, [request, selectedSymbol]);

  useEffect(() => {
    if (!selectedMarket) return;
    setLeverage((current) => Math.min(Math.max(current, selectedMarket.min_leverage), selectedMarket.max_leverage));
  }, [selectedMarket]);

  useEffect(() => {
    let cancelled = false;
    const loadPublicMarket = async () => {
      setLoadingBook(true);
      try {
        const [tickerPayload, premiumPayload, depthPayload, tradesPayload, candlesPayload] = await Promise.all([
          fetchPublic(`/fapi/v1/ticker/24hr?symbol=${selectedSymbol}`),
          fetchPublic(`/fapi/v1/premiumIndex?symbol=${selectedSymbol}`),
          fetchPublic(`/fapi/v1/depth?symbol=${selectedSymbol}&limit=20`),
          fetchPublic(`/fapi/v1/trades?symbol=${selectedSymbol}&limit=24`),
          fetchPublic(`/fapi/v1/klines?symbol=${selectedSymbol}&interval=${timeframe}&limit=240`),
        ]);

        if (cancelled) return;
        setTicker(tickerPayload);
        setPremium(premiumPayload);
        setOrderBook({ bids: Array.isArray(depthPayload?.bids) ? depthPayload.bids : [], asks: Array.isArray(depthPayload?.asks) ? depthPayload.asks : [] });
        setRecentTrades(Array.isArray(tradesPayload) ? tradesPayload : []);
        setCandles((Array.isArray(candlesPayload) ? candlesPayload : []).map((row) => ({ time: Math.floor(Number(row[0]) / 1000), open: toNumber(row[1]), high: toNumber(row[2]), low: toNumber(row[3]), close: toNumber(row[4]), volume: toNumber(row[5]) })));
        setError("");
      } catch (nextError) {
        if (!cancelled) setError(cleanError(nextError?.message || "Unable to load futures market data"));
      } finally {
        if (!cancelled) setLoadingBook(false);
      }
    };

    loadPublicMarket();
    const timer = window.setInterval(loadPublicMarket, 7000);
    return () => { cancelled = true; window.clearInterval(timer); };
  }, [selectedSymbol, timeframe, chartRefreshToken]);

  useEffect(() => {
    if (!user) {
      setMarginStatus(null);
      setPositions([]);
      setOpenOrders([]);
      return;
    }

    let cancelled = false;
    const loadPrivate = async () => {
      try {
        const [marginPayload, positionsPayload, ordersPayload] = await Promise.all([
          request("/api/futures/margin/status", { method: "GET" }),
          request(`/api/futures/positions?symbol=${selectedSymbol}&per_page=20`, { method: "GET" }),
          request(`/api/futures/orders/open?symbol=${selectedSymbol}&per_page=20`, { method: "GET" }),
        ]);
        if (cancelled) return;
        setMarginStatus(marginPayload?.data || null);
        setPositions(Array.isArray(positionsPayload?.data?.data) ? positionsPayload.data.data : Array.isArray(positionsPayload?.data) ? positionsPayload.data : []);
        setOpenOrders(Array.isArray(ordersPayload?.data?.data) ? ordersPayload.data.data : Array.isArray(ordersPayload?.data) ? ordersPayload.data : []);
      } catch {
        if (!cancelled) {
          setMarginStatus(null);
          setPositions([]);
          setOpenOrders([]);
        }
      }
    };

    loadPrivate();
    const timer = window.setInterval(loadPrivate, 9000);
    return () => { cancelled = true; window.clearInterval(timer); };
  }, [request, selectedSymbol, user]);

  useEffect(() => {
    if (!user || !quantity || quantityDecimal.lte(0) || !selectedMarket) {
      setValidation(null);
      return;
    }

    const timer = window.setTimeout(async () => {
      try {
        const payload = await request("/api/futures/orders/validate", {
          method: "POST",
          body: JSON.stringify({
            symbol: selectedSymbol,
            type: orderType,
            side,
            quantity,
            leverage,
            price: orderType === "market" ? null : priceInput || activePrice,
            stop_price: orderType.includes("stop") ? stopPrice || null : null,
          }),
        });
        setValidation(payload);
      } catch (nextError) {
        setValidation({ data: { can_place: false, errors: [cleanError(nextError?.message || "Unable to validate order")], data: null } });
      }
    }, 350);

    return () => window.clearTimeout(timer);
  }, [request, user, quantity, selectedSymbol, orderType, side, leverage, priceInput, stopPrice, activePrice, selectedMarket, quantityDecimal]);


  useEffect(() => {
    if (orderType === "market") setPriceInput("");
    else if (!priceInput && activePrice) setPriceInput(activePrice.toFixed(pricePrecision));
  }, [orderType, activePrice, pricePrecision, priceInput]);

  const applyPercentage = (percent) => {
    setSelectedPct(percent);
    const price = decimal(orderType === "market" ? activePrice : priceInput || activePrice);
    if (price.lte(0) || availableMargin.lte(0)) return;
    const usableNotional = availableMargin.mul(leverage).mul(percent).div(100).mul(0.985);
    setQuantity(usableNotional.div(price).toDecimalPlaces(6, Decimal.ROUND_DOWN).toString());
  };

  const handleSubmit = async () => {
    setFormError("");
    setNotice("");
    if (!user) return setFormError("Authentication required");
    if (!quantity || quantityDecimal.lte(0)) return setFormError("Enter a valid quantity");
    if (validation?.data?.can_place === false) return setFormError(validation.data.errors?.[0] || "Order is not valid");

    setSubmitting(true);
    try {
      await request("/api/futures/orders", {
        method: "POST",
        body: JSON.stringify({
          symbol: selectedSymbol,
          type: orderType,
          side,
          quantity,
          leverage,
          price: orderType === "market" ? null : priceInput || activePrice,
          stop_price: orderType.includes("stop") ? stopPrice || null : null,
          metadata: { source: "web_futures_terminal", margin_type: marginMode },
        }),
      });

      let conditionalCreated = 0;
      const refreshedPositionsPayload = await request(`/api/futures/positions?symbol=${selectedSymbol}&per_page=20`, { method: "GET" }).catch(() => null);
      const refreshedPositions = Array.isArray(refreshedPositionsPayload?.data?.data) ? refreshedPositionsPayload.data.data : [];
      setPositions(refreshedPositions);
      const targetPosition = refreshedPositions.find((position) => String(position.symbol).toUpperCase() === selectedSymbol && position.status === "open");
      if (tpEnabled && targetPosition) {
        if (tpPrice) {
          await request("/api/futures/conditional-orders", { method: "POST", body: JSON.stringify({ symbol: selectedSymbol, type: "take_profit", trigger_order_type: "market", trigger_price: tpPrice, quantity, position_id: targetPosition.id }) }).catch(() => null);
          conditionalCreated += 1;
        }
        if (slPrice) {
          await request("/api/futures/conditional-orders", { method: "POST", body: JSON.stringify({ symbol: selectedSymbol, type: "stop_loss", trigger_order_type: "market", trigger_price: slPrice, quantity, position_id: targetPosition.id }) }).catch(() => null);
          conditionalCreated += 1;
        }
      }

      const [marginPayload, openOrdersPayload] = await Promise.all([
        request("/api/futures/margin/status", { method: "GET" }).catch(() => null),
        request(`/api/futures/orders/open?symbol=${selectedSymbol}&per_page=20`, { method: "GET" }).catch(() => null),
      ]);
      setMarginStatus(marginPayload?.data || null);
      setOpenOrders(Array.isArray(openOrdersPayload?.data?.data) ? openOrdersPayload.data.data : []);
      setQuantity("");
      setStopPrice("");
      setSelectedPct(0);
      setNotice(conditionalCreated ? "Futures order submitted. TP/SL was also created where a position was available." : "Futures order submitted.");
    } catch (nextError) {
      setFormError(cleanError(nextError?.message || "Unable to submit futures order"));
    } finally {
      setSubmitting(false);
    }
  };

  const handleCancelOrder = async (orderUuid) => {
    try {
      await request(`/api/futures/orders/${orderUuid}`, { method: "DELETE" });
      const payload = await request(`/api/futures/orders/open?symbol=${selectedSymbol}&per_page=20`, { method: "GET" });
      setOpenOrders(Array.isArray(payload?.data?.data) ? payload.data.data : []);
      setNotice("Order cancelled");
    } catch (nextError) {
      setNotice(cleanError(nextError?.message || "Unable to cancel order"));
    }
  };

  const handleMarginModeChange = async (nextMode) => {
    setMarginMode(nextMode);
    setShowMarginMenu(false);
    if (!currentPosition) return;
    try {
      await request("/api/futures/positions/margin-type", { method: "POST", body: JSON.stringify({ position_id: currentPosition.id, margin_type: nextMode }) });
      const positionsPayload = await request(`/api/futures/positions?symbol=${selectedSymbol}&per_page=20`, { method: "GET" });
      setPositions(Array.isArray(positionsPayload?.data?.data) ? positionsPayload.data.data : []);
      setNotice(`Margin mode updated to ${nextMode}`);
    } catch {
      // keep local preference when no open position exists yet
    }
  };

  return (
    <main className="min-h-[100dvh] overflow-x-hidden bg-[#04070d] text-white">
      <div className="mx-auto flex w-full max-w-[1800px] flex-col gap-3 px-2 pb-[calc(env(safe-area-inset-bottom)+12px)] pt-[calc(env(safe-area-inset-top)+8px)] sm:px-3 lg:px-4">
        <ProductNav onOpenConvert={onOpenConvert} onOpenSpot={onOpenSpot} onOpenOptions={onOpenOptions} onOpenTradFi={onOpenTradFi ?? onOpenSmart} />

        <section className="rounded-2xl border border-white/8 bg-[#070d16] px-3 py-2.5">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <button type="button" onClick={onBack} className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/[0.04] text-slate-300"><ArrowLeft className="h-4 w-4" /></button>
                <button type="button" onClick={() => setShowMarketSelector(true)} className="inline-flex min-w-0 items-center gap-1 rounded-xl bg-white/[0.04] px-3 py-2 text-left"><span className="truncate text-sm font-semibold">{selectedSymbol} PERP</span><ChevronDown className="h-4 w-4 text-slate-400" /></button>
                <button type="button" onClick={() => setFavorites((current) => current.includes(selectedSymbol) ? current.filter((item) => item !== selectedSymbol) : [...current, selectedSymbol])} className={`inline-flex h-9 w-9 items-center justify-center rounded-xl ${favorites.includes(selectedSymbol) ? "bg-amber-400/12 text-amber-300" : "bg-white/[0.04] text-slate-400"}`}><Star className={`h-4 w-4 ${favorites.includes(selectedSymbol) ? "fill-current" : ""}`} /></button>
              </div>
              <div className="mt-2 flex flex-wrap items-end gap-x-4 gap-y-2">
                <div>
                  <div className="font-mono text-2xl font-semibold">{activePrice ? formatPrice(activePrice) : "--"}</div>
                  <div className={`text-xs font-medium ${toNumber(ticker?.priceChangePercent) >= 0 ? "text-emerald-400" : "text-rose-400"}`}>{formatPct(ticker?.priceChangePercent)}</div>
                </div>
                <div className="grid min-w-0 grid-cols-2 gap-x-4 gap-y-1 text-[11px] text-slate-400 sm:grid-cols-4">
                  <div>Mark <span className="ml-1 font-mono text-slate-100">{formatPrice(premium?.markPrice)}</span></div>
                  <div>Index <span className="ml-1 font-mono text-slate-100">{formatPrice(premium?.indexPrice)}</span></div>
                  <div>Funding <span className="ml-1 font-mono text-slate-100">{premium?.lastFundingRate ? `${(toNumber(premium.lastFundingRate) * 100).toFixed(4)}%` : "--"}</span></div>
                  <div>Next <span className="ml-1 font-mono text-slate-100">{formatCountdown(toNumber(premium?.nextFundingTime))}</span></div>
                </div>
              </div>
            </div>
            <div className="flex items-center gap-1">
              {MOBILE_MODES.map((mode) => <button key={mode} type="button" onClick={() => setMobileMode(mode)} className={`rounded-xl px-3 py-2 text-xs font-medium ${mobileMode === mode ? "bg-amber-400 text-slate-950" : "bg-white/[0.04] text-slate-300"}`}>{mode === "chart" ? "Chart" : "Book"}</button>)}
              <button type="button" className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/[0.04] text-slate-400"><MoreHorizontal className="h-4 w-4" /></button>
            </div>
          </div>
        </section>

        {error ? <div className="rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-xs text-rose-200">{error}</div> : null}
        {notice ? <div className="rounded-xl border border-amber-400/20 bg-amber-400/10 px-3 py-2 text-xs text-amber-100">{notice}</div> : null}

        <div className="hidden gap-3 xl:grid xl:grid-cols-[240px_minmax(0,1fr)_330px]">
          <MarketSidebar markets={filteredMarkets} selectedSymbol={selectedSymbol} onSelect={setSelectedSymbol} favorites={favorites} onSearch={setMarketSearch} search={marketSearch} />
          <div className="min-w-0 space-y-3">
            <ChartPanel candles={candles} loading={loadingBook} error={!loadingBook && candles.length === 0 ? error : ""} timeframe={timeframe} onTimeframe={setTimeframe} chartType={chartType} onChartType={setChartType} premium={premium} onRefresh={() => setChartRefreshToken((current) => current + 1)} />
            <AccountTabs tab={accountTab} onTab={setAccountTab} positions={positions} openOrders={openOrders} marginStatus={marginStatus} onCancelOrder={handleCancelOrder} onClosePosition={(position) => { setSide(position.side === "long" ? "short" : "long"); setQuantity(String(position.quantity)); setMobileMode("trade"); }} />
          </div>
          <div className="min-w-0 space-y-3">
            <OrderEntryPanel user={user} market={selectedMarket} side={side} onSide={setSide} orderType={orderType} onOrderType={setOrderType} marginMode={marginMode} showMarginMenu={showMarginMenu} onToggleMarginMenu={() => setShowMarginMenu((current) => !current)} onMarginMode={handleMarginModeChange} leverage={leverage} showLeverage={showLeverage} onToggleLeverage={() => setShowLeverage((current) => !current)} onLeverage={setLeverage} availableMargin={availableMargin} quantity={quantity} onQuantity={setQuantity} priceInput={priceInput} onPrice={setPriceInput} stopPrice={stopPrice} onStopPrice={setStopPrice} selectedPct={selectedPct} onApplyPercentage={applyPercentage} activePrice={activePrice} summaryData={summaryData} tpEnabled={tpEnabled} onTpEnabled={setTpEnabled} tpPrice={tpPrice} onTpPrice={setTpPrice} slPrice={slPrice} onSlPrice={setSlPrice} submitting={submitting} onSubmit={handleSubmit} formError={formError} />
            <OrderBookPanel symbol={selectedSymbol} orderBook={orderBook} recentTrades={recentTrades} activePrice={activePrice} premium={premium} loading={loadingBook} onSelectPrice={(value) => { setOrderType("limit"); setPriceInput(String(value)); }} />
          </div>
        </div>

        <div className="space-y-3 xl:hidden">
          {mobileMode === "chart" ? <ChartPanel candles={candles} loading={loadingBook} error={!loadingBook && candles.length === 0 ? error : ""} timeframe={timeframe} onTimeframe={setTimeframe} chartType={chartType} onChartType={setChartType} premium={premium} onRefresh={() => setChartRefreshToken((current) => current + 1)} mobile /> : <div className="grid min-w-0 grid-cols-[minmax(0,1.18fr)_minmax(104px,0.82fr)] gap-2 overflow-hidden rounded-2xl border border-white/8 bg-[#070d16] p-2 max-[360px]:grid-cols-[minmax(0,1.1fr)_minmax(98px,0.9fr)]"><OrderEntryPanel user={user} market={selectedMarket} side={side} onSide={setSide} orderType={orderType} onOrderType={setOrderType} marginMode={marginMode} showMarginMenu={showMarginMenu} onToggleMarginMenu={() => setShowMarginMenu((current) => !current)} onMarginMode={handleMarginModeChange} leverage={leverage} showLeverage={showLeverage} onToggleLeverage={() => setShowLeverage((current) => !current)} onLeverage={setLeverage} availableMargin={availableMargin} quantity={quantity} onQuantity={setQuantity} priceInput={priceInput} onPrice={setPriceInput} stopPrice={stopPrice} onStopPrice={setStopPrice} selectedPct={selectedPct} onApplyPercentage={applyPercentage} activePrice={activePrice} summaryData={summaryData} tpEnabled={tpEnabled} onTpEnabled={setTpEnabled} tpPrice={tpPrice} onTpPrice={setTpPrice} slPrice={slPrice} onSlPrice={setSlPrice} submitting={submitting} onSubmit={handleSubmit} formError={formError} compact /><OrderBookPanel symbol={selectedSymbol} orderBook={orderBook} recentTrades={recentTrades} activePrice={activePrice} premium={premium} loading={loadingBook} onSelectPrice={(value) => { setOrderType("limit"); setPriceInput(String(value)); }} compact /></div>}
          <AccountTabs tab={accountTab} onTab={setAccountTab} positions={positions} openOrders={openOrders} marginStatus={marginStatus} onCancelOrder={handleCancelOrder} onClosePosition={(position) => { setSide(position.side === "long" ? "short" : "long"); setQuantity(String(position.quantity)); setMobileMode("trade"); }} />
        </div>
      </div>

      {showMarketSelector ? <MarketSelectorSheet markets={filteredMarkets} favorites={favorites} selectedSymbol={selectedSymbol} search={marketSearch} onSearch={setMarketSearch} onClose={() => setShowMarketSelector(false)} onSelect={(symbol) => { setSelectedSymbol(symbol); setShowMarketSelector(false); }} /> : null}
    </main>
  );
}

function ProductNav({ onOpenConvert, onOpenSpot, onOpenOptions, onOpenTradFi }) { return <nav className="rounded-xl border border-white/8 bg-[#070d16]/95 px-2 py-1" aria-label="Trading products"><div className="flex min-w-0 items-center gap-1 overflow-x-auto no-scrollbar">{PRODUCT_TABS.map((tab) => { const active = tab === "Futures"; const handler = tab === "Convert" ? onOpenConvert : tab === "Spot" ? onOpenSpot : tab === "Options" ? onOpenOptions : tab === "TradFi" ? onOpenTradFi : undefined; return <button key={tab} type="button" onClick={handler} className={`relative shrink-0 rounded-lg px-2.5 py-1.5 text-[12px] font-semibold tracking-wide transition sm:px-3 ${active ? "bg-[#d1ab55] text-slate-950 shadow-[0_8px_22px_rgba(209,171,85,.18)]" : "text-slate-400 hover:bg-white/[0.04] hover:text-white"}`}>{tab}</button>; })}</div></nav>; }
function MarketSidebar({ markets, selectedSymbol, onSelect, favorites, search, onSearch }) {
  return <div className="min-w-0 rounded-2xl border border-white/8 bg-[#070d16] p-2.5"><div className="text-xs uppercase tracking-[0.18em] text-slate-500">Markets</div><div className="mt-2 rounded-xl bg-white/[0.04] px-3 py-2"><div className="flex items-center gap-2"><Search className="h-4 w-4 text-slate-500" /><input value={search} onChange={(event) => onSearch(event.target.value)} placeholder="Search futures" className="w-full bg-transparent text-sm text-white outline-none placeholder:text-slate-500" /></div></div><div className="mt-2 grid grid-cols-[minmax(0,1fr)_auto] px-2 text-[10px] uppercase tracking-[0.16em] text-slate-500"><span>Contract</span><span className="text-right">Last / 24h</span></div><div className="mt-2 space-y-1 overflow-y-auto">{markets.map((market) => <button key={market.symbol} type="button" onClick={() => onSelect(market.symbol)} className={`w-full rounded-xl border px-3 py-2 text-left ${selectedSymbol === market.symbol ? "border-amber-400/30 bg-amber-400/8" : "border-white/8 bg-white/[0.02] hover:bg-white/[0.04]"}`}><div className="flex items-center justify-between gap-3"><div className="flex min-w-0 items-center gap-2"><Star className={`h-3.5 w-3.5 ${favorites.includes(market.symbol) ? "fill-current text-amber-300" : "text-slate-500"}`} /><div className="min-w-0"><div className="truncate text-sm font-semibold">{market.symbol}</div><div className="text-[11px] text-slate-500">Perpetual</div></div></div><div className="text-right"><div className="font-mono text-xs text-slate-100">{formatPrice(market.last_price)}</div><div className={`text-[11px] ${toNumber(market.price_change_percent) >= 0 ? "text-emerald-400" : "text-rose-400"}`}>{formatPct(market.price_change_percent)}</div></div></div></button>)}</div></div>;
}
function MarketSelectorSheet({ markets, favorites, selectedSymbol, search, onSearch, onClose, onSelect }) {
  return <div className="fixed inset-0 z-50 flex items-end bg-black/70 p-0 lg:items-start lg:justify-center lg:p-6" onClick={onClose}><div className="w-full max-h-[88dvh] rounded-t-[20px] border border-white/10 bg-[#050b14] p-3 lg:mt-12 lg:max-w-[920px] lg:rounded-[24px] lg:p-5" onClick={(event) => event.stopPropagation()}><div className="flex items-center justify-between gap-3"><div><div className="text-xs uppercase tracking-[0.18em] text-slate-500">Futures markets</div><div className="text-base font-semibold lg:text-lg">Select contract</div></div><button type="button" onClick={onClose} className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/[0.04] text-slate-300"><X className="h-4 w-4" /></button></div><div className="mt-3 rounded-xl bg-white/[0.04] px-3 py-2"><div className="flex items-center gap-2"><Search className="h-4 w-4 text-slate-500" /><input value={search} onChange={(event) => onSearch(event.target.value)} placeholder="Search futures..." className="w-full bg-transparent text-sm text-white outline-none placeholder:text-slate-500" /></div></div><div className="mt-3 grid grid-cols-[minmax(0,1.25fr)_minmax(0,0.95fr)_auto_auto] gap-3 px-2 text-[10px] uppercase tracking-[0.16em] text-slate-500"><span>Contract</span><span className="text-right">Last Price</span><span className="text-right">24h</span><span className="text-right">Volume</span></div><div className="mt-2 max-h-[62dvh] space-y-1 overflow-y-auto">
    {markets.map((market) => <button key={market.symbol} type="button" onClick={() => onSelect(market.symbol)} className={`w-full rounded-xl border px-3 py-2.5 text-left ${selectedSymbol === market.symbol ? "border-amber-400/30 bg-amber-400/8" : "border-white/8 bg-white/[0.02] hover:bg-white/[0.04]"}`}>
      <div className="grid grid-cols-[minmax(0,1.25fr)_minmax(0,0.95fr)_auto_auto] items-center gap-3">
        <div className="min-w-0"><div className="flex items-center gap-2"><Star className={`h-3.5 w-3.5 ${favorites.includes(market.symbol) ? "fill-current text-amber-300" : "text-slate-500"}`} /><div className="truncate text-sm font-semibold">{market.symbol}</div></div><div className="mt-1 text-[11px] text-slate-500">USDT perpetual</div></div>
        <div className="text-right font-mono text-sm text-slate-100">{formatPrice(market.last_price)}</div>
        <div className={`text-right text-xs font-medium ${toNumber(market.price_change_percent) >= 0 ? "text-emerald-400" : "text-rose-400"}`}>{formatPct(market.price_change_percent)}</div>
        <div className="text-right text-[11px] text-slate-400">{formatCompact(market.quote_volume)}</div>
      </div>
    </button>)}
  </div></div></div>;
}
function ChartPanel({ candles, loading, error, timeframe, onTimeframe, chartType, onChartType, premium, onRefresh, mobile = false }) { return <div className="rounded-2xl border border-white/8 bg-[#070d16] p-2.5"><div className="flex flex-col gap-2 border-b border-white/8 pb-2 sm:flex-row sm:items-center sm:justify-between"><div className="flex items-center gap-1 overflow-x-auto">{TIMEFRAMES.map((item) => <button key={item} type="button" onClick={() => onTimeframe(item)} className={`shrink-0 rounded-lg px-2.5 py-1.5 text-xs ${timeframe === item ? "bg-amber-400 text-slate-950" : "bg-white/[0.04] text-slate-300"}`}>{item.replace("h", "H")}</button>)}</div><div className="flex items-center gap-1 overflow-x-auto"><IconToggle icon={CandlestickChart} active={chartType === "candles"} onClick={() => onChartType("candles")} /><IconToggle icon={ChartNoAxesCombined} active={chartType === "line"} onClick={() => onChartType("line")} /><IconToggle icon={AreaChart} active={chartType === "area"} onClick={() => onChartType("area")} /><button type="button" onClick={onRefresh} className="inline-flex items-center gap-1 rounded-lg bg-white/[0.04] px-2.5 py-1.5 text-xs text-slate-300"><RefreshCcw className="h-3.5 w-3.5" />Refresh</button></div></div><div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-400"><span>Mark <span className="font-mono text-slate-100">{formatPrice(premium?.markPrice)}</span></span><span>Index <span className="font-mono text-slate-100">{formatPrice(premium?.indexPrice)}</span></span><span>Funding <span className="font-mono text-slate-100">{premium?.lastFundingRate ? `${(toNumber(premium.lastFundingRate) * 100).toFixed(4)}%` : "--"}</span></span></div><TradingChart candles={candles} chartType={chartType} loading={loading} error={error} heightClassName={mobile ? 'h-[320px]' : 'h-[360px]'} onRetry={onRefresh} /></div>; }
function OrderEntryPanel(props) { const validationErrors = props.formError || (props.user && props.summaryData === null && props.quantity ? "Validation pending" : ""); return <div className={`min-w-0 rounded-2xl border border-white/8 bg-[#070d16] ${props.compact ? "p-2" : "p-2.5"}`}><div className="flex items-center justify-between gap-2"><div className="relative flex gap-1"><button type="button" onClick={props.onToggleMarginMenu} className="rounded-lg bg-white/[0.04] px-2.5 py-2 text-xs font-medium capitalize text-slate-200">{props.marginMode} <ChevronDown className="ml-1 inline h-3.5 w-3.5" /></button>{props.showMarginMenu ? <div className="absolute left-0 top-[calc(100%+0.35rem)] z-20 rounded-xl border border-white/10 bg-[#111827] p-1"><button type="button" onClick={() => props.onMarginMode("cross")} className="block w-full rounded-lg px-3 py-2 text-left text-xs text-slate-200 hover:bg-white/[0.04]">Cross</button><button type="button" onClick={() => props.onMarginMode("isolated")} className="block w-full rounded-lg px-3 py-2 text-left text-xs text-slate-200 hover:bg-white/[0.04]">Isolated</button></div> : null}</div><button type="button" onClick={props.onToggleLeverage} className="rounded-lg bg-white/[0.04] px-2.5 py-2 text-xs font-medium text-slate-200">{props.leverage}x <ChevronDown className="ml-1 inline h-3.5 w-3.5" /></button></div><div className="mt-2 rounded-xl border border-white/8 bg-white/[0.025] px-2.5 py-2"><div className="flex items-center justify-between gap-2 text-[12px] text-slate-400"><span>Available</span><span className="min-w-0 truncate text-right font-mono text-[12px] font-semibold text-slate-100">{props.availableMargin.toFixed(2)} USDT</span></div></div><div className="mt-2 grid grid-cols-2 gap-1 rounded-xl bg-white/[0.04] p-1"><button type="button" onClick={() => props.onSide("long")} className={`rounded-lg py-2 text-[13px] font-semibold ${props.side === "long" ? "bg-emerald-500 text-white" : "text-slate-400"}`}>Long</button><button type="button" onClick={() => props.onSide("short")} className={`rounded-lg py-2 text-[13px] font-semibold ${props.side === "short" ? "bg-rose-500 text-white" : "text-slate-400"}`}>Short</button></div><label className="mt-2 block"><span className="mb-1 block text-[11px] text-slate-500">Order type</span><select value={props.orderType} onChange={(event) => props.onOrderType(event.target.value)} className="h-10 w-full rounded-xl bg-white/[0.04] px-2.5 text-[12px] font-semibold text-white outline-none"><option value="market">Market</option><option value="limit">Limit</option><option value="stop-market">Stop</option><option value="stop-limit">Stop-Limit</option></select></label>{props.orderType !== "market" ? <MiniField label="Price" value={props.priceInput} onChange={props.onPrice} suffix="USDT" placeholder={props.activePrice ? props.activePrice.toFixed(3) : "0.00"} /> : null}{props.orderType.includes("stop") ? <MiniField label="Stop Price" value={props.stopPrice} onChange={props.onStopPrice} suffix="USDT" placeholder="0.00" /> : null}<MiniField label="Quantity" value={props.quantity} onChange={props.onQuantity} suffix={props.market?.symbol?.replace("USDT", "") || "Qty"} placeholder="0.0000" /><div className="mt-2 grid grid-cols-4 gap-1">{PERCENTAGES.map((percent) => <button key={percent} type="button" onClick={() => props.onApplyPercentage(percent)} className={`rounded-lg py-1.5 text-[11px] ${props.selectedPct === percent ? "bg-amber-400 text-slate-950" : "bg-white/[0.04] text-slate-300"}`}>{percent}%</button>)}</div><div className="mt-2 rounded-xl border border-white/8 bg-white/[0.03] p-2 text-[11px] text-slate-400"><SummaryLine label="Value" value={`${props.summaryData?.notional_value || (props.quantity && props.activePrice ? decimal(props.quantity).mul(props.orderType === "market" ? props.activePrice : props.priceInput || props.activePrice).toFixed(3) : "--")} USDT`} /><SummaryLine label="Cost" value={`${props.summaryData?.margin_required || "--"} USDT`} /><SummaryLine label="Est. Fee" value={`${props.summaryData?.notional_value ? decimal(props.summaryData.notional_value).mul(0.0005).toFixed(4) : "--"} USDT`} /><SummaryLine label="Liq. Price" value={props.summaryData?.execution_price ? formatPrice(props.side === "long" ? decimal(props.summaryData.execution_price).mul(decimal(1).minus(decimal(0.8).div(props.leverage))).toNumber() : decimal(props.summaryData.execution_price).mul(decimal(1).plus(decimal(0.8).div(props.leverage))).toNumber()) : "Calculate"} /></div><label className="mt-2 flex items-center gap-2 text-[11px] text-slate-300"><input type="checkbox" checked={props.tpEnabled} onChange={(event) => props.onTpEnabled(event.target.checked)} className="h-4 w-4 rounded border-white/20 bg-transparent" /> TP / SL</label>{props.tpEnabled ? <div className="mt-2 grid gap-2"><MiniField label="Take Profit" value={props.tpPrice} onChange={props.onTpPrice} suffix="USDT" placeholder="0.00" /><MiniField label="Stop Loss" value={props.slPrice} onChange={props.onSlPrice} suffix="USDT" placeholder="0.00" /></div> : null}{validationErrors && validationErrors !== "Validation pending" ? <div className="mt-2 rounded-lg border border-rose-500/20 bg-rose-500/10 px-2.5 py-2 text-[11px] text-rose-200">{validationErrors}</div> : null}<button type="button" disabled={props.submitting || !props.user} onClick={props.onSubmit} className={`mt-2 w-full rounded-xl px-3 py-2.5 text-sm font-semibold ${props.side === "long" ? "bg-emerald-500 text-white" : "bg-rose-500 text-white"} disabled:cursor-not-allowed disabled:opacity-55`}>{!props.user ? "Authentication required" : props.submitting ? "Submitting..." : props.side === "long" ? "LONG" : "SHORT"}</button>{props.showLeverage ? <div className="mt-3 rounded-xl border border-white/10 bg-[#0b1220] p-3 text-xs"><div className="flex items-center justify-between"><span>Leverage</span><span className="font-semibold text-white">{props.leverage}x</span></div><input type="range" min={props.market?.min_leverage || 1} max={props.market?.max_leverage || 100} value={props.leverage} onChange={(event) => props.onLeverage(Number(event.target.value))} className="mt-3 w-full" /><div className="mt-2 flex items-center justify-between text-slate-400"><span>{props.market?.min_leverage || 1}x</span><span>{props.market?.max_leverage || 100}x</span></div><div className="mt-2 flex items-start gap-2 rounded-lg bg-amber-400/10 px-2 py-2 text-amber-100"><ShieldAlert className="mt-0.5 h-4 w-4 shrink-0" /> Higher leverage increases liquidation risk.</div></div> : null}</div>; }
function OrderBookPanel({ symbol, orderBook, recentTrades, activePrice, premium, loading, onSelectPrice, compact = false }) { const bids = (orderBook.bids || []).slice(0, compact ? 8 : 12); const asks = (orderBook.asks || []).slice(0, compact ? 8 : 12).slice().reverse(); const maxAsk = Math.max(1, ...asks.map((row) => Number(row[1] || row.amount || 0))); const maxBid = Math.max(1, ...bids.map((row) => Number(row[1] || row.amount || 0))); return <div className={`rounded-2xl border border-white/8 bg-[#070d16] ${compact ? "p-2" : "p-2.5"}`}><div className="flex items-center justify-between gap-2"><div><div className="text-xs uppercase tracking-[0.18em] text-slate-500">Order Book</div><div className="text-[11px] text-slate-400">{symbol}</div></div><div className="text-right text-[11px] text-slate-400"><div>{premium?.lastFundingRate ? `${(toNumber(premium.lastFundingRate) * 100).toFixed(4)}%` : "--"}</div><div>{formatCountdown(toNumber(premium?.nextFundingTime))}</div></div></div><div className="mt-2 grid grid-cols-[1fr_auto] gap-2 px-1 text-[10px] uppercase tracking-[0.18em] text-slate-500"><span>Price</span><span>Qty</span></div>{loading ? <div className="mt-3 space-y-2">{Array.from({ length: 8 }).map((_, index) => <div key={index} className="h-5 animate-pulse rounded bg-white/[0.04]" />)}</div> : <><div className="mt-1 space-y-1">{asks.map((row, index) => <DepthRow key={`ask-${index}`} price={row[0] || row.price} quantity={row[1] || row.amount} side="sell" maxAmount={maxAsk} onSelect={onSelectPrice} />)}</div><div className="my-2 rounded-lg border border-amber-400/20 bg-amber-400/10 px-2 py-1.5 text-center"><div className="font-mono text-sm font-semibold text-amber-200">{formatPrice(activePrice)}</div><div className="text-[10px] text-slate-400">Mark {formatPrice(premium?.markPrice)}</div></div><div className="space-y-1">{bids.map((row, index) => <DepthRow key={`bid-${index}`} price={row[0] || row.price} quantity={row[1] || row.amount} side="buy" maxAmount={maxBid} onSelect={onSelectPrice} />)}</div><div className="mt-3 border-t border-white/8 pt-2"><div className="mb-2 text-[10px] uppercase tracking-[0.18em] text-slate-500">Recent Trades</div><div className="space-y-1">{recentTrades.slice(0, compact ? 8 : 10).map((trade) => <TradeRow key={`${trade.id || trade.time || Math.random()}`} trade={trade} />)}</div></div></>}</div>; }
function AccountTabs({ tab, onTab, positions, openOrders, marginStatus, onCancelOrder, onClosePosition }) { return <div className="rounded-2xl border border-white/8 bg-[#070d16] p-2.5"><div className="flex gap-1 overflow-x-auto">{ACCOUNT_TABS.map((item) => <button key={item} type="button" onClick={() => onTab(item)} className={`shrink-0 rounded-lg px-3 py-2 text-xs ${tab === item ? "bg-white text-slate-950" : "bg-white/[0.04] text-slate-300"}`}>{item === "positions" ? `Positions (${positions.length})` : item === "openOrders" ? `Open Orders (${openOrders.length})` : item === "assets" ? "Assets" : "Tools"}</button>)}</div><div className="mt-2">{tab === "positions" ? (positions.length ? <div className="space-y-2">{positions.map((position) => <div key={position.id} className="rounded-xl border border-white/8 bg-white/[0.03] p-2.5"><div className="flex items-start justify-between gap-2"><div><div className="text-sm font-semibold text-white">{position.symbol}</div><div className="text-[11px] text-slate-500 capitalize">{position.side} {position.leverage}x | {position.margin_type || "cross"}</div></div><button type="button" onClick={() => onClosePosition(position)} className="rounded-lg bg-white/[0.06] px-2 py-1 text-[11px] text-slate-200">Close</button></div><div className="mt-2 grid grid-cols-2 gap-2 text-[11px] text-slate-400 sm:grid-cols-4"><Metric label="Size" value={position.quantity} /><Metric label="Entry" value={position.entry_price} /><Metric label="Mark" value={position.mark_price} /><Metric label="Liq." value={position.liquidation_price} /><Metric label="Margin" value={position.margin} /><Metric label="uPnL" value={position.unrealized_pnl} /></div></div>)}</div> : <CompactEmpty title="No Open Positions" copy="Your active futures positions will appear here." />) : tab === "openOrders" ? (openOrders.length ? <div className="space-y-2">{openOrders.map((order) => <div key={order.order_uuid} className="rounded-xl border border-white/8 bg-white/[0.03] p-2.5"><div className="flex items-start justify-between gap-2"><div><div className="text-sm font-semibold capitalize">{order.side} {order.type}</div><div className="text-[11px] text-slate-500">{order.symbol}</div></div><button type="button" onClick={() => onCancelOrder(order.order_uuid)} className="rounded-lg bg-rose-500/10 px-2 py-1 text-[11px] text-rose-200">Cancel</button></div><div className="mt-2 grid grid-cols-2 gap-2 text-[11px] text-slate-400 sm:grid-cols-4"><Metric label="Price" value={order.price || "Market"} /><Metric label="Qty" value={order.quantity} /><Metric label="Filled" value={order.filled_quantity} /><Metric label="Leverage" value={`${order.leverage}x`} /></div></div>)}</div> : <CompactEmpty title="No Open Orders" copy="Your live futures orders will appear here." />) : tab === "assets" ? <div className="rounded-xl border border-white/8 bg-white/[0.03] p-3 text-sm"><div className="grid grid-cols-2 gap-3 text-[11px] text-slate-400 sm:grid-cols-4"><Metric label="Wallet Balance" value={marginStatus?.total_margin || "0"} /><Metric label="Available" value={marginStatus?.available_margin || "0"} /><Metric label="Locked" value={marginStatus?.locked_margin || "0"} /><Metric label="Usage" value={marginStatus?.margin_usage_percentage ? `${Number(marginStatus.margin_usage_percentage).toFixed(2)}%` : "0%"} /></div></div> : <div className="grid gap-2 sm:grid-cols-2"><ToolCard title="Risk" value="Use leverage responsibly" icon={ShieldAlert} /><ToolCard title="Transfer" value="Move funds to futures wallet" icon={Wallet} /><ToolCard title="Preferences" value="Adjust contract layout" icon={SlidersHorizontal} /></div>}</div></div>; }
function IconToggle({ icon: Icon, active, onClick }) { return <button type="button" onClick={onClick} className={`inline-flex h-8 w-8 items-center justify-center rounded-lg ${active ? "bg-white text-slate-950" : "bg-white/[0.04] text-slate-300"}`}><Icon className="h-4 w-4" /></button>; }
function MiniField({ label, value, onChange, suffix, placeholder }) { return <label className="mt-2 block"><span className="mb-1 block text-[11px] text-slate-500">{label}</span><div className="flex items-center rounded-xl bg-white/[0.04] px-2.5"><input value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} inputMode="decimal" className="h-10 w-full bg-transparent text-sm text-white outline-none placeholder:text-slate-500" /><span className="text-[11px] font-medium text-slate-400">{suffix}</span></div></label>; }
function SummaryLine({ label, value }) { return <div className="mt-1.5 flex items-center justify-between gap-3 first:mt-0"><span>{label}</span><span className="font-mono text-white">{value}</span></div>; }
function DepthRow({ price, quantity, side, maxAmount, onSelect }) { const amount = Number(quantity || 0); const width = Math.min(100, (amount / maxAmount) * 100); return <button type="button" onClick={() => onSelect(price)} className="relative grid w-full grid-cols-[1fr_auto] gap-2 overflow-hidden rounded-lg px-2 py-1 text-[11px]"><span className={`absolute inset-y-0 right-0 ${side === "buy" ? "bg-emerald-500/10" : "bg-rose-500/10"}`} style={{ width: `${width}%` }} /><span className={`relative z-10 font-mono ${side === "buy" ? "text-emerald-400" : "text-rose-400"}`}>{formatPrice(price)}</span><span className="relative z-10 font-mono text-slate-300">{Number(quantity || 0).toFixed(3)}</span></button>; }
function TradeRow({ trade }) { const buyerMaker = Boolean(trade.isBuyerMaker); return <div className="grid grid-cols-[1fr_auto_auto] gap-2 rounded-lg px-1 py-1 text-[11px]"><span className={`font-mono ${buyerMaker ? "text-rose-400" : "text-emerald-400"}`}>{formatPrice(trade.price)}</span><span className="text-right font-mono text-slate-300">{Number(trade.qty || 0).toFixed(3)}</span><span className="text-right text-slate-500">{trade.time ? new Date(Number(trade.time)).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) : "--"}</span></div>; }
function Metric({ label, value }) { return <div><div>{label}</div><div className="mt-1 font-mono text-white">{value}</div></div>; }
function ToolCard({ title, value, icon: Icon }) { return <div className="rounded-xl border border-white/8 bg-white/[0.03] p-3"><div className="flex items-center gap-2"><Icon className="h-4 w-4 text-amber-300" /><div className="text-sm font-semibold text-white">{title}</div></div><div className="mt-2 text-[11px] text-slate-400">{value}</div></div>; }
function CompactEmpty({ title, copy }) { return <div className="rounded-xl border border-dashed border-white/10 bg-white/[0.02] px-4 py-6 text-center"><div className="text-sm font-semibold text-white">{title}</div><div className="mt-1 text-xs text-slate-400">{copy}</div></div>; }
function cleanError(message) { const lower = String(message || "").toLowerCase(); if (lower.includes("sqlstate") || lower.includes("syntax")) return "Futures service is temporarily unavailable"; if (lower.includes("unauthenticated")) return "Authentication required"; return message; }

export default Futures;

