# ExaEarn Phase 15E Futures Market Making

Futures market-maker bots submit real limit/post-only orders through `FuturesOrderService`. They do not write to the order book directly and they inherit existing Futures risk, margin reservation, position and settlement behavior.

The bot records every generated order in `market_maker_bot_orders` with bot, quote-cycle, strategy-version and client-order attribution before linking to the actual Futures order.
