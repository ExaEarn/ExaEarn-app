import React, { useEffect, useMemo, useState } from 'react';
import Decimal from 'decimal.js';
import {
  ArrowLeft,
  ArrowRightLeft,
  AreaChart,
  CandlestickChart,
  ChartNoAxesCombined,
  ChevronDown,
  Clock3,
  MoreHorizontal,
  RefreshCcw,
  Search,
  Star,
  Wifi,
  WifiOff,
  X,
} from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { isLocalApiPreview } from '../../config/apiConfig';
import { marketDataService } from '../../services/marketDataService';
import { onEvent } from '../../services/webSocketService';
import type { Candle, OrderBookLevel, OrderFormState, RecentTrade, TradingPair, UserOrder, WalletBalance } from '../../types/market';
import TradingChart from '../../components/market/TradingChart';

const TIMEFRAMES = ['1m', '3m', '5m', '15m', '30m', '1h', '4h', '1d'] as const;
const QUOTES = ['ALL', 'USDT', 'USDC', 'BTC', 'ETH', 'EXA'] as const;
const ORDER_TYPES: OrderFormState['type'][] = ['limit', 'market', 'stop_loss'];
const PRODUCT_TABS = ['Convert', 'Spot', 'Futures', 'Options', 'TradFi'] as const;
const ACCOUNT_TABS = ['openOrders', 'tradeHistory', 'assets'] as const;
const MOBILE_MODES = ['chart', 'trade'] as const;
const DEPTH_TABS = ['book', 'trades'] as const;
const CHART_TYPES = ['candles', 'line', 'area'] as const;
const PERCENTAGES = [25, 50, 75, 100] as const;
const FAVORITES_KEY = 'exaearn_trade_favorites';

type ProductTab = typeof PRODUCT_TABS[number];
type AccountTab = typeof ACCOUNT_TABS[number];
type MobileMode = typeof MOBILE_MODES[number];
type DepthTab = typeof DEPTH_TABS[number];
type ChartType = typeof CHART_TYPES[number];

type TradeTerminalProps = {
  onBack?: () => void;
  onOpenConvert?: () => void;
  onOpenFutures?: () => void;
  onOpenOptions?: () => void;
  onOpenTradFi?: () => void;
};

const decimal = (value: string | number | null | undefined) => {
  try {
    return new Decimal(value || 0);
  } catch {
    return new Decimal(0);
  }
};

const formatPrice = (value: number) => {
  if (!Number.isFinite(value)) return '--';
  if (value >= 1000) return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
  if (value >= 1) return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 }).format(value);
  return new Intl.NumberFormat(undefined, { minimumFractionDigits: 4, maximumFractionDigits: 6 }).format(value);
};

const formatCompact = (value: number) => new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 2 }).format(value || 0);
const readFavorites = () => { try { const raw = localStorage.getItem(FAVORITES_KEY); const parsed = raw ? JSON.parse(raw) : []; return Array.isArray(parsed) ? parsed : []; } catch { return []; } };
const getInitialPairFromPath = () => { const segments = window.location.pathname.split('/').filter(Boolean); return segments[0] === 'trade' && segments[1] ? marketDataService.normalizePair(segments[1]) : 'BTC/USDT'; };
const pricePrecisionFromValue = (value: number) => (value >= 1000 ? 2 : value >= 1 ? 4 : 6);

