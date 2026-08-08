import { isLocalApiPreview } from '../config/apiConfig';
import type { Candle, RecentTrade, TradingPair, UserOrder, WalletBalance } from '../types/market';

const BINANCE_REST_URL = 'https://data-api.binance.vision';
const FALLBACK_SYMBOLS = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'XRPUSDT', 'BNBUSDT', 'ADAUSDT', 'DOTUSDT', 'AVAXUSDT', 'MATICUSDT', 'ATOMUSDT', 'LINKUSDT'];
type ApiRequestOptions = RequestInit & { timeoutMs?: number };
type ApiRequest = (path: string, options?: ApiRequestOptions) => Promise<any>;
const publicMarketRequestOptions = (): ApiRequestOptions => ({ method: 'GET', timeoutMs: isLocalApiPreview() ? 2500 : 8000 });
const privateTradingRequestOptions = (): ApiRequestOptions => ({ method: 'GET', timeoutMs: isLocalApiPreview() ? 5000 : 12000 });

const toPairPath = (pair: string) => pair.replace('/', '-');
const toApiSymbol = (pair: string) => normalizePair(pair).replace('/', '');

const toNumber = (value: unknown, fallback = 0): number => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const normalizePair = (value: string) => {
  const clean = String(value || '').trim().toUpperCase();
  if (clean.includes('/')) return clean;
  if (clean.includes('-')) return clean.replace('-', '/');
  const quotes = ['USDT', 'USDC', 'BTC', 'ETH', 'EXA'];
  for (const quote of quotes) {
    if (clean.endsWith(quote) && clean.length > quote.length) {
      return `${clean.slice(0, -quote.length)}/${quote}`;
    }
  }
  return clean;
};

const splitPair = (pair: string) => {
  const normalized = normalizePair(pair);
  const [base, quote = 'USDT'] = normalized.split('/');
  return { base, quote };
};

const looksAlive = (market: Partial<TradingPair>) => (
  toNumber(market.last ?? market.last_price) > 0 ||
  toNumber(market.volume) > 0 ||
  toNumber(market.high24h) > 0 ||
  toNumber(market.low24h) > 0
);

