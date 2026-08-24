# ExaEarn Phase 3 Market Data Source Map

Date: 2026-08-18

## Source Rules

For markets operated by the ExaEarn authoritative Spot engine, internal market data is sourced from ExaEarn resting orders and ExaEarn executions. External exchange data may be used only as `EXTERNAL_REFERENCE` fallback or reference display when ExaEarn has no internal data for a field.

External data must not be counted as ExaEarn internal volume, trades, or order-book liquidity.

## Field Map

| Field | Current Source After Phase 3 | Internal/External | Authoritative? | Fallback? | Frontend/Backend | Must Migrate? |
|---|---|---:|---:|---:|---|---:|
| symbol | `markets` table | Internal | Yes | No | Backend | No |
| base_asset | `markets.base_currency` | Internal | Yes | No | Backend | No |
| quote_asset | `markets.quote_currency` | Internal | Yes | No | Backend | No |
| market_type | normalized backend contract | Internal | Yes | No | Backend | No |
| status | `markets.trading_status/status` | Internal | Yes | No | Backend | No |
| order book | `spot_order_book_snapshots`, open orders | Internal | Yes | External reference book if internal book empty | Backend | No |
| best bid/ask | normalized book levels | Internal when internal book exists | Yes | External reference if book fallback used | Backend | No |
| recent trades | `trades` table | Internal | Yes | External reference trades if no ExaEarn trades | Backend | No |
| last_trade_price | last ExaEarn `trade.price` | Internal | Yes | No | Backend | No |
| reference_price | `ExternalReferenceMarketDataService` | External | No | Yes | Backend | No |
| 24h high/low | ExaEarn trades rolling window | Internal | Yes | External reference ticker when no ExaEarn trade | Backend | No |
| 24h base/quote volume | ExaEarn trades rolling window | Internal | Yes | External reference volume only when `source=EXTERNAL_REFERENCE` | Backend | No |
| candles/klines | ExaEarn trades bucketed by interval | Internal | Yes | External reference candles if no ExaEarn trades | Backend | No |
| Web market service | `/api/v1/market/*` | Backend-normalized | Yes | Backend reference only | Web | Migrated |
| Mobile market list | `/api/v1/market/tickers` | Backend-normalized | Yes | Backend reference only | Mobile | Migrated |
| Mobile trade screen | `/api/v1/market/ticker`, `/order-book` | Backend-normalized | Yes | Backend reference only | Mobile | Migrated |
| Futures market data | existing futures Binance futures integration | External/futures scope | Not Phase 3 Spot | Existing | Backend/Web | Future Phase 5 |
| SOR/external liquidity | `ExternalLiquidityProviderService` | External | No | Routing/reference | Backend | Future hardening |

## Legacy Paths Remaining

`TradeService` still contains legacy Binance/CoinGecko helper methods for compatibility and non-cutover flows. Public Phase 3 market APIs and migrated web/mobile Spot market reads now route through `MarketDataService`.