export default function TradeTerminal({ onBack, onOpenConvert, onOpenFutures, onOpenOptions, onOpenTradFi }: TradeTerminalProps) {
  const { request, user } = useAuth();
  const [markets, setMarkets] = useState<TradingPair[]>([]);
  const [selectedPair, setSelectedPair] = useState<string>(getInitialPairFromPath);
  const [timeframe, setTimeframe] = useState<string>(() => localStorage.getItem('exaearn_trade_timeframe') || '15m');
  const [chartType, setChartType] = useState<ChartType>(() => (localStorage.getItem('exaearn_trade_chart_type') as ChartType) || 'candles');
  const [search, setSearch] = useState('');
  const [quoteFilter, setQuoteFilter] = useState<typeof QUOTES[number]>('USDT');
  const [favoritesOnly, setFavoritesOnly] = useState(false);
  const [favoritePairs, setFavoritePairs] = useState<string[]>(readFavorites);
  const [showMarketSelector, setShowMarketSelector] = useState(false);
  const [mobileMode, setMobileMode] = useState<MobileMode>('trade');
  const [depthTab, setDepthTab] = useState<DepthTab>('book');
  const [accountTab, setAccountTab] = useState<AccountTab>('openOrders');
  const [loadingMarkets, setLoadingMarkets] = useState(true);
  const [loadingMarketData, setLoadingMarketData] = useState(true);
  const [loadingAccount, setLoadingAccount] = useState(true);
  const [error, setError] = useState('');
  const [connectionState, setConnectionState] = useState<'live' | 'reconnecting'>('live');
  const [candles, setCandles] = useState<Candle[]>([]);
  const [recentTrades, setRecentTrades] = useState<RecentTrade[]>([]);
  const [bids, setBids] = useState<OrderBookLevel[]>([]);
  const [asks, setAsks] = useState<OrderBookLevel[]>([]);
  const [openOrders, setOpenOrders] = useState<UserOrder[]>([]);
  const [tradeHistory, setTradeHistory] = useState<RecentTrade[]>([]);
  const [balances, setBalances] = useState<WalletBalance[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [orderMessage, setOrderMessage] = useState('');
  const [formError, setFormError] = useState('');
  const [bookPrecision, setBookPrecision] = useState<number>(0.01);
  const [form, setForm] = useState<OrderFormState>({ side: 'buy', type: 'limit', price: '', amount: '', stopPrice: '' });

  const selectedMarket = useMemo(() => markets.find((market) => market.pair === selectedPair) ?? null, [markets, selectedPair]);
  const activePrice = selectedMarket?.last ?? candles.at(-1)?.close ?? 0;
  const selectedBaseBalance = useMemo(() => balances.find((balance) => String(balance.currency).toUpperCase() === String(selectedMarket?.base || '').toUpperCase()) ?? null, [balances, selectedMarket?.base]);
  const selectedQuoteBalance = useMemo(() => balances.find((balance) => String(balance.currency).toUpperCase() === String(selectedMarket?.quote || '').toUpperCase()) ?? null, [balances, selectedMarket?.quote]);
  const availableBaseBalance = decimal(selectedBaseBalance?.balance);
  const availableQuoteBalance = decimal(selectedQuoteBalance?.balance);
  const selectedPrice = decimal(form.type === 'market' ? activePrice : form.price || activePrice);
  const amountDecimal = decimal(form.amount);
  const totalDecimal = selectedPrice.mul(amountDecimal);
  const feeRate = decimal(selectedMarket?.taker_fee || 0);
  const estimatedFee = totalDecimal.mul(feeRate);
  const minAmount = decimal(selectedMarket?.min_order_size || 0);
  const maxAmount = decimal(selectedMarket?.max_order_size || 0);

  const filteredMarkets = useMemo(() => {
    const term = search.trim().toUpperCase();
    return markets.filter((market) => {
      const matchesQuote = quoteFilter === 'ALL' || market.quote === quoteFilter || market.base === quoteFilter;
      const matchesFavorite = !favoritesOnly || favoritePairs.includes(market.pair);
      const matchesSearch = !term || [market.base, market.quote, market.pair, market.symbol].some((value) => value?.includes(term));
      return matchesQuote && matchesFavorite && matchesSearch;
    });
  }, [favoritePairs, favoritesOnly, markets, quoteFilter, search]);

  const aggregatedAsks = useMemo(() => aggregateLevels(asks, bookPrecision), [asks, bookPrecision]);
  const aggregatedBids = useMemo(() => aggregateLevels(bids, bookPrecision), [bids, bookPrecision]);

  useEffect(() => { localStorage.setItem('exaearn_trade_timeframe', timeframe); }, [timeframe]);
  useEffect(() => { localStorage.setItem('exaearn_trade_chart_type', chartType); }, [chartType]);
  useEffect(() => { localStorage.setItem(FAVORITES_KEY, JSON.stringify(favoritePairs)); }, [favoritePairs]);

  useEffect(() => {
    const loadMarkets = async () => {
      setLoadingMarkets(true);
      try {
        const nextMarkets = await marketDataService.getMarkets(request);
        setMarkets(nextMarkets);
        if (!nextMarkets.some((market) => market.pair === selectedPair) && nextMarkets[0]) {
          setSelectedPair(nextMarkets[0].pair);
        }
        setError('');
      } catch (nextError: any) {
        setError(safeTradeError(nextError?.message || 'Unable to load markets right now.'));
      } finally {
        setLoadingMarkets(false);
      }
    };

    loadMarkets();
  }, [request, selectedPair]);

  useEffect(() => {
    if (!selectedPair) return;
    const slug = marketDataService.toPairPath(selectedPair);
    if (window.location.pathname !== `/trade/${slug}`) {
      window.history.replaceState({ page: 'trade', pair: selectedPair }, '', `/trade/${slug}`);
    }
  }, [selectedPair]);

  useEffect(() => {
    if (!selectedPair) return;

    const loadMarketData = async () => {
      setLoadingMarketData(true);
      setLoadingAccount(Boolean(user));
      try {
        const [nextCandles, depth, trades] = await Promise.all([
          marketDataService.getCandles(request, selectedPair, timeframe, 500),
          marketDataService.getOrderBook(request, selectedPair, 24),
          marketDataService.getRecentTrades(request, selectedPair, 40),
        ]);

        setCandles(nextCandles);
        setRecentTrades(trades);
        setBids(normalizeLevels(depth?.bids ?? [], 'buy'));
        setAsks(normalizeLevels(depth?.asks ?? [], 'sell'));
        setForm((current) => ({
          ...current,
          price: current.type === 'market' ? '' : current.price || String(nextCandles.at(-1)?.close ?? selectedMarket?.last ?? ''),
        }));
        setConnectionState('live');
        setError('');
      } catch (nextError: any) {
        setConnectionState('reconnecting');
        setError(safeTradeError(nextError?.message || 'Market unavailable'));
      } finally {
        setLoadingMarketData(false);
      }

      if (!user) {
        setLoadingAccount(false);
        return;
      }

      try {
        const [orders, userTrades, walletBalances] = await Promise.all([
          marketDataService.getOpenOrders(request, selectedPair),
          marketDataService.getTradeHistory(request, selectedPair),
          marketDataService.getBalances(request),
        ]);
        setOpenOrders(orders);
        setTradeHistory(userTrades);
        setBalances(walletBalances);
      } catch {
        // Keep market data usable even if private account data is temporarily slow.
      } finally {
        setLoadingAccount(false);
      }
    };

    loadMarketData();
  }, [request, selectedPair, timeframe, user]);

  useEffect(() => {
    if (!selectedPair) return;

    const timer = window.setInterval(async () => {
      try {
        const [nextMarkets, nextCandles, depth, trades] = await Promise.all([
          marketDataService.getMarkets(request),
          marketDataService.getCandles(request, selectedPair, timeframe, 500),
          marketDataService.getOrderBook(request, selectedPair, 24),
          marketDataService.getRecentTrades(request, selectedPair, 40),
        ]);

        setMarkets(nextMarkets);
        setCandles(nextCandles);
        setRecentTrades(trades);
        setBids(normalizeLevels(depth?.bids ?? [], 'buy'));
        setAsks(normalizeLevels(depth?.asks ?? [], 'sell'));
        setConnectionState('live');
      } catch {
        setConnectionState('reconnecting');
      }
    }, isLocalApiPreview() ? 15000 : 8000);

    return () => window.clearInterval(timer);
  }, [request, selectedPair, timeframe]);


  useEffect(() => {
    const unsubscribe = onEvent('market:stream', (payload: any) => {
      const streamPair = marketDataService.normalizePair(String(payload?.pair || payload?.data?.pair || ''));
      if (!streamPair || streamPair !== selectedPair) return;

      setConnectionState('live');

      if (payload?.type === 'trade' && payload?.data) {
        setRecentTrades((current) => [payload.data, ...current].slice(0, 40));
      }

      if (payload?.type === 'order_book' && payload?.data) {
        setBids(normalizeLevels(payload.data.bids ?? [], 'buy'));
        setAsks(normalizeLevels(payload.data.asks ?? [], 'sell'));
      }

      if (payload?.type === 'candle' && payload?.data) {
        setCandles((current) => mergeCandle(current, normalizeCandle(payload.data)));
      }
    });

    return unsubscribe;
  }, [selectedPair]);

  const validationMessage = useMemo(() => {
    if (!selectedMarket) return 'Market unavailable';
    if (!form.amount) return 'Enter an amount';
    if (amountDecimal.lte(0)) return 'Amount must be greater than zero';
    if (minAmount.gt(0) && amountDecimal.lt(minAmount)) return `Minimum ${minAmount.toString()} ${selectedMarket.base}`;
    if (maxAmount.gt(0) && amountDecimal.gt(maxAmount)) return `Maximum ${maxAmount.toString()} ${selectedMarket.base}`;
    if (form.type !== 'market' && selectedPrice.lte(0)) return 'Enter a valid price';
    if (form.type === 'stop_loss' && decimal(form.stopPrice).lte(0)) return 'Enter a valid stop price';
    if (form.side === 'buy' && availableQuoteBalance.gt(0) && totalDecimal.gt(availableQuoteBalance)) return `Insufficient ${selectedMarket.quote} balance`;
    if (form.side === 'sell' && availableBaseBalance.gt(0) && amountDecimal.gt(availableBaseBalance)) return `Insufficient ${selectedMarket.base} balance`;
    return '';
  }, [amountDecimal, availableBaseBalance, availableQuoteBalance, form.amount, form.side, form.stopPrice, form.type, maxAmount, minAmount, selectedMarket, selectedPrice, totalDecimal]);

  const handleProductTab = (tab: ProductTab) => {
    if (tab === 'Spot') return;
    if (tab === 'Convert') { onOpenConvert?.(); return; }
    if (tab === 'Futures') { onOpenFutures?.(); return; }
    if (tab === 'Options') { onOpenOptions?.(); return; }
    if (tab === 'TradFi') { onOpenTradFi?.(); return; }
  };

  const handleRefresh = async () => {
    setOrderMessage('Refreshing market data...');
    try {
      const [nextCandles, depth, trades, orders, userTrades, walletBalances] = await Promise.all([
        marketDataService.getCandles(request, selectedPair, timeframe, 500),
        marketDataService.getOrderBook(request, selectedPair, 24),
        marketDataService.getRecentTrades(request, selectedPair, 40),
        user ? marketDataService.getOpenOrders(request, selectedPair) : Promise.resolve([]),
        user ? marketDataService.getTradeHistory(request, selectedPair) : Promise.resolve([]),
        user ? marketDataService.getBalances(request) : Promise.resolve([]),
      ]);
      setCandles(nextCandles);
      setRecentTrades(trades);
      setBids(normalizeLevels(depth?.bids ?? [], 'buy'));
      setAsks(normalizeLevels(depth?.asks ?? [], 'sell'));
      setOpenOrders(orders);
      setTradeHistory(userTrades);
      setBalances(walletBalances);
      setOrderMessage('Market refreshed');
    } catch (nextError: any) {
      setOrderMessage(safeTradeError(nextError?.message || 'Unable to refresh market data'));
    }
  };

  const applyPercentage = (percent: number) => {
    if (!selectedMarket) return;
    if (form.side === 'buy') {
      const priceSource = form.type === 'market' ? decimal(activePrice || 0) : selectedPrice;
      if (priceSource.lte(0)) {
        setFormError('Enter a valid price first');
        return;
      }
      const amount = availableQuoteBalance.mul(percent).div(100).div(priceSource);
      setForm((current) => ({ ...current, amount: amount.toDecimalPlaces(8, Decimal.ROUND_DOWN).toString() }));
      return;
    }
    const amount = availableBaseBalance.mul(percent).div(100);
    setForm((current) => ({ ...current, amount: amount.toDecimalPlaces(8, Decimal.ROUND_DOWN).toString() }));
  };

  const submitOrder = async () => {
    if (!selectedMarket) return;
    setSubmitting(true);
    setOrderMessage('');
    setFormError('');

    if (validationMessage) {
      setFormError(validationMessage);
      setSubmitting(false);
      return;
    }

    try {
      const payload: Record<string, unknown> = {
        pair: selectedPair,
        side: form.side,
        type: form.type,
        amount: form.amount,
      };
      if (form.type !== 'market') payload.price = form.price;
      if (form.type === 'stop_loss') payload.stop_price = form.stopPrice;

      await marketDataService.placeOrder(request, payload);
      setOrderMessage(`${form.side === 'buy' ? 'Buy' : 'Sell'} order submitted`);
      setOpenOrders(await marketDataService.getOpenOrders(request, selectedPair));
      setBalances(await marketDataService.getBalances(request));
      setForm((current) => ({ ...current, amount: '', stopPrice: '' }));
    } catch (nextError: any) {
      setOrderMessage(safeTradeError(nextError?.message || 'Order could not be submitted'));
    } finally {
      setSubmitting(false);
    }
  };

  const cancelOrder = async (orderUuid: string) => {
    try {
      setOrderMessage('Cancelling order...');
      await marketDataService.cancelOrder(request, orderUuid);
      setOpenOrders(await marketDataService.getOpenOrders(request, selectedPair));
      setOrderMessage('Order cancelled');
    } catch (nextError: any) {
      setOrderMessage(safeTradeError(nextError?.message || 'Unable to cancel order'));
    }
  };

  const currentPricePrecision = pricePrecisionFromValue(activePrice);

  return (
    <main className="min-h-[100dvh] overflow-x-hidden bg-[#04070d] text-white">
      <div className="mx-auto flex w-full max-w-[1800px] flex-col gap-3 px-2 pb-[calc(env(safe-area-inset-bottom)+12px)] pt-[calc(env(safe-area-inset-top)+8px)] sm:px-3 lg:px-4">
        <TradingProductNav onSelect={handleProductTab} />

        <CompactMarketHeader
          market={selectedMarket}
          activePrice={activePrice}
          connectionState={connectionState}
          isFavorite={favoritePairs.includes(selectedPair)}
          onToggleFavorite={() => setFavoritePairs((current) => current.includes(selectedPair) ? current.filter((item) => item !== selectedPair) : [...current, selectedPair])}
          onOpenMarketSelector={() => setShowMarketSelector(true)}
          onBack={onBack}
        />

        {error ? <div className="rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-xs text-rose-200">{error}</div> : null}

        <div className="rounded-2xl border border-white/8 bg-[#070d16] p-2 shadow-[0_10px_30px_rgba(0,0,0,0.28)] lg:hidden">
          <div className="grid grid-cols-2 gap-2">
            {MOBILE_MODES.map((mode) => (
              <button key={mode} type="button" onClick={() => setMobileMode(mode)} className={`rounded-xl px-3 py-2 text-sm font-medium ${mobileMode === mode ? 'bg-amber-400 text-slate-950' : 'bg-white/[0.03] text-slate-300'}`}>
                {mode === 'chart' ? 'Chart' : 'Trade'}
              </button>
            ))}
          </div>
        </div>

        <div className="hidden gap-3 lg:grid lg:grid-cols-[260px_minmax(0,1fr)_330px] xl:grid-cols-[280px_minmax(0,1fr)_360px]">
          <MarketSidebar
            markets={filteredMarkets}
            loading={loadingMarkets}
            search={search}
            onSearch={setSearch}
            quoteFilter={quoteFilter}
            onQuoteFilter={setQuoteFilter}
            favoritesOnly={favoritesOnly}
            onToggleFavorites={() => setFavoritesOnly((current) => !current)}
            favoritePairs={favoritePairs}
            selectedPair={selectedPair}
            onSelectPair={setSelectedPair}
            onToggleFavorite={(pair) => setFavoritePairs((current) => current.includes(pair) ? current.filter((item) => item !== pair) : [...current, pair])}
          />

          <div className="min-w-0 space-y-3">
            <ChartPanel
              loading={loadingMarketData}
              error={!loadingMarketData && candles.length === 0 ? error : ''}
              candles={candles}
              timeframe={timeframe}
              onTimeframe={setTimeframe}
              chartType={chartType}
              onChartType={setChartType}
              onRefresh={handleRefresh}
            />
            <BottomTabs
              accountTab={accountTab}
              onAccountTab={setAccountTab}
              loading={loadingAccount}
              openOrders={openOrders}
              tradeHistory={tradeHistory}
              balances={balances}
              selectedMarket={selectedMarket}
              onCancelOrder={cancelOrder}
            />
          </div>

          <div className="min-w-0 space-y-3">
            <OrderEntryPanel
              market={selectedMarket}
              form={form}
              onForm={setForm}
              activePrice={activePrice}
              currentPricePrecision={currentPricePrecision}
              availableBaseBalance={availableBaseBalance}
              availableQuoteBalance={availableQuoteBalance}
              totalDecimal={totalDecimal}
              estimatedFee={estimatedFee}
              feeRate={feeRate}
              minAmount={minAmount}
              maxAmount={maxAmount}
              validationMessage={validationMessage}
              formError={formError}
              orderMessage={orderMessage}
              submitting={submitting}
              onApplyPercentage={applyPercentage}
              onSubmit={submitOrder}
            />
            <DepthTradesPanel
              depthTab={depthTab}
              onDepthTab={setDepthTab}
              aggregatedAsks={aggregatedAsks}
              aggregatedBids={aggregatedBids}
              activePrice={activePrice}
              recentTrades={recentTrades}
              bookPrecision={bookPrecision}
              onBookPrecision={setBookPrecision}
              currentPricePrecision={currentPricePrecision}
              onSelectBookPrice={(price) => setForm((current) => ({ ...current, price: price.toFixed(currentPricePrecision), type: current.type === 'market' ? 'limit' : current.type }))}
            />
          </div>
        </div>

        <div className="grid gap-3 lg:hidden">
          {mobileMode === 'chart' ? (
            <>
              <ChartPanel
                loading={loadingMarketData}
                timeframe={timeframe}
                onTimeframe={setTimeframe}
                chartType={chartType}
                onChartType={setChartType}
                onRefresh={handleRefresh}
                mobile
              />
              <DepthTradesPanel
                depthTab={depthTab}
                onDepthTab={setDepthTab}
                aggregatedAsks={aggregatedAsks}
                aggregatedBids={aggregatedBids}
                activePrice={activePrice}
                recentTrades={recentTrades}
                bookPrecision={bookPrecision}
                onBookPrecision={setBookPrecision}
                currentPricePrecision={currentPricePrecision}
                onSelectBookPrice={(price) => setForm((current) => ({ ...current, price: price.toFixed(currentPricePrecision), type: current.type === 'market' ? 'limit' : current.type }))}
              />
            </>
          ) : (
            <div className="grid min-w-0 grid-cols-[minmax(0,1.18fr)_minmax(104px,0.82fr)] gap-2 overflow-hidden rounded-2xl border border-white/8 bg-[#070d16] p-2 max-[360px]:grid-cols-[minmax(0,1.1fr)_minmax(98px,0.9fr)]">
              <OrderEntryPanel
                market={selectedMarket}
                form={form}
                onForm={setForm}
                activePrice={activePrice}
                currentPricePrecision={currentPricePrecision}
                availableBaseBalance={availableBaseBalance}
                availableQuoteBalance={availableQuoteBalance}
                totalDecimal={totalDecimal}
                estimatedFee={estimatedFee}
                feeRate={feeRate}
                minAmount={minAmount}
                maxAmount={maxAmount}
                validationMessage={validationMessage}
                formError={formError}
                orderMessage={orderMessage}
                submitting={submitting}
                onApplyPercentage={applyPercentage}
                onSubmit={submitOrder}
                compact
              />
              <DepthTradesPanel
                depthTab={depthTab}
                onDepthTab={setDepthTab}
                aggregatedAsks={aggregatedAsks}
                aggregatedBids={aggregatedBids}
                activePrice={activePrice}
                recentTrades={recentTrades}
                bookPrecision={bookPrecision}
                onBookPrecision={setBookPrecision}
                currentPricePrecision={currentPricePrecision}
                onSelectBookPrice={(price) => setForm((current) => ({ ...current, price: price.toFixed(currentPricePrecision), type: current.type === 'market' ? 'limit' : current.type }))}
                compact
              />
            </div>
          )}

          <BottomTabs
            accountTab={accountTab}
            onAccountTab={setAccountTab}
            loading={loadingAccount}
            openOrders={openOrders}
            tradeHistory={tradeHistory}
            balances={balances}
            selectedMarket={selectedMarket}
            onCancelOrder={cancelOrder}
          />
        </div>

        {showMarketSelector ? (
          <MarketSelectorSheet
            markets={filteredMarkets}
            search={search}
            onSearch={setSearch}
            quoteFilter={quoteFilter}
            onQuoteFilter={setQuoteFilter}
            favoritesOnly={favoritesOnly}
            onToggleFavorites={() => setFavoritesOnly((current) => !current)}
            favoritePairs={favoritePairs}
            selectedPair={selectedPair}
            onSelectPair={(pair) => { setSelectedPair(pair); setShowMarketSelector(false); }}
            onToggleFavorite={(pair) => setFavoritePairs((current) => current.includes(pair) ? current.filter((item) => item !== pair) : [...current, pair])}
            onClose={() => setShowMarketSelector(false)}
          />
        ) : null}
      </div>
    </main>
  );
}

function TradingProductNav({ onSelect }: { onSelect: (tab: ProductTab) => void }) {
  return (
    <div className="rounded-2xl border border-white/8 bg-[#070d16] px-2 py-1.5">
      <div className="flex items-center gap-1 overflow-x-auto">
        {PRODUCT_TABS.map((tab) => {
          const active = tab === 'Spot';
          return (
            <button key={tab} type="button" onClick={() => onSelect(tab)} className={`shrink-0 rounded-xl px-3 py-2 text-sm font-medium ${active ? 'bg-white text-slate-950' : 'text-slate-400 hover:text-white'}`}>
              {tab}
            </button>
          );
        })}
      </div>
    </div>
  );
}

function CompactMarketHeader({ market, activePrice, connectionState, isFavorite, onToggleFavorite, onOpenMarketSelector, onBack }: any) {
  return (
    <div className="rounded-2xl border border-white/8 bg-[#070d16] px-3 py-2.5">
      <div className="flex items-center justify-between gap-2">
        <div className="flex min-w-0 items-center gap-2">
          <button type="button" onClick={onBack} className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/[0.04] text-slate-300"><ArrowLeft className="h-4 w-4" /></button>
          <button type="button" onClick={onOpenMarketSelector} className="flex min-w-0 items-center gap-1 rounded-xl bg-white/[0.04] px-3 py-2 text-left">
            <span className="truncate text-sm font-semibold">{market?.pair || 'Market unavailable'}</span>
            <ChevronDown className="h-4 w-4 shrink-0 text-slate-400" />
          </button>
        </div>
        <div className="flex items-center gap-1">
          <button type="button" onClick={onToggleFavorite} aria-label="Toggle favorite" className={`inline-flex h-9 w-9 items-center justify-center rounded-xl ${isFavorite ? 'bg-amber-400/12 text-amber-300' : 'bg-white/[0.04] text-slate-400'}`}><Star className={`h-4 w-4 ${isFavorite ? 'fill-current' : ''}`} /></button>
          <button type="button" aria-label="More market actions" className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/[0.04] text-slate-400"><MoreHorizontal className="h-4 w-4" /></button>
        </div>
      </div>
      <div className="mt-2 flex flex-wrap items-end justify-between gap-3">
        <div>
          <div className="font-mono text-2xl font-semibold text-white">{activePrice ? `$${formatPrice(activePrice)}` : '--'}</div>
          <div className={`mt-1 text-xs font-medium ${market?.change24h >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>{market?.change24h >= 0 ? '+' : ''}{(market?.change24h ?? 0).toFixed(2)}%</div>
        </div>
        <div className="grid min-w-0 grid-cols-3 gap-3 text-right text-xs text-slate-400">
          <div><div>High</div><div className="mt-1 font-mono text-white">{market?.high24h ? formatPrice(market.high24h) : '--'}</div></div>
          <div><div>Low</div><div className="mt-1 font-mono text-white">{market?.low24h ? formatPrice(market.low24h) : '--'}</div></div>
          <div><div>24h Vol</div><div className="mt-1 font-mono text-white">{market?.volume ? formatCompact(market.volume) : '--'}</div></div>
        </div>
      </div>
      <div className="mt-2 flex items-center gap-2 text-[11px] text-slate-500">
        {connectionState === 'live' ? <Wifi className="h-3.5 w-3.5 text-emerald-400" /> : <WifiOff className="h-3.5 w-3.5 text-amber-300" />}
        <span>{connectionState === 'live' ? 'Live data' : 'Live data reconnecting...'}</span>
      </div>
    </div>
  );
}

function MarketSidebar(props: any) {
  return (
    <div className="min-w-0 rounded-2xl border border-white/8 bg-[#070d16] p-2.5">
      <div className="flex items-center justify-between"><div><div className="text-xs uppercase tracking-[0.18em] text-slate-500">Markets</div><div className="text-sm font-semibold">Watchlist</div></div><button type="button" onClick={props.onToggleFavorites} className={`rounded-xl px-2 py-1.5 text-xs ${props.favoritesOnly ? 'bg-amber-400 text-slate-950' : 'bg-white/[0.04] text-slate-300'}`}>Fav</button></div>
      <div className="mt-2 rounded-xl bg-white/[0.04] px-3 py-2"><div className="flex items-center gap-2"><Search className="h-4 w-4 text-slate-500" /><input value={props.search} onChange={(event) => props.onSearch(event.target.value)} placeholder="Search markets" className="w-full bg-transparent text-sm text-white outline-none placeholder:text-slate-500" /></div></div>
      <div className="mt-2 flex gap-1 overflow-x-auto pb-1">{QUOTES.map((quote) => <button key={quote} type="button" onClick={() => props.onQuoteFilter(quote)} className={`shrink-0 rounded-lg px-2.5 py-1.5 text-xs ${props.quoteFilter === quote ? 'bg-white text-slate-950' : 'bg-white/[0.04] text-slate-300'}`}>{quote}</button>)}</div>
      <div className="mt-2 space-y-1 overflow-y-auto">{props.loading ? Array.from({ length: 8 }).map((_, index) => <div key={index} className="h-14 animate-pulse rounded-xl bg-white/[0.04]" />) : props.markets.map((market: TradingPair) => <MarketRow key={market.pair} market={market} selected={market.pair === props.selectedPair} favorite={props.favoritePairs.includes(market.pair)} onSelect={() => props.onSelectPair(market.pair)} onToggleFavorite={() => props.onToggleFavorite(market.pair)} />)}</div>
    </div>
  );
}

function MarketSelectorSheet(props: any) {
  return (
    <div className="fixed inset-0 z-50 flex items-end bg-black/70 p-0 lg:items-start lg:justify-center lg:p-6" onClick={props.onClose}>
      <div className="w-full max-h-[88dvh] rounded-t-[20px] border border-white/10 bg-[#050b14] p-3 lg:mt-12 lg:max-w-[820px] lg:rounded-[24px] lg:p-5" onClick={(event) => event.stopPropagation()}>
        <div className="flex items-center justify-between gap-3"><div><div className="text-xs uppercase tracking-[0.18em] text-slate-500">Markets</div><div className="text-base font-semibold lg:text-lg">Select trading pair</div></div><button type="button" onClick={props.onClose} className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/[0.04] text-slate-300"><X className="h-4 w-4" /></button></div>
        <div className="mt-3 grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]"><div className="rounded-xl bg-white/[0.04] px-3 py-2"><div className="flex items-center gap-2"><Search className="h-4 w-4 text-slate-500" /><input value={props.search} onChange={(event) => props.onSearch(event.target.value)} placeholder="Search markets..." className="w-full bg-transparent text-sm text-white outline-none placeholder:text-slate-500" /></div></div><div className="flex gap-1 overflow-x-auto pb-1 lg:pb-0">{QUOTES.map((quote) => <button key={quote} type="button" onClick={() => props.onQuoteFilter(quote)} className={`shrink-0 rounded-lg px-2.5 py-1.5 text-xs ${props.quoteFilter === quote ? 'bg-white text-slate-950' : 'bg-white/[0.04] text-slate-300'}`}>{quote}</button>)}</div></div>
        <div className="mt-3 hidden grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_auto_auto] gap-3 px-3 text-[11px] uppercase tracking-[0.18em] text-slate-500 lg:grid"><span>Pair</span><span>Last Price</span><span>24h</span><span>Volume</span></div>
        <div className="mt-2 max-h-[62dvh] space-y-1 overflow-y-auto">{props.markets.length ? props.markets.map((market: TradingPair) => <MarketRow key={market.pair} market={market} selected={market.pair === props.selectedPair} favorite={props.favoritePairs.includes(market.pair)} onSelect={() => props.onSelectPair(market.pair)} onToggleFavorite={() => props.onToggleFavorite(market.pair)} />) : <EmptyState copy="No markets match your filters." />}</div>
      </div>
    </div>
  );
}

function ChartPanel({ loading, error, candles, timeframe, onTimeframe, chartType, onChartType, onRefresh, mobile = false }: any) {
  const heightClassName = mobile ? 'h-[58dvh] min-h-[320px] max-h-[430px]' : 'h-[430px]';
  return <section className="min-w-0 overflow-hidden rounded-2xl border border-white/8 bg-[#070d16] p-2 shadow-[0_18px_42px_rgba(0,0,0,.22)]"><div className="flex min-w-0 flex-col gap-2 border-b border-white/8 pb-2 sm:flex-row sm:items-center sm:justify-between"><div className="flex min-w-0 items-center gap-1 overflow-x-auto no-scrollbar">{TIMEFRAMES.map((item) => <button key={item} type="button" onClick={() => onTimeframe(item)} className={`shrink-0 rounded-md px-2 py-1.5 text-[11px] font-semibold ${timeframe === item ? 'bg-amber-400 text-slate-950' : 'bg-white/[0.04] text-slate-300 hover:text-white'}`}>{item.replace('h', 'H')}</button>)}</div><div className="flex min-w-0 items-center gap-1 overflow-x-auto no-scrollbar"><IconToggle icon={CandlestickChart} active={chartType === 'candles'} onClick={() => onChartType('candles')} /><IconToggle icon={ChartNoAxesCombined} active={chartType === 'line'} onClick={() => onChartType('line')} /><IconToggle icon={AreaChart} active={chartType === 'area'} onClick={() => onChartType('area')} /><button type="button" onClick={onRefresh} className="inline-flex shrink-0 items-center gap-1 rounded-md bg-white/[0.04] px-2 py-1.5 text-[11px] font-semibold text-slate-300 hover:text-white"><RefreshCcw className="h-3.5 w-3.5" />Refresh</button></div></div><div className="min-w-0 overflow-hidden"><TradingChart candles={candles} chartType={chartType} loading={loading} error={error} heightClassName={heightClassName} onRetry={onRefresh} /></div></section>;
}
function OrderEntryPanel({ market, form, onForm, activePrice, currentPricePrecision, availableBaseBalance, availableQuoteBalance, totalDecimal, estimatedFee, feeRate, minAmount, maxAmount, validationMessage, formError, orderMessage, submitting, onApplyPercentage, onSubmit, compact = false }: any) {
  return <div className={`min-w-0 rounded-2xl border border-white/8 bg-[#070d16] ${compact ? 'p-2' : 'p-2.5'}`}><div className="grid grid-cols-2 gap-1 rounded-xl bg-white/[0.04] p-1"><button type="button" onClick={() => onForm((current: OrderFormState) => ({ ...current, side: 'buy' }))} className={`rounded-lg py-2 text-[13px] font-semibold ${form.side === 'buy' ? 'bg-emerald-500 text-white' : 'text-slate-400'}`}>Buy</button><button type="button" onClick={() => onForm((current: OrderFormState) => ({ ...current, side: 'sell' }))} className={`rounded-lg py-2 text-[13px] font-semibold ${form.side === 'sell' ? 'bg-rose-500 text-white' : 'text-slate-400'}`}>Sell</button></div><div className="mt-2 rounded-xl border border-white/8 bg-white/[0.025] px-2.5 py-2"><div className="flex items-center justify-between gap-2 text-[12px] text-slate-400"><span>Available</span><span className="min-w-0 truncate text-right font-mono text-[12px] font-semibold text-slate-100">{form.side === 'buy' ? `${availableQuoteBalance.toFixed(2)} ${market?.quote || ''}` : `${availableBaseBalance.toFixed(6)} ${market?.base || ''}`}</span></div></div><div className="mt-2 grid grid-cols-3 gap-1">{ORDER_TYPES.map((type) => <button key={type} type="button" onClick={() => onForm((current: OrderFormState) => ({ ...current, type, price: type === 'market' ? '' : current.price }))} className={`min-w-0 rounded-md px-1.5 py-1.5 text-center text-[10px] font-semibold uppercase leading-none tracking-wide ${form.type === type ? 'bg-white text-slate-950' : 'bg-white/[0.04] text-slate-300'}`}>{type.replace('_', ' ')}</button>)}</div>{form.type !== 'market' ? <MiniField label="Price" suffix={market?.quote || 'USDT'} value={form.price} onChange={(value) => onForm((current: OrderFormState) => ({ ...current, price: value }))} placeholder={activePrice ? activePrice.toFixed(currentPricePrecision) : '0.00'} /> : null}{form.type === 'stop_loss' ? <MiniField label="Stop" suffix={market?.quote || 'USDT'} value={form.stopPrice} onChange={(value) => onForm((current: OrderFormState) => ({ ...current, stopPrice: value }))} placeholder="0.00" /> : null}<MiniField label="Quantity" suffix={market?.base || 'BTC'} value={form.amount} onChange={(value) => onForm((current: OrderFormState) => ({ ...current, amount: value }))} placeholder="0.000000" /><div className="mt-2 grid grid-cols-5 gap-1">{[0, ...PERCENTAGES].map((percent) => <button key={percent} type="button" onClick={() => percent === 0 ? onForm((current: OrderFormState) => ({ ...current, amount: '' })) : onApplyPercentage(percent)} className="rounded-md bg-white/[0.04] py-1.5 text-[10px] font-semibold text-slate-300">{percent === 0 ? '0' : `${percent}%`}</button>)}</div><div className="mt-2 rounded-xl border border-white/8 bg-white/[0.03] p-2 text-[11px] text-slate-400"><Line label="Order value" value={`${totalDecimal.gt(0) ? totalDecimal.toFixed(6) : '0.000000'} ${market?.quote || ''}`} /><Line label="Fee" value={`${estimatedFee.gt(0) ? estimatedFee.toFixed(6) : '0.000000'} ${market?.quote || ''}`} /><Line label="Fee rate" value={`${feeRate.mul(100).toFixed(2)}%`} /><Line label="Min / Max" value={`${minAmount.toString() || '0'} / ${maxAmount.gt(0) ? maxAmount.toString() : 'No cap'} ${market?.base || ''}`} /></div>{formError ? <div className="mt-2 rounded-lg border border-rose-500/20 bg-rose-500/10 px-2.5 py-2 text-[11px] text-rose-200">{formError}</div> : null}{!formError && orderMessage ? <div className="mt-2 rounded-lg border border-white/8 bg-white/[0.03] px-2.5 py-2 text-[11px] text-slate-200">{orderMessage}</div> : null}<button type="button" disabled={submitting || Boolean(validationMessage)} onClick={onSubmit} className={`mt-2 w-full rounded-xl px-3 py-2.5 text-sm font-semibold ${form.side === 'buy' ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white'} disabled:cursor-not-allowed disabled:opacity-55`}>{submitting ? 'Submitting...' : `${form.side === 'buy' ? 'Buy' : 'Sell'} ${market?.base || ''}`}</button></div>;
}

function DepthTradesPanel({ depthTab, onDepthTab, aggregatedAsks, aggregatedBids, activePrice, recentTrades, bookPrecision, onBookPrecision, currentPricePrecision, onSelectBookPrice, compact = false }: any) {
  return <div className={`rounded-2xl border border-white/8 bg-[#070d16] ${compact ? 'p-2' : 'p-2.5'}`}><div className="flex items-center justify-between gap-2"><div className="grid grid-cols-2 gap-1 rounded-xl bg-white/[0.04] p-1"><button type="button" onClick={() => onDepthTab('book')} className={`rounded-lg px-2.5 py-1.5 text-xs ${depthTab === 'book' ? 'bg-white text-slate-950' : 'text-slate-300'}`}>Order Book</button><button type="button" onClick={() => onDepthTab('trades')} className={`rounded-lg px-2.5 py-1.5 text-xs ${depthTab === 'trades' ? 'bg-white text-slate-950' : 'text-slate-300'}`}>Trades</button></div><div className="flex gap-1">{[0.01,0.1,1].map((step) => <button key={step} type="button" onClick={() => onBookPrecision(step)} className={`rounded-lg px-2 py-1 text-[11px] ${bookPrecision === step ? 'bg-amber-400 text-slate-950' : 'bg-white/[0.04] text-slate-300'}`}>{step}</button>)}</div></div>{depthTab === 'book' ? <div className="mt-2 space-y-1"><div className="grid grid-cols-[1fr_auto] gap-2 px-1 text-[10px] uppercase tracking-[0.18em] text-slate-500"><span>Price</span><span>Qty</span></div>{aggregatedAsks.slice(0, compact ? 6 : 8).reverse().map((level: OrderBookLevel, index: number) => <DepthRow key={`ask-${index}`} level={level} side="sell" precision={currentPricePrecision} onSelect={onSelectBookPrice} />)}<div className="rounded-lg border border-amber-400/20 bg-amber-400/10 px-2 py-1.5 text-center font-mono text-sm font-semibold text-amber-200">{activePrice ? activePrice.toFixed(currentPricePrecision) : '--'}</div>{aggregatedBids.slice(0, compact ? 6 : 8).map((level: OrderBookLevel, index: number) => <DepthRow key={`bid-${index}`} level={level} side="buy" precision={currentPricePrecision} onSelect={onSelectBookPrice} />)}</div> : <div className="mt-2 space-y-1"><div className="grid grid-cols-[1fr_auto_auto] gap-2 px-1 text-[10px] uppercase tracking-[0.18em] text-slate-500"><span>Price</span><span>Amount</span><span>Time</span></div>{recentTrades.slice(0, compact ? 10 : 14).map((trade: RecentTrade, index: number) => <TradePrintRow key={trade.trade_uuid || `${trade.price}-${trade.executed_at}-${index}`} trade={trade} fallbackSide={index % 2 === 0 ? 'buy' : 'sell'} />)}</div>}</div>;
}

function BottomTabs({ accountTab, onAccountTab, loading, openOrders, tradeHistory, balances, selectedMarket, onCancelOrder }: any) {
  return <div className="rounded-2xl border border-white/8 bg-[#070d16] p-2.5"><div className="flex gap-1 overflow-x-auto">{ACCOUNT_TABS.map((tab) => <button key={tab} type="button" onClick={() => onAccountTab(tab)} className={`shrink-0 rounded-lg px-3 py-2 text-xs ${accountTab === tab ? 'bg-white text-slate-950' : 'bg-white/[0.04] text-slate-300'}`}>{tab === 'openOrders' ? `Open Orders (${openOrders.length})` : tab === 'tradeHistory' ? 'Trade History' : 'Assets'}</button>)}</div><div className="mt-2">{loading ? <div className="grid gap-2">{Array.from({ length: 3 }).map((_, index) => <div key={index} className="h-12 animate-pulse rounded-xl bg-white/[0.04]" />)}</div> : accountTab === 'openOrders' ? (openOrders.length ? <div className="space-y-2">{openOrders.map((order: UserOrder) => <OpenOrderRow key={order.order_uuid} order={order} onCancel={() => onCancelOrder(order.order_uuid)} />)}</div> : <EmptyState copy="No open orders" />) : accountTab === 'tradeHistory' ? (tradeHistory.length ? <div className="space-y-2">{tradeHistory.map((trade: RecentTrade, index: number) => <TradeHistoryRow key={trade.trade_uuid || `${trade.price}-${trade.executed_at}-${index}`} trade={trade} />)}</div> : <EmptyState copy="No trade history yet" />) : (balances.length ? <div className="space-y-2">{balances.map((balance: WalletBalance) => <AssetRow key={balance.currency} balance={balance} highlight={balance.currency === selectedMarket?.base || balance.currency === selectedMarket?.quote} />)}</div> : <EmptyState copy="No balances available" />)}</div></div>;
}

function IconToggle({ icon: Icon, active, onClick }: any) { return <button type="button" onClick={onClick} className={`inline-flex h-8 w-8 items-center justify-center rounded-lg ${active ? 'bg-white text-slate-950' : 'bg-white/[0.04] text-slate-300'}`}><Icon className="h-4 w-4" /></button>; }
function MiniField({ label, value, onChange, placeholder, suffix }: any) { return <label className="mt-2 block"><span className="mb-1 block text-[11px] text-slate-500">{label}</span><div className="flex items-center rounded-xl bg-white/[0.04] px-2.5"><input value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} className="h-10 w-full bg-transparent text-sm text-white outline-none placeholder:text-slate-500" inputMode="decimal" /><span className="text-[11px] font-medium text-slate-400">{suffix}</span></div></label>; }
function Line({ label, value }: any) { return <div className="mt-1.5 flex items-center justify-between gap-3 first:mt-0"><span>{label}</span><span className="font-mono text-white">{value}</span></div>; }
function DepthRow({ level, side, precision, onSelect }: any) { const shade = Math.min(100, Number(level.depth_percent || 0)); return <button type="button" onClick={() => onSelect(level.price)} className="relative grid w-full grid-cols-[1fr_auto] gap-2 overflow-hidden rounded-lg px-2 py-1.5 text-xs"><span className={`absolute inset-y-0 right-0 ${side === 'buy' ? 'bg-emerald-500/10' : 'bg-rose-500/10'}`} style={{ width: `${shade}%` }} /><span className={`relative z-10 font-mono ${side === 'buy' ? 'text-emerald-400' : 'text-rose-400'}`}>{Number(level.price).toFixed(precision)}</span><span className="relative z-10 text-right font-mono text-slate-300">{formatCompact(Number(level.amount))}</span></button>; }
function OpenOrderRow({ order, onCancel }: any) { return <div className="rounded-xl border border-white/8 bg-white/[0.03] p-2.5"><div className="flex items-start justify-between gap-2"><div><div className="text-sm font-semibold capitalize">{order.side} {order.type}</div><div className="text-[11px] text-slate-500">{order.pair}</div></div><button type="button" onClick={onCancel} className="rounded-lg bg-rose-500/10 px-2 py-1 text-[11px] text-rose-200">Cancel</button></div><div className="mt-2 grid grid-cols-3 gap-2 text-[11px] text-slate-400"><div>Price<div className="mt-1 font-mono text-white">{order.price}</div></div><div>Amount<div className="mt-1 font-mono text-white">{order.amount}</div></div><div>Filled<div className="mt-1 font-mono text-white">{order.filled_amount}</div></div></div></div>; }
function TradeHistoryRow({ trade }: any) { const side = String(trade?.metadata?.maker_side || trade.side || 'buy'); return <div className="rounded-xl border border-white/8 bg-white/[0.03] p-2.5"><div className="flex items-start justify-between gap-2"><div className={`text-sm font-semibold ${side === 'buy' ? 'text-emerald-400' : 'text-rose-400'}`}>{trade.price}</div><div className="text-[11px] text-slate-500">{formatDateTime(trade.executed_at)}</div></div><div className="mt-2 grid grid-cols-3 gap-2 text-[11px] text-slate-400"><div>Pair<div className="mt-1 font-mono text-white">{trade.pair}</div></div><div>Amount<div className="mt-1 font-mono text-white">{trade.amount}</div></div><div>Quote<div className="mt-1 font-mono text-white">{trade.quote_amount ?? '--'}</div></div></div></div>; }
function AssetRow({ balance, highlight = false }: any) { return <div className={`rounded-xl border p-2.5 ${highlight ? 'border-amber-400/20 bg-amber-400/8' : 'border-white/8 bg-white/[0.03]'}`}><div className="flex items-center justify-between gap-2"><div><div className="text-sm font-semibold text-white">{balance.currency}</div><div className="text-[11px] text-slate-500">Locked {balance.locked}</div></div><div className="font-mono text-sm text-white">{balance.balance}</div></div></div>; }
function TradePrintRow({ trade, fallbackSide }: any) { const side = String(trade?.metadata?.maker_side || trade.side || fallbackSide); return <div className="grid grid-cols-[1fr_auto_auto] gap-2 rounded-lg px-2 py-1.5 text-[11px]"><span className={`font-mono ${side === 'buy' ? 'text-emerald-400' : 'text-rose-400'}`}>{trade.price}</span><span className="text-right font-mono text-slate-300">{trade.amount}</span><span className="text-right text-slate-500">{formatTradeTime(trade.executed_at)}</span></div>; }
function EmptyState({ copy }: any) { return <div className="rounded-xl border border-dashed border-white/10 bg-white/[0.02] px-4 py-6 text-center text-sm text-slate-400">{copy}</div>; }
function MarketRow({ market, selected, favorite, onSelect, onToggleFavorite }: any) { return <button type="button" onClick={onSelect} className={`w-full rounded-xl border px-3 py-2.5 text-left transition ${selected ? 'border-amber-400/30 bg-amber-400/8' : 'border-white/8 bg-white/[0.02] hover:bg-white/[0.04]'}`}><div className="flex items-center gap-2 lg:grid lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_auto_auto] lg:gap-3"><div className="min-w-0"><div className="flex items-center gap-2"><button type="button" onClick={(event) => { event.stopPropagation(); onToggleFavorite(); }} className={`inline-flex h-5 w-5 items-center justify-center ${favorite ? 'text-amber-300' : 'text-slate-500'}`}><Star className={`h-3.5 w-3.5 ${favorite ? 'fill-current' : ''}`} /></button><div className="truncate text-sm font-semibold text-white">{market.pair}</div></div><div className="mt-1 text-[11px] text-slate-500 lg:hidden">{market.base}/{market.quote}</div></div><div className="hidden font-mono text-sm text-slate-200 lg:block">{formatPrice(market.last)}</div><div className={`hidden text-xs font-medium lg:block ${market.change24h >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>{market.change24h >= 0 ? '+' : ''}{market.change24h.toFixed(2)}%</div><div className="hidden text-right font-mono text-xs text-slate-400 lg:block">{formatCompact(market.volume)}</div><div className="ml-auto grid min-w-[120px] grid-cols-3 gap-2 text-[11px] text-slate-500 lg:hidden"><span className="text-right font-mono text-slate-300">{formatPrice(market.last)}</span><span className={`${market.change24h >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>{market.change24h >= 0 ? '+' : ''}{market.change24h.toFixed(2)}%</span><span className="text-right">{formatCompact(market.volume)}</span></div></div></button>; }
function normalizeLevels(rows: any[], side: 'buy' | 'sell'): OrderBookLevel[] { const mapped = rows.map((row) => ({ price: Number((Array.isArray(row) ? row[0] : row.price) || 0), amount: Number((Array.isArray(row) ? row[1] : row.amount) || 0), side })).filter((row) => row.price > 0 && row.amount > 0); const maxAmount = Math.max(...mapped.map((item) => item.amount), 1); return mapped.map((item, index) => ({ ...item, total: item.amount, depth: item.amount, depth_percent: (item.amount / maxAmount) * 100, index } as OrderBookLevel & { depth_percent: number })); }
function aggregateLevels(levels: OrderBookLevel[], step: number): OrderBookLevel[] { const map = new Map<number, OrderBookLevel>(); levels.forEach((level) => { const bucket = level.side === 'sell' ? Math.ceil(level.price / step) * step : Math.floor(level.price / step) * step; const existing = map.get(bucket); if (existing) { existing.amount += level.amount; existing.total += level.total; existing.depth += level.depth; } else { map.set(bucket, { ...level, price: bucket }); } }); const rows = Array.from(map.values()).sort((a, b) => a.side === 'sell' ? a.price - b.price : b.price - a.price); const maxAmount = Math.max(...rows.map((row) => row.amount), 1); return rows.map((row) => ({ ...row, depth_percent: (row.amount / maxAmount) * 100 } as OrderBookLevel & { depth_percent: number })); }
function normalizeCandle(raw: any): Candle { return { time: Number(raw.time || raw.timestamp || 0), open: Number(raw.open || 0), high: Number(raw.high || 0), low: Number(raw.low || 0), close: Number(raw.close || 0), volume: Number(raw.volume || 0) }; }
function mergeCandle(current: Candle[], next: Candle) { if (!next.time) return current; if (!current.length) return [next]; const latest = current[current.length - 1]; return latest.time === next.time ? [...current.slice(0, -1), next] : [...current, next].slice(-500); }
function safeTradeError(message: string) { const lower = String(message || '').toLowerCase(); if (lower.includes('no query results for model')) return 'Market unavailable'; if (lower.includes('sqlstate') || lower.includes('connection refused')) return 'Trading service is temporarily unavailable'; return message; }
function formatTradeTime(value?: string) { if (!value) return '--'; const date = new Date(value); return Number.isNaN(date.getTime()) ? '--' : date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
function formatDateTime(value?: string) { if (!value) return '--'; const date = new Date(value); return Number.isNaN(date.getTime()) ? '--' : date.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }); }

