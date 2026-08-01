export type TradingPair = {
  symbol: string;
  pair: string;
  base: string;
  quote: string;
  last: number;
  last_price?: string | number;
  change24h: number;
  price_change_percent?: number;
  volume: number;
  high24h?: number;
  low24h?: number;
  status?: string;
  source?: string;
  synced_at?: string;
  price_precision?: number | string;
  min_order_size?: number | string;
  max_order_size?: number | string;
  maker_fee?: number | string;
  taker_fee?: number | string;
  favorite?: boolean;
};

export type Candle = {
  time: number;
  open: number;
  high: number;
  low: number;
  close: number;
  volume: number;
};

export type OrderBookLevel = {
  price: number;
  amount: number;
  total: number;
  depth: number;
  side?: 'buy' | 'sell';
};

export type RecentTrade = {
  trade_uuid?: string;
  pair: string;
  price: string | number;
  amount: string | number;
  quote_amount?: string | number;
  executed_at?: string;
  side?: 'buy' | 'sell';
  metadata?: Record<string, unknown>;
};

export type UserOrder = {
  order_uuid: string;
  pair: string;
  side: 'buy' | 'sell';
  type: string;
  price: string | number;
  stop_price?: string | number;
  amount: string | number;
  filled_amount: string | number;
  remaining_amount: string | number;
  locked_amount?: string | number;
  locked_currency?: string;
  status: string;
  created_at?: string;
  triggered_at?: string;
};

export type WalletBalance = {
  currency: string;
  balance: string | number;
  locked: string | number;
};

export type MarketFilters = {
  search: string;
  quote: string;
  favoritesOnly: boolean;
};

export type OrderFormState = {
  side: 'buy' | 'sell';
  type: 'market' | 'limit' | 'stop_loss' | 'take_profit';
  price: string;
  amount: string;
  stopPrice: string;
};