const fetchPublicJson = async (path: string) => {
  const response = await fetch(`${BINANCE_REST_URL}${path}`, {
    method: 'GET',
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) {
    throw new Error(`Public market data request failed with status ${response.status}`);
  }

  return response.json();
};

const normalizeMarket = (item: any): TradingPair => ({
  symbol: String(item.symbol || item.pair || ''),
  pair: normalizePair(String(item.pair || item.symbol || '')),
  base: String(item.base || item.base_currency || splitPair(String(item.pair || item.symbol || '')).base || '').toUpperCase(),
  quote: String(item.quote || item.quote_currency || splitPair(String(item.pair || item.symbol || '')).quote || 'USDT').toUpperCase(),
  last: toNumber(item.last ?? item.last_price),
  last_price: item.last_price ?? item.last,
  change24h: toNumber(item.change24h ?? item.price_change_percent ?? item.priceChangePercent),
  price_change_percent: toNumber(item.price_change_percent ?? item.priceChangePercent ?? item.change24h),
  volume: toNumber(item.volume ?? item.quoteVolume),
  high24h: toNumber(item.high24h ?? item.highPrice),
  low24h: toNumber(item.low24h ?? item.lowPrice),
  status: item.status || 'active',
  source: item.source,
  synced_at: item.synced_at,
  price_precision: item.price_precision,
  min_order_size: item.min_order_size,
  max_order_size: item.max_order_size,
  maker_fee: item.maker_fee,
  taker_fee: item.taker_fee,
  favorite: Boolean(item.favorite),
});

const fetchBinanceMarkets = async (): Promise<TradingPair[]> => {
  const symbols = encodeURIComponent(JSON.stringify(FALLBACK_SYMBOLS));
  const payload = await fetchPublicJson(`/api/v3/ticker/24hr?symbols=${symbols}`);
  const rows = Array.isArray(payload) ? payload : [];

  return rows.map((item: any) => {
    const pair = normalizePair(String(item.symbol || ''));
    const { base, quote } = splitPair(pair);

    return normalizeMarket({
      symbol: pair,
      pair,
      base,
      quote,
      last: item.lastPrice,
      last_price: item.lastPrice,
      change24h: item.priceChangePercent,
      price_change_percent: item.priceChangePercent,
      volume: item.quoteVolume,
      high24h: item.highPrice,
      low24h: item.lowPrice,
      source: 'binance-browser',
      status: 'active',
    });
  });
};

const mergeMarkets = (backendMarkets: TradingPair[], fallbackMarkets: TradingPair[]) => {
  const merged = new Map<string, TradingPair>();

  backendMarkets.forEach((market) => {
    merged.set(market.pair, market);
  });

  fallbackMarkets.forEach((market) => {
    const current = merged.get(market.pair);
    if (!current) {
      merged.set(market.pair, market);
      return;
    }

    merged.set(market.pair, {
      ...current,
      ...(!looksAlive(current) ? market : {}),
      price_precision: current.price_precision ?? market.price_precision,
      min_order_size: current.min_order_size ?? market.min_order_size,
      max_order_size: current.max_order_size ?? market.max_order_size,
      maker_fee: current.maker_fee ?? market.maker_fee,
      taker_fee: current.taker_fee ?? market.taker_fee,
    });
  });

  return Array.from(merged.values()).sort((a, b) => a.pair.localeCompare(b.pair));
};

export const marketDataService = {
  toPairPath,
  normalizePair,
  toApiSymbol,

  async getMarkets(request: ApiRequest): Promise<TradingPair[]> {
    let backendMarkets: TradingPair[] = [];
    try {
      const payload = await request('/api/trade/markets', publicMarketRequestOptions());
      backendMarkets = (Array.isArray(payload?.data) ? payload.data : []).map(normalizeMarket);
    } catch (error) {
      console.warn('ExaEarn market snapshot timed out; using public market fallback.', error);
    }

    if (backendMarkets.length > 0 && backendMarkets.some(looksAlive) && backendMarkets.length >= 4) {
      return backendMarkets;
    }

    try {
      const fallbackMarkets = await fetchBinanceMarkets();
      return mergeMarkets(backendMarkets, fallbackMarkets);
    } catch {
      return backendMarkets;
    }
  },

  async getCandles(
    request: ApiRequest,
    pair: string,
    interval: string,
    limit = 500,
  ): Promise<Candle[]> {
    let candles: Candle[] = [];
    try {
      const payload = await request(`/api/v1/market/klines?symbol=${encodeURIComponent(pair)}&interval=${encodeURIComponent(interval)}&limit=${limit}`, publicMarketRequestOptions());
      const rows = Array.isArray(payload?.data) ? payload.data : [];
      candles = rows
        .map((item: any) => ({
          time: toNumber(item.time),
          open: toNumber(item.open),
          high: toNumber(item.high),
          low: toNumber(item.low),
          close: toNumber(item.close),
          volume: toNumber(item.volume),
        }))
        .filter((item: Candle) => item.time > 0);
    } catch (error) {
      console.warn('ExaEarn candle endpoint timed out; using public candle fallback.', error);
    }

    if (candles.length > 0) {
      return candles;
    }

    try {
      const fallback = await fetchPublicJson(`/api/v3/klines?symbol=${toApiSymbol(pair)}&interval=${encodeURIComponent(interval)}&limit=${limit}`);
      return (Array.isArray(fallback) ? fallback : [])
        .map((item: any[]) => ({
          time: Math.floor(toNumber(item?.[0]) / 1000),
          open: toNumber(item?.[1]),
          high: toNumber(item?.[2]),
          low: toNumber(item?.[3]),
          close: toNumber(item?.[4]),
          volume: toNumber(item?.[5]),
        }))
        .filter((item: Candle) => item.time > 0);
    } catch {
      return candles;
    }
  },

  async getOrderBook(request: ApiRequest, pair: string, limit = 20) {
    let backendData = { pair, bids: [], asks: [] };
    try {
      const payload = await request(`/api/trade/order-book?pair=${encodeURIComponent(pair)}&limit=${limit}`, publicMarketRequestOptions());
      backendData = payload?.data ?? backendData;
    } catch (error) {
      console.warn('ExaEarn order book endpoint timed out; using public depth fallback.', error);
    }

    if (Array.isArray(backendData?.bids) && backendData.bids.length > 0) {
      return backendData;
    }

    try {
      const fallback = await fetchPublicJson(`/api/v3/depth?symbol=${toApiSymbol(pair)}&limit=${Math.max(5, Math.min(limit, 100))}`);
      return {
        pair: normalizePair(pair),
        bids: Array.isArray(fallback?.bids) ? fallback.bids.map((row: any[]) => ({ price: row?.[0], amount: row?.[1], side: 'buy' })) : [],
        asks: Array.isArray(fallback?.asks) ? fallback.asks.map((row: any[]) => ({ price: row?.[0], amount: row?.[1], side: 'sell' })) : [],
        last_synced_at: new Date().toISOString(),
        source: 'binance-browser',
      };
    } catch {
      return backendData;
    }
  },

  async getRecentTrades(request: ApiRequest, pair: string, limit = 50): Promise<RecentTrade[]> {
    let backendTrades: RecentTrade[] = [];
    try {
      const payload = await request(`/api/trade/trades?pair=${encodeURIComponent(pair)}&limit=${limit}`, publicMarketRequestOptions());
      backendTrades = Array.isArray(payload?.data) ? payload.data : [];
    } catch (error) {
      console.warn('ExaEarn recent trades endpoint timed out; using public trades fallback.', error);
    }

    if (backendTrades.length > 0) {
      return backendTrades;
    }

    try {
      const fallback = await fetchPublicJson(`/api/v3/trades?symbol=${toApiSymbol(pair)}&limit=${Math.max(1, Math.min(limit, 1000))}`);
      return (Array.isArray(fallback) ? fallback : []).map((item: any) => ({
        trade_uuid: `binance-${item.id}`,
        pair: normalizePair(pair),
        price: item.price,
        amount: item.qty,
        quote_amount: String(toNumber(item.price) * toNumber(item.qty)),
        executed_at: item.time ? new Date(toNumber(item.time)).toISOString() : new Date().toISOString(),
        side: item.isBuyerMaker ? 'sell' : 'buy',
        metadata: { source: 'binance-browser', is_buyer_maker: Boolean(item.isBuyerMaker) },
      }));
    } catch {
      return backendTrades;
    }
  },
  async getOpenOrders(request: ApiRequest, pair?: string): Promise<UserOrder[]> {
    const query = pair ? `?pair=${encodeURIComponent(pair)}` : '';
    const payload = await request(`/api/trade/orders${query}`, privateTradingRequestOptions());
    const data = payload?.data?.data ?? payload?.data ?? [];
    return Array.isArray(data) ? data : [];
  },

  async getTradeHistory(request: ApiRequest, pair?: string): Promise<RecentTrade[]> {
    const query = pair ? `?pair=${encodeURIComponent(pair)}` : '';
    const payload = await request(`/api/trade/history${query}`, privateTradingRequestOptions());
    const data = payload?.data?.data ?? payload?.data ?? [];
    return Array.isArray(data) ? data : [];
  },

  async getBalances(request: ApiRequest): Promise<WalletBalance[]> {
    const payload = await request('/api/wallet/balances', privateTradingRequestOptions());
    const data = payload?.data ?? [];
    return Array.isArray(data) ? data : [];
  },

  async placeOrder(request: ApiRequest, body: Record<string, unknown>) {
    return request('/api/trade/orders', {
      method: 'POST',
      body: JSON.stringify(body),
      headers: {
        'X-Idempotency-Key': `trade-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`,
      },
    });
  },

  async cancelOrder(request: ApiRequest, orderUuid: string) {
    return request(`/api/trade/orders/${orderUuid}`, { method: 'DELETE' });
  },
};
