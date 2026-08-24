import { isLocalApiPreview } from '../config/apiConfig';
import type { Candle, RecentTrade, TradingPair, UserOrder, WalletBalance } from '../types/market';

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

const normalizeMarket = (item: any): TradingPair => ({
  symbol: String(item.symbol || item.pair || ''),
  pair: normalizePair(String(item.pair || item.symbol || '')),
  base: String(item.base || item.base_asset || item.base_currency || splitPair(String(item.pair || item.symbol || '')).base || '').toUpperCase(),
  quote: String(item.quote || item.quote_asset || item.quote_currency || splitPair(String(item.pair || item.symbol || '')).quote || 'USDT').toUpperCase(),
  last: toNumber(item.last ?? item.last_price ?? item.last_trade_price ?? item.reference_price),
  last_price: item.last_price ?? item.last_trade_price ?? item.reference_price ?? item.last,
  change24h: toNumber(item.change24h ?? item.price_change_percent ?? item.priceChangePercent),
  price_change_percent: toNumber(item.price_change_percent ?? item.priceChangePercent ?? item.change24h),
  volume: toNumber(item.volume ?? item.quote_volume_24h ?? item.quoteVolume),
  high24h: toNumber(item.high24h ?? item.high_24h ?? item.highPrice),
  low24h: toNumber(item.low24h ?? item.low_24h ?? item.lowPrice),
  status: item.status || 'active',
  source: item.source,
  synced_at: item.synced_at ?? item.updated_at,
  price_precision: item.price_precision,
  min_order_size: item.min_order_size,
  max_order_size: item.max_order_size,
  maker_fee: item.maker_fee,
  taker_fee: item.taker_fee,
  favorite: Boolean(item.favorite),
});

export const marketDataService = {
  toPairPath,
  normalizePair,
  toApiSymbol,

  async getMarkets(request: ApiRequest): Promise<TradingPair[]> {
    try {
      const payload = await request('/api/v1/market/tickers', publicMarketRequestOptions());
      const rows = Array.isArray(payload?.data) ? payload.data : [];
      return rows.map(normalizeMarket);
    } catch (error) {
      console.warn('ExaEarn market snapshot is unavailable.', error);
      return [];
    }
  },

  async getCandles(
    request: ApiRequest,
    pair: string,
    interval: string,
    limit = 500,
  ): Promise<Candle[]> {
    try {
      const payload = await request(`/api/v1/market/klines/${encodeURIComponent(toPairPath(pair))}?interval=${encodeURIComponent(interval)}&limit=${limit}`, publicMarketRequestOptions());
      const rows = Array.isArray(payload?.data) ? payload.data : [];
      return rows
        .map((item: any) => ({
          time: toNumber(item.time ?? item.open_time),
          open: toNumber(item.open),
          high: toNumber(item.high),
          low: toNumber(item.low),
          close: toNumber(item.close),
          volume: toNumber(item.volume ?? item.base_volume),
        }))
        .filter((item: Candle) => item.time > 0);
    } catch (error) {
      console.warn('ExaEarn candles are unavailable.', error);
      return [];
    }
  },

  async getOrderBook(request: ApiRequest, pair: string, limit = 20) {
    let backendData = { pair, bids: [], asks: [] };
    try {
      const payload = await request(`/api/v1/market/order-book/${encodeURIComponent(toPairPath(pair))}?limit=${limit}`, publicMarketRequestOptions());
      backendData = payload?.data ?? backendData;
    } catch (error) {
      console.warn('ExaEarn order book is unavailable.', error);
    }

    return backendData;
  },

  async getRecentTrades(request: ApiRequest, pair: string, limit = 50): Promise<RecentTrade[]> {
    try {
      const payload = await request(`/api/v1/market/trades/${encodeURIComponent(toPairPath(pair))}?limit=${limit}`, publicMarketRequestOptions());
      return Array.isArray(payload?.data) ? payload.data : [];
    } catch (error) {
      console.warn('ExaEarn recent trades are unavailable.', error);
      return [];
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
