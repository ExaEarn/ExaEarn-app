import { useCallback, useEffect, useMemo, useState } from "react";
import { useAuth } from "../../context/AuthContext";
import { onEvent } from "../../services/webSocketService";
import { marketDataService } from "../../services/marketDataService";

const REFRESH_INTERVAL_MS = 30000;
const STALE_AFTER_MS = 30000;

function toNullableNumber(value) {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

function isTickerMissing(item) {
  const last = Number(item?.last ?? item?.last_price ?? 0);
  const volume = Number(item?.volume ?? 0);
  const high = Number(item?.high24h ?? item?.high_24h ?? 0);
  const low = Number(item?.low24h ?? item?.low_24h ?? 0);

  return last <= 0 && volume <= 0 && high <= 0 && low <= 0;
}

function normalizeMarket(item, favorite = false) {
  const normalized = marketDataService.normalizePair(String(item?.pair || item?.symbol || ""));
  const [base = "", quote = "USDT"] = normalized.split("/");
  const missingTicker = isTickerMissing(item);

  return {
    ...item,
    symbol: normalized,
    pair: normalized,
    base: String(item?.base || item?.base_currency || base || "").toUpperCase(),
    quote: String(item?.quote || item?.quote_currency || quote || "USDT").toUpperCase(),
    last: missingTicker ? null : toNullableNumber(item?.last ?? item?.last_price),
    change24h: missingTicker ? null : toNullableNumber(item?.change24h ?? item?.price_change_percent ?? item?.change_24h),
    volume: missingTicker ? null : toNullableNumber(item?.volume ?? item?.quoteVolume),
    high24h: missingTicker ? null : toNullableNumber(item?.high24h ?? item?.high_24h ?? item?.highPrice),
    low24h: missingTicker ? null : toNullableNumber(item?.low24h ?? item?.low_24h ?? item?.lowPrice),
    bestBid: toNullableNumber(item?.bestBid ?? item?.best_bid),
    bestAsk: toNullableNumber(item?.bestAsk ?? item?.best_ask),
    favorite: Boolean(item?.favorite ?? favorite),
    source: item?.source || "api",
    timestamp: item?.timestamp || item?.synced_at || new Date().toISOString(),
    stale: false,
  };
}

function mergeMarkets(previous, incoming) {
  const favorites = new Map(previous.map((item) => [item.pair, Boolean(item.favorite)]));
  const previousByPair = new Map(previous.map((item) => [item.pair, item]));

  return incoming.map((item) => {
    const pair = marketDataService.normalizePair(String(item?.pair || item?.symbol || ""));
    const normalized = normalizeMarket(item, favorites.get(pair));
    const existing = previousByPair.get(normalized.pair);

    return {
      ...existing,
      ...normalized,
      favorite: normalized.favorite,
      stale: false,
      timestamp: normalized.timestamp || new Date().toISOString(),
    };
  });
}

export function useMarketData() {
  const { request } = useAuth();
  const [loading, setLoading] = useState(true);
  const [offline, setOffline] = useState(!navigator.onLine);
  const [pairs, setPairs] = useState([]);

  const loadMarkets = useCallback(async () => {
    const nextMarkets = await marketDataService.getMarkets(request);
    setPairs((previous) => mergeMarkets(previous, nextMarkets));
    setOffline(false);
  }, [request]);

  useEffect(() => {
    let ignore = false;

    async function runLoad() {
      try {
        await loadMarkets();
      } catch (error) {
        if (!ignore) {
          console.warn("Failed to load live market tickers.", error);
          setOffline(true);
        }
      } finally {
        if (!ignore) {
          setLoading(false);
        }
      }
    }

    runLoad();
    const timer = window.setInterval(runLoad, REFRESH_INTERVAL_MS);

    return () => {
      ignore = true;
      window.clearInterval(timer);
    };
  }, [loadMarkets]);

  useEffect(() => {
    const unsubscribe = onEvent("market:stream", (payload) => {
      const streamPair = marketDataService.normalizePair(String(payload?.pair || payload?.data?.pair || ""));
      if (!streamPair || payload?.type !== "trade" || !payload?.data) return;

      setPairs((current) =>
        current.map((item) =>
          item.pair === streamPair
            ? {
                ...item,
                last: toNullableNumber(payload.data.price) ?? item.last,
                timestamp: payload.data.timestamp || new Date().toISOString(),
                stale: false,
              }
            : item
        )
      );
    });

    return unsubscribe;
  }, []);

  useEffect(() => {
    const timer = window.setInterval(() => {
      const threshold = Date.now() - STALE_AFTER_MS;
      setPairs((current) =>
        current.map((item) => {
          const updatedAt = Date.parse(item.timestamp || "");
          return {
            ...item,
            stale: Number.isFinite(updatedAt) ? updatedAt < threshold : item.stale,
          };
        })
      );
    }, 5000);

    return () => window.clearInterval(timer);
  }, []);

  useEffect(() => {
    const handleOnline = () => setOffline(false);
    const handleOffline = () => setOffline(true);

    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);

    return () => {
      window.removeEventListener("online", handleOnline);
      window.removeEventListener("offline", handleOffline);
    };
  }, []);

  const enrichedPairs = useMemo(
    () =>
      pairs.map((item) => ({
        ...item,
        base: item.base || item.pair.split("/")[0] || "",
        quote: item.quote || item.pair.split("/")[1] || "USDT",
      })),
    [pairs]
  );

  return {
    loading,
    offline,
    pairs: enrichedPairs,
    setPairs,
    refresh: loadMarkets,
  };
}

export default useMarketData;
